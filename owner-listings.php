<?php
session_start();
include "db.php";

$ownerId = isset($_GET["owner_id"]) ? (int)$_GET["owner_id"] : 0;
if ($ownerId <= 0) {
    header("Location: index.php");
    exit();
}

$ownerStmt = $conn->prepare("SELECT id, full_name, role, profile_photo FROM users WHERE id=? LIMIT 1");
$ownerStmt->bind_param("i", $ownerId);
$ownerStmt->execute();
$ownerRes = $ownerStmt->get_result();
$owner = $ownerRes ? $ownerRes->fetch_assoc() : null;
$ownerStmt->close();

if (!$owner) {
    header("Location: index.php");
    exit();
}

$listStmt = $conn->prepare("
    SELECT
        p.*,
        p.TYPE AS type,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.rating_count, 0) AS rating_count
    FROM properties p
    LEFT JOIN (
        SELECT property_id, ROUND(AVG(feedback_rating), 1) AS avg_rating, COUNT(*) AS rating_count
        FROM listing_feedback
        WHERE feedback_rating BETWEEN 1 AND 5
        GROUP BY property_id
    ) r ON r.property_id = p.id
    WHERE p.owner_user_id=? AND p.status='approved'
    ORDER BY p.id DESC
");
$listStmt->bind_param("i", $ownerId);
$listStmt->execute();
$listings = $listStmt->get_result();

function renderStars($avgRating)
{
    $filled = (int)round((float)$avgRating);
    $html = '<span class="inline-flex items-center gap-0.5">';
    for ($i = 1; $i <= 5; $i++) {
        $fillClass = $i <= $filled ? "text-amber-500" : "text-slate-300 dark:text-slate-600";
        $html .= '<svg class="w-4 h-4 ' . $fillClass . '" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.95-.69l1.07-3.292z"/></svg>';
    }
    $html .= '</span>';
    return $html;
}

function listingSpecText($row)
{
    $type = strtolower(trim((string)($row["type"] ?? "")));
    $seater = trim((string)($row["seater_option"] ?? ""));
    $bhk = trim((string)($row["bhk_option"] ?? ""));
    $isPgOrHostel = strpos($type, "pg") !== false || strpos($type, "hostel") !== false;
    $isFlatLike = strpos($type, "flat") !== false || strpos($type, "apartment") !== false || strpos($type, "bhk") !== false;

    if ($isPgOrHostel) {
        return $seater !== "" ? $seater : "Seater not set";
    }
    if ($isFlatLike) {
        return $bhk !== "" ? $bhk : "BHK not set";
    }
    return "";
}

$ownerPhotoPath = "";
if (!empty($owner["profile_photo"]) && is_file(__DIR__ . "/uploads/profiles/" . $owner["profile_photo"])) {
    $ownerPhotoPath = "uploads/profiles/" . $owner["profile_photo"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((string)$owner["full_name"]); ?> - Listings</title>
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
                    colors: {
                        brand: {
                            ink: '#1f5c49',
                            mist: '#f3f4f6',
                            slate: '#1f2937',
                            sky: '#0ea5e9'
                        }
                    },
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
            <div class="flex items-center gap-3">
                <?php if ($ownerPhotoPath !== "") { ?>
                    <img src="<?php echo htmlspecialchars($ownerPhotoPath); ?>" alt="Owner photo" class="w-11 h-11 rounded-full object-cover border border-slate-200">
                <?php } else { ?>
                    <div class="w-11 h-11 rounded-full bg-slate-100 border border-slate-200"></div>
                <?php } ?>
                <div>
                    <h1 class="font-display text-2xl"><?php echo htmlspecialchars((string)$owner["full_name"]); ?></h1>
                    <p class="text-xs text-slate-500 dark:text-slate-300">Approved listings by this owner</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700 text-sm">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="index.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700 text-sm">Back to Home</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8 sm:py-10">
        <div class="grid grid-flow-col auto-cols-[82%] sm:auto-cols-[58%] md:grid-flow-row md:auto-cols-auto md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-7 overflow-x-auto md:overflow-visible mobile-slider pb-2">
            <?php if ($listings && $listings->num_rows > 0) { ?>
                <?php while ($row = $listings->fetch_assoc()) { ?>
                    <?php $specText = listingSpecText($row); ?>
                    <article class="group rounded-3xl bg-white border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-300 dark:bg-slate-900 dark:border-slate-800 snap-start">
                        <img src="uploads/<?php echo htmlspecialchars((string)$row["image"]); ?>" alt="<?php echo htmlspecialchars((string)$row["title"]); ?>" class="w-full h-56 object-cover">
                        <div class="p-5">
                            <h3 class="font-display text-xl"><?php echo htmlspecialchars((string)$row["title"]); ?></h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Sector <?php echo htmlspecialchars((string)$row["sector"]); ?> · <?php echo htmlspecialchars((string)$row["type"]); ?><?php if ($specText !== "") { ?> · <?php echo htmlspecialchars($specText); ?><?php } ?></p>
                            <div class="mt-2 flex items-center gap-2">
                                <?php echo renderStars((float)$row["avg_rating"]); ?>
                                <p class="text-sm text-amber-600 font-semibold">
                                    <?php echo (int)$row["rating_count"] > 0 ? number_format((float)$row["avg_rating"], 1) . "/5 (" . (int)$row["rating_count"] . ")" : "Not rated yet"; ?>
                                </p>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">Rs <?php echo (int)$row["rent"]; ?>/mo</p>
                            <a href="property.php?id=<?php echo (int)$row["id"]; ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-full bg-slate-900 text-white text-sm font-semibold group-hover:bg-cyan-700 transition">View stay</a>
                        </div>
                    </article>
                <?php } ?>
            <?php } else { ?>
                <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-800 p-10 text-center text-slate-500 dark:text-slate-300">
                    No approved listings found for this owner.
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
