<?php
session_start();
include "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["admin"])) {
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit();
}

$adminUsername = (string)$_SESSION["admin"];
$adminId = 0;
$adminStmt = $conn->prepare("SELECT id FROM admin WHERE username=? LIMIT 1");
if ($adminStmt) {
    $adminStmt->bind_param("s", $adminUsername);
    $adminStmt->execute();
    $adminRes = $adminStmt->get_result();
    $adminRow = $adminRes ? $adminRes->fetch_assoc() : null;
    $adminId = $adminRow ? (int)$adminRow["id"] : 0;
    $adminStmt->close();
}

$filter = isset($_GET["filter"]) ? strtolower(trim((string)$_GET["filter"])) : "all";
if (!in_array($filter, ["all", "unread"], true)) {
    $filter = "all";
}

$rows = [];
if ($adminId > 0) {
    $where = $filter === "unread" ? " AND is_read=0" : "";
    $stmt = $conn->prepare("
        SELECT id, title, message, url, is_read, created_at
        FROM admin_notifications
        WHERE admin_id=?{$where}
        ORDER BY id DESC
        LIMIT 8
    ");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        $stmt->close();
    }
}

echo json_encode([
    "filter" => $filter,
    "items" => $rows
]);
exit();
?>
