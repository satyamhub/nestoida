<?php
session_start();
include "db.php";

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$allowedStatusFilters = ["all", "approved", "pending", "rejected"];
$filterStatus = isset($_GET["status"]) ? strtolower(trim($_GET["status"])) : "all";
if (!in_array($filterStatus, $allowedStatusFilters, true)) {
    $filterStatus = "all";
}

$search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
if ($page < 1) {
    $page = 1;
}

$perPage = 10;
$offset = ($page - 1) * $perPage;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["id"])) {
    $action = strtolower(trim($_POST["action"]));
    $id = (int)$_POST["id"];
    $statusMap = [
        "approve" => "approved",
        "reject" => "rejected",
        "pending" => "pending"
    ];

    if ($id > 0 && isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];
        $stmt = $conn->prepare("UPDATE properties SET status=? WHERE id=?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();
    }

    $redirectQuery = http_build_query([
        "search" => $search,
        "status" => $filterStatus,
        "page" => $page
    ]);
    header("Location: dashboard.php" . ($redirectQuery ? "?" . $redirectQuery : ""));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["bulk_action"], $_POST["ids"]) && is_array($_POST["ids"])) {
    $bulkAction = strtolower(trim($_POST["bulk_action"]));
    $statusMap = [
        "approve" => "approved",
        "reject" => "rejected",
        "pending" => "pending"
    ];

    if (isset($statusMap[$bulkAction])) {
        $ids = array_values(array_filter(array_map("intval", $_POST["ids"]), function ($id) {
            return $id > 0;
        }));

        if (!empty($ids)) {
            $newStatus = $statusMap[$bulkAction];
            $stmt = $conn->prepare("UPDATE properties SET status=? WHERE id=?");
            foreach ($ids as $id) {
                $stmt->bind_param("si", $newStatus, $id);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    $redirectQuery = http_build_query([
        "search" => $search,
        "status" => $filterStatus,
        "page" => $page
    ]);
    header("Location: dashboard.php" . ($redirectQuery ? "?" . $redirectQuery : ""));
    exit();
}

$whereParts = [];
$params = [];
$types = "";

if ($search !== "") {
    $whereParts[] = "(
        p.title LIKE ?
        OR p.sector LIKE ?
        OR p.type LIKE ?
        OR p.seater_option LIKE ?
        OR p.bhk_option LIKE ?
        OR p.description LIKE ?
        OR p.amenities LIKE ?
        OR p.phone LIKE ?
        OR CAST(p.rent AS CHAR) LIKE ?
        OR p.status LIKE ?
    )";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "ssssssssss";
}

if ($filterStatus !== "all") {
    $whereParts[] = "p.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$whereClause = "";
if (!empty($whereParts)) {
    $whereClause = " WHERE " . implode(" AND ", $whereParts);
}

$countSql = "SELECT COUNT(*) AS total FROM properties p" . $whereClause;
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$filteredTotal = $countResult ? (int)$countResult->fetch_assoc()["total"] : 0;
$countStmt->close();

$totalPages = max(1, (int)ceil($filteredTotal / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
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
" . $whereClause . " ORDER BY p.id DESC LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$listTypes = $types . "ii";
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;
$listStmt->bind_param($listTypes, ...$listParams);
$listStmt->execute();
$propertiesResult = $listStmt->get_result();
$hasRows = ($propertiesResult && $propertiesResult->num_rows > 0);

$totalResult = $conn->query("SELECT COUNT(*) AS total FROM properties");
$approvedResult = $conn->query("SELECT COUNT(*) AS total FROM properties WHERE status='approved'");
$pendingResult = $conn->query("SELECT COUNT(*) AS total FROM properties WHERE status='pending' OR status IS NULL OR status=''");
$rejectedResult = $conn->query("SELECT COUNT(*) AS total FROM properties WHERE status='rejected'");

$total = $totalResult ? (int)$totalResult->fetch_assoc()["total"] : 0;
$approved = $approvedResult ? (int)$approvedResult->fetch_assoc()["total"] : 0;
$pending = $pendingResult ? (int)$pendingResult->fetch_assoc()["total"] : 0;
$rejected = $rejectedResult ? (int)$rejectedResult->fetch_assoc()["total"] : 0;

function buildDashboardQuery($search, $status, $page)
{
    return http_build_query([
        "search" => $search,
        "status" => $status,
        "page" => $page
    ]);
}

function statusBadgeClass($status)
{
    $safe = strtolower((string)$status);
    if ($safe === "approved") {
        return "bg-emerald-100 text-emerald-700 border border-emerald-200";
    }
    if ($safe === "rejected") {
        return "bg-rose-100 text-rose-700 border border-rose-200";
    }
    return "bg-amber-100 text-amber-700 border border-amber-200";
}

function renderStars($avgRating)
{
    $filled = (int)round((float)$avgRating);
    $html = '<span class="inline-flex items-center gap-0.5">';
    for ($i = 1; $i <= 5; $i++) {
        $fillClass = $i <= $filled ? "text-amber-500" : "text-slate-300 dark:text-slate-600";
        $html .= '<svg class="w-3.5 h-3.5 ' . $fillClass . '" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.95-.69l1.07-3.292z"/></svg>';
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
    <title>Nestoida Dashboard</title>
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
                            ink: '#0f172a',
                            muted: '#64748b',
                            cyan: '#0891b2'
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
            #page-loader, #dashboard-listing-loader { display: none !important; }
        </style>
    </noscript>
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui font-body bg-gradient-to-b from-slate-50 to-white text-slate-900 min-h-screen dark:from-slate-950 dark:to-slate-900 dark:text-slate-100">
    <div id="page-loader" class="fixed inset-0 z-50 bg-white dark:bg-slate-950 flex items-center justify-center">
        <div class="flex items-center gap-3 text-slate-700 dark:text-slate-100">
            <div class="w-6 h-6 border-2 border-slate-300 border-t-slate-900 rounded-full animate-spin"></div>
            <span class="text-sm font-semibold tracking-wide">Loading workspace...</span>
        </div>
    </div>

    <header class="sticky top-0 z-40 backdrop-blur bg-white/85 border-b border-slate-200 dark:bg-slate-950/80 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <a href="index.php" aria-label="Go to homepage" class="inline-flex">
                        <img src="assets/img/nestoida-logo.svg" alt="Nestoida Logo" class="w-9 h-9">
                    </a>
                    <h1 class="font-display text-2xl tracking-tight">Nestoida Dashboard</h1>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-300">Manage listings, approvals, and publishing workflow</p>
            </div>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="manage-users.php" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Manage Users</a>
                <a href="admin-profile.php" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Profile</a>
                <a href="index.php" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Public Site</a>
                <a href="add-property.php" class="px-4 py-2 rounded-full bg-slate-900 text-white hover:bg-cyan-700 transition">Add Property</a>
                <a href="logout.php" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        <section class="mb-6 rounded-3xl border border-slate-200 p-6 md:p-8 bg-[radial-gradient(circle_at_20%_20%,rgba(255,56,92,0.20),transparent_40%),radial-gradient(circle_at_85%_10%,rgba(255,180,92,0.20),transparent_35%),linear-gradient(120deg,#ffffff_0%,#fff8f3_60%,#fff5f7_100%)] dark:bg-[radial-gradient(circle_at_20%_20%,rgba(255,56,92,0.16),transparent_40%),radial-gradient(circle_at_85%_10%,rgba(255,180,92,0.12),transparent_35%),linear-gradient(120deg,#1d1d1d_0%,#1f1b1a_60%,#23181d_100%)] dark:border-slate-800">
            <p class="text-xs uppercase tracking-[0.18em] text-rose-600 dark:text-rose-300">Admin Workspace</p>
            <h2 class="font-display text-3xl md:text-4xl mt-2">Moderate listings with confidence</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Track approvals, detect pending updates from owners, and publish quality listings quickly.</p>
        </section>

        <section class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Total</p>
                <p class="font-display text-3xl mt-2"><?php echo $total; ?></p>
            </div>
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.16em] text-emerald-700">Approved</p>
                <p class="font-display text-3xl mt-2 text-emerald-700"><?php echo $approved; ?></p>
            </div>
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.16em] text-amber-700">Pending</p>
                <p class="font-display text-3xl mt-2 text-amber-700"><?php echo $pending; ?></p>
            </div>
            <div class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.16em] text-rose-700">Rejected</p>
                <p class="font-display text-3xl mt-2 text-rose-700"><?php echo $rejected; ?></p>
            </div>
        </section>

        <section class="bg-white border border-slate-200 rounded-3xl mt-8 overflow-hidden shadow-sm dark:bg-slate-900 dark:border-slate-800">
            <div class="px-5 py-4 border-b border-slate-200 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
                <h2 class="font-display text-xl">Manage Properties</h2>
                <form method="GET" id="dashboard-search-form" class="w-full lg:w-auto flex flex-col sm:flex-row gap-2 sm:items-center">
                    <div class="flex-1 sm:min-w-[320px] border border-slate-200 rounded-full px-4 py-2 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <label class="block text-[10px] uppercase tracking-[0.16em] text-slate-500 dark:text-slate-300">Search Listings</label>
                        <input
                            type="text"
                            id="dashboard-search-input"
                            name="search"
                            placeholder="Address, sector, title, phone, price..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full border-0 p-0 mt-1 text-sm bg-transparent focus:ring-0"
                        >
                    </div>
                    <select id="dashboard-status-select" name="status" class="border border-slate-300 rounded-full px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <option value="all" <?php echo $filterStatus === "all" ? "selected" : ""; ?>>All</option>
                        <option value="approved" <?php echo $filterStatus === "approved" ? "selected" : ""; ?>>Approved</option>
                        <option value="pending" <?php echo $filterStatus === "pending" ? "selected" : ""; ?>>Pending</option>
                        <option value="rejected" <?php echo $filterStatus === "rejected" ? "selected" : ""; ?>>Rejected</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <button class="bg-slate-900 text-white px-5 py-3 rounded-full font-semibold hover:bg-cyan-700 transition">Apply</button>
                </form>
            </div>

            <div id="dashboard-listing-loader" class="hidden p-5 space-y-3 animate-pulse">
                <?php for ($i = 0; $i < 8; $i++) { ?>
                    <div class="h-11 rounded-xl bg-slate-200"></div>
                <?php } ?>
            </div>

            <div id="dashboard-listing-content">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
                            <tr>
                                <th class="text-left px-4 py-3"><input type="checkbox" id="select-all" class="h-4 w-4 rounded border-slate-300"></th>
                                <th class="text-left px-4 py-3">ID</th>
                                <th class="text-left px-4 py-3">Title</th>
                                <th class="text-left px-4 py-3">Sector</th>
                                <th class="text-left px-4 py-3">Rent</th>
                                <th class="text-left px-4 py-3">Rating</th>
                                <th class="text-left px-4 py-3">Status</th>
                                <th class="text-left px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if ($hasRows) { ?>
                            <?php while ($row = $propertiesResult->fetch_assoc()) { ?>
                                <?php $rowStatus = isset($row["status"]) && $row["status"] !== "" ? $row["status"] : "pending"; ?>
                                <tr
                                    class="hover:bg-slate-50/70 dark:hover:bg-slate-800/60 transition"
                                    data-search="<?php echo htmlspecialchars(strtolower(
                                        ($row['title'] ?? '') . ' ' .
                                        ($row['sector'] ?? '') . ' ' .
                                        ($row['type'] ?? '') . ' ' .
                                        ($row['seater_option'] ?? '') . ' ' .
                                        ($row['bhk_option'] ?? '') . ' ' .
                                        ($row['description'] ?? '') . ' ' .
                                        ($row['amenities'] ?? '') . ' ' .
                                        ($row['phone'] ?? '') . ' ' .
                                        (string)($row['rent'] ?? '') . ' ' .
                                        ($rowStatus ?? '')
                                    )); ?>"
                                    data-status="<?php echo htmlspecialchars(strtolower($rowStatus)); ?>"
                                >
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="ids[]" value="<?php echo (int)$row["id"]; ?>" class="row-check h-4 w-4 rounded border-slate-300" form="bulk-form">
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-500 dark:text-slate-300">#<?php echo (int)$row['id']; ?></td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold"><?php echo htmlspecialchars($row['title']); ?></p>
                                        <?php
                                        $dashType = strtolower(trim((string)($row['type'] ?? '')));
                                        $dashSpec = "";
                                        $dashIsPgOrHostel = strpos($dashType, "pg") !== false || strpos($dashType, "hostel") !== false;
                                        $dashIsFlatLike = strpos($dashType, "flat") !== false || strpos($dashType, "apartment") !== false || strpos($dashType, "bhk") !== false;
                                        if ($dashIsPgOrHostel) {
                                            $dashSpec = !empty($row['seater_option']) ? (string)$row['seater_option'] : "Seater not set";
                                        } elseif ($dashIsFlatLike) {
                                            $dashSpec = !empty($row['bhk_option']) ? (string)$row['bhk_option'] : "BHK not set";
                                        }
                                        ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-300">
                                            <?php echo htmlspecialchars($row['type'] ?? "Listing"); ?>
                                            <?php if ($dashSpec !== "") { ?> · <?php echo htmlspecialchars($dashSpec); ?><?php } ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($row['sector']); ?></td>
                                    <td class="px-4 py-3 font-semibold">Rs <?php echo (int)$row['rent']; ?></td>
                                    <td class="px-4 py-3 text-amber-600 font-semibold">
                                        <div class="flex items-center gap-2">
                                            <?php echo renderStars((float)$row['avg_rating']); ?>
                                            <span><?php echo (int)$row['rating_count'] > 0 ? number_format((float)$row['avg_rating'], 1) . " (" . (int)$row['rating_count'] . ")" : "Not rated"; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo statusBadgeClass($rowStatus); ?>">
                                            <?php echo htmlspecialchars(ucfirst($rowStatus)); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="property.php?id=<?php echo (int)$row['id']; ?>" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">View</a>
                                            <a href="edit-property.php?id=<?php echo (int)$row['id']; ?>" class="px-3 py-1.5 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Edit</a>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo (int)$row["id"]; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="px-3 py-1.5 rounded-full bg-emerald-600 text-white hover:bg-emerald-700 transition">Approve</button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo (int)$row["id"]; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="px-3 py-1.5 rounded-full bg-rose-600 text-white hover:bg-rose-700 transition">Reject</button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?php echo (int)$row["id"]; ?>">
                                                <input type="hidden" name="action" value="pending">
                                                <button type="submit" class="px-3 py-1.5 rounded-full border border-amber-300 text-amber-700 hover:bg-amber-50 transition">Pending</button>
                                            </form>
                                            <a href="delete-property.php?id=<?php echo (int)$row['id']; ?>" class="px-3 py-1.5 rounded-full border border-rose-300 text-rose-700 hover:bg-rose-50 transition" onclick="return confirm('Delete this property?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                            <tr id="client-empty-row" class="hidden">
                                <td colspan="8" class="px-4 py-10 text-center">
                                    <p class="font-display text-xl">No matching properties on this page</p>
                                    <p class="text-slate-500 dark:text-slate-300 mt-1">Try another search or status filter.</p>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <p class="font-display text-xl">No properties found</p>
                                    <p class="text-slate-500 dark:text-slate-300 mt-1">Try a different filter or search value.</p>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasRows) { ?>
                    <form method="POST" id="bulk-form"></form>
                    <div class="px-5 py-4 border-t border-slate-200 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 dark:border-slate-800">
                        <p class="text-sm text-slate-500 dark:text-slate-300">Bulk actions apply to selected rows on this page.</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" form="bulk-form" name="bulk_action" value="approve" class="px-4 py-2 rounded-full bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition">Approve Selected</button>
                            <button type="submit" form="bulk-form" name="bulk_action" value="reject" class="px-4 py-2 rounded-full bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition">Reject Selected</button>
                            <button type="submit" form="bulk-form" name="bulk_action" value="pending" class="px-4 py-2 rounded-full border border-amber-300 text-amber-700 text-sm font-semibold hover:bg-amber-50 transition">Mark Pending</button>
                        </div>
                    </div>
                <?php } ?>

                <div class="px-5 py-4 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm dark:border-slate-800">
                    <p class="text-slate-500 dark:text-slate-300">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $filteredTotal; ?> results)</p>
                    <div class="flex gap-2">
                        <?php if ($page > 1) { ?>
                            <a href="dashboard.php?<?php echo buildDashboardQuery($search, $filterStatus, $page - 1); ?>" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Previous</a>
                        <?php } ?>
                        <?php if ($page < $totalPages) { ?>
                            <a href="dashboard.php?<?php echo buildDashboardQuery($search, $filterStatus, $page + 1); ?>" class="px-4 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Next</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
    const selectAll = document.getElementById("select-all");
    const rowChecks = document.querySelectorAll(".row-check");
    const pageLoader = document.getElementById("page-loader");
    const dashboardListingLoader = document.getElementById("dashboard-listing-loader");
    const dashboardListingContent = document.getElementById("dashboard-listing-content");
    const themeToggle = document.getElementById("theme-toggle");
    const themeToggleLabel = document.getElementById("theme-toggle-label");
    const dashboardSearchForm = document.getElementById("dashboard-search-form");
    const dashboardSearchInput = document.getElementById("dashboard-search-input");
    const dashboardStatusSelect = document.getElementById("dashboard-status-select");
    const dashboardRows = Array.from(document.querySelectorAll("tr[data-search]"));
    const clientEmptyRow = document.getElementById("client-empty-row");
    let dashboardSearchTimer = null;

    function syncThemeLabel() {
        if (!themeToggleLabel) return;
        themeToggleLabel.textContent = document.documentElement.classList.contains("dark") ? "Light" : "Dark";
    }
    syncThemeLabel();
    if (themeToggle) {
        themeToggle.addEventListener("click", function () {
            const root = document.documentElement;
            const isDark = root.classList.toggle("dark");
            try {
                localStorage.setItem("nestoida_theme", isDark ? "dark" : "light");
            } catch (e) {}
            syncThemeLabel();
        });
    }

    function applyDashboardFilter() {
        if (!dashboardSearchInput || dashboardRows.length === 0) return;
        const query = dashboardSearchInput.value.trim().toLowerCase();
        const selectedStatus = dashboardStatusSelect ? dashboardStatusSelect.value.toLowerCase() : "all";
        let visible = 0;

        dashboardRows.forEach(function (row) {
            const searchText = row.getAttribute("data-search") || "";
            const statusText = row.getAttribute("data-status") || "";
            const matchedQuery = query === "" || searchText.includes(query);
            const matchedStatus = selectedStatus === "all" || statusText === selectedStatus;
            const show = matchedQuery && matchedStatus;
            row.classList.toggle("hidden", !show);
            if (show) visible++;
        });

        if (clientEmptyRow) {
            clientEmptyRow.classList.toggle("hidden", visible !== 0);
        }
        if (selectAll) {
            selectAll.checked = false;
        }
    }

    if (dashboardSearchInput && dashboardSearchForm) {
        dashboardSearchInput.addEventListener("input", function () {
            clearTimeout(dashboardSearchTimer);
            dashboardSearchTimer = setTimeout(function () {
                applyDashboardFilter();
            }, 350);
        });
    }

    if (dashboardStatusSelect && dashboardSearchForm) {
        dashboardStatusSelect.addEventListener("change", function () {
            applyDashboardFilter();
        });
    }

    applyDashboardFilter();

    if (selectAll) {
        selectAll.addEventListener("change", function () {
            rowChecks.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    window.addEventListener("load", function () {
        if (dashboardListingLoader && dashboardListingContent) {
            dashboardListingContent.classList.add("hidden");
            dashboardListingLoader.classList.remove("hidden");

            setTimeout(function () {
                dashboardListingLoader.classList.add("hidden");
                dashboardListingContent.classList.remove("hidden");
            }, 450);
        }

        if (pageLoader) {
            pageLoader.classList.add("opacity-0", "pointer-events-none", "transition-opacity", "duration-300");
            setTimeout(function () {
                pageLoader.remove();
            }, 300);
        }
    });
    </script>
    <script src="assets/js/back-button.js"></script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
