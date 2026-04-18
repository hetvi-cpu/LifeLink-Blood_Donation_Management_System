<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$report_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$month       = isset($_GET['month']) ? trim($_GET['month']) : ''; // format: YYYY-MM

$allowed_types = [
    'SummaryReport',
    'UserReport',
    'DonorReport',
    'RequestReport',
    'CampaignReport',
    'EmergencyReport',
    'DonationReport',
    'MonthlyReport',
];

if (!in_array($report_type, $allowed_types)) {
    echo json_encode(["status" => "error", "message" => "Invalid report type. Allowed: " . implode(', ', $allowed_types)]);
    exit;
}

$report_data = [];
$summary     = [];

// Month filter helper
$month_filter = '';
if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month_filter = $conn->real_escape_string($month);
}

switch ($report_type) {

    case 'SummaryReport':
        $rows = [];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role_id != 1");
        $rows[] = ['Metric' => 'Total Registered Users', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM donors");
        $rows[] = ['Metric' => 'Total Donors', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM blood_requests");
        $rows[] = ['Metric' => 'Total Blood Requests', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM blood_requests WHERE status = 'Fulfilled'");
        $rows[] = ['Metric' => 'Fulfilled Blood Requests', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM blood_requests WHERE status = 'Pending'");
        $rows[] = ['Metric' => 'Pending Blood Requests', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM blood_requests WHERE urgency_level IN ('Critical','High')");
        $rows[] = ['Metric' => 'Emergency / High-Priority Requests', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM campaigns");
        $rows[] = ['Metric' => 'Total Campaigns', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM campaigns WHERE status = 'Active'");
        $rows[] = ['Metric' => 'Active Campaigns', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM campaign_registrations");
        $rows[] = ['Metric' => 'Total Campaign Registrations', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM emergency_alerts");
        $rows[] = ['Metric' => 'Total Emergency Alerts', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM emergency_alerts WHERE is_fulfilled = 1");
        $rows[] = ['Metric' => 'Fulfilled Emergency Alerts', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM hospital_details");
        $rows[] = ['Metric' => 'Registered Hospitals', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM notifications");
        $rows[] = ['Metric' => 'Total Notifications Sent', 'Value' => $r->fetch_assoc()['cnt']];
        $r = $conn->query("SELECT blood_group, COUNT(*) AS cnt FROM blood_requests GROUP BY blood_group ORDER BY cnt DESC LIMIT 1");
        $bg = $r->fetch_assoc();
        $rows[] = ['Metric' => 'Most Requested Blood Group', 'Value' => $bg ? $bg['blood_group'] . ' (' . $bg['cnt'] . ' requests)' : 'N/A'];
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role_id != 1 AND user_status = 'Active'");
        $rows[] = ['Metric' => 'Active Users', 'Value' => $r->fetch_assoc()['cnt']];
        $report_data = $rows;
        $summary = [
            ['label' => 'Total Users',    'value' => $rows[0]['Value']],
            ['label' => 'Total Donors',   'value' => $rows[1]['Value']],
            ['label' => 'Blood Requests', 'value' => $rows[2]['Value']],
            ['label' => 'Campaigns',      'value' => $rows[6]['Value']],
        ];
        break;

    case 'UserReport':
        $mf = $month_filter ? "AND DATE_FORMAT(u.created_at,'%Y-%m') = '$month_filter'" : '';
        $r  = $conn->query("
            SELECT
                u.name                                AS 'Full Name',
                u.email                               AS 'Email',
                u.blood_group                         AS 'Blood Group',
                u.location                            AS 'City',
                ro.role_name                          AS 'Role',
                u.user_status                         AS 'Status',
                CASE WHEN d.donor_id IS NOT NULL THEN 'Yes' ELSE 'No' END AS 'Is Donor',
                DATE_FORMAT(u.created_at,'%d %b %Y')  AS 'Joined On',
                COALESCE((SELECT COUNT(*) FROM blood_requests br WHERE br.firebase_uid = u.firebase_uid),0) AS 'Total Blood Requests',
                COALESCE((SELECT COUNT(*) FROM campaign_registrations cr WHERE cr.firebase_uid = u.firebase_uid),0) AS 'Campaign Registrations'
            FROM users u
            LEFT JOIN roles ro ON ro.role_id = u.role_id
            LEFT JOIN donors d  ON d.firebase_uid = u.firebase_uid
            WHERE u.role_id != 1 $mf
            ORDER BY u.created_at DESC
        ");
        while ($row = $r->fetch_assoc()) $report_data[] = $row;
        $total   = count($report_data);
        $active  = count(array_filter($report_data, fn($x) => $x['Status'] === 'Active'));
        $donors  = count(array_filter($report_data, fn($x) => $x['Is Donor'] === 'Yes'));
        $summary = [
            ['label' => 'Total Users',   'value' => $total],
            ['label' => 'Active',        'value' => $active],
            ['label' => 'Donors',        'value' => $donors],
            ['label' => 'Inactive',      'value' => $total - $active],
        ];
        break;

    case 'DonorReport':
        $mf = $month_filter ? "AND DATE_FORMAT(d.registration_date,'%Y-%m') = '$month_filter'" : '';
        $r  = $conn->query("
            SELECT
                u.name                                AS 'Donor Name',
                u.email                               AS 'Email',
                u.blood_group                         AS 'Blood Group',
                u.location                            AS 'City',
                u.last_donation_date                  AS 'Last Donation Date',
                DATEDIFF(CURDATE(), u.last_donation_date) AS 'Days Since Last Donation',
                CASE
                    WHEN u.last_donation_date IS NULL THEN 'Eligible'
                    WHEN DATEDIFF(CURDATE(), u.last_donation_date) >= 90 THEN 'Eligible'
                    ELSE 'Not Eligible Yet'
                END                                   AS 'Eligibility Status',
                d.weight                              AS 'Weight (kg)',
                CASE WHEN d.is_smoker = 1 THEN 'Yes' ELSE 'No' END AS 'Smoker',
                CASE WHEN d.has_anemia = 1 THEN 'Yes' ELSE 'No' END AS 'Has Anemia',
                u.points                              AS 'Donation Points',
                u.user_status                         AS 'Account Status',
                DATE_FORMAT(d.registration_date,'%d %b %Y')  AS 'Registered As Donor On',
                COALESCE((SELECT COUNT(*) FROM donations dn WHERE dn.user_id = u.user_id AND dn.status = 'Completed'),0) AS 'Completed Donations',
                COALESCE((SELECT COUNT(*) FROM campaign_registrations cr WHERE cr.firebase_uid = u.firebase_uid AND cr.status = 'Completed'),0) AS 'Campaigns Participated'
            FROM donors d
            JOIN users u ON u.firebase_uid = d.firebase_uid
            WHERE 1=1 $mf
            ORDER BY u.blood_group, u.name
        ");
        while ($row = $r->fetch_assoc()) $report_data[] = $row;
        $total    = count($report_data);
        $eligible = count(array_filter($report_data, fn($x) => $x['Eligibility Status'] === 'Eligible'));
        $summary  = [
            ['label' => 'Total Donors',      'value' => $total],
            ['label' => 'Eligible Now',      'value' => $eligible],
            ['label' => 'Not Yet Eligible',  'value' => $total - $eligible],
        ];
        break;

    case 'RequestReport':
        $mf = $month_filter ? "AND DATE_FORMAT(br.created_at,'%Y-%m') = '$month_filter'" : '';
        $r  = $conn->query("
            SELECT
                br.request_id                                       AS 'Request ID',
                u.name                                              AS 'Requested By',
                u.email                                             AS 'Requester Email',
                br.patient_name                                     AS 'Patient Name',
                br.blood_group                                      AS 'Blood Group',
                br.units_required                                   AS 'Units Required',
                br.donation_type                                    AS 'Donation Type',
                br.urgency_level                                    AS 'Urgency Level',
                br.hospital_name                                    AS 'Hospital',
                br.city                                             AS 'City',
                br.status                                           AS 'Status',
                DATE_FORMAT(br.required_date,'%d %b %Y')           AS 'Required By Date',
                DATE_FORMAT(br.created_at,'%d %b %Y %H:%i')        AS 'Created At',
                br.contact_person                                   AS 'Contact Person',
                br.contact_mobile                                   AS 'Contact Mobile'
            FROM blood_requests br
            LEFT JOIN users u ON u.firebase_uid = br.firebase_uid
            WHERE 1=1 $mf
            ORDER BY FIELD(br.urgency_level,'Critical','High','Normal'), br.created_at DESC
        ");
        while ($row = $r->fetch_assoc()) $report_data[] = $row;
        $total     = count($report_data);
        $pending   = count(array_filter($report_data, fn($x) => $x['Status'] === 'Pending'));
        $fulfilled = count(array_filter($report_data, fn($x) => $x['Status'] === 'Fulfilled'));
        $critical  = count(array_filter($report_data, fn($x) => in_array($x['Urgency Level'], ['Critical','High'])));
        $summary   = [
            ['label' => 'Total Requests',   'value' => $total],
            ['label' => 'Pending',          'value' => $pending],
            ['label' => 'Fulfilled',        'value' => $fulfilled],
            ['label' => 'High Priority',    'value' => $critical],
        ];
        break;

    case 'CampaignReport':
        $mf = $month_filter ? "AND DATE_FORMAT(c.created_at,'%Y-%m') = '$month_filter'" : '';
        $r  = $conn->query("
            SELECT
                c.campaign_name                                         AS 'Campaign Name',
                c.campaign_type                                         AS 'Type',
                c.organized_by                                          AS 'Organized By',
                c.blood_group_needed                                    AS 'Blood Groups Needed',
                c.target_units                                          AS 'Target Units',
                c.venue_info                                            AS 'Venue',
                c.contact_person_name                                   AS 'Contact Person',
                c.contact_phone                                         AS 'Contact Phone',
                c.status                                                AS 'Status',
                DATE_FORMAT(c.created_at,'%d %b %Y')                   AS 'Created On',
                DATE_FORMAT(c.created_at,'%M %Y')                      AS 'Month',
                COUNT(cr.registration_id)                               AS 'Total Registrations',
                SUM(CASE WHEN cr.status='Completed' THEN 1 ELSE 0 END) AS 'Completed',
                SUM(CASE WHEN cr.status='Cancelled' THEN 1 ELSE 0 END) AS 'Cancelled'
            FROM campaigns c
            LEFT JOIN campaign_registrations cr ON cr.campaign_name = c.campaign_name
            WHERE 1=1 $mf
            GROUP BY c.campaign_id
            ORDER BY c.created_at DESC
        ");
        while ($row = $r->fetch_assoc()) $report_data[] = $row;
        $total    = count($report_data);
        $active   = count(array_filter($report_data, fn($x) => $x['Status'] === 'Active'));
        $upcoming = count(array_filter($report_data, fn($x) => $x['Status'] === 'Upcoming'));
        $totalReg = array_sum(array_column($report_data, 'Total Registrations'));
        $summary  = [
            ['label' => 'Total Campaigns',    'value' => $total],
            ['label' => 'Active',             'value' => $active],
            ['label' => 'Upcoming',           'value' => $upcoming],
            ['label' => 'Total Registrations','value' => $totalReg],
        ];
        break;

    case 'EmergencyReport':
        $mf = $month_filter ? "AND DATE_FORMAT(ea.created_at,'%Y-%m') = '$month_filter'" : '';
        $r  = $conn->query("
            SELECT
                ea.alert_id                                              AS 'Alert ID',
                ea.title                                                 AS 'Title',
                ea.blood_group_needed                                    AS 'Blood Group Needed',
                ea.alert_type                                            AS 'Alert Type',
                ea.location                                              AS 'Location',
                ea.contact_number                                        AS 'Contact Number',
                ea.description                                           AS 'Description',
                ea.donors_notified                                       AS 'Donors Notified',
                CASE WHEN ea.is_fulfilled=1 THEN 'Fulfilled' ELSE 'Active' END AS 'Status',
                DATE_FORMAT(ea.alert_timestamp,'%d %b %Y %H:%i')        AS 'Alert Timestamp',
                DATE_FORMAT(ea.created_at,'%d %b %Y')                   AS 'Created On',
                a.name                                                   AS 'Created By (Admin)'
            FROM emergency_alerts ea
            LEFT JOIN admin a ON a.admin_id = ea.admin_id
            WHERE 1=1 $mf
            ORDER BY ea.created_at DESC
        ");
        while ($row = $r->fetch_assoc()) $report_data[] = $row;
        $total     = count($report_data);
        $fulfilled = count(array_filter($report_data, fn($x) => $x['Status'] === 'Fulfilled'));
        $summary   = [
            ['label' => 'Total Alerts', 'value' => $total],
            ['label' => 'Active',       'value' => $total - $fulfilled],
            ['label' => 'Fulfilled',    'value' => $fulfilled],
        ];
        break;

    case 'DonationReport':
        $mf = $month_filter ? "AND DATE_FORMAT(dn.donation_date,'%Y-%m') = '$month_filter'" : '';
        $r  = $conn->query("
            SELECT
                u.name                                               AS 'Donor Name',
                u.email                                              AS 'Email',
                u.blood_group                                        AS 'Blood Group',
                h.hospital_name                                      AS 'Hospital',
                dn.volume_ml                                         AS 'Volume (ml)',
                dn.status                                            AS 'Status',
                DATE_FORMAT(dn.donation_date,'%d %b %Y')            AS 'Donation Date',
                DATE_FORMAT(dn.created_at,'%d %b %Y')               AS 'Recorded On'
            FROM donations dn
            JOIN users u           ON u.user_id      = dn.user_id
            JOIN hospital_details h ON h.hospital_id = dn.hospital_id
            WHERE 1=1 $mf
            ORDER BY dn.donation_date DESC
        ");
        while ($row = $r->fetch_assoc()) $report_data[] = $row;
        $total     = count($report_data);
        $completed = count(array_filter($report_data, fn($x) => $x['Status'] === 'Completed'));
        $totalVol  = array_sum(array_column($report_data, 'Volume (ml)'));
        $summary   = [
            ['label' => 'Total Donations', 'value' => $total],
            ['label' => 'Completed',       'value' => $completed],
            ['label' => 'Total Volume',    'value' => number_format($totalVol) . ' ml'],
        ];
        break;

    case 'MonthlyReport':
        $r = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS mk, COUNT(*) AS cnt FROM users WHERE role_id!=1 GROUP BY mk ORDER BY mk");
        $users_m = [];
        while ($row = $r->fetch_assoc()) $users_m[$row['mk']] = $row['cnt'];

        $r = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS mk, COUNT(*) AS cnt FROM blood_requests GROUP BY mk");
        $req_m = [];
        while ($row = $r->fetch_assoc()) $req_m[$row['mk']] = $row['cnt'];

        $r = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS mk, COUNT(*) AS cnt FROM campaigns GROUP BY mk");
        $camp_m = [];
        while ($row = $r->fetch_assoc()) $camp_m[$row['mk']] = $row['cnt'];

        $r = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS mk, COUNT(*) AS cnt FROM emergency_alerts GROUP BY mk");
        $emg_m = [];
        while ($row = $r->fetch_assoc()) $emg_m[$row['mk']] = $row['cnt'];

        $r = $conn->query("SELECT DATE_FORMAT(registration_date,'%Y-%m') AS mk, COUNT(*) AS cnt FROM campaign_registrations GROUP BY mk");
        $creg_m = [];
        while ($row = $r->fetch_assoc()) $creg_m[$row['mk']] = $row['cnt'];

        $r = $conn->query("SELECT DATE_FORMAT(registration_date,'%Y-%m') AS mk, COUNT(*) AS cnt FROM donors GROUP BY mk");
        $don_m = [];
        while ($row = $r->fetch_assoc()) $don_m[$row['mk']] = $row['cnt'];

        $all = array_unique(array_merge(
            array_keys($users_m), array_keys($req_m), array_keys($camp_m),
            array_keys($emg_m), array_keys($creg_m), array_keys($don_m)
        ));
        sort($all);

        foreach ($all as $mk) {
            $report_data[] = [
                'Month'                    => date('F Y', strtotime($mk . '-01')),
                'New Users Registered'     => $users_m[$mk]  ?? 0,
                'New Donors Registered'    => $don_m[$mk]    ?? 0,
                'Blood Requests Created'   => $req_m[$mk]    ?? 0,
                'Campaigns Created'        => $camp_m[$mk]   ?? 0,
                'Campaign Registrations'   => $creg_m[$mk]   ?? 0,
                'Emergency Alerts Created' => $emg_m[$mk]    ?? 0,
            ];
        }

        $summary = [
            ['label' => 'Months Covered', 'value' => count($report_data)],
            ['label' => 'Total Users',    'value' => array_sum(array_column($report_data, 'New Users Registered'))],
            ['label' => 'Total Requests', 'value' => array_sum(array_column($report_data, 'Blood Requests Created'))],
            ['label' => 'Total Campaigns','value' => array_sum(array_column($report_data, 'Campaigns Created'))],
        ];
        break;
}

// Log to reports table
$admin_id  = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 1;
$json_data = $conn->real_escape_string(json_encode($report_data));
$conn->query("INSERT INTO reports (report_type, report_data) VALUES ('$report_type', '$json_data')");

echo json_encode([
    "status"       => "success",
    "report_type"  => $report_type,
    "count"        => count($report_data),
    "generated_at" => date('Y-m-d H:i:s'),
    "summary"      => $summary,
    "data"         => $report_data,
]);

$conn->close();
?>