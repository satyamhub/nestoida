<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"], $_SESSION["user_role"]) || $_SESSION["user_role"] !== "owner") {
    header("Location: user-login.php");
    exit();
}

$ownerId = (int)$_SESSION["user_id"];
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.title,
        p.status,
        COALESCE(v.views, 0) AS views,
        COALESCE(c.calls, 0) AS calls,
        COALESCE(f.favorites, 0) AS favorites,
        COALESCE(i.inquiries, 0) AS inquiries
    FROM properties p
    LEFT JOIN (
        SELECT property_id, COUNT(*) AS views
        FROM property_events
        WHERE event_type='view'
        GROUP BY property_id
    ) v ON v.property_id = p.id
    LEFT JOIN (
        SELECT property_id, COUNT(*) AS calls
        FROM property_events
        WHERE event_type='call'
        GROUP BY property_id
    ) c ON c.property_id = p.id
    LEFT JOIN (
        SELECT property_id, COUNT(*) AS favorites
        FROM user_favorites
        GROUP BY property_id
    ) f ON f.property_id = p.id
    LEFT JOIN (
        SELECT property_id, COUNT(*) AS inquiries
        FROM property_inquiries
        GROUP BY property_id
    ) i ON i.property_id = p.id
    WHERE p.owner_user_id = ?
    ORDER BY p.id DESC
");
$stmt->bind_param("i", $ownerId);
$stmt->execute();
$rows = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Analytics - Nestoida</title>
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
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <h1 class="font-display text-2xl">Owner Analytics</h1>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700 text-sm">Back to Dashboard</a>
            </div>
        </div>
    </header>
    <main class="max-w-6xl mx-auto px-6 py-8">
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden dark:bg-slate-900 dark:border-slate-800">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/70">
                        <tr>
                            <th class="text-left px-4 py-3">Listing</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Views</th>
                            <th class="text-left px-4 py-3">Call Clicks</th>
                            <th class="text-left px-4 py-3">Favorites</th>
                            <th class="text-left px-4 py-3">Inquiries</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if ($rows && $rows->num_rows > 0) { ?>
                            <?php while ($row = $rows->fetch_assoc()) { ?>
                                <tr>
                                    <td class="px-4 py-3 font-semibold"><?php echo htmlspecialchars((string)$row["title"]); ?></td>
                                    <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars((string)$row["status"]); ?></td>
                                    <td class="px-4 py-3"><?php echo (int)$row["views"]; ?></td>
                                    <td class="px-4 py-3"><?php echo (int)$row["calls"]; ?></td>
                                    <td class="px-4 py-3"><?php echo (int)$row["favorites"]; ?></td>
                                    <td class="px-4 py-3"><?php echo (int)$row["inquiries"]; ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No listings found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
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
