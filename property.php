<?php
session_start();
include "db.php";
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdminLoggedIn = isset($_SESSION['admin']);
$isUserLoggedIn = isset($_SESSION['user_id'], $_SESSION['user_role']);
$userRole = $isUserLoggedIn ? $_SESSION['user_role'] : null;
$currentUserId = $isUserLoggedIn ? (int)$_SESSION['user_id'] : 0;
$favoriteSaved = isset($_GET["fav"]) && $_GET["fav"] === "1";
$reportSaved = isset($_GET["report"]) && $_GET["report"] === "1";
$inquirySaved = isset($_GET["inquiry"]) && $_GET["inquiry"] === "1";

function renderStars($avgRating)
{
    $filled = (int)round((float)$avgRating);
    $html = '<span class="inline-flex items-center gap-1">';
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

function extractCoordsFromMapsUrl($value)
{
    $raw = trim((string)$value);
    if ($raw === "") {
        return null;
    }
    $decoded = urldecode($raw);
    $patterns = [
        '/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        '/[?&]q=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        '/[?&]ll=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
        '/[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $decoded, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return [$lat, $lng];
            }
        }
    }
    return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["toggle_favorite"])) {
    if ($currentUserId > 0 && $id > 0) {
        $toggleStmt = $conn->prepare("SELECT id FROM user_favorites WHERE user_id=? AND property_id=? LIMIT 1");
        $toggleStmt->bind_param("ii", $currentUserId, $id);
        $toggleStmt->execute();
        $toggleRes = $toggleStmt->get_result();
        $exists = $toggleRes && $toggleRes->num_rows > 0;
        $toggleStmt->close();
        if ($exists) {
            $deleteFav = $conn->prepare("DELETE FROM user_favorites WHERE user_id=? AND property_id=?");
            $deleteFav->bind_param("ii", $currentUserId, $id);
            $deleteFav->execute();
            $deleteFav->close();
        } else {
            $insertFav = $conn->prepare("INSERT INTO user_favorites (user_id, property_id) VALUES (?, ?)");
            $insertFav->bind_param("ii", $currentUserId, $id);
            $insertFav->execute();
            $insertFav->close();
        }
    }
    header("Location: property.php?id=" . $id . "&fav=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_report"])) {
    $reason = trim((string)($_POST["report_reason"] ?? ""));
    $details = trim((string)($_POST["report_details"] ?? ""));
    $reporterName = trim((string)($_POST["reporter_name"] ?? ""));
    $reporterEmail = trim((string)($_POST["reporter_email"] ?? ""));
    if ($reason !== "" && $id > 0) {
        if ($currentUserId > 0) {
            $reporterName = (string)($_SESSION["user_name"] ?? "User");
            $reporterEmail = (string)($_SESSION["user_email"] ?? "");
        } elseif ($reporterName === "") {
            $reporterName = "Guest";
        }
        $insertReport = $conn->prepare("INSERT INTO listing_reports (property_id, user_id, reporter_name, reporter_email, reason, details) VALUES (?, ?, ?, ?, ?, ?)");
        if ($insertReport) {
            $uid = $currentUserId > 0 ? $currentUserId : null;
            $insertReport->bind_param("iissss", $id, $uid, $reporterName, $reporterEmail, $reason, $details);
            $insertReport->execute();
            $insertReport->close();
        }
    }
    header("Location: property.php?id=" . $id . "&report=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_inquiry"])) {
    $name = trim((string)($_POST["inquiry_name"] ?? ""));
    $email = trim((string)($_POST["inquiry_email"] ?? ""));
    $phone = trim((string)($_POST["inquiry_phone"] ?? ""));
    $message = trim((string)($_POST["inquiry_message"] ?? ""));
    if ($message !== "" && $id > 0) {
        if ($currentUserId > 0) {
            $name = (string)($_SESSION["user_name"] ?? $name);
            $email = (string)($_SESSION["user_email"] ?? $email);
        }
        if ($name === "") {
            $name = "Guest";
        }
        $inquiryStmt = $conn->prepare("
            INSERT INTO property_inquiries (property_id, owner_user_id, user_id, name, email, phone, message)
            SELECT p.id, p.owner_user_id, ?, ?, ?, ?, ?
            FROM properties p
            WHERE p.id = ?
            LIMIT 1
        ");
        if ($inquiryStmt) {
            $uid = $currentUserId > 0 ? $currentUserId : null;
            $inquiryStmt->bind_param("issssi", $uid, $name, $email, $phone, $message, $id);
            $inquiryStmt->execute();
            $inquiryStmt->close();
        }
    }
    header("Location: property.php?id=" . $id . "&inquiry=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_feedback"])) {
    $comment = trim((string)($_POST["comment"] ?? ""));
    $feedbackRating = isset($_POST["feedback_rating"]) ? (int)$_POST["feedback_rating"] : 0;
    $guestName = trim((string)($_POST["guest_name"] ?? ""));
    $guestEmail = trim((string)($_POST["guest_email"] ?? ""));

    if ($comment !== "" && $id > 0 && $feedbackRating >= 1 && $feedbackRating <= 5) {
        $userId = null;
        $commenterName = $guestName;
        $commenterEmail = $guestEmail;
        $commenterRole = "guest";

        if ($isUserLoggedIn) {
            $userId = (int)$_SESSION["user_id"];
            $commenterName = (string)($_SESSION["user_name"] ?? "User");
            $commenterEmail = (string)($_SESSION["user_email"] ?? "");
            $commenterRole = (string)($_SESSION["user_role"] ?? "viewer");
        } elseif ($isAdminLoggedIn) {
            $commenterName = (string)($_SESSION["admin"] ?? "Admin");
            $commenterRole = "admin";
        } elseif ($commenterName === "") {
            $commenterName = "Guest";
        }

        $insertFeedback = $conn->prepare("
            INSERT INTO listing_feedback (property_id, owner_user_id, user_id, commenter_name, commenter_email, commenter_role, feedback_rating, comment)
            SELECT p.id, p.owner_user_id, ?, ?, ?, ?, ?, ?
            FROM properties p
            WHERE p.id = ?
            LIMIT 1
        ");
        if ($insertFeedback) {
            $insertFeedback->bind_param("isssisi", $userId, $commenterName, $commenterEmail, $commenterRole, $feedbackRating, $comment, $id);
            $insertFeedback->execute();
            $insertFeedback->close();
        }

        if ($isUserLoggedIn) {
            $voterHash = "user:" . (int)$_SESSION["user_id"];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"] ?? "";
            $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
            $voterHash = hash("sha256", $ip . "|" . $ua);
        }
        $insertRating = $conn->prepare("INSERT INTO property_ratings (property_id, voter_hash, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), created_at=CURRENT_TIMESTAMP");
        if ($insertRating) {
            $insertRating->bind_param("isi", $id, $voterHash, $feedbackRating);
            $insertRating->execute();
            $insertRating->close();
        }
    }

    header("Location: property.php?id=" . $id . "&feedback=1");
    exit();
}

$stmt = $conn->prepare("
    SELECT
        p.*,
        p.TYPE AS type,
        COALESCE(o.full_name, 'Nestoida Team') AS owner_name,
        o.profile_photo AS owner_photo,
        COALESCE(r.avg_rating, 0) AS avg_rating,
        COALESCE(r.rating_count, 0) AS rating_count
    FROM properties p
    LEFT JOIN users o ON o.id = p.owner_user_id
    LEFT JOIN (
        SELECT property_id, ROUND(AVG(feedback_rating), 1) AS avg_rating, COUNT(*) AS rating_count
        FROM listing_feedback
        WHERE feedback_rating BETWEEN 1 AND 5
        GROUP BY property_id
    ) r ON r.property_id = p.id
    WHERE p.id=?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;

if (!$row) {
    header("Location: index.php");
    exit();
}
$imageList = [];
$imgStmt = $conn->prepare("SELECT image_name, label FROM property_images WHERE property_id=? ORDER BY is_cover DESC, sort_order ASC, id ASC");
if ($imgStmt) {
    $imgStmt->bind_param("i", $id);
    $imgStmt->execute();
    $imgRes = $imgStmt->get_result();
    if ($imgRes) {
        while ($img = $imgRes->fetch_assoc()) {
            $imageList[] = [
                "name" => (string)$img["image_name"],
                "label" => (string)($img["label"] ?? "")
            ];
        }
    }
    $imgStmt->close();
}
if (empty($imageList) && !empty($row["image"])) {
    $imageList[] = ["name" => (string)$row["image"], "label" => ""];
}
$feedbackSaved = isset($_GET["feedback"]) && $_GET["feedback"] === "1";
$specText = listingSpecText($row);
$isFavorite = false;
if ($currentUserId > 0) {
    $favStmt = $conn->prepare("SELECT id FROM user_favorites WHERE user_id=? AND property_id=? LIMIT 1");
    if ($favStmt) {
        $favStmt->bind_param("ii", $currentUserId, $id);
        $favStmt->execute();
        $favRes = $favStmt->get_result();
        $isFavorite = $favRes && $favRes->num_rows > 0;
        $favStmt->close();
    }
}
$favoriteCount = 0;
$favCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_favorites WHERE property_id=?");
if ($favCountStmt) {
    $favCountStmt->bind_param("i", $id);
    $favCountStmt->execute();
    $favCountRes = $favCountStmt->get_result();
    $favoriteCount = $favCountRes ? (int)($favCountRes->fetch_assoc()["total"] ?? 0) : 0;
    $favCountStmt->close();
}

$feedbackStmt = $conn->prepare("
    SELECT
        lf.*,
        COALESCE(u.full_name, lf.commenter_name, 'Guest') AS display_name,
        u.profile_photo AS user_photo,
        COALESCE(lf.feedback_rating, 0) AS feedback_rating,
        CASE
            WHEN COALESCE(lf.commenter_role, '') <> '' THEN LOWER(lf.commenter_role)
            WHEN u.role IS NOT NULL THEN LOWER(u.role)
            ELSE 'guest'
        END AS display_role
    FROM listing_feedback lf
    LEFT JOIN users u ON u.id = lf.user_id
    WHERE lf.property_id = ?
    ORDER BY lf.id DESC
    LIMIT 20
");
$feedbackStmt->bind_param("i", $id);
$feedbackStmt->execute();
$feedbackResult = $feedbackStmt->get_result();
$feedbackCount = $feedbackResult ? (int)$feedbackResult->num_rows : 0;
$latitude = isset($row["latitude"]) ? (float)$row["latitude"] : null;
$longitude = isset($row["longitude"]) ? (float)$row["longitude"] : null;
$hasExactCoordinates = $latitude !== null && $longitude !== null && $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
$coordsFromMapUrl = extractCoordsFromMapsUrl((string)($row["map_url"] ?? ""));
if (!$hasExactCoordinates && $coordsFromMapUrl) {
    $latitude = $coordsFromMapUrl[0];
    $longitude = $coordsFromMapUrl[1];
    $hasExactCoordinates = true;
}
if ($hasExactCoordinates) {
    $mapEmbedUrl = "https://www.google.com/maps?q=" . urlencode($latitude . "," . $longitude) . "&z=16&output=embed";
    $mapCaption = "Map preview based on exact coordinates provided by owner.";
} else {
    $mapQuery = trim((string)($row["title"] ?? "")) . ", Sector " . trim((string)($row["sector"] ?? "")) . ", Noida";
    $mapEmbedUrl = "https://www.google.com/maps?q=" . urlencode($mapQuery) . "&output=embed";
    $mapCaption = "Map preview based on listing title and sector.";
}

$nearestLandmark = "";
if ($hasExactCoordinates) {
    $landmarks = [
        ["name" => "Noida Sector 18 Metro", "lat" => 28.5708, "lng" => 77.3260],
        ["name" => "Noida City Centre Metro", "lat" => 28.5745, "lng" => 77.3561],
        ["name" => "Botanical Garden Metro", "lat" => 28.5639, "lng" => 77.3346],
        ["name" => "Sector 62 Electronic City Metro", "lat" => 28.6270, "lng" => 77.3649]
    ];
    $best = null;
    foreach ($landmarks as $lm) {
        $dLat = deg2rad($lm["lat"] - $latitude);
        $dLon = deg2rad($lm["lng"] - $longitude);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($latitude)) * cos(deg2rad($lm["lat"])) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceKm = 6371 * $c;
        if ($best === null || $distanceKm < $best["distance"]) {
            $best = ["name" => $lm["name"], "distance" => $distanceKm];
        }
    }
    if ($best) {
        $nearestLandmark = $best["name"] . " (" . number_format((float)$best["distance"], 1) . " km)";
    }
}

try {
    $eventType = "view";
    $ua = substr((string)($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 255);
    $ip = substr((string)($_SERVER["REMOTE_ADDR"] ?? ""), 0, 64);
    $uid = $currentUserId > 0 ? $currentUserId : null;
    $eventStmt = $conn->prepare("INSERT INTO property_events (property_id, user_id, event_type, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
    if ($eventStmt) {
        $eventStmt->bind_param("iisss", $id, $uid, $eventType, $ua, $ip);
        $eventStmt->execute();
        $eventStmt->close();
    }
} catch (Throwable $e) {
    // Ignore analytics write failures.
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($row['title']); ?> - Nestoida</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr(trim((string)$row['description']), 0, 150)); ?>">
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
        <div class="max-w-5xl mx-auto px-6 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="index.php" class="text-sm px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Back to Listings</a>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <?php if ($isAdminLoggedIn) { ?>
                    <a href="dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Admin Dashboard</a>
                    <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
                <?php } elseif ($isUserLoggedIn) { ?>
                    <?php if ($userRole === 'owner') { ?>
                        <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Owner Panel</a>
                    <?php } ?>
                    <span class="px-3 py-2 rounded-full border border-cyan-300 text-cyan-700 dark:border-cyan-600 dark:text-cyan-300 capitalize"><?php echo htmlspecialchars($userRole); ?></span>
                    <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
                <?php } else { ?>
                    <a href="user-login.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">User Login</a>
                    <a href="user-register.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Register</a>
                <?php } ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        <section class="mb-6">
            <h1 class="font-display text-3xl md:text-4xl"><?php echo htmlspecialchars($row['title']); ?></h1>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-300">
                <span class="inline-flex items-center gap-1"><?php echo renderStars((float)$row['avg_rating']); ?></span>
                <span class="font-semibold text-amber-600">
                    <?php echo $row['rating_count'] > 0 ? number_format((float)$row['avg_rating'], 1) . " (" . (int)$row['rating_count'] . " reviews)" : "Not rated yet"; ?>
                </span>
                <span>Sector <?php echo htmlspecialchars($row['sector']); ?></span>
                <span>·</span>
                <span><?php echo htmlspecialchars($row['type']); ?></span>
                <?php if ($specText !== "") { ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars($specText); ?></span>
                <?php } ?>
                <?php if (!empty($row['furnishing'])) { ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars((string)$row['furnishing']); ?></span>
                <?php } ?>
                <?php if (!empty($row['available_from'])) { ?>
                    <span>·</span>
                    <span>Available from <?php echo htmlspecialchars((string)$row['available_from']); ?></span>
                <?php } ?>
                <?php if (!empty($row['address_line'])) { ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars((string)$row['address_line']); ?></span>
                <?php } ?>
            </div>
        </section>

        <section class="grid lg:grid-cols-3 gap-7">
            <article class="lg:col-span-2 bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-lg shadow-slate-100 dark:bg-slate-900 dark:border-slate-800">
                <div class="relative">
                    <button type="button" id="gallery-prev" class="absolute left-3 top-1/2 -translate-y-1/2 z-10 rounded-full border border-white/70 bg-white/80 px-3 py-2 text-sm font-semibold shadow hover:bg-white <?php echo count($imageList) <= 1 ? 'hidden' : ''; ?>">
                        ‹
                    </button>
                    <button type="button" id="gallery-next" class="absolute right-3 top-1/2 -translate-y-1/2 z-10 rounded-full border border-white/70 bg-white/80 px-3 py-2 text-sm font-semibold shadow hover:bg-white <?php echo count($imageList) <= 1 ? 'hidden' : ''; ?>">
                        ›
                    </button>
                    <div id="gallery-slider" class="flex gap-3 overflow-x-auto mobile-slider snap-x scroll-smooth">
                        <?php foreach ($imageList as $imgItem) { ?>
                            <?php $imgName = $imgItem["name"]; ?>
                            <div class="relative w-full flex-shrink-0 snap-start">
                                <img
                                    src="uploads/<?php echo htmlspecialchars($imgName); ?>"
                                    alt="<?php echo htmlspecialchars($row['title']); ?>"
                                    class="w-full h-80 md:h-[460px] object-cover rounded-none cursor-zoom-in"
                                    loading="lazy"
                                    data-full="uploads/<?php echo htmlspecialchars($imgName); ?>"
                                >
                                <?php if (!empty($imgItem["label"])) { ?>
                                    <span class="absolute top-3 left-3 px-3 py-1 text-xs font-semibold rounded-full bg-white/90 text-slate-800 border border-slate-200"><?php echo htmlspecialchars($imgItem["label"]); ?></span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="p-6 md:p-8">
                    <div class="grid md:grid-cols-2 gap-6">
                        <section class="rounded-2xl border border-slate-200 p-5 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                            <h2 class="font-display text-xl">Description</h2>
                            <p class="mt-3 text-slate-600 dark:text-slate-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                        </section>

                        <section class="rounded-2xl border border-slate-200 p-5 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                            <h2 class="font-display text-xl">Amenities</h2>
                            <p class="mt-3 text-slate-600 dark:text-slate-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($row['amenities'])); ?></p>
                        </section>
                    </div>

                    <section class="mt-8 rounded-2xl border border-slate-200 p-5 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                        <h2 class="font-display text-xl">Feedback & Comments</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-300">Share experience for the owner and future renters with rating.</p>
                        <?php if ($feedbackSaved) { ?>
                            <p class="mt-3 text-sm text-emerald-600 font-semibold">Thanks. Your feedback was submitted.</p>
                        <?php } ?>

                        <form method="POST" class="mt-4 space-y-3">
                            <?php if (!$isUserLoggedIn && !$isAdminLoggedIn) { ?>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <input type="text" name="guest_name" placeholder="Your name" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                                    <input type="email" name="guest_email" placeholder="Your email (optional)" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                                </div>
                            <?php } ?>
                            <div>
                                <label class="block text-sm font-semibold mb-1">Your Rating</label>
                                <select name="feedback_rating" required class="w-full sm:w-56 border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                                    <option value="">Select rating</option>
                                    <option value="5">5 - Excellent</option>
                                    <option value="4">4 - Very Good</option>
                                    <option value="3">3 - Good</option>
                                    <option value="2">2 - Fair</option>
                                    <option value="1">1 - Poor</option>
                                </select>
                            </div>
                            <textarea name="comment" rows="4" required placeholder="Write your feedback..." class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700"></textarea>
                            <button type="submit" name="submit_feedback" value="1" class="px-5 py-2.5 rounded-full bg-slate-900 text-white font-semibold">Post Comment</button>
                        </form>

                        <div class="mt-6 <?php echo $feedbackCount > 3 ? 'relative' : ''; ?>">
                            <?php if ($feedbackCount > 3) { ?>
                                <div class="absolute right-0 -top-12 flex gap-2">
                                    <button type="button" id="feedback-prev" class="px-3 py-1.5 rounded-full border border-slate-300 text-sm">Prev</button>
                                    <button type="button" id="feedback-next" class="px-3 py-1.5 rounded-full border border-slate-300 text-sm">Next</button>
                                </div>
                            <?php } ?>
                            <div id="feedback-slider" class="<?php echo $feedbackCount > 3 ? 'flex gap-3 overflow-x-auto mobile-slider snap-x pb-2' : 'space-y-3'; ?>">
                            <?php if ($feedbackResult && $feedbackResult->num_rows > 0) { ?>
                                <?php while ($fb = $feedbackResult->fetch_assoc()) { ?>
                                    <?php
                                    $fbPhotoPath = "";
                                    if (!empty($fb["user_photo"]) && is_file(__DIR__ . "/uploads/profiles/" . $fb["user_photo"])) {
                                        $fbPhotoPath = "uploads/profiles/" . $fb["user_photo"];
                                    }
                                    ?>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 dark:bg-slate-900 dark:border-slate-700 <?php echo $feedbackCount > 3 ? 'min-w-[84%] sm:min-w-[62%] flex-shrink-0 snap-start' : ''; ?>">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <?php if ($fbPhotoPath !== "") { ?>
                                                    <img src="<?php echo htmlspecialchars($fbPhotoPath); ?>" alt="User photo" class="w-7 h-7 rounded-full object-cover border border-slate-200">
                                                <?php } else { ?>
                                                    <div class="w-7 h-7 rounded-full bg-slate-100 border border-slate-200"></div>
                                                <?php } ?>
                                                <p class="text-sm font-semibold"><?php echo htmlspecialchars((string)$fb["display_name"]); ?></p>
                                                <?php
                                                $role = strtolower((string)($fb["display_role"] ?? "guest"));
                                                $roleClass = "bg-slate-100 text-slate-700 border-slate-200";
                                                $roleLabel = "Guest";
                                                if ($role === "admin") {
                                                    $roleClass = "bg-rose-100 text-rose-700 border-rose-200";
                                                    $roleLabel = "Admin";
                                                } elseif ($role === "owner") {
                                                    $roleClass = "bg-amber-100 text-amber-700 border-amber-200";
                                                    $roleLabel = "Owner";
                                                } elseif ($role === "viewer") {
                                                    $roleClass = "bg-cyan-100 text-cyan-700 border-cyan-200";
                                                    $roleLabel = "Viewer";
                                                }
                                                ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border <?php echo $roleClass; ?>"><?php echo $roleLabel; ?></span>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars((string)$fb["created_at"]); ?></p>
                                        </div>
                                        <div class="mt-2 flex items-center gap-2">
                                            <?php echo renderStars((float)$fb["feedback_rating"]); ?>
                                            <span class="text-xs font-semibold text-amber-600"><?php echo (int)$fb["feedback_rating"]; ?>/5</span>
                                        </div>
                                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200"><?php echo nl2br(htmlspecialchars((string)$fb["comment"])); ?></p>
                                    </div>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="text-sm text-slate-500 dark:text-slate-300">No feedback yet.</p>
                            <?php } ?>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8 rounded-2xl border border-slate-200 p-5 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                        <h2 class="font-display text-xl">Location Map</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars($mapCaption); ?></p>
                        <?php if ($nearestLandmark !== "") { ?>
                            <p class="mt-1 text-sm text-cyan-700 dark:text-cyan-300">Nearest metro: <?php echo htmlspecialchars($nearestLandmark); ?></p>
                        <?php } ?>
                        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <iframe
                                src="<?php echo htmlspecialchars($mapEmbedUrl); ?>"
                                class="w-full h-80"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                title="Property Location Map"
                                allowfullscreen
                            ></iframe>
                        </div>
                    </section>
                </div>
            </article>

            <aside class="lg:col-span-1">
                <div class="sticky top-24 bg-white border border-slate-200 rounded-3xl p-6 shadow-lg shadow-slate-100 dark:bg-slate-900 dark:border-slate-800">
                    <?php
                    $ownerPhotoPath = "";
                    if (!empty($row["owner_photo"]) && is_file(__DIR__ . "/uploads/profiles/" . $row["owner_photo"])) {
                        $ownerPhotoPath = "uploads/profiles/" . $row["owner_photo"];
                    }
                    ?>
                    <a href="owner-listings.php?owner_id=<?php echo (int)($row["owner_user_id"] ?? 0); ?>" class="mb-4 flex items-center gap-3 hover:opacity-85 transition" title="View owner listings">
                        <?php if ($ownerPhotoPath !== "") { ?>
                            <img src="<?php echo htmlspecialchars($ownerPhotoPath); ?>" alt="Owner photo" class="w-11 h-11 rounded-full object-cover border border-slate-200">
                        <?php } else { ?>
                            <div class="w-11 h-11 rounded-full bg-slate-100 border border-slate-200"></div>
                        <?php } ?>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-300">Listed by</p>
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars((string)$row["owner_name"]); ?></p>
                        </div>
                    </a>
                    <p class="text-3xl font-display text-slate-900 dark:text-slate-100">Rs <?php echo (int)$row['rent']; ?><span class="text-base font-body text-slate-500 dark:text-slate-300"> / month</span></p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">Verified listing ready to contact</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                        <?php if (!empty($row['available_from'])) { ?>
                            Available from <?php echo htmlspecialchars((string)$row['available_from']); ?>
                        <?php } else { ?>
                            Available now
                        <?php } ?>
                    </p>
                    <?php if ($favoriteSaved) { ?>
                        <p class="mt-2 text-xs text-emerald-600 font-semibold">Saved favorites updated.</p>
                    <?php } ?>
                    <?php if ($reportSaved) { ?>
                        <p class="mt-2 text-xs text-emerald-600 font-semibold">Report submitted to admin moderation queue.</p>
                    <?php } ?>
                    <?php if ($inquirySaved) { ?>
                        <p class="mt-2 text-xs text-emerald-600 font-semibold">Inquiry sent to owner.</p>
                    <?php } ?>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-300"><?php echo (int)$favoriteCount; ?> users saved this listing</p>
                    <div class="mt-6 flex flex-col gap-3">
                        <?php if ($currentUserId > 0) { ?>
                            <form method="POST">
                                <button type="submit" name="toggle_favorite" value="1" class="w-full inline-flex items-center justify-center border border-slate-300 px-6 py-3 rounded-full font-semibold hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                                    <?php echo $isFavorite ? "Remove From Favorites" : "Save To Favorites"; ?>
                                </button>
                            </form>
                        <?php } ?>
                        <a class="inline-flex items-center justify-center bg-slate-900 text-white px-6 py-3 rounded-full font-semibold hover:bg-cyan-700 transition" href="tel:<?php echo htmlspecialchars($row['phone']); ?>" onclick="fetch('track-event.php?property_id=<?php echo (int)$id; ?>&type=call', {keepalive:true});">
                            Call Owner
                        </a>
                        <a class="inline-flex items-center justify-center border border-slate-300 px-6 py-3 rounded-full font-semibold hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition" href="index.php">
                            Explore More Listings
                        </a>
                    </div>
                    <section class="mt-5 rounded-2xl border border-slate-200 p-4 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                        <h3 class="font-display text-lg">Send Inquiry</h3>
                        <form method="POST" class="mt-3 space-y-2">
                            <?php if (!$isUserLoggedIn) { ?>
                                <input type="text" name="inquiry_name" placeholder="Your name" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700">
                                <input type="email" name="inquiry_email" placeholder="Email" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700">
                            <?php } ?>
                            <input type="text" name="inquiry_phone" placeholder="Phone (optional)" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700">
                            <textarea name="inquiry_message" rows="3" required placeholder="I want details about availability and visit..." class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700"></textarea>
                            <button type="submit" name="submit_inquiry" value="1" class="w-full px-4 py-2.5 rounded-full bg-slate-900 text-white font-semibold">Send Inquiry</button>
                        </form>
                    </section>
                    <section class="mt-4 rounded-2xl border border-slate-200 p-4 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                        <h3 class="font-display text-lg">Report Listing</h3>
                        <form method="POST" class="mt-3 space-y-2">
                            <?php if (!$isUserLoggedIn) { ?>
                                <input type="text" name="reporter_name" placeholder="Your name" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700">
                                <input type="email" name="reporter_email" placeholder="Email (optional)" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700">
                            <?php } ?>
                            <select name="report_reason" required class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700">
                                <option value="">Select reason</option>
                                <option value="Spam/Fake">Spam/Fake</option>
                                <option value="Wrong Price">Wrong Price</option>
                                <option value="Misleading Info">Misleading Info</option>
                                <option value="Abusive Content">Abusive Content</option>
                            </select>
                            <textarea name="report_details" rows="2" placeholder="Extra details (optional)" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-900 dark:border-slate-700"></textarea>
                            <button type="submit" name="submit_report" value="1" class="w-full px-4 py-2.5 rounded-full border border-rose-300 text-rose-700 font-semibold hover:bg-rose-50">Report To Admin</button>
                        </form>
                    </section>
                    <p class="mt-5 text-xs text-slate-500 dark:text-slate-300">Nestoida helps you compare prices and details before you decide.</p>
                </div>
            </aside>
        </section>
    </main>
    <div id="image-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
        <button id="image-modal-close" class="absolute top-5 right-5 text-white text-4xl w-12 h-12 flex items-center justify-center rounded-full bg-black/40 hover:bg-black/60">×</button>
        <button id="image-modal-prev" class="absolute left-6 top-1/2 -translate-y-1/2 text-white text-3xl">‹</button>
        <button id="image-modal-next" class="absolute right-6 top-1/2 -translate-y-1/2 text-white text-3xl">›</button>
        <div id="image-modal-track" class="flex gap-4 overflow-x-auto snap-x max-w-[92vw]">
            <?php foreach ($imageList as $imgItem) { ?>
                <?php $imgName = $imgItem["name"]; ?>
                <div class="relative w-[92vw] max-w-[92vw] flex-shrink-0 snap-start">
                    <img src="uploads/<?php echo htmlspecialchars($imgName); ?>" alt="Property image" class="max-h-[90vh] w-full object-contain rounded-2xl shadow-2xl">
                    <?php if (!empty($imgItem["label"])) { ?>
                        <span class="absolute top-3 left-3 px-3 py-1 text-xs font-semibold rounded-full bg-white/90 text-slate-800 border border-slate-200"><?php echo htmlspecialchars($imgItem["label"]); ?></span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
    <script>
        (function () {
            const btn = document.getElementById("theme-toggle");
            const label = document.getElementById("theme-toggle-label");
            const feedbackSlider = document.getElementById("feedback-slider");
            const feedbackPrev = document.getElementById("feedback-prev");
            const feedbackNext = document.getElementById("feedback-next");
            const gallerySlider = document.getElementById("gallery-slider");
            const galleryPrev = document.getElementById("gallery-prev");
            const galleryNext = document.getElementById("gallery-next");
            const imageModal = document.getElementById("image-modal");
            const imageModalTrack = document.getElementById("image-modal-track");
            const imageModalClose = document.getElementById("image-modal-close");
            const imageModalPrev = document.getElementById("image-modal-prev");
            const imageModalNext = document.getElementById("image-modal-next");
            const galleryImages = gallerySlider ? Array.from(gallerySlider.querySelectorAll("img[data-full]")) : [];
            let currentImageIndex = -1;
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

            function scrollFeedback(direction) {
                if (!feedbackSlider) return;
                const amount = Math.max(280, Math.floor(feedbackSlider.clientWidth * 0.8));
                feedbackSlider.scrollBy({ left: direction * amount, behavior: "smooth" });
            }
            if (feedbackPrev) {
                feedbackPrev.addEventListener("click", function () { scrollFeedback(-1); });
            }
            if (feedbackNext) {
                feedbackNext.addEventListener("click", function () { scrollFeedback(1); });
            }

            function scrollGallery(direction) {
                if (!gallerySlider) return;
                const amount = Math.max(260, Math.floor(gallerySlider.clientWidth * 0.65));
                const maxScroll = gallerySlider.scrollWidth - gallerySlider.clientWidth;
                if (direction > 0 && gallerySlider.scrollLeft >= maxScroll - 5) {
                    gallerySlider.scrollTo({ left: 0, behavior: "smooth" });
                    return;
                }
                if (direction < 0 && gallerySlider.scrollLeft <= 5) {
                    gallerySlider.scrollTo({ left: maxScroll, behavior: "smooth" });
                    return;
                }
                gallerySlider.scrollBy({ left: direction * amount, behavior: "smooth" });
            }
            if (galleryPrev) {
                galleryPrev.addEventListener("click", function () { scrollGallery(-1); });
            }
            if (galleryNext) {
                galleryNext.addEventListener("click", function () { scrollGallery(1); });
            }

            if (gallerySlider && imageModal && imageModalTrack) {
                gallerySlider.addEventListener("click", function (event) {
                    const target = event.target;
                    if (!(target instanceof HTMLImageElement)) return;
                    currentImageIndex = galleryImages.indexOf(target);
                    imageModal.classList.remove("hidden");
                    imageModal.classList.add("flex");
                    document.body.classList.add("overflow-hidden");
                    const slideWidth = imageModalTrack.clientWidth;
                    imageModalTrack.scrollTo({ left: slideWidth * currentImageIndex, behavior: "smooth" });
                });
            }
            function closeImageModal() {
                if (!imageModal) return;
                imageModal.classList.add("hidden");
                imageModal.classList.remove("flex");
                document.body.classList.remove("overflow-hidden");
                currentImageIndex = -1;
            }
            function showModalImage(offset) {
                if (!imageModalTrack || galleryImages.length === 0) return;
                if (currentImageIndex < 0) currentImageIndex = 0;
                const nextIndex = (currentImageIndex + offset + galleryImages.length) % galleryImages.length;
                const slideWidth = imageModalTrack.clientWidth;
                imageModalTrack.scrollTo({ left: slideWidth * nextIndex, behavior: "smooth" });
                currentImageIndex = nextIndex;
            }
            if (imageModalClose) {
                imageModalClose.addEventListener("click", closeImageModal);
            }
            if (imageModalPrev) {
                imageModalPrev.addEventListener("click", function () { showModalImage(-1); });
            }
            if (imageModalNext) {
                imageModalNext.addEventListener("click", function () { showModalImage(1); });
            }
            if (imageModal) {
                imageModal.addEventListener("click", function (event) {
                    if (event.target === imageModal) closeImageModal();
                });
            }
            if (imageModalTrack) {
                imageModalTrack.addEventListener("scroll", function () {
                    const slideWidth = imageModalTrack.clientWidth || 1;
                    const idx = Math.round(imageModalTrack.scrollLeft / slideWidth);
                    currentImageIndex = idx;
                });
            }
            document.addEventListener("keydown", function (event) {
                if (!imageModal || imageModal.classList.contains("hidden")) return;
                if (event.key === "Escape") {
                    closeImageModal();
                } else if (event.key === "ArrowLeft") {
                    showModalImage(-1);
                } else if (event.key === "ArrowRight") {
                    showModalImage(1);
                }
            });
        })();
    </script>
    <script src="assets/js/back-button.js"></script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
