<?php
session_start();
include "db.php";
require_once "reset-utils.php";

$email = trim($_GET["email"] ?? "");
$token = trim($_GET["token"] ?? "");
$error = "";
$message = "";

if ($email === "" || $token === "") {
    $error = "Invalid verification link.";
} else {
    $verification = verification_verify_token($conn, $email, $token);
    if (!$verification) {
        $error = "Verification link is invalid or expired.";
    } else {
        $verificationId = (int)$verification["id"];
        $userId = (int)$verification["user_id"];

        $update = $conn->prepare("UPDATE users SET email_verified_at=NOW() WHERE id=?");
        $update->bind_param("i", $userId);

        if ($update->execute()) {
            verification_mark_used($conn, $verificationId, $email);
            $message = "Email verified successfully. You can now login.";
        } else {
            $error = "Could not verify email.";
        }

        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Nestoida</title>
    <script>
        (function () {
            try {
                if (localStorage.getItem("nestoida_theme") === "dark") {
                    document.documentElement.classList.add("dark");
                }
            } catch (e) {}
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui bg-gradient-to-b from-slate-50 to-white min-h-screen dark:from-slate-950 dark:to-slate-900 text-slate-900 dark:text-slate-100 font-sans">
    <main class="max-w-lg mx-auto px-6 py-16">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8">
            <h1 class="text-2xl font-semibold">Email Verification</h1>
            <?php if ($error !== "") { ?>
                <div class="mt-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <?php if ($message !== "") { ?>
                <div class="mt-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
            <?php } ?>
            <div class="mt-6 flex gap-2 text-sm">
                <a href="user-login.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">User Login</a>
                <a href="resend-verification.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Resend Verification</a>
                <a href="index.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Home</a>
            </div>
        </div>
    </main>
    <script src="assets/js/back-button.js"></script>
</body>
</html>
