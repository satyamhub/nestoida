<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: user-login.php");
    exit();
}

$userId = (int)$_SESSION["user_id"];
$stmt = $conn->prepare("
    SELECT
        p.*,
        p.type AS type,
        COALESCE(u.full_name, 'Nestoida Team') AS owner_name,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.rating_count, 0) AS rating_count,
        f.created_at AS saved_at
    FROM user_favorites f
    INNER JOIN properties p ON p.id = f.property_id
    LEFT JOIN users u ON u.id = p.owner_user_id
    LEFT JOIN (
        SELECT property_id, ROUND(AVG(feedback_rating), 1) AS avg_rating, COUNT(*) AS rating_count
        FROM listing_feedback
        WHERE feedback_rating BETWEEN 1 AND 5
        GROUP BY property_id
    ) r ON r.property_id = p.id
    WHERE f.user_id = ? AND p.status='approved'
    ORDER BY f.id DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$rows = $stmt->get_result();

function renderStars($avgRating)
{
    $filled = (int)round((float)$avgRating);
    $html = '<span class="inline-flex items-center gap-0.5">';
    for ($i = 1; $i <= 5; $i++) {
        $fillClass = $i <= $filled ? "text-amber-500" : "text-slate-300 dark:text-slate-600";
        $html .= '<svg class="w-4 h-4 ' . $fillClass . '" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.95-.69l1.07-3.292z"/></svg>';
    }
    $html .= '</span>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Nestoida</title>
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
<body class="airbnb-ui font-body bg-gradient-to-b from-slate-50 to-white text-slate-900 min-h-screen dark:from-slate-950 dark:to-slate-900 dark:text-slate-100">
    <header class="sticky top-0 z-40 backdrop-blur bg-white/85 border-b border-slate-200 dark:bg-slate-950/80 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between gap-3">
            <h1 class="font-display text-2xl">My Favorites</h1>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="index.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Home</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Logout</a>
            </div>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-6 py-8">
        <div class="grid grid-flow-col auto-cols-[82%] sm:auto-cols-[58%] md:grid-flow-row md:auto-cols-auto md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-7 overflow-x-auto md:overflow-visible mobile-slider pb-2">
            <?php if ($rows && $rows->num_rows > 0) { ?>
                <?php while ($row = $rows->fetch_assoc()) { ?>
                    <article class="group rounded-3xl bg-white border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-300 dark:bg-slate-900 dark:border-slate-800 snap-start">
                        <img src="uploads/<?php echo htmlspecialchars((string)$row["image"]); ?>" alt="<?php echo htmlspecialchars((string)$row["title"]); ?>" class="w-full h-56 object-cover" loading="lazy">
                        <div class="p-5">
                            <h3 class="font-display text-xl"><?php echo htmlspecialchars((string)$row["title"]); ?></h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Sector <?php echo htmlspecialchars((string)$row["sector"]); ?> · <?php echo htmlspecialchars((string)$row["type"]); ?></p>
                            <div class="mt-2 flex items-center gap-2">
                                <?php echo renderStars((float)$row["avg_rating"]); ?>
                                <span class="text-sm text-amber-600 font-semibold"><?php echo (int)$row["rating_count"] > 0 ? number_format((float)$row["avg_rating"], 1) : "Not rated"; ?></span>
                            </div>
                            <p class="mt-2 text-sm font-semibold">Rs <?php echo (int)$row["rent"]; ?>/mo</p>
                            <a href="property.php?id=<?php echo (int)$row["id"]; ?>" class="inline-flex mt-4 px-4 py-2 rounded-full bg-slate-900 text-white text-sm font-semibold">Open Listing</a>
                        </div>
                    </article>
                <?php } ?>
            <?php } else { ?>
                <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-800 p-10 text-center text-slate-500 dark:text-slate-300">
                    You have no saved favorites yet.
                </div>
            <?php } ?>
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
