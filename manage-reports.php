<?php
session_start();
include "db.php";

if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["report_id"], $_POST["action"])) {
    $reportId = (int)$_POST["report_id"];
    $action = strtolower(trim((string)$_POST["action"]));
    if ($reportId > 0 && in_array($action, ["resolve", "reopen"], true)) {
        $status = $action === "resolve" ? "resolved" : "open";
        $stmt = $conn->prepare("UPDATE listing_reports SET status=?, reviewed_at=NOW() WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("si", $status, $reportId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: manage-reports.php");
    exit();
}

$rows = $conn->query("
    SELECT
        r.*,
        p.title AS property_title,
        p.status AS property_status
    FROM listing_reports r
    INNER JOIN properties p ON p.id = r.property_id
    ORDER BY
        CASE WHEN r.status='open' THEN 0 ELSE 1 END,
        r.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderation Reports - Nestoida</title>
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
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <h1 class="font-display text-2xl">Listing Reports</h1>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700 text-sm">Back to Dashboard</a>
            </div>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-6 py-8">
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden dark:bg-slate-900 dark:border-slate-800">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/70">
                        <tr>
                            <th class="text-left px-4 py-3">Listing</th>
                            <th class="text-left px-4 py-3">Reason</th>
                            <th class="text-left px-4 py-3">Reporter</th>
                            <th class="text-left px-4 py-3">Details</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if ($rows && $rows->num_rows > 0) { ?>
                            <?php while ($row = $rows->fetch_assoc()) { ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold"><?php echo htmlspecialchars((string)$row["property_title"]); ?></p>
                                        <a href="property.php?id=<?php echo (int)$row["property_id"]; ?>" target="_blank" rel="noopener" class="text-xs text-cyan-700">Open listing</a>
                                    </td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars((string)$row["reason"]); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars((string)($row["reporter_name"] ?: "Guest")); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars((string)$row["details"]); ?></td>
                                    <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars((string)$row["status"]); ?></td>
                                    <td class="px-4 py-3">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="report_id" value="<?php echo (int)$row["id"]; ?>">
                                            <?php if ((string)$row["status"] === "open") { ?>
                                                <button type="submit" name="action" value="resolve" class="px-3 py-1.5 rounded-full bg-emerald-600 text-white text-xs">Resolve</button>
                                            <?php } else { ?>
                                                <button type="submit" name="action" value="reopen" class="px-3 py-1.5 rounded-full border border-slate-300 text-xs">Reopen</button>
                                            <?php } ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No reports found.</td></tr>
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
