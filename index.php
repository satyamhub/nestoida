<?php
session_start();
include "db.php";

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['sector']) ? trim($_GET['sector']) : "");
$isAdminLoggedIn = isset($_SESSION['admin']);
$isUserLoggedIn = isset($_SESSION['user_id'], $_SESSION['user_role']);
$userRole = $isUserLoggedIn ? $_SESSION['user_role'] : null;

if ($searchQuery !== "") {
    $stmt = $conn->prepare("
        SELECT
            p.*,
            p.TYPE AS type,
            COALESCE(u.full_name, 'Nestoida Team') AS owner_name,
            u.profile_photo AS owner_photo,
            COALESCE(u.owner_verified, 0) AS owner_verified,
            COALESCE(r.avg_rating, 0) AS avg_rating,
            COALESCE(r.rating_count, 0) AS rating_count
        FROM properties p
        LEFT JOIN users u ON u.id = p.owner_user_id
        LEFT JOIN (
            SELECT property_id, ROUND(AVG(feedback_rating), 1) AS avg_rating, COUNT(*) AS rating_count
            FROM listing_feedback
            WHERE feedback_rating BETWEEN 1 AND 5
            GROUP BY property_id
        ) r ON r.property_id = p.id
        WHERE p.status='approved' AND (
            p.sector LIKE ?
            OR p.title LIKE ?
            OR p.type LIKE ?
            OR p.seater_option LIKE ?
            OR p.bhk_option LIKE ?
            OR p.address_line LIKE ?
            OR p.description LIKE ?
            OR p.amenities LIKE ?
            OR p.furnishing LIKE ?
            OR p.phone LIKE ?
            OR CAST(p.rent AS CHAR) LIKE ?
        )
        ORDER BY p.id DESC
    ");
    $queryLike = "%" . $searchQuery . "%";
    $stmt->bind_param("sssssssssss", $queryLike, $queryLike, $queryLike, $queryLike, $queryLike, $queryLike, $queryLike, $queryLike, $queryLike, $queryLike, $queryLike);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT
            p.*,
            p.TYPE AS type,
            COALESCE(u.full_name, 'Nestoida Team') AS owner_name,
            u.profile_photo AS owner_photo,
            COALESCE(u.owner_verified, 0) AS owner_verified,
            COALESCE(r.avg_rating, 0) AS avg_rating,
            COALESCE(r.rating_count, 0) AS rating_count
        FROM properties p
        LEFT JOIN users u ON u.id = p.owner_user_id
        LEFT JOIN (
            SELECT property_id, ROUND(AVG(feedback_rating), 1) AS avg_rating, COUNT(*) AS rating_count
            FROM listing_feedback
            WHERE feedback_rating BETWEEN 1 AND 5
            GROUP BY property_id
        ) r ON r.property_id = p.id
        WHERE p.status='approved'
        ORDER BY p.id DESC
    ");
}

$approvedCountResult = $conn->query("SELECT COUNT(*) AS total FROM properties WHERE status='approved'");
$approvedCount = $approvedCountResult ? (int)$approvedCountResult->fetch_assoc()['total'] : 0;

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nestoida</title>
    <meta name="description" content="Find verified PGs, hostels, flats and co-living listings in Noida with pricing, availability and owner details.">
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
    <noscript>
        <style>
            #listing-loader { display: none !important; }
        </style>
    </noscript>
    <style>
        .theme-smooth,
        .theme-smooth * {
            transition:
                background-color .6s ease,
                color .6s ease,
                border-color .6s ease,
                box-shadow .6s ease,
                fill .6s ease,
                stroke .6s ease,
                opacity .6s ease;
        }
        .theme-fade {
            position: fixed;
            inset: 0;
            z-index: 30;
            pointer-events: none;
            opacity: 0;
            transition: opacity .6s ease;
            background: linear-gradient(180deg, #87d7ff 0%, #d7f2ff 55%, #f7fdff 100%);
        }
        .dark .theme-fade {
            background: linear-gradient(180deg, #0a0f1f 0%, #0b1020 100%);
        }
        .theme-fade-active .theme-fade {
            opacity: .35;
        }
    </style>
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui font-body bg-gradient-to-b from-slate-50 to-white text-slate-900 min-h-screen dark:from-slate-950 dark:to-slate-900 dark:text-slate-100 theme-smooth">
<div id="theme-fade" class="theme-fade" aria-hidden="true"></div>

<header class="sticky top-0 z-40 backdrop-blur bg-white/85 border-b border-slate-200 dark:bg-slate-950/80 dark:border-slate-800">
    <div class="max-w-6xl mx-auto px-6 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="index.php" aria-label="Go to homepage" class="inline-flex">
                    <img src="assets/img/nestoida-logo.svg" alt="Nestoida Logo" class="w-9 h-9">
                
                <h1 class="font-display text-2xl tracking-tight">Nestoida</h1>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-300">Professional PG & co-living discovery</p>
            </a>
        </div>
        <nav class="flex flex-wrap gap-2 text-sm">
            <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                <span id="theme-toggle-label" class="sr-only">Dark</span>
                <span class="inline-flex items-center gap-2">
                    <svg id="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-amber-400">
                        <circle cx="12" cy="12" r="4" fill="currentColor"/>
                        <path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" stroke="currentColor" stroke-linecap="round" stroke-width="2" fill="none"/>
                    </svg>
                    <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-sky-200 hidden">
                        <path d="M20.5 14.2A7.5 7.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7Z" fill="currentColor"/>
                    </svg>
                </span>
            </button>
            <a href="index.php" class="px-3 py-2 rounded-full bg-slate-900 text-white dark:bg-cyan-700">Home</a>
            <?php if ($isAdminLoggedIn) { ?>
                <a href="dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Dashboard</a>
                <a href="manage-users.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Manage Users</a>
                <a href="manage-reports.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Reports</a>
                <a href="admin-profile.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Profile</a>
                <a href="add-property.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Add Property</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
            <?php } elseif ($isUserLoggedIn) { ?>
                <?php if ($userRole === 'owner') { ?>
                    <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Owner Panel</a>
                    <a href="owner-profile.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Profile</a>
                    <a href="add-property.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Add Property</a>
                <?php } else { ?>
                    <a href="become-owner.php" class="px-3 py-2 rounded-full border border-emerald-300 text-emerald-700 hover:border-emerald-500 dark:border-emerald-600 dark:text-emerald-300 transition">Become Owner</a>
                <?php } ?>
                <a href="favorites.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Favorites</a>
                <span class="px-3 py-2 rounded-full border border-cyan-300 text-cyan-700 dark:border-cyan-600 dark:text-cyan-300 capitalize"><?php echo htmlspecialchars($userRole); ?></span>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
            <?php } else { ?>
                <a href="user-login.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">User Login</a>
                <a href="user-register.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Register</a>
                <a href="login.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Admin Login</a>
            <?php } ?>
        </nav>
    </div>
    <div id="sticky-search-wrap" class="max-w-6xl mx-auto px-6 pb-3 opacity-0 -translate-y-2 pointer-events-none transition-all duration-300">
        <div class="bg-white border border-slate-200 rounded-full px-4 py-2 shadow-sm dark:bg-slate-900 dark:border-slate-700">
            <input
                type="text"
                id="nav-search-input"
                placeholder="Search listings..."
                autocomplete="off"
                class="w-full border-0 p-0 bg-transparent focus:ring-0 text-sm"
            >
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
    <section class="relative overflow-hidden rounded-3xl p-7 md:p-10 border border-slate-200 bg-[radial-gradient(circle_at_15%_20%,rgba(255,56,92,0.24),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(255,180,92,0.24),transparent_40%),linear-gradient(125deg,#ffffff_0%,#fff5f7_55%,#fffaf2_100%)] dark:bg-[radial-gradient(circle_at_15%_20%,rgba(255,56,92,0.20),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(255,180,92,0.16),transparent_40%),linear-gradient(125deg,#1d1d1d_0%,#21171a_55%,#241f19_100%)] dark:border-slate-800">
        <div class="grid lg:grid-cols-2 gap-8 items-end">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-rose-600 dark:text-rose-300">Noida Homes, Fast</p>
                <h2 class="font-display text-3xl md:text-5xl mt-3 leading-tight">Find spaces that feel like home.</h2>
                <p class="mt-4 text-sm md:text-base text-slate-600 dark:text-slate-300 max-w-2xl">Discover verified PGs, flats, and hostels with clear details, monthly pricing, and direct contact in one flow.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="rounded-2xl border border-white/60 bg-white/70 dark:bg-slate-900/70 dark:border-slate-700 p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-300">Approved Listings</p>
                    <p class="font-display text-3xl mt-1"><?php echo $approvedCount; ?></p>
                </div>
                <div class="rounded-2xl border border-white/60 bg-white/70 dark:bg-slate-900/70 dark:border-slate-700 p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-300">Top Search Fields</p>
                    <p class="font-semibold mt-1">Sector, Price, Address</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-7 bg-white border border-slate-200 rounded-full p-2 sm:p-3 shadow-sm dark:bg-slate-900 dark:border-slate-800">
        <form method="GET" id="home-search-form" class="flex flex-col sm:flex-row gap-2 sm:gap-3 items-stretch sm:items-center">
            <div class="flex-1 px-3 sm:px-4 py-2">
                <label class="block text-[11px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Search</label>
                <input
                    type="text"
                    id="home-search-input"
                    name="q"
                    placeholder="Try Sector 62, 8000, wifi, co-living..."
                    value="<?php echo htmlspecialchars($searchQuery); ?>"
                    autocomplete="off"
                    class="w-full border-0 p-0 mt-1 bg-transparent focus:ring-0 text-sm md:text-base"
                >
            </div>
            <button class="bg-slate-900 text-white px-6 py-3 rounded-full font-semibold hover:bg-slate-800 transition">Search</button>
        </form>
    </section>

    <section class="mt-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 dark:bg-slate-900 dark:border-slate-800">
            <label class="block text-[11px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Min Price</label>
            <input id="filter-min-rent" type="number" placeholder="0" class="w-full border-0 p-0 mt-1 bg-transparent focus:ring-0 text-sm">
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 dark:bg-slate-900 dark:border-slate-800">
            <label class="block text-[11px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Max Price</label>
            <input id="filter-max-rent" type="number" placeholder="50000" class="w-full border-0 p-0 mt-1 bg-transparent focus:ring-0 text-sm">
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 dark:bg-slate-900 dark:border-slate-800">
            <label class="block text-[11px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Type</label>
            <select id="filter-type" class="w-full border-0 p-0 mt-1 bg-transparent focus:ring-0 text-sm">
                <option value="">All</option>
                <option value="pg">PG</option>
                <option value="hostel">Hostel</option>
                <option value="flat">Flat</option>
                <option value="co-living">Co-living</option>
            </select>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 dark:bg-slate-900 dark:border-slate-800">
            <label class="block text-[11px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Furnishing</label>
            <select id="filter-furnishing" class="w-full border-0 p-0 mt-1 bg-transparent focus:ring-0 text-sm">
                <option value="">Any</option>
                <option value="fully furnished">Fully Furnished</option>
                <option value="semi furnished">Semi Furnished</option>
                <option value="unfurnished">Unfurnished</option>
            </select>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl px-4 py-3 dark:bg-slate-900 dark:border-slate-800">
            <label class="block text-[11px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Min Rating</label>
            <select id="filter-rating" class="w-full border-0 p-0 mt-1 bg-transparent focus:ring-0 text-sm">
                <option value="0">Any</option>
                <option value="3">3+</option>
                <option value="4">4+</option>
                <option value="4.5">4.5+</option>
            </select>
        </div>
    </section>

    <section class="mt-10">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-display text-2xl">Stays In Noida</h3>
            <p class="text-sm text-slate-500 dark:text-slate-300">Curated verified listings</p>
        </div>
        <div id="listing-loader" class="hidden grid grid-flow-col auto-cols-[82%] sm:auto-cols-[58%] md:grid-flow-row md:auto-cols-auto md:grid-cols-3 gap-4 md:gap-7 overflow-x-auto md:overflow-visible mobile-slider pb-2">
            <?php for ($i = 0; $i < 6; $i++) { ?>
                <div class="rounded-2xl border border-slate-200 overflow-hidden animate-pulse bg-white">
                    <div class="h-56 bg-slate-200"></div>
                    <div class="p-5 space-y-3">
                        <div class="h-5 bg-slate-200 rounded w-3/4"></div>
                        <div class="h-4 bg-slate-200 rounded w-1/3"></div>
                        <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                        <div class="h-10 bg-slate-200 rounded w-28"></div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div id="listing-content" class="grid grid-flow-col auto-cols-[82%] sm:auto-cols-[58%] md:grid-flow-row md:auto-cols-auto md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-7 overflow-x-auto md:overflow-visible mobile-slider pb-2">
            <?php if ($result && $result->num_rows > 0) { ?>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <article
                        class="group rounded-3xl bg-white border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition duration-300 dark:bg-slate-900 dark:border-slate-800 cursor-pointer snap-start"
                        data-search="<?php echo htmlspecialchars(strtolower($row['title'] . ' ' . $row['sector'] . ' ' . ($row['address_line'] ?? '') . ' ' . ($row['type'] ?? '') . ' ' . ($row['seater_option'] ?? '') . ' ' . ($row['bhk_option'] ?? '') . ' ' . ($row['furnishing'] ?? '') . ' ' . ($row['owner_name'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . ($row['amenities'] ?? '') . ' ' . ($row['phone'] ?? '') . ' ' . (string)($row['rent'] ?? ''))); ?>"
                        data-rent="<?php echo (int)($row['rent'] ?? 0); ?>"
                        data-type="<?php echo htmlspecialchars(strtolower((string)($row['type'] ?? ''))); ?>"
                        data-rating="<?php echo htmlspecialchars((string)((float)($row['avg_rating'] ?? 0))); ?>"
                        data-furnishing="<?php echo htmlspecialchars(strtolower((string)($row['furnishing'] ?? ''))); ?>"
                        data-url="property.php?id=<?php echo (int)$row['id']; ?>"
                        tabindex="0"
                        role="link"
                        onclick="window.location.href=this.dataset.url"
                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href=this.dataset.url;}"
                    >
                        <div class="relative">
                            <img
                                src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
                                alt="<?php echo htmlspecialchars($row['title']); ?>"
                                class="w-full h-60 object-cover"
                                loading="lazy"
                                decoding="async"
                            >
                            <span class="absolute top-3 left-3 px-3 py-1 text-xs font-semibold rounded-full bg-white/90 text-slate-800">Guest Favorite</span>
                        </div>
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-display text-xl tracking-tight"><?php echo htmlspecialchars($row['title']); ?></h3>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                                        Sector <?php echo htmlspecialchars($row['sector']); ?> · <?php echo htmlspecialchars($row['type']); ?>
                                        <?php $specText = listingSpecText($row); ?>
                                        <?php if ($specText !== "") { ?>
                                            · <?php echo htmlspecialchars($specText); ?>
                                        <?php } ?>
                                        <?php if (!empty($row['furnishing'])) { ?>
                                            · <?php echo htmlspecialchars((string)$row['furnishing']); ?>
                                        <?php } ?>
                                    </p>
                                    <?php if ($specText !== "") { ?>
                                        <p class="mt-2">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border border-rose-200 text-rose-700 bg-rose-50">
                                                <?php echo htmlspecialchars($specText); ?>
                                            </span>
                                        </p>
                                    <?php } ?>
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-300">
                                        <?php if (!empty($row['available_from'])) { ?>
                                            Available from <?php echo htmlspecialchars((string)$row['available_from']); ?>
                                        <?php } else { ?>
                                            Available now
                                        <?php } ?>
                                    </p>
                                </div>
                                <p class="text-sm font-semibold whitespace-nowrap">Rs <?php echo (int)$row['rent']; ?>/mo</p>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <?php echo renderStars((float)$row['avg_rating']); ?>
                                <p class="text-sm text-amber-600 font-semibold">
                                    <?php echo $row['rating_count'] > 0 ? number_format((float)$row['avg_rating'], 1) . "/5 (" . (int)$row['rating_count'] . ")" : "Not rated yet"; ?>
                                </p>
                            </div>
                            <?php
                            $ownerPhotoPath = nestoida_profile_photo_url($row["owner_photo"] ?? "");
                            ?>
                            <a href="owner-listings.php?owner_id=<?php echo (int)($row["owner_user_id"] ?? 0); ?>" class="mt-3 inline-flex items-center gap-2 hover:opacity-85 transition" title="View owner listings">
                                <?php if ($ownerPhotoPath !== "") { ?>
                                    <img src="<?php echo htmlspecialchars($ownerPhotoPath); ?>" alt="Owner photo" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                                <?php } else { ?>
                                    <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200"></div>
                                <?php } ?>
                                <p class="text-xs text-slate-600 dark:text-slate-300 inline-flex items-center gap-1">
                                    Hosted by <?php echo htmlspecialchars((string)($row["owner_name"] ?? "Nestoida Team")); ?>
                                    <?php if (!empty($row["owner_verified"])) { ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-cyan-200 text-cyan-700 bg-cyan-50 dark:border-cyan-600 dark:text-cyan-200 dark:bg-cyan-900/40">
                                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 0a10 10 0 100 20 10 10 0 000-20zm4.2 7.3-4.8 5a1 1 0 01-1.4 0l-2.2-2.3a1 1 0 011.4-1.4l1.5 1.5 4.1-4.2a1 1 0 011.4 1.4z"/></svg>
                                            Verified
                                        </span>
                                    <?php } ?>
                                </p>
                            </a>
                            <a
                                href="property.php?id=<?php echo (int)$row['id']; ?>"
                                onclick="event.stopPropagation();"
                                class="inline-flex items-center gap-2 mt-5 px-4 py-2 rounded-full bg-slate-900 text-white text-sm font-semibold group-hover:bg-cyan-700 transition"
                            >
                                View stay
                                <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </article>
                <?php } ?>
                <div id="client-no-results" class="hidden md:col-span-2 xl:col-span-3 bg-white border border-slate-200 rounded-2xl p-10 text-center dark:bg-slate-900 dark:border-slate-800">
                    <p class="font-display text-xl">No matching listings</p>
                    <p class="mt-2 text-slate-500 dark:text-slate-300 text-sm">Try another keyword.</p>
                </div>
            <?php } else { ?>
                <div class="md:col-span-2 xl:col-span-3 bg-white border border-slate-200 rounded-2xl p-10 text-center dark:bg-slate-900 dark:border-slate-800">
                    <p class="font-display text-xl">No approved listings found</p>
                    <p class="mt-2 text-slate-500 dark:text-slate-300 text-sm">Try a different sector or clear the current search.</p>
                </div>
            <?php } ?>
        </div>
    </section>
</main>

<script>
window.addEventListener("load", function () {
    const listingLoader = document.getElementById("listing-loader");
    const listingContent = document.getElementById("listing-content");

    if (listingLoader && listingContent) {
        listingContent.classList.add("hidden");
        listingLoader.classList.remove("hidden");

        setTimeout(function () {
            listingLoader.classList.add("hidden");
            listingContent.classList.remove("hidden");
        }, 450);
    }

});

(function () {
    const homeSearchForm = document.getElementById("home-search-form");
    const homeSearchInput = document.getElementById("home-search-input");
    const navSearchInput = document.getElementById("nav-search-input");
    const stickySearchWrap = document.getElementById("sticky-search-wrap");
    const filterMinRent = document.getElementById("filter-min-rent");
    const filterMaxRent = document.getElementById("filter-max-rent");
    const filterType = document.getElementById("filter-type");
    const filterFurnishing = document.getElementById("filter-furnishing");
    const filterRating = document.getElementById("filter-rating");
    const homeCards = Array.from(document.querySelectorAll("#listing-content article[data-search]"));
    const homeNoResults = document.getElementById("client-no-results");
    let homeSearchTimer = null;
    let syncingInputs = false;

    function applyHomeFilter() {
        if (!homeSearchInput || homeCards.length === 0) return;
        const value = homeSearchInput.value.trim().toLowerCase();
        const minRent = filterMinRent && filterMinRent.value !== "" ? parseInt(filterMinRent.value, 10) : null;
        const maxRent = filterMaxRent && filterMaxRent.value !== "" ? parseInt(filterMaxRent.value, 10) : null;
        const type = filterType ? filterType.value.trim().toLowerCase() : "";
        const furnishing = filterFurnishing ? filterFurnishing.value.trim().toLowerCase() : "";
        const minRating = filterRating ? parseFloat(filterRating.value || "0") : 0;
        let visible = 0;

        homeCards.forEach(function (card) {
            const searchable = card.getAttribute("data-search") || "";
            const rent = parseInt(card.getAttribute("data-rent") || "0", 10);
            const cardType = (card.getAttribute("data-type") || "").toLowerCase();
            const rating = parseFloat(card.getAttribute("data-rating") || "0");
            const cardFurnishing = (card.getAttribute("data-furnishing") || "").toLowerCase();
            const matchedText = value === "" || searchable.includes(value);
            const matchedMin = minRent === null || rent >= minRent;
            const matchedMax = maxRent === null || rent <= maxRent;
            const matchedType = type === "" || cardType.includes(type);
            const matchedFurnishing = furnishing === "" || cardFurnishing.includes(furnishing);
            const matchedRating = rating >= minRating;
            const matched = matchedText && matchedMin && matchedMax && matchedType && matchedFurnishing && matchedRating;
            card.classList.toggle("hidden", !matched);
            if (matched) visible++;
        });

        if (homeNoResults) {
            homeNoResults.classList.toggle("hidden", visible !== 0);
        }
    }

    function syncSearchInputs(source, value) {
        if (syncingInputs) return;
        syncingInputs = true;
        if (source !== "home" && homeSearchInput) {
            homeSearchInput.value = value;
        }
        if (source !== "nav" && navSearchInput) {
            navSearchInput.value = value;
        }
        syncingInputs = false;
        applyHomeFilter();
    }

    function toggleStickySearch() {
        if (!stickySearchWrap) return;
        const show = window.scrollY > 220;
        stickySearchWrap.classList.toggle("opacity-0", !show);
        stickySearchWrap.classList.toggle("-translate-y-2", !show);
        stickySearchWrap.classList.toggle("pointer-events-none", !show);
    }

    if (homeSearchForm && homeSearchInput) {
        homeSearchInput.addEventListener("input", function () {
            clearTimeout(homeSearchTimer);
            homeSearchTimer = setTimeout(function () {
                syncSearchInputs("home", homeSearchInput.value);
            }, 350);
        });

        applyHomeFilter();
    }

    if (navSearchInput) {
        navSearchInput.addEventListener("input", function () {
            clearTimeout(homeSearchTimer);
            homeSearchTimer = setTimeout(function () {
                syncSearchInputs("nav", navSearchInput.value);
            }, 350);
        });
    }
    [filterMinRent, filterMaxRent, filterType, filterFurnishing, filterRating].forEach(function (el) {
        if (!el) return;
        el.addEventListener("input", applyHomeFilter);
        el.addEventListener("change", applyHomeFilter);
    });

    syncSearchInputs("home", homeSearchInput ? homeSearchInput.value : "");
    window.addEventListener("scroll", toggleStickySearch);
    toggleStickySearch();
})();

(function () {
    const btn = document.getElementById("theme-toggle");
    const label = document.getElementById("theme-toggle-label");
    const iconSun = document.getElementById("theme-icon-sun");
    const iconMoon = document.getElementById("theme-icon-moon");
    function syncThemeLabel() {
        if (!label) return;
        label.textContent = document.documentElement.classList.contains("dark") ? "Light" : "Dark";
    }
    function syncThemeIcons() {
        const isDark = document.documentElement.classList.contains("dark");
        if (iconSun && iconMoon) {
            iconSun.classList.toggle("hidden", isDark);
            iconMoon.classList.toggle("hidden", !isDark);
        }
    }
    syncThemeLabel();
    syncThemeIcons();
    if (btn) {
        btn.addEventListener("click", function () {
            const root = document.documentElement;
            const isDark = root.classList.toggle("dark");
            document.body.classList.add("theme-fade-active");
            try {
                localStorage.setItem("nestoida_theme", isDark ? "dark" : "light");
            } catch (e) {}
            syncThemeLabel();
            syncThemeIcons();
            setTimeout(function () {
                document.body.classList.remove("theme-fade-active");
            }, 650);
        });
    }
})();

</script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
