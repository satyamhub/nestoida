<?php
session_start();
include "db.php";
require_once "reset-utils.php";

$error = "";
$message = "";
$devVerificationLink = "";
$mailErrorDetail = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "viewer";

    if (!in_array($role, ["owner", "viewer"], true)) {
        $role = "viewer";
    }

    if ($fullName === "" || $email === "" || $password === "") {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $existing = $checkStmt->get_result();

        if ($existing && $existing->num_rows > 0) {
            $error = "Email already registered. Please login.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $fullName, $email, $hash, $role);
            if ($insertStmt->execute()) {
                $newUserId = (int)$insertStmt->insert_id;
                $requesterHash = reset_requester_hash();

                if (verification_is_rate_limited($conn, $email, $requesterHash)) {
                    $message = "Registration successful. Please verify your email later using resend verification.";
                } else {
                    $token = verification_create_token($conn, $newUserId, $email, $requesterHash);
                    if ($token !== null) {
                        $verifyLink = reset_base_url() . "/verify-email.php?email=" . urlencode($email) . "&token=" . urlencode($token);
                        if (verification_send_email($email, $verifyLink)) {
                            $message = "Registration successful. Please check your email and verify your account.";
                        } else {
                            $message = "Registration successful, but verification email could not be sent. Use the verification link below.";
                            $devVerificationLink = $verifyLink;
                            $mailErrorDetail = function_exists("smtp_get_last_error") ? smtp_get_last_error() : "";
                        }
                    } else {
                        $message = "Registration successful. Please verify your email from login page.";
                    }
                }
            } else {
                $error = "Could not register account.";
            }
            $insertStmt->close();
        }

        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Register - Nestoida</title>
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
                <h1 class="font-display text-3xl">Create Account</h1>
                <button id="theme-toggle" type="button" class="px-3 py-2 text-sm rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
            </div>

            <?php if ($error !== "") { ?>
                <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <?php if ($message !== "") { ?>
                <div class="mb-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
            <?php } ?>
            <?php if ($devVerificationLink !== "") { ?>
                <div class="mb-4 border border-cyan-200 bg-cyan-50 text-cyan-800 rounded-xl p-3 text-sm break-all">
                    <p class="font-semibold mb-1">Verification Link (fallback):</p>
                    <a href="<?php echo htmlspecialchars($devVerificationLink); ?>" class="underline"><?php echo htmlspecialchars($devVerificationLink); ?></a>
                    <?php if ($mailErrorDetail !== "") { ?>
                        <p class="mt-2 text-xs text-cyan-900">SMTP error: <?php echo htmlspecialchars($mailErrorDetail); ?></p>
                    <?php } ?>
                </div>
            <?php } ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Full Name</label>
                    <input type="text" name="full_name" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Account Type</label>
                    <select name="role" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <option value="viewer">Viewer</option>
                        <option value="owner">Property Owner</option>
                    </select>
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Register</button>
            </form>

            <div class="mt-4 flex gap-2 text-sm">
                <a href="user-login.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">User Login</a>
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
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
