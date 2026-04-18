<?php
/**
 * find_nearby_donors.php
 * - Compatible blood group matching (not exact)
 * - Active users only
 * - Excludes the requester
 * - Excludes donors already in an active commitment (status=Accepted)
 * - Pincode priority: same pincode = priority 1, others = priority 2
 */

include '../../config/db_config.php';

$pincode     = isset($_GET['pincode'])     ? trim($_GET['pincode'])     : '';
$blood_group = isset($_GET['blood_group']) ? trim($_GET['blood_group']) : '';
$uid         = isset($_GET['uid'])         ? trim($_GET['uid'])         : '';

if (!$pincode || !$blood_group || !$uid) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// ── Blood Compatibility Map ──────────────────────────────────────────────
// Key = requested blood group → Value = donor blood groups that can donate to it
$compatibility = [
    'O-'  => ['O-'],
    'O+'  => ['O-', 'O+'],
    'A-'  => ['O-', 'A-'],
    'A+'  => ['O-', 'O+', 'A-', 'A+'],
    'B-'  => ['O-', 'B-'],
    'B+'  => ['O-', 'O+', 'B-', 'B+'],
    'AB-' => ['O-', 'A-', 'B-', 'AB-'],
    'AB+' => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
];

$compatible_groups = $compatibility[$blood_group] ?? [$blood_group];
$placeholders      = implode(',', array_fill(0, count($compatible_groups), '?'));
$group_types       = str_repeat('s', count($compatible_groups));

// ── Query ─────────────────────────────────────────────────────────────────
$sql = "SELECT firebase_uid, name, phone_number, gender, location, pincode, blood_group,
        CASE WHEN pincode = ? THEN 1 ELSE 2 END AS priority
        FROM users
        WHERE blood_group IN ($placeholders)
          AND firebase_uid != ?
          AND user_status = 'Active'
          AND firebase_uid NOT IN (
              SELECT donor_uid FROM donor_requests WHERE status = 'Accepted'
          )
        ORDER BY priority ASC, name ASC";

$stmt = $conn->prepare($sql);

// bind: pincode + compatible_groups + uid
$bind_types  = 's' . $group_types . 's';
$bind_values = array_merge([$pincode], $compatible_groups, [$uid]);
$refs = [];
foreach ($bind_values as $k => $v) { $refs[$k] = &$bind_values[$k]; }
array_unshift($refs, $bind_types);
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$result = $stmt->get_result();

$donors = [];
while ($row = $result->fetch_assoc()) {
    $row['match_type'] = ($row['priority'] == 1) ? "Immediate Area" : "Other Areas";
    $donors[] = $row;
}
$stmt->close();

echo json_encode([
    "status"            => "success",
    "search_criteria"   => ["pincode" => $pincode, "blood_group" => $blood_group],
    "compatible_groups" => $compatible_groups,
    "matching_donors"   => $donors,
    "count"             => count($donors),
]);

$conn->close();
?>