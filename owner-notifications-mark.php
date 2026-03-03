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
$action = isset($_POST["action"]) ? strtolower(trim((string)$_POST["action"])) : "";
$noteId = isset($_POST["id"]) ? (int)$_POST["id"] : 0;

if ($action === "all") {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $ownerId);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["ok" => true]);
    exit();
}

if ($action === "one" && $noteId > 0) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    if ($stmt) {
        $stmt->bind_param("ii", $noteId, $ownerId);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["ok" => true]);
    exit();
}

http_response_code(400);
echo json_encode(["error" => "bad_request"]);
exit();
?>
