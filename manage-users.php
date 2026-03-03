<?php
session_start();
include "db.php";

if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";
$search = trim($_GET["q"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST" && !nestoida_csrf_valid()) {
    $error = "Invalid request. Please refresh and try again.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["user_id"])) {
    $action = trim($_POST["action"]);
    $userId = (int)$_POST["user_id"];

    if ($userId <= 0) {
        $error = "Invalid user.";
    } else {
        if ($action === "set_role" && isset($_POST["role"])) {
            $role = $_POST["role"] === "owner" ? "owner" : "viewer";
            $stmt = $conn->prepare("UPDATE users SET role=?, session_version=COALESCE(session_version, 1) + 1 WHERE id=?");
            $stmt->bind_param("si", $role, $userId);
            if ($stmt->execute()) {
                $message = "User role updated and active session invalidated.";
            } else {
                $error = "Could not update role.";
            }
            $stmt->close();
        } elseif ($action === "toggle_owner_verified" && isset($_POST["owner_verified"])) {
            $verified = (int)$_POST["owner_verified"] === 1 ? 1 : 0;
            $stmt = $conn->prepare("UPDATE users SET owner_verified=? WHERE id=? AND role='owner'");
            $stmt->bind_param("ii", $verified, $userId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = $verified ? "Owner verified." : "Owner verification removed.";
                    if ($verified === 1) {
                        $noteStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, url) VALUES (?, ?, ?, ?)");
                        if ($noteStmt) {
                            $title = "Owner verification approved";
                            $msg = "Your owner profile is now verified. Your listings will show the verified badge.";
                            $url = "owner-dashboard.php";
                            $noteStmt->bind_param("isss", $userId, $title, $msg, $url);
                            $noteStmt->execute();
                            $noteStmt->close();
                        }
                    }
                } else {
                    $error = "Only owners can be verified.";
                }
            } else {
                $error = "Could not update owner verification.";
            }
            $stmt->close();
        } elseif ($action === "delete_user") {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $message = "User deleted.";
            } else {
                $error = "Could not delete user.";
            }
            $stmt->close();
        }
    }
}

if ($search !== "") {
    $like = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT id, full_name, email, role, owner_verified, email_verified_at, created_at, profile_photo FROM users WHERE full_name LIKE ? OR email LIKE ? ORDER BY id DESC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $usersResult = $stmt->get_result();
} else {
    $usersResult = $conn->query("SELECT id, full_name, email, role, owner_verified, email_verified_at, created_at, profile_photo FROM users ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Nestoida</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui font-body bg-slate-50 min-h-screen text-slate-900">
    <header class="sticky top-0 z-40 backdrop-blur bg-white/85 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h1 class="font-display text-2xl">Manage Users</h1>
            <div class="flex gap-2 text-sm">
                <a href="dashboard.php" class="px-3 py-2 rounded-full border border-slate-300">Dashboard</a>
                <a href="admin-profile.php" class="px-3 py-2 rounded-full border border-slate-300">Admin Profile</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8">
        <?php if ($message !== "") { ?>
            <div class="mb-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
        <?php } ?>
        <?php if ($error !== "") { ?>
            <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <section class="bg-white border border-slate-200 rounded-3xl p-4 shadow-sm">
            <form method="GET" class="flex gap-2">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or email" class="flex-1 border border-slate-300 rounded-xl px-4 py-3">
                <button class="bg-slate-900 text-white px-5 py-3 rounded-full font-semibold">Search</button>
            </form>
        </section>

        <section class="mt-6 bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="text-left px-4 py-3">User</th>
                            <th class="text-left px-4 py-3">Email</th>
                            <th class="text-left px-4 py-3">Role</th>
                            <th class="text-left px-4 py-3">Verified</th>
                            <th class="text-left px-4 py-3">Owner Badge</th>
                            <th class="text-left px-4 py-3">Joined</th>
                            <th class="text-left px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($usersResult && $usersResult->num_rows > 0) { ?>
                            <?php while ($u = $usersResult->fetch_assoc()) { ?>
                                <?php
                                $uPhoto = "";
                                $isOwnerVerified = !empty($u["owner_verified"]);
                                $ownerBadgeClass = $isOwnerVerified ? "border-cyan-300 text-cyan-700 bg-cyan-50" : "border-slate-200 text-slate-500 bg-slate-50";
                                $uPhoto = nestoida_profile_photo_url($u["profile_photo"] ?? "");
                                ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <?php if ($uPhoto !== "") { ?>
                                                <img src="<?php echo htmlspecialchars($uPhoto); ?>" alt="User photo" class="w-10 h-10 rounded-full object-cover border border-slate-200">
                                            <?php } else { ?>
                                                <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200"></div>
                                            <?php } ?>
                                            <div>
                                                <p class="font-semibold"><?php echo htmlspecialchars($u["full_name"]); ?></p>
                                                <p class="text-xs text-slate-500">#<?php echo (int)$u["id"]; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($u["email"]); ?></td>
                                    <td class="px-4 py-3 capitalize"><?php echo htmlspecialchars($u["role"]); ?></td>
                                    <td class="px-4 py-3"><?php echo !empty($u["email_verified_at"]) ? "Yes" : "No"; ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($u["role"] === "owner") { ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold border <?php echo $ownerBadgeClass; ?>">
                                                <?php if ($isOwnerVerified) { ?>
                                                    <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 0a10 10 0 100 20 10 10 0 000-20zm4.2 7.3-4.8 5a1 1 0 01-1.4 0l-2.2-2.3a1 1 0 011.4-1.4l1.5 1.5 4.1-4.2a1 1 0 011.4 1.4z"/></svg>
                                                <?php } ?>
                                                <?php echo $isOwnerVerified ? "Verified" : "Not verified"; ?>
                                            </span>
                                        <?php } else { ?>
                                            <span class="text-xs text-slate-400">—</span>
                                        <?php } ?>
                                    </td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars((string)$u["created_at"]); ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" class="inline-flex items-center gap-2">
                                                <?php echo nestoida_csrf_field(); ?>
                                                <input type="hidden" name="action" value="set_role">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u["id"]; ?>">
                                                <select name="role" class="border border-slate-300 rounded-full px-3 py-1.5">
                                                    <option value="viewer" <?php echo $u["role"] === "viewer" ? "selected" : ""; ?>>Viewer</option>
                                                    <option value="owner" <?php echo $u["role"] === "owner" ? "selected" : ""; ?>>Owner</option>
                                                </select>
                                                <button type="submit" class="px-3 py-1.5 rounded-full border border-slate-300">Update</button>
                                            </form>
                                            <?php if ($u["role"] === "owner") { ?>
                                                <form method="POST" class="inline">
                                                    <?php echo nestoida_csrf_field(); ?>
                                                    <input type="hidden" name="action" value="toggle_owner_verified">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$u["id"]; ?>">
                                                    <input type="hidden" name="owner_verified" value="<?php echo $isOwnerVerified ? 0 : 1; ?>">
                                                    <button type="submit" class="px-3 py-1.5 rounded-full border border-cyan-300 text-cyan-700">
                                                        <?php echo $isOwnerVerified ? "Remove Badge" : "Verify Owner"; ?>
                                                    </button>
                                                </form>
                                            <?php } ?>
                                            <form method="POST" onsubmit="return confirm('Delete this user?');" class="inline">
                                                <?php echo nestoida_csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u["id"]; ?>">
                                                <button type="submit" class="px-3 py-1.5 rounded-full border border-rose-300 text-rose-700">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">No users found.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="assets/js/back-button.js"></script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
