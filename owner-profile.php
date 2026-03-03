<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"], $_SESSION["user_role"]) || $_SESSION["user_role"] !== "owner") {
    header("Location: user-login.php");
    exit();
}

$userId = (int)$_SESSION["user_id"];
$error = "";
$message = "";

function profile_upload_error($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Image is too large.";
        case UPLOAD_ERR_PARTIAL:
            return "Image upload interrupted.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing server temp directory.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Server cannot write file.";
        case UPLOAD_ERR_EXTENSION:
            return "Upload blocked by extension.";
        default:
            return "Image upload failed.";
    }
}

$stmt = $conn->prepare("SELECT id, full_name, email, role, profile_photo, owner_verified FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userRes = $stmt->get_result();
$user = $userRes ? $userRes->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: user-login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!nestoida_csrf_valid()) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $photoName = $user["profile_photo"] ?? null;

    if ($fullName === "" || $email === "") {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email.";
    } elseif ($newPassword !== "" && strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Password confirmation does not match.";
    }

    if ($error === "") {
        $dupStmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
        $dupStmt->bind_param("si", $email, $userId);
        $dupStmt->execute();
        $dupRes = $dupStmt->get_result();
        if ($dupRes && $dupRes->num_rows > 0) {
            $error = "Email already in use by another account.";
        }
        $dupStmt->close();
    }

    $image = $_FILES["profile_photo"] ?? null;
    if ($error === "" && $image && isset($image["error"]) && (int)$image["error"] !== UPLOAD_ERR_NO_FILE) {
        $allowedMimeTypes = ["image/jpeg", "image/png"];
        if ((int)$image["error"] !== UPLOAD_ERR_OK) {
            $error = profile_upload_error((int)$image["error"]);
        } elseif (!is_uploaded_file($image["tmp_name"])) {
            $error = "Invalid image upload.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($image["tmp_name"]);
            if (!in_array($mime, $allowedMimeTypes, true)) {
                $error = "Only JPG and PNG images are allowed.";
            } else {
                $ext = $mime === "image/png" ? "png" : "jpg";
                $dir = __DIR__ . "/uploads/profiles";
                if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
                    $error = "Profile upload directory could not be created.";
                } elseif (!is_writable($dir)) {
                    $error = "Profile upload directory is not writable.";
                } else {
                    $photoName = "user_" . $userId . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                    if (!move_uploaded_file($image["tmp_name"], $dir . "/" . $photoName)) {
                        $error = "Failed to save profile photo.";
                    }
                }
            }
        }
    }

    if ($error === "") {
        if ($newPassword !== "") {
            $passHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, password=?, profile_photo=? WHERE id=?");
            $upd->bind_param("ssssi", $fullName, $email, $passHash, $photoName, $userId);
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, profile_photo=? WHERE id=?");
            $upd->bind_param("sssi", $fullName, $email, $photoName, $userId);
        }
        if ($upd->execute()) {
            $_SESSION["user_name"] = $fullName;
            $_SESSION["user_email"] = $email;
            $message = "Profile updated successfully.";
            $user["full_name"] = $fullName;
            $user["email"] = $email;
            $user["profile_photo"] = $photoName;
        } else {
            $error = "Unable to update profile.";
        }
        $upd->close();
    }
    }
}

$photoPath = nestoida_profile_photo_url($user["profile_photo"] ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Profile - Nestoida</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/airbnb.css">
    <link rel="icon" type="image/svg+xml" href="assets/img/nestoida-logo.svg">
</head>
<body class="airbnb-ui font-body bg-slate-50 min-h-screen text-slate-900">
    <header class="sticky top-0 z-40 backdrop-blur bg-white/85 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <h1 class="font-display text-2xl inline-flex items-center gap-2">
                Owner Profile
                <?php if (!empty($user["owner_verified"])) { ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-cyan-200 text-cyan-700 bg-cyan-50">
                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 0a10 10 0 100 20 10 10 0 000-20zm4.2 7.3-4.8 5a1 1 0 01-1.4 0l-2.2-2.3a1 1 0 011.4-1.4l1.5 1.5 4.1-4.2a1 1 0 011.4 1.4z"/></svg>
                        Verified
                    </span>
                <?php } ?>
            </h1>
            <div class="flex gap-2 text-sm">
                <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300">Owner Dashboard</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300">Logout</a>
            </div>
        </div>
    </header>
    <main class="max-w-4xl mx-auto px-6 py-8">
        <?php if ($message !== "") { ?>
            <div class="mb-4 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($message); ?></div>
        <?php } ?>
        <?php if ($error !== "") { ?>
            <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>
        <form method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm space-y-5">
            <?php echo nestoida_csrf_field(); ?>
            <div class="flex items-center gap-4">
                <?php if ($photoPath !== "") { ?>
                    <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Profile Photo" class="w-20 h-20 rounded-full object-cover border border-slate-200">
                <?php } else { ?>
                    <div class="w-20 h-20 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 text-sm">No photo</div>
                <?php } ?>
                <div>
                    <label class="block text-sm font-semibold mb-1">Profile Photo</label>
                    <input type="file" name="profile_photo" class="text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user["full_name"]); ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user["email"]); ?>" required class="w-full border border-slate-300 rounded-xl px-4 py-3">
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">New Password (optional)</label>
                    <input type="password" name="new_password" class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>
            </div>
            <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-full font-semibold">Save Profile</button>
        </form>
    </main>
    <script src="assets/js/back-button.js"></script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
