<?php
session_start();
include "db.php";

$error = "";
$unverifiedEmail = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT id, full_name, email, password, role, email_verified_at, COALESCE(session_version, 1) AS session_version FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user["password"])) {
            if (!empty($user["email_verified_at"])) {
                $_SESSION["user_id"] = (int)$user["id"];
                $_SESSION["user_name"] = $user["full_name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["user_role"] = $user["role"];
                $_SESSION["user_session_version"] = (int)$user["session_version"];

                if ($user["role"] === "owner") {
                    header("Location: owner-dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $unverifiedEmail = $user["email"];
                $error = "Please verify your email before login.";
            }
        }
    }

    if ($error === "") {
        $error = "Invalid email or password.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Nestoida</title>
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
                <h1 class="font-display text-3xl">User Login</h1>
                <button id="theme-toggle" type="button" class="px-3 py-2 text-sm rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-300 mb-4">Login as property owner or viewer.</p>

            <?php if ($error !== "") { ?>
                <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
                <?php if ($unverifiedEmail !== "") { ?>
                    <a href="resend-verification.php?email=<?php echo urlencode($unverifiedEmail); ?>" class="inline-block -mt-2 mb-3 text-sm text-cyan-700 dark:text-cyan-300 hover:underline">Resend verification email</a>
                <?php } ?>
            <?php } ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Login</button>
            </form>
            <div class="mt-3">
                <a href="forgot-password.php" class="text-sm text-cyan-700 dark:text-cyan-300 hover:underline">Forgot password?</a>
            </div>

            <div class="mt-4 flex gap-2 text-sm">
                <a href="user-register.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Create Account</a>
                <a href="index.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Home</a>
                <a href="login.php" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700">Admin Login</a>
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
