<?php
session_start();
include "db.php";

$propertyId = isset($_GET["property_id"]) ? (int)$_GET["property_id"] : 0;
$eventType = strtolower(trim((string)($_GET["type"] ?? "")));
$allowed = ["call", "share", "website"];
if ($propertyId > 0 && in_array($eventType, $allowed, true)) {
    try {
        $userId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
        $ua = substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255);
        $ip = substr((string)($_SERVER["REMOTE_ADDR"] ?? ""), 0, 64);
        $stmt = $conn->prepare("INSERT INTO property_events (property_id, user_id, event_type, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisss", $propertyId, $userId, $eventType, $ua, $ip);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Silently ignore analytics failures.
    }
}

http_response_code(204);
exit();
?>
