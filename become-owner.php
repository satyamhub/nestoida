<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"], $_SESSION["user_role"])) {
    header("Location: user-login.php");
    exit();
}

$userId = (int)$_SESSION["user_id"];
$role = (string)$_SESSION["user_role"];

if ($role === "owner") {
    header("Location: owner-dashboard.php");
    exit();
}

$error = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!nestoida_csrf_valid()) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET role='owner', owner_verified=0, session_version=COALESCE(session_version, 1) + 1 WHERE id=? AND role='viewer'");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            $stmt->close();
            $fetch = $conn->prepare("SELECT role, COALESCE(session_version, 1) AS session_version FROM users WHERE id=? LIMIT 1");
            $fetch->bind_param("i", $userId);
            $fetch->execute();
            $res = $fetch->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $_SESSION["user_role"] = $row["role"];
                $_SESSION["user_session_version"] = (int)$row["session_version"];
            } else {
                $_SESSION["user_role"] = "owner";
                $_SESSION["user_session_version"] = (int)($_SESSION["user_session_version"] ?? 1);
            }
            $fetch->close();
            header("Location: owner-dashboard.php");
            exit();
        } else {
            $error = "Unable to switch role right now.";
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
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
    <title>Become an Owner - Nestoida</title>
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
    <header class="sticky top-0 z-40 backdrop-blur bg-white/85 border-b border-slate-200 dark:bg-slate-950/80 dark:border-slate-800">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <h1 class="font-display text-2xl">Become an Owner</h1>
            <div class="flex gap-2 text-sm">
                <a href="index.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Home</a>
                <a href="favorites.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Favorites</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6 py-10">
        <div class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm dark:bg-slate-900 dark:border-slate-800">
            <h2 class="font-display text-2xl">List your properties on Nestoida</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Switching to Owner unlocks listing management, analytics, and owner verification. You can still browse as a viewer.</p>

            <?php if ($error !== "") { ?>
                <div class="mt-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <?php if ($message !== "") { ?>
                <div class="mt-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
            <?php } ?>

            <form method="POST" class="mt-6">
                <?php echo nestoida_csrf_field(); ?>
                <button type="submit" class="w-full sm:w-auto bg-slate-900 text-white px-6 py-3 rounded-full font-semibold hover:bg-cyan-700 transition">Become an Owner</button>
            </form>
        </div>
    </main>

    <script src="assets/js/back-button.js"></script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
