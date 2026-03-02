<?php
session_start();
include "db.php";
require_once "reset-utils.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $requesterHash = reset_requester_hash();

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (reset_is_rate_limited($conn, "user", $email, $requesterHash)) {
        $error = "Too many reset requests. Please try again later.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $message = "If this email exists, a reset link has been sent.";

        if ($result && $result->num_rows > 0) {
            $token = reset_create_token($conn, "user", $email, $requesterHash);
            if ($token !== null) {
                $resetLink = reset_base_url() . "/reset-password.php?email=" . urlencode($email) . "&token=" . urlencode($token);
                if (!reset_send_email($email, $resetLink, "user")) {
                    $error = "Unable to send reset email right now. Please try again later.";
                    $message = "";
                }
            } else {
                $error = "Could not create reset request.";
                $message = "";
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Nestoida</title>
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
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Space Grotesk"', 'sans-serif'],
                        body: ['"Manrope"', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui font-body bg-gradient-to-b from-slate-50 to-white min-h-screen text-slate-900 dark:from-slate-950 dark:to-slate-900 dark:text-slate-100">
    <main class="max-w-xl mx-auto px-6 py-12">
        <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm dark:bg-slate-900 dark:border-slate-800">
            <div class="flex items-center justify-between gap-2 mb-4">
                <h1 class="font-display text-3xl">Forgot Password</h1>
                <button id="theme-toggle" type="button" class="px-3 py-2 text-sm rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-300 mb-4">Enter your user account email to reset your password.</p>

            <?php if ($error !== "") { ?>
                <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <?php if ($message !== "") { ?>
                <div class="mb-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
            <?php } ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Generate Reset Link</button>
            </form>

            <div class="mt-4 flex gap-2 text-sm">
                <a href="user-login.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">User Login</a>
                <a href="user-register.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Register</a>
                <a href="index.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Home</a>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const btn = document.getElementById("theme-toggle");
            const label = document.getElementById("theme-toggle-label");
            function syncThemeLabel() {
                if (!label) return;
                label.textContent = document.documentElement.classList.contains("dark") ? "Light" : "Dark";
            }
            syncThemeLabel();
            if (btn) {
                btn.addEventListener("click", function () {
                    const root = document.documentElement;
                    const isDark = root.classList.toggle("dark");
                    try {
                        localStorage.setItem("nestoida_theme", isDark ? "dark" : "light");
                    } catch (e) {}
                    syncThemeLabel();
                });
            }
        })();
    </script>
    <script src="assets/js/back-button.js"></script>
</body>
</html>
