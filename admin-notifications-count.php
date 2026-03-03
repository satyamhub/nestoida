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

$unread = 0;
if ($adminId > 0) {
    $unreadStmt = $conn->prepare("SELECT COUNT(*) AS total FROM admin_notifications WHERE admin_id=? AND is_read=0");
    if ($unreadStmt) {
        $unreadStmt->bind_param("i", $adminId);
        $unreadStmt->execute();
        $unreadRes = $unreadStmt->get_result();
        $unread = $unreadRes ? (int)($unreadRes->fetch_assoc()["total"] ?? 0) : 0;
        $unreadStmt->close();
    }
}

echo json_encode(["unread" => $unread]);
exit();
?>
