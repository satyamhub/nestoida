<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"], $_SESSION["user_role"]) || $_SESSION["user_role"] !== "owner") {
    header("Location: user-login.php");
    exit();
}

$ownerId = (int)$_SESSION["user_id"];
$ownerName = (string)($_SESSION["user_name"] ?? "Owner");
$ownerVerified = false;
$ownerNameStmt = $conn->prepare("SELECT full_name, owner_verified FROM users WHERE id=? LIMIT 1");
if ($ownerNameStmt) {
    $ownerNameStmt->bind_param("i", $ownerId);
    $ownerNameStmt->execute();
    $ownerNameRes = $ownerNameStmt->get_result();
    $ownerNameRow = $ownerNameRes ? $ownerNameRes->fetch_assoc() : null;
    if ($ownerNameRow && !empty($ownerNameRow["full_name"])) {
        $ownerName = (string)$ownerNameRow["full_name"];
    }
    $ownerVerified = !empty($ownerNameRow["owner_verified"]);
    $ownerNameStmt->close();
}
$updated = isset($_GET["updated"]) && $_GET["updated"] === "1";

$stmt = $conn->prepare("
    SELECT
        p.id, p.title, p.type, p.seater_option, p.bhk_option, p.sector, p.rent, p.status, p.created_at, p.updated_at,
        COALESCE(f.feedback_count, 0) AS feedback_count
    FROM properties p
    LEFT JOIN (
        SELECT property_id, COUNT(*) AS feedback_count
        FROM listing_feedback
        GROUP BY property_id
    ) f ON f.property_id = p.id
    WHERE p.owner_user_id=?
    ORDER BY p.id DESC
");
$stmt->bind_param("i", $ownerId);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
$approvedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;

if ($result) {
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
        $statusValue = strtolower((string)($r["status"] ?? "pending"));
        if ($statusValue === "approved") {
            $approvedCount++;
        } elseif ($statusValue === "rejected") {
            $rejectedCount++;
        } else {
            $pendingCount++;
        }
    }
}
$totalCount = count($rows);
$feedbackTotal = 0;
$feedbackStmt = $conn->prepare("SELECT COUNT(*) AS total FROM listing_feedback WHERE owner_user_id=?");
$feedbackStmt->bind_param("i", $ownerId);
$feedbackStmt->execute();
$feedbackRes = $feedbackStmt->get_result();
if ($feedbackRes) {
    $feedbackTotal = (int)($feedbackRes->fetch_assoc()["total"] ?? 0);
}
$feedbackStmt->close();

$inquiryTotal = 0;
$inquiryCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM property_inquiries WHERE owner_user_id=?");
$inquiryCountStmt->bind_param("i", $ownerId);
$inquiryCountStmt->execute();
$inquiryCountRes = $inquiryCountStmt->get_result();
if ($inquiryCountRes) {
    $inquiryTotal = (int)($inquiryCountRes->fetch_assoc()["total"] ?? 0);
}
$inquiryCountStmt->close();

