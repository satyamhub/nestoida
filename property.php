<?php
session_start();
include "db.php";
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdminLoggedIn = isset($_SESSION['admin']);
$isUserLoggedIn = isset($_SESSION['user_id'], $_SESSION['user_role']);
$userRole = $isUserLoggedIn ? $_SESSION['user_role'] : null;

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
$feedbackSaved = isset($_GET["feedback"]) && $_GET["feedback"] === "1";
$specText = listingSpecText($row);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($row['title']); ?> - Nestoida</title>
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
            </div>
        </section>

        <section class="grid lg:grid-cols-3 gap-7">
            <article class="lg:col-span-2 bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-lg shadow-slate-100 dark:bg-slate-900 dark:border-slate-800">
                <img
                    src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
                    alt="<?php echo htmlspecialchars($row['title']); ?>"
                    class="w-full h-80 md:h-[460px] object-cover"
                >

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
                    <div class="mt-6 flex flex-col gap-3">
                        <a class="inline-flex items-center justify-center bg-slate-900 text-white px-6 py-3 rounded-full font-semibold hover:bg-cyan-700 transition" href="tel:<?php echo htmlspecialchars($row['phone']); ?>">
                            Call Owner
                        </a>
                        <a class="inline-flex items-center justify-center border border-slate-300 px-6 py-3 rounded-full font-semibold hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition" href="index.php">
                            Explore More Listings
                        </a>
                    </div>
                    <p class="mt-5 text-xs text-slate-500 dark:text-slate-300">Nestoida helps you compare prices and details before you decide.</p>
                </div>
            </aside>
        </section>
    </main>
    <script>
        (function () {
            const btn = document.getElementById("theme-toggle");
            const label = document.getElementById("theme-toggle-label");
            const feedbackSlider = document.getElementById("feedback-slider");
            const feedbackPrev = document.getElementById("feedback-prev");
            const feedbackNext = document.getElementById("feedback-next");
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
        })();
    </script>
    <script src="assets/js/back-button.js"></script>
</body>
</html>
