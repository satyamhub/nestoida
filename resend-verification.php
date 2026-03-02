<?php
session_start();
include "db.php";
require_once "reset-utils.php";

$email = trim($_GET["email"] ?? $_POST["email"] ?? "");
$error = "";
$message = "";
$devVerificationLink = "";
$mailErrorDetail = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $requesterHash = reset_requester_hash();

        if (verification_is_rate_limited($conn, $email, $requesterHash)) {
            $error = "Too many requests. Please try again later.";
        } else {
            $stmt = $conn->prepare("SELECT id, email_verified_at FROM users WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            $message = "If account exists and is not verified, a verification email has been sent.";

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (empty($user['email_verified_at'])) {
                    $token = verification_create_token($conn, (int)$user['id'], $email, $requesterHash);
                    if ($token !== null) {
                        $verifyLink = reset_base_url() . "/verify-email.php?email=" . urlencode($email) . "&token=" . urlencode($token);
                        if (!verification_send_email($email, $verifyLink)) {
                            $message = "Email could not be sent. Use the verification link below.";
                            $devVerificationLink = $verifyLink;
                            $mailErrorDetail = function_exists("smtp_get_last_error") ? smtp_get_last_error() : "";
                        }
                    } else {
                        $error = "Could not create verification request.";
                        $message = "";
                    }
                }
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Nestoida</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui bg-gradient-to-b from-slate-50 to-white min-h-screen text-slate-900 font-sans">
    <main class="max-w-xl mx-auto px-6 py-12">
        <div class="bg-white border border-slate-200 rounded-2xl p-8">
            <h1 class="text-2xl font-semibold">Resend Verification Email</h1>
            <?php if ($error !== "") { ?>
                <div class="mt-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <?php if ($message !== "") { ?>
                <div class="mt-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
            <?php } ?>
            <?php if ($devVerificationLink !== "") { ?>
                <div class="mt-4 border border-cyan-200 bg-cyan-50 text-cyan-800 rounded-xl p-3 text-sm break-all">
                    <p class="font-semibold mb-1">Verification Link (fallback):</p>
                    <a href="<?php echo htmlspecialchars($devVerificationLink); ?>" class="underline"><?php echo htmlspecialchars($devVerificationLink); ?></a>
                    <?php if ($mailErrorDetail !== "") { ?>
                        <p class="mt-2 text-xs text-cyan-900">SMTP error: <?php echo htmlspecialchars($mailErrorDetail); ?></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <form method="POST" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-semibold">Resend Verification</button>
            </form>
            <div class="mt-4 flex gap-2 text-sm">
                <a href="user-login.php" class="px-3 py-2 rounded-lg border border-slate-300">User Login</a>
                <a href="index.php" class="px-3 py-2 rounded-lg border border-slate-300">Home</a>
            </div>
        </div>
    </main>
    <script src="assets/js/back-button.js"></script>
</body>
</html>
