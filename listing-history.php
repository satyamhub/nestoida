<?php
session_start();
include "db.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$isAdmin = isset($_SESSION["admin"]);
$isOwner = isset($_SESSION["user_id"], $_SESSION["user_role"]) && $_SESSION["user_role"] === "owner";
$ownerId = $isOwner ? (int)$_SESSION["user_id"] : 0;

if (!$isAdmin && !$isOwner) {
    header("Location: user-login.php");
    exit();
}

$propStmt = $conn->prepare("SELECT id, title, owner_user_id FROM properties WHERE id=? LIMIT 1");
$propStmt->bind_param("i", $id);
$propStmt->execute();
$propRes = $propStmt->get_result();
$property = $propRes ? $propRes->fetch_assoc() : null;
$propStmt->close();

if (!$property) {
    header("Location: index.php");
    exit();
}
if ($isOwner && (int)$property["owner_user_id"] !== $ownerId) {
    header("Location: owner-dashboard.php");
    exit();
}

$historyStmt = $conn->prepare("
    SELECT field_name, old_value, new_value, changed_by_role, changed_by_user_id, created_at
    FROM property_change_log
    WHERE property_id=?
    ORDER BY id DESC
    LIMIT 100
");
$historyStmt->bind_param("i", $id);
$historyStmt->execute();
$history = $historyStmt->get_result();
$historyStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listing History - Nestoida</title>
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
            <div>
                <h1 class="font-display text-2xl">Listing History</h1>
                <p class="text-xs text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars((string)$property["title"]); ?> (#<?php echo (int)$property["id"]; ?>)</p>
            </div>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <?php if ($isAdmin) { ?>
                    <a href="dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Dashboard</a>
                <?php } else { ?>
                    <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Owner Panel</a>
                <?php } ?>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-8">
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden dark:bg-slate-900 dark:border-slate-800">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/70">
                        <tr>
                            <th class="text-left px-4 py-3">Field</th>
                            <th class="text-left px-4 py-3">Old Value</th>
                            <th class="text-left px-4 py-3">New Value</th>
                            <th class="text-left px-4 py-3">Changed By</th>
                            <th class="text-left px-4 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if ($history && $history->num_rows > 0) { ?>
                            <?php while ($row = $history->fetch_assoc()) { ?>
                                <tr>
                                    <td class="px-4 py-3 font-semibold"><?php echo htmlspecialchars((string)$row["field_name"]); ?></td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars((string)($row["old_value"] ?? "-")); ?></td>
                                    <td class="px-4 py-3 text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars((string)($row["new_value"] ?? "-")); ?></td>
                                    <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars((string)$row["changed_by_role"]); ?></td>
                                    <td class="px-4 py-3 text-slate-500"><?php echo htmlspecialchars((string)$row["created_at"]); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No changes recorded yet.</td>
                            </tr>
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
