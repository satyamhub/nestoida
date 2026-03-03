<?php
session_start();
include "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"], $_SESSION["user_role"]) || $_SESSION["user_role"] !== "owner") {
    http_response_code(401);
    echo json_encode(["error" => "unauthorized"]);
    exit();
}

$ownerId = (int)$_SESSION["user_id"];
$unread = 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id=? AND is_read=0");
if ($stmt) {
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $unread = $res ? (int)($res->fetch_assoc()["total"] ?? 0) : 0;
    $stmt->close();
}

echo json_encode(["unread" => $unread]);
exit();
?>
