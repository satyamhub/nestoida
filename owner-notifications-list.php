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
$filter = isset($_GET["filter"]) ? strtolower(trim((string)$_GET["filter"])) : "all";
if (!in_array($filter, ["all", "unread"], true)) {
    $filter = "all";
}

$rows = [];
$where = $filter === "unread" ? " AND is_read=0" : "";
$stmt = $conn->prepare("
    SELECT id, title, message, url, is_read, created_at
    FROM notifications
    WHERE user_id=?{$where}
    ORDER BY id DESC
    LIMIT 8
");
if ($stmt) {
    $stmt->bind_param("i", $ownerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt->close();
}

echo json_encode([
    "filter" => $filter,
    "items" => $rows
]);
exit();
?>
