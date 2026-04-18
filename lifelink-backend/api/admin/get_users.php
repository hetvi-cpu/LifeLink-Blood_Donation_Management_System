<?php
require_once '../../config/db_config.php';

// GET  /api/admin/get_users.php           → list users (search + pagination)
// POST /api/admin/get_users.php           → update a user (status / role)
// DELETE /api/admin/get_users.php         → delete a user by user_id

$method = $_SERVER['REQUEST_METHOD'];

// ─── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;

    if (!$userId) {
        echo json_encode(["status" => "error", "message" => "user_id is required"]);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    echo json_encode([
        "status"  => $stmt->affected_rows > 0 ? "success" : "error",
        "message" => $stmt->affected_rows > 0 ? "User deleted" : "User not found"
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// ─── POST (update status or role) ────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
    $status = $body['user_status'] ?? null;
    $roleId = isset($body['role_id']) ? (int)$body['role_id'] : null;

    if (!$userId) {
        echo json_encode(["status" => "error", "message" => "user_id is required"]);
        exit;
    }
    $fields = []; $types = ""; $vals = [];
    if ($status && in_array($status, ['Active','Inactive','Suspended'])) {
        $fields[] = "user_status = ?"; $types .= "s"; $vals[] = $status;
    }
    if ($roleId && in_array($roleId, [1,2])) {
        $fields[] = "role_id = ?"; $types .= "i"; $vals[] = $roleId;
    }
    if (empty($fields)) {
        echo json_encode(["status" => "error", "message" => "Nothing valid to update"]);
        exit;
    }
    $types .= "i"; $vals[] = $userId;
    $stmt = $conn->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE user_id = ?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "User updated"]);
    $stmt->close();
    $conn->close();
    exit;
}

// ─── GET (list with optional search + pagination) ────────────────────────────
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

if ($search !== '') {
    $like = "%{$conn->real_escape_string($search)}%";
    $stmt = $conn->prepare(
        "SELECT u.user_id, u.name, u.email, u.phone_number, u.blood_group,
                u.pincode, u.user_status, u.points, u.last_donation_date, u.created_at,
                r.role_name
         FROM users u JOIN roles r ON u.role_id = r.role_id
         WHERE u.name LIKE ? OR u.email LIKE ? OR u.blood_group LIKE ?
         ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("sssii", $like, $like, $like, $perPage, $offset);
    $cstmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE name LIKE ? OR email LIKE ? OR blood_group LIKE ?");
    $cstmt->bind_param("sss", $like, $like, $like);
    $cstmt->execute();
    $total = $cstmt->get_result()->fetch_assoc()['total'];
    $cstmt->close();
} else {
    $stmt = $conn->prepare(
        "SELECT u.user_id, u.name, u.email, u.phone_number, u.blood_group,
                u.pincode, u.user_status, u.points, u.last_donation_date, u.created_at,
                r.role_name
         FROM users u JOIN roles r ON u.role_id = r.role_id
         ORDER BY u.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("ii", $perPage, $offset);
    $total = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
}

$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($row = $res->fetch_assoc()) $users[] = $row;

echo json_encode([
    "status"      => "success",
    "data"        => $users,
    "total"       => (int)$total,
    "page"        => $page,
    "per_page"    => $perPage,
    "total_pages" => (int)ceil($total / $perPage)
]);
$stmt->close();
$conn->close();
?>