$recentInquiryStmt = $conn->prepare("
    SELECT name, email, phone, message, created_at, property_id
    FROM property_inquiries
    WHERE owner_user_id=?
    ORDER BY id DESC
    LIMIT 8
");
$recentInquiryStmt->bind_param("i", $ownerId);
$recentInquiryStmt->execute();
$recentInquiries = $recentInquiryStmt->get_result();
$recentInquiryStmt->close();

$recentFeedbackStmt = $conn->prepare("
    SELECT
        lf.comment,
        COALESCE(lf.feedback_rating, 0) AS feedback_rating,
        lf.created_at,
        p.title AS property_title,
        COALESCE(u.full_name, lf.commenter_name, 'Guest') AS commenter_name,
        CASE
            WHEN COALESCE(lf.commenter_role, '') <> '' THEN LOWER(lf.commenter_role)
            WHEN u.role IS NOT NULL THEN LOWER(u.role)
            ELSE 'guest'
        END AS commenter_role
    FROM listing_feedback lf
    INNER JOIN properties p ON p.id = lf.property_id
    LEFT JOIN users u ON u.id = lf.user_id
    WHERE lf.owner_user_id=?
    ORDER BY lf.id DESC
    LIMIT 8
");
$recentFeedbackStmt->bind_param("i", $ownerId);
$recentFeedbackStmt->execute();
$recentFeedback = $recentFeedbackStmt->get_result();
$recentFeedbackStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ownerName); ?> Dashboard - Nestoida</title>
    <script>
        (function () {
            try {
                if (localStorage.getItem("nestoida_theme") === "dark") {
                    document.documentElement.classList.add("dark");
                }
            } catch (e) {}
        })();
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const btn = document.getElementById("owner-notif-btn");
            const panel = document.getElementById("owner-notif-panel");
            const list = document.getElementById("owner-notif-list");
            const badge = document.getElementById("owner-notif-badge");
            const markAll = document.getElementById("owner-notif-mark");
            let open = false;

            function render(items) {
                if (!list) return;
                if (!items || items.length === 0) {
                    list.innerHTML = '<p class="text-sm text-slate-500 dark:text-slate-300">No notifications yet.</p>';
                    return;
                }
                list.innerHTML = items.map(function (note) {
                    const isNew = parseInt(note.is_read, 10) === 0;
                    const badgeHtml = isNew ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-emerald-200 text-emerald-700 bg-emerald-50">New</span>' : '';
                    const openLink = note.url ? '<a class="text-xs text-cyan-700 dark:text-cyan-300" href="' + note.url + '">Open</a>' : '';
                    const markBtn = isNew ? '<button data-id="' + note.id + '" class="text-xs text-slate-500 underline">Mark read</button>' : '';
                    return '<article class="rounded-xl border border-slate-200 p-3 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">' +
                        '<div class="flex items-center justify-between gap-2">' +
                            '<p class="text-sm font-semibold">' + note.title + badgeHtml + '</p>' +
                            '<p class="text-xs text-slate-500">' + note.created_at + '</p>' +
                        '</div>' +
                        '<p class="mt-1 text-sm text-slate-700 dark:text-slate-200">' + note.message + '</p>' +
                        '<div class="mt-2 flex items-center gap-3">' + openLink + markBtn + '</div>' +
                    '</article>';
                }).join('');
            }

            function fetchCount() {
                if (!badge) return;
                fetch("owner-notifications-count.php", { credentials: "same-origin" })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        const unread = parseInt(data.unread || 0, 10);
                        badge.textContent = unread;
                        badge.classList.toggle("hidden", unread <= 0);
                    })
                    .catch(function () {});
            }

            function fetchList() {
                fetch("owner-notifications-list.php?filter=all", { credentials: "same-origin" })
                    .then(function (res) { return res.json(); })
                    .then(function (data) { render(data.items || []); })
                    .catch(function () {});
            }

            function markRead(id) {
                const body = new URLSearchParams();
                body.set("action", id ? "one" : "all");
                if (id) body.set("id", String(id));
                fetch("owner-notifications-mark.php", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: body.toString()
                }).then(function () {
                    fetchCount();
                    fetchList();
                }).catch(function () {});
            }

            if (btn && panel) {
                btn.addEventListener("click", function () {
                    open = !open;
                    panel.classList.toggle("hidden", !open);
                    if (open) fetchList();
                });
                document.addEventListener("click", function (event) {
                    if (!open) return;
                    if (panel.contains(event.target) || btn.contains(event.target)) return;
                    open = false;
                    panel.classList.add("hidden");
                });
            }

            if (markAll) {
                markAll.addEventListener("click", function () {
                    markRead(null);
                });
            }

            if (list) {
                list.addEventListener("click", function (event) {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) return;
                    const id = target.getAttribute("data-id");
                    if (id) {
                        markRead(id);
                    }
                });
            }

            fetchCount();
            setInterval(fetchCount, 20000);

            // Welcome dropdown on every login
            if (panel && btn) {
                panel.classList.remove("hidden");
                open = true;
                fetchList();
                setTimeout(function () {
                    panel.classList.add("hidden");
                    open = false;
                }, 3000);
            }
        });
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
        <div class="max-w-6xl mx-auto px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <a href="index.php" aria-label="Go to homepage" class="inline-flex">
                        <img src="assets/img/nestoida-logo.svg" alt="Nestoida Logo" class="w-9 h-9">
                    </a>
                    <h1 class="font-display text-2xl inline-flex items-center gap-2">
                        <?php echo htmlspecialchars($ownerName); ?> Dashboard
                        <?php if ($ownerVerified) { ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-cyan-200 text-cyan-700 bg-cyan-50 dark:border-cyan-600 dark:text-cyan-200 dark:bg-cyan-900/40">
                                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 0a10 10 0 100 20 10 10 0 000-20zm4.2 7.3-4.8 5a1 1 0 01-1.4 0l-2.2-2.3a1 1 0 011.4-1.4l1.5 1.5 4.1-4.2a1 1 0 011.4 1.4z"/></svg>
                                Verified
                            </span>
                        <?php } ?>
                    </h1>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-300">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"] ?? "Owner"); ?></p>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <div class="relative z-50">
                    <button id="owner-notif-btn" type="button" class="relative px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">
                        <span class="sr-only">Notifications</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a5 5 0 00-5 5v2.6l-.9 1.8a1 1 0 00.9 1.6h12a1 1 0 00.9-1.6L15 9.6V7a5 5 0 00-5-5z"/><path d="M8.2 16a1.8 1.8 0 003.6 0H8.2z"/></svg>
                        <span id="owner-notif-badge" class="absolute -top-1 -right-1 inline-flex items-center justify-center w-5 h-5 text-[10px] font-semibold rounded-full bg-rose-500 text-white hidden">0</span>
                    </button>
                    <div id="owner-notif-panel" class="hidden absolute right-0 mt-2 w-80 rounded-2xl border border-slate-200 bg-white shadow-lg dark:bg-slate-900 dark:border-slate-700 z-50 pointer-events-auto">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-800">
                            <p class="font-semibold text-sm">Notifications</p>
                            <button id="owner-notif-mark" type="button" class="text-xs text-cyan-700 dark:text-cyan-300">Mark all read</button>
                        </div>
                        <div id="owner-notif-list" class="max-h-80 overflow-y-auto p-3 space-y-2">
                            <p class="text-sm text-slate-500 dark:text-slate-300">No notifications yet.</p>
                        </div>
                    </div>
                </div>
                <a href="owner-profile.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Profile</a>
                <a href="owner-analytics.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Analytics</a>
                <a href="add-property.php" class="px-3 py-2 rounded-full bg-slate-900 text-white">Add Property</a>
                <a href="index.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Home</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 dark:border-slate-700">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <?php if ($updated) { ?>
            <div class="mb-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm">
                Listing updated successfully.
            </div>
        <?php } ?>
        <section class="grid sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-300 uppercase tracking-[0.16em]">Total</p>
                <p class="text-3xl font-display mt-2"><?php echo $totalCount; ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-300 uppercase tracking-[0.16em]">Approved</p>
                <p class="text-3xl font-display mt-2 text-emerald-600"><?php echo $approvedCount; ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-300 uppercase tracking-[0.16em]">Pending</p>
                <p class="text-3xl font-display mt-2 text-amber-600"><?php echo $pendingCount; ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-300 uppercase tracking-[0.16em]">Rejected</p>
                <p class="text-3xl font-display mt-2 text-rose-600"><?php echo $rejectedCount; ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-300 uppercase tracking-[0.16em]">Feedback</p>
                <p class="text-3xl font-display mt-2 text-cyan-600"><?php echo $feedbackTotal; ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-300 uppercase tracking-[0.16em]">Inquiries</p>
                <p class="text-3xl font-display mt-2 text-indigo-600"><?php echo $inquiryTotal; ?></p>
            </div>
        </section>

        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden dark:bg-slate-900 dark:border-slate-800">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="font-display text-xl">My Listings</h2>
                <a href="add-property.php" class="px-4 py-2 rounded-full bg-slate-900 text-white text-sm font-semibold">Add New</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/70 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="text-left px-4 py-3">ID</th>
                            <th class="text-left px-4 py-3">Title</th>
                            <th class="text-left px-4 py-3">Sector</th>
                            <th class="text-left px-4 py-3">Rent</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Feedback</th>
                            <th class="text-left px-4 py-3">Created</th>
                            <th class="text-left px-4 py-3">Updated</th>
                            <th class="text-left px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (!empty($rows)) { ?>
                            <?php foreach ($rows as $row) { ?>
                                <tr>
                                    <td class="px-4 py-3">#<?php echo (int)$row["id"]; ?></td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold"><?php echo htmlspecialchars($row["title"]); ?></p>
                                        <?php
                                        $ownerType = strtolower(trim((string)($row["type"] ?? "")));
                                        $ownerSpec = "";
                                        $ownerIsPgOrHostel = strpos($ownerType, "pg") !== false || strpos($ownerType, "hostel") !== false;
                                        $ownerIsFlatLike = strpos($ownerType, "flat") !== false || strpos($ownerType, "apartment") !== false || strpos($ownerType, "bhk") !== false;
                                        if ($ownerIsPgOrHostel) {
                                            $ownerSpec = !empty($row["seater_option"]) ? (string)$row["seater_option"] : "Seater not set";
                                        } elseif ($ownerIsFlatLike) {
                                            $ownerSpec = !empty($row["bhk_option"]) ? (string)$row["bhk_option"] : "BHK not set";
                                        }
                                        ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-300">
                                            <?php echo htmlspecialchars($row["type"] ?? "Listing"); ?>
                                            <?php if ($ownerSpec !== "") { ?> · <?php echo htmlspecialchars($ownerSpec); ?><?php } ?>
                                        </p>
                                    </td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row["sector"]); ?></td>
                                    <td class="px-4 py-3">Rs <?php echo (int)$row["rent"]; ?></td>
                                    <td class="px-4 py-3 capitalize">
                                        <?php $status = htmlspecialchars($row["status"] ?: "pending"); ?>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs border <?php echo strtolower($status) === "approved" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (strtolower($status) === "rejected" ? "bg-rose-50 text-rose-700 border-rose-200" : "bg-amber-50 text-amber-700 border-amber-200"); ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3"><?php echo (int)($row["feedback_count"] ?? 0); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($row["created_at"]); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars((string)($row["updated_at"] ?? "-")); ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="property.php?id=<?php echo (int)$row["id"]; ?>" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                                                View
                                            </a>
                                            <a href="owner-edit-property.php?id=<?php echo (int)$row["id"]; ?>" class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                                                Edit
                                            </a>
                                            <a href="listing-history.php?id=<?php echo (int)$row["id"]; ?>" class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                                                History
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-500 dark:text-slate-300">No listings yet. Add your first property.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <section class="mt-6 bg-white border border-slate-200 rounded-3xl p-5 dark:bg-slate-900 dark:border-slate-800">
            <h2 class="font-display text-xl">Recent Feedback</h2>
            <div class="mt-4 space-y-3">
                <?php if ($recentFeedback && $recentFeedback->num_rows > 0) { ?>
                    <?php while ($fb = $recentFeedback->fetch_assoc()) { ?>
                        <article class="rounded-xl border border-slate-200 p-3 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                            <?php
                            $fbRole = strtolower((string)($fb["commenter_role"] ?? "guest"));
                            $fbRoleClass = "bg-slate-100 text-slate-700 border-slate-200";
                            $fbRoleLabel = "Guest";
                            if ($fbRole === "admin") {
                                $fbRoleClass = "bg-rose-100 text-rose-700 border-rose-200";
                                $fbRoleLabel = "Admin";
                            } elseif ($fbRole === "owner") {
                                $fbRoleClass = "bg-amber-100 text-amber-700 border-amber-200";
                                $fbRoleLabel = "Owner";
                            } elseif ($fbRole === "viewer") {
                                $fbRoleClass = "bg-cyan-100 text-cyan-700 border-cyan-200";
                                $fbRoleLabel = "Viewer";
                            }
                            ?>
                            <p class="text-sm font-semibold">
                                <?php echo htmlspecialchars((string)$fb["commenter_name"]); ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border <?php echo $fbRoleClass; ?>"><?php echo $fbRoleLabel; ?></span>
                                on <?php echo htmlspecialchars((string)$fb["property_title"]); ?>
                            </p>
                            <p class="mt-1 text-xs font-semibold text-amber-600"><?php echo (int)($fb["feedback_rating"] ?? 0); ?>/5</p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200"><?php echo nl2br(htmlspecialchars((string)$fb["comment"])); ?></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars((string)$fb["created_at"]); ?></p>
                        </article>
                    <?php } ?>
                <?php } else { ?>
                    <p class="text-sm text-slate-500 dark:text-slate-300">No feedback received yet.</p>
                <?php } ?>
            </div>
        </section>

        <section class="mt-6 bg-white border border-slate-200 rounded-3xl p-5 dark:bg-slate-900 dark:border-slate-800">
            <h2 class="font-display text-xl">Recent Inquiries</h2>
            <div class="mt-4 space-y-3">
                <?php if ($recentInquiries && $recentInquiries->num_rows > 0) { ?>
                    <?php while ($inq = $recentInquiries->fetch_assoc()) { ?>
                        <article class="rounded-xl border border-slate-200 p-3 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars((string)$inq["name"]); ?> · Listing #<?php echo (int)$inq["property_id"]; ?></p>
                            <?php if (!empty($inq["email"])) { ?><p class="text-xs text-slate-500"><?php echo htmlspecialchars((string)$inq["email"]); ?></p><?php } ?>
                            <?php if (!empty($inq["phone"])) { ?><p class="text-xs text-slate-500"><?php echo htmlspecialchars((string)$inq["phone"]); ?></p><?php } ?>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200"><?php echo nl2br(htmlspecialchars((string)$inq["message"])); ?></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars((string)$inq["created_at"]); ?></p>
                        </article>
                    <?php } ?>
                <?php } else { ?>
                    <p class="text-sm text-slate-500 dark:text-slate-300">No inquiries yet.</p>
                <?php } ?>
            </div>
        </section>
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
