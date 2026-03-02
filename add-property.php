<?php
session_start();
$isAdmin = isset($_SESSION['admin']);
$isOwner = isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'owner';

if (!$isAdmin && !$isOwner) {
    header("Location: user-login.php");
    exit();
}

include "db.php";
$message = "";
$error = "";

function upload_error_message($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Image is too large. Please upload a smaller file.";
        case UPLOAD_ERR_PARTIAL:
            return "Image upload was interrupted. Please try again.";
        case UPLOAD_ERR_NO_FILE:
            return "Please select an image file.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Server upload temp folder is missing.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Server cannot write uploaded file.";
        case UPLOAD_ERR_EXTENSION:
            return "Upload blocked by server extension.";
        default:
            return "Image upload failed.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $type = trim($_POST['type']);
    $typeLower = strtolower($type);
    $rent = $_POST['rent'];
    $sector = $_POST['sector'];
    $description = $_POST['description'];
    $amenities = $_POST['amenities'];
    $phone = $_POST['phone'];
    $seaterOption = in_array($typeLower, ['pg', 'hostel'], true) ? trim($_POST['seater_option'] ?? '') : '';
    $bhkOption = in_array($typeLower, ['flat', 'apartment'], true) ? trim($_POST['bhk_option'] ?? '') : '';

    if ($seaterOption === '' && in_array($typeLower, ['pg', 'hostel'], true)) {
        $error = "Please choose seater option for PG/Hostel.";
    }
    if ($bhkOption === '' && in_array($typeLower, ['flat', 'apartment'], true)) {
        $error = "Please choose BHK option for Flat.";
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png'];
    $image = $_FILES['image'] ?? null;

    if (!$image || !isset($image['error'])) {
        $error = "Image upload data is missing.";
    } elseif ((int)$image['error'] !== UPLOAD_ERR_OK) {
        $error = upload_error_message((int)$image['error']);
    } elseif (!is_uploaded_file($image['tmp_name'])) {
        $error = "Invalid uploaded file.";
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($image['tmp_name']);
        if (!in_array($detectedMime, $allowedMimeTypes, true)) {
            $error = "Only JPG and PNG images are allowed.";
        }
    }

    if ($error === "") {
        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];

        $uploadDir = __DIR__ . "/uploads";
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            $error = "Upload folder could not be created.";
        } elseif (!is_writable($uploadDir)) {
            $error = "Upload folder is not writable.";
        } else {
            $extension = $extensionMap[$detectedMime] ?? 'jpg';
            $imageName = "property_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . "." . $extension;
            $destinationPath = $uploadDir . "/" . $imageName;

            if (move_uploaded_file($image['tmp_name'], $destinationPath)) {
                $status = $isAdmin ? "approved" : "pending";
                $ownerUserId = $isOwner ? (int)$_SESSION['user_id'] : null;

                if ($ownerUserId !== null) {
                    $stmt = $conn->prepare("INSERT INTO properties (owner_user_id, title, type, seater_option, bhk_option, rent, sector, description, amenities, image, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssissssss", $ownerUserId, $title, $type, $seaterOption, $bhkOption, $rent, $sector, $description, $amenities, $imageName, $phone, $status);
                } else {
                    $stmt = $conn->prepare("INSERT INTO properties (owner_user_id, title, type, seater_option, bhk_option, rent, sector, description, amenities, image, phone, status) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssissssss", $title, $type, $seaterOption, $bhkOption, $rent, $sector, $description, $amenities, $imageName, $phone, $status);
                }

                if ($stmt->execute()) {
                    $message = $isAdmin ? "Property added and approved." : "Property submitted. It will be reviewed by admin.";
                } else {
                    $error = "Could not save property.";
                }
                $stmt->close();
            } else {
                $error = "Image upload failed. Please check folder permissions.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Nestoida</title>
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
        <div class="max-w-4xl mx-auto px-6 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl">Add New Property</h1>
                <p class="text-xs text-slate-500 dark:text-slate-300">Submit listing for moderation and publishing</p>
            </div>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <?php if ($isAdmin) { ?>
                    <a href="dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Dashboard</a>
                <?php } else { ?>
                    <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Owner Panel</a>
                <?php } ?>
                <a href="index.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Home</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
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

        <form method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm space-y-5 dark:bg-slate-900 dark:border-slate-800">
            <div>
                <label class="block text-sm font-semibold mb-1">Property Title</label>
                <input type="text" name="title" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Type</label>
                    <select id="type" name="type" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <option value="">Select type</option>
                        <option value="PG">PG</option>
                        <option value="Hostel">Hostel</option>
                        <option value="Flat">Flat</option>
                        <option value="Co-living">Co-living</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Monthly Rent</label>
                    <input type="number" name="rent" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div id="seater-wrap" class="hidden">
                    <label class="block text-sm font-semibold mb-1">Seater Option (PG/Hostel)</label>
                    <select id="seater_option" name="seater_option" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <option value="">Select seater</option>
                        <option value="1 Seater">1 Seater</option>
                        <option value="2 Seater">2 Seater</option>
                        <option value="3 Seater">3 Seater</option>
                        <option value="4 Seater">4 Seater</option>
                    </select>
                </div>
                <div id="bhk-wrap" class="hidden">
                    <label class="block text-sm font-semibold mb-1">Flat Configuration</label>
                    <select id="bhk_option" name="bhk_option" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <option value="">Select BHK</option>
                        <option value="1 RK">1 RK</option>
                        <option value="1 BHK">1 BHK</option>
                        <option value="2 BHK">2 BHK</option>
                        <option value="3 BHK">3 BHK</option>
                        <option value="4 BHK">4 BHK</option>
                    </select>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Sector</label>
                    <input type="text" name="sector" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Contact Phone</label>
                    <input type="text" name="phone" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Description</label>
                <textarea name="description" rows="4" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Amenities</label>
                <textarea name="amenities" rows="3" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Listing Image</label>
                <input type="file" name="image" required class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:bg-slate-900 dark:border-slate-700">
            </div>

            <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Submit Listing</button>
        </form>
    </main>
    <script>
        (function () {
            const btn = document.getElementById("theme-toggle");
            const label = document.getElementById("theme-toggle-label");
            const typeInput = document.getElementById("type");
            const seaterWrap = document.getElementById("seater-wrap");
            const bhkWrap = document.getElementById("bhk-wrap");
            const seaterInput = document.getElementById("seater_option");
            const bhkInput = document.getElementById("bhk_option");

            function syncTypeFields() {
                if (!typeInput || !seaterWrap || !bhkWrap) return;
                const v = (typeInput.value || "").toLowerCase();
                const pgOrHostel = v === "pg" || v === "hostel";
                const flat = v === "flat" || v === "apartment";

                seaterWrap.classList.toggle("hidden", !pgOrHostel);
                bhkWrap.classList.toggle("hidden", !flat);
                if (seaterInput) seaterInput.required = pgOrHostel;
                if (bhkInput) bhkInput.required = flat;
            }

            function syncThemeLabel() {
                if (!label) return;
                label.textContent = document.documentElement.classList.contains("dark") ? "Light" : "Dark";
            }
            syncThemeLabel();
            syncTypeFields();
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
            if (typeInput) {
                typeInput.addEventListener("change", syncTypeFields);
            }
        })();
    </script>
    <script src="assets/js/back-button.js"></script>
</body>
</html>
