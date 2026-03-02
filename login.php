<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "db.php";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();

        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin'] = $username;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Wrong password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Nestoida</title>
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
<body class="airbnb-ui font-body min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-cyan-900 text-white px-4 py-10 dark:from-slate-950 dark:via-slate-900 dark:to-black">
    <div class="max-w-5xl mx-auto grid lg:grid-cols-2 gap-6 items-stretch">
        <section class="rounded-3xl bg-white/10 border border-white/15 backdrop-blur p-8 md:p-10 dark:bg-slate-900/60 dark:border-slate-700">
            <p class="text-xs uppercase tracking-[0.18em] text-cyan-200">Admin Portal</p>
            <h1 class="font-display text-4xl mt-3">Nestoida Control Panel</h1>
            <p class="text-slate-200 mt-3 leading-relaxed">Access listing workflows, approve submissions, and keep your rental catalog accurate and up to date.</p>
            <div class="mt-8 text-sm text-slate-300 space-y-2">
                <p>Secure admin-only access</p>
                <p>Fast moderation controls</p>
                <p>Professional publishing flow</p>
            </div>
        </section>

        <section class="rounded-3xl bg-white text-slate-900 p-8 md:p-10 shadow-2xl dark:bg-slate-900 dark:text-slate-100">
            <h2 class="font-display text-3xl tracking-tight">Sign in</h2>
            <p class="text-sm text-slate-500 dark:text-slate-300 mt-1">Use your admin credentials to continue.</p>

            <?php if ($error !== "") { ?>
                <div class="mt-5 border border-rose-200 bg-rose-50 text-rose-700 text-sm rounded-xl p-3">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php } ?>

            <form method="POST" class="mt-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Username</label>
                    <input type="text" name="username" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Login to Dashboard</button>
            </form>
            <div class="mt-3">
                <a href="admin-forgot-password.php" class="text-sm text-cyan-700 dark:text-cyan-300 hover:underline">Forgot admin password?</a>
            </div>

            <div class="mt-5 flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-lg border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="index.php" class="px-3 py-2 rounded-lg border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Back to Home</a>
                <a href="dashboard.php" class="px-3 py-2 rounded-lg border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Dashboard</a>
            </div>
        </section>
    </div>
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
