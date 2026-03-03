<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"], $_SESSION["user_role"]) || $_SESSION["user_role"] !== "owner") {
    header("Location: user-login.php");
    exit();
}

$ownerId = (int)$_SESSION["user_id"];
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: owner-dashboard.php");
    exit();
}

$error = "";

function upload_error_message($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Image is too large. Please upload a smaller file.";
        case UPLOAD_ERR_PARTIAL:
            return "Image upload was interrupted. Please try again.";
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

$stmt = $conn->prepare("SELECT p.*, p.TYPE AS type FROM properties p WHERE p.id=? AND p.owner_user_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $ownerId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    header("Location: owner-dashboard.php");
    exit();
}

$existingImages = [];
$existingImagesRes = $conn->prepare("SELECT id, image_name, is_cover, sort_order, label FROM property_images WHERE property_id=? ORDER BY is_cover DESC, sort_order ASC, id ASC");
if ($existingImagesRes) {
    $existingImagesRes->bind_param("i", $id);
    $existingImagesRes->execute();
    $resImgs = $existingImagesRes->get_result();
    if ($resImgs) {
        while ($imgRow = $resImgs->fetch_assoc()) {
            $existingImages[] = $imgRow;
        }
    }
    $existingImagesRes->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!nestoida_csrf_valid()) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
    $title = trim($_POST["title"] ?? "");
    $type = trim($_POST["type"] ?? "");
    $typeLower = strtolower($type);
    $rent = isset($_POST["rent"]) ? (int)$_POST["rent"] : 0;
    $sector = trim($_POST["sector"] ?? "");
    $addressLine = trim((string)($_POST["address_line"] ?? ""));
    $description = trim($_POST["description"] ?? "");
    $amenities = trim($_POST["amenities"] ?? "");
    $furnishing = trim((string)($_POST["furnishing"] ?? ""));
    $availableFromInput = trim((string)($_POST["available_from"] ?? ""));
    $availableFrom = $availableFromInput !== "" ? $availableFromInput : null;
    $phone = trim($_POST["phone"] ?? "");
    $mapUrl = trim((string)($_POST["map_url"] ?? ""));
    $seaterOption = in_array($typeLower, ['pg', 'hostel'], true) ? trim($_POST['seater_option'] ?? '') : '';
    $bhkOption = in_array($typeLower, ['flat', 'apartment'], true) ? trim($_POST['bhk_option'] ?? '') : '';
    $latitudeInput = trim((string)($_POST["latitude"] ?? ""));
    $longitudeInput = trim((string)($_POST["longitude"] ?? ""));
    $latitude = $latitudeInput !== "" ? (float)$latitudeInput : null;
    $longitude = $longitudeInput !== "" ? (float)$longitudeInput : null;

    if ($title === "" || $type === "" || $sector === "" || $phone === "" || $rent <= 0) {
        $error = "Please fill all required fields with valid details.";
    }
    if ($error === "" && ($latitudeInput !== "" || $longitudeInput !== "") && ($latitudeInput === "" || $longitudeInput === "")) {
        $error = "Please provide both latitude and longitude for exact location.";
    }
    if ($error === "" && $latitude !== null && ($latitude < -90 || $latitude > 90)) {
        $error = "Latitude must be between -90 and 90.";
    }
    if ($error === "" && $longitude !== null && ($longitude < -180 || $longitude > 180)) {
        $error = "Longitude must be between -180 and 180.";
    }
    if ($error === "" && $seaterOption === '' && in_array($typeLower, ['pg', 'hostel'], true)) {
        $error = "Please choose seater option for PG/Hostel.";
    }
    if ($error === "" && $bhkOption === '' && in_array($typeLower, ['flat', 'apartment'], true)) {
        $error = "Please choose BHK option for Flat.";
    }

    $imageName = $row["image"];
    $images = $_FILES["images"] ?? null;
    $imagesToRemove = isset($_POST["remove_images"]) && is_array($_POST["remove_images"]) ? array_map("intval", $_POST["remove_images"]) : [];
    $coverImageId = isset($_POST["cover_image_id"]) ? (int)$_POST["cover_image_id"] : 0;
    $imageOrders = isset($_POST["image_order"]) && is_array($_POST["image_order"]) ? $_POST["image_order"] : [];
    $imageLabels = isset($_POST["image_label"]) && is_array($_POST["image_label"]) ? $_POST["image_label"] : [];
    $newImageLabelsRaw = trim((string)($_POST["new_image_labels"] ?? ""));
    $newImageLabels = $newImageLabelsRaw !== '' ? preg_split('/\r\n|\r|\n/', $newImageLabelsRaw) : [];
    $newImageNames = [];

    if ($error === "" && !empty($imagesToRemove)) {
        $removeStmt = $conn->prepare("SELECT id, image_name, is_cover FROM property_images WHERE property_id=? AND id=? LIMIT 1");
        if ($removeStmt) {
            foreach ($imagesToRemove as $imgId) {
                if ($imgId <= 0) {
                    continue;
                }
                $removeStmt->bind_param("ii", $id, $imgId);
                $removeStmt->execute();
                $removeRes = $removeStmt->get_result();
                $removeRow = $removeRes ? $removeRes->fetch_assoc() : null;
                if ($removeRow) {
                    $deleteStmt = $conn->prepare("DELETE FROM property_images WHERE id=? AND property_id=?");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("ii", $imgId, $id);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                    }
                    $filePath = __DIR__ . "/uploads/" . $removeRow["image_name"];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            $removeStmt->close();
        }
    }

    if ($error === "" && $images && isset($images["name"]) && is_array($images["name"])) {
        $allowedMimeTypes = ["image/jpeg", "image/png"];
        $extensionMap = [
            "image/jpeg" => "jpg",
            "image/png" => "png"
        ];
        $uploadDir = __DIR__ . "/uploads";
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            $error = "Upload folder could not be created.";
        } elseif (!is_writable($uploadDir)) {
            $error = "Upload folder is not writable.";
        } else {
            $fileCount = count($images["name"]);
            for ($i = 0; $i < $fileCount; $i++) {
                $fileError = (int)($images["error"][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($fileError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($fileError !== UPLOAD_ERR_OK) {
                    $error = upload_error_message($fileError);
                    break;
                }
                $tmpName = $images["tmp_name"][$i] ?? "";
                if ($tmpName === "" || !is_uploaded_file($tmpName)) {
                    $error = "Invalid uploaded file.";
                    break;
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($tmpName);
                if (!in_array($detectedMime, $allowedMimeTypes, true)) {
                    $error = "Only JPG and PNG images are allowed.";
                    break;
                }
                $extension = $extensionMap[$detectedMime] ?? "jpg";
                $newName = "property_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . "." . $extension;
                $destinationPath = $uploadDir . "/" . $newName;
                if (!move_uploaded_file($tmpName, $destinationPath)) {
                    $error = "Image upload failed. Please check folder permissions.";
                    break;
                }
                $newImageNames[] = $newName;
            }
        }
    }

    if ($error === "") {
        $remainingCount = count($existingImages) - count(array_filter($imagesToRemove)) + count($newImageNames);
        if ($remainingCount <= 0) {
            $error = "Please keep at least one listing image.";
        }
    }

    if ($error === "") {
        $changes = [];
        $oldValues = [
            "title" => (string)($row["title"] ?? ""),
            "type" => (string)($row["type"] ?? ""),
            "seater_option" => (string)($row["seater_option"] ?? ""),
            "bhk_option" => (string)($row["bhk_option"] ?? ""),
            "rent" => (string)($row["rent"] ?? ""),
            "sector" => (string)($row["sector"] ?? ""),
            "address_line" => (string)($row["address_line"] ?? ""),
            "description" => (string)($row["description"] ?? ""),
            "amenities" => (string)($row["amenities"] ?? ""),
            "furnishing" => (string)($row["furnishing"] ?? ""),
            "available_from" => (string)($row["available_from"] ?? ""),
            "phone" => (string)($row["phone"] ?? ""),
            "latitude" => (string)($row["latitude"] ?? ""),
            "longitude" => (string)($row["longitude"] ?? ""),
            "map_url" => (string)($row["map_url"] ?? "")
        ];
        $newValues = [
            "title" => (string)$title,
            "type" => (string)$type,
            "seater_option" => (string)$seaterOption,
            "bhk_option" => (string)$bhkOption,
            "rent" => (string)$rent,
            "sector" => (string)$sector,
            "address_line" => (string)$addressLine,
            "description" => (string)$description,
            "amenities" => (string)$amenities,
            "furnishing" => (string)$furnishing,
            "available_from" => (string)$availableFrom,
            "phone" => (string)$phone,
            "latitude" => $latitude !== null ? (string)$latitude : "",
            "longitude" => $longitude !== null ? (string)$longitude : "",
            "map_url" => (string)$mapUrl
        ];
        foreach ($newValues as $field => $value) {
            $old = $oldValues[$field] ?? "";
            if (trim($old) !== trim($value)) {
                $changes[] = [$field, $old, $value];
            }
        }

        $updateStmt = $conn->prepare("UPDATE properties SET title=?, type=?, seater_option=?, bhk_option=?, latitude=?, longitude=?, map_url=?, rent=?, sector=?, address_line=?, description=?, amenities=?, furnishing=?, available_from=?, phone=?, image=?, updated_at=NOW() WHERE id=? AND owner_user_id=?");
        $updateStmt->bind_param(
            "ssssddsissssssssii",
            $title,
            $type,
            $seaterOption,
            $bhkOption,
            $latitude,
            $longitude,
            $mapUrl,
            $rent,
            $sector,
            $addressLine,
            $description,
            $amenities,
            $furnishing,
            $availableFrom,
            $phone,
            $imageName,
            $id,
            $ownerId
        );
        $updateStmt->execute();
        $updatedRows = $updateStmt->affected_rows;
        $updateStmt->close();

        if ($updatedRows >= 0) {
            if (!empty($newImageNames)) {
                $maxOrder = 0;
                foreach ($existingImages as $imgRow) {
                    $maxOrder = max($maxOrder, (int)($imgRow["sort_order"] ?? 0));
                }
                $imgStmt = $conn->prepare("INSERT INTO property_images (property_id, image_name, is_cover, sort_order, label) VALUES (?, ?, ?, ?, ?)");
                if ($imgStmt) {
                    foreach ($newImageNames as $idx => $imgName) {
                        $isCover = 0;
                        $maxOrder++;
                        $label = isset($newImageLabels[$idx]) ? trim((string)$newImageLabels[$idx]) : "";
                        $imgStmt->bind_param("isiis", $id, $imgName, $isCover, $maxOrder, $label);
                        $imgStmt->execute();
                    }
                    $imgStmt->close();
                }
            }
            if (!empty($imageOrders)) {
                $orderStmt = $conn->prepare("UPDATE property_images SET sort_order=? WHERE id=? AND property_id=?");
                if ($orderStmt) {
                    foreach ($imageOrders as $imgId => $orderVal) {
                        $imgId = (int)$imgId;
                        $orderVal = (int)$orderVal;
                        if ($imgId <= 0) {
                            continue;
                        }
                        $orderStmt->bind_param("iii", $orderVal, $imgId, $id);
                        $orderStmt->execute();
                    }
                    $orderStmt->close();
                }
            }
            if (!empty($imageLabels)) {
                $labelStmt = $conn->prepare("UPDATE property_images SET label=? WHERE id=? AND property_id=?");
                if ($labelStmt) {
                    foreach ($imageLabels as $imgId => $labelVal) {
                        $imgId = (int)$imgId;
                        if ($imgId <= 0) {
                            continue;
                        }
                        $labelVal = trim((string)$labelVal);
                        $labelStmt->bind_param("sii", $labelVal, $imgId, $id);
                        $labelStmt->execute();
                    }
                    $labelStmt->close();
                }
            }
            if ($coverImageId > 0) {
                $resetCover = $conn->prepare("UPDATE property_images SET is_cover=0 WHERE property_id=?");
                if ($resetCover) {
                    $resetCover->bind_param("i", $id);
                    $resetCover->execute();
                    $resetCover->close();
                }
                $setCover = $conn->prepare("UPDATE property_images SET is_cover=1 WHERE property_id=? AND id=?");
                if ($setCover) {
                    $setCover->bind_param("ii", $id, $coverImageId);
                    $setCover->execute();
                    $setCover->close();
                }
            }
            $coverStmt = $conn->prepare("SELECT image_name FROM property_images WHERE property_id=? ORDER BY is_cover DESC, sort_order ASC, id ASC LIMIT 1");
            if ($coverStmt) {
                $coverStmt->bind_param("i", $id);
                $coverStmt->execute();
                $coverRes = $coverStmt->get_result();
                $coverRow = $coverRes ? $coverRes->fetch_assoc() : null;
                if ($coverRow && !empty($coverRow["image_name"])) {
                    $newCover = $coverRow["image_name"];
                    $coverUpdate = $conn->prepare("UPDATE properties SET image=? WHERE id=? AND owner_user_id=?");
                    if ($coverUpdate) {
                        $coverUpdate->bind_param("sii", $newCover, $id, $ownerId);
                        $coverUpdate->execute();
                        $coverUpdate->close();
                    }
                }
                $coverStmt->close();
            }
            if (!empty($changes)) {
                $logStmt = $conn->prepare("INSERT INTO property_change_log (property_id, changed_by_role, changed_by_user_id, field_name, old_value, new_value) VALUES (?, 'owner', ?, ?, ?, ?)");
                if ($logStmt) {
                    foreach ($changes as $change) {
                        [$field, $oldVal, $newVal] = $change;
                        $logStmt->bind_param("iisss", $id, $ownerId, $field, $oldVal, $newVal);
                        $logStmt->execute();
                    }
                    $logStmt->close();
                }
            }
            $adminNotice = $conn->prepare("
                INSERT INTO admin_notifications (admin_id, title, message, url)
                SELECT a.id, 'Owner updated listing', ?, ?
                FROM admin a
            ");
            if ($adminNotice) {
                $adminMessage = "Listing #" . $id . " was updated by the owner.";
                $adminUrl = "edit-property.php?id=" . $id;
                $adminNotice->bind_param("ss", $adminMessage, $adminUrl);
                $adminNotice->execute();
                $adminNotice->close();
            }
        }

        if ($updatedRows >= 0) {
            header("Location: owner-dashboard.php?updated=1");
            exit();
        }
        $error = "Unable to update listing right now.";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Listing - Nestoida</title>
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
            darkMode: "class",
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Space Grotesk"', "sans-serif"],
                        body: ['"Manrope"', "sans-serif"]
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
                <h1 class="font-display text-2xl">Edit Listing</h1>
                <p class="text-xs text-slate-500 dark:text-slate-300">Updates apply immediately to your listing.</p>
            </div>
            <div class="flex gap-2 text-sm">
                <button id="theme-toggle" type="button" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">
                    <span id="theme-toggle-label">Dark</span>
                </button>
                <a href="owner-dashboard.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Owner Dashboard</a>
                <a href="logout.php" class="px-3 py-2 rounded-full border border-slate-300 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-400 transition">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-8">
        <?php if ($error !== "") { ?>
            <div class="mb-4 border border-rose-200 bg-rose-50 text-rose-700 rounded-xl p-3 text-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <form method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-sm space-y-5 dark:bg-slate-900 dark:border-slate-800">
            <?php echo nestoida_csrf_field(); ?>
            <div>
                <label class="block text-sm font-semibold mb-1">Property Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($row["title"]); ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700" required>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Type</label>
                    <select id="type" name="type" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700" required>
                        <?php
                        $typeValue = strtolower((string)$row["type"]);
                        $typeOptions = ["PG", "Hostel", "Flat", "Co-living"];
                        foreach ($typeOptions as $opt) {
                            $selected = $typeValue === strtolower($opt) ? "selected" : "";
                            echo '<option value="' . htmlspecialchars($opt) . '" ' . $selected . '>' . htmlspecialchars($opt) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Monthly Rent</label>
                    <input type="number" name="rent" value="<?php echo (int)$row["rent"]; ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700" required>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div id="seater-wrap" class="hidden">
                    <label class="block text-sm font-semibold mb-1">Seater Option (PG/Hostel)</label>
                    <select id="seater_option" name="seater_option" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <?php
                        $seaterValue = (string)($row["seater_option"] ?? "");
                        $seaterOptions = ["", "1 Seater", "2 Seater", "3 Seater", "4 Seater"];
                        foreach ($seaterOptions as $opt) {
                            $label = $opt === "" ? "Select seater" : $opt;
                            $selected = $seaterValue === $opt ? "selected" : "";
                            echo '<option value="' . htmlspecialchars($opt) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div id="bhk-wrap" class="hidden">
                    <label class="block text-sm font-semibold mb-1">Flat Configuration</label>
                    <select id="bhk_option" name="bhk_option" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                        <?php
                        $bhkValue = (string)($row["bhk_option"] ?? "");
                        $bhkOptions = ["", "1 RK", "1 BHK", "2 BHK", "3 BHK", "4 BHK"];
                        foreach ($bhkOptions as $opt) {
                            $label = $opt === "" ? "Select BHK" : $opt;
                            $selected = $bhkValue === $opt ? "selected" : "";
                            echo '<option value="' . htmlspecialchars($opt) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Sector</label>
                    <input type="text" name="sector" value="<?php echo htmlspecialchars($row["sector"]); ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Address</label>
                    <input type="text" name="address_line" value="<?php echo htmlspecialchars((string)($row["address_line"] ?? "")); ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Contact Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($row["phone"]); ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Available From</label>
                    <input type="date" name="available_from" value="<?php echo !empty($row["available_from"]) ? htmlspecialchars((string)$row["available_from"]) : ""; ?>" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50 dark:bg-slate-800/60 dark:border-slate-700">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-semibold">Google Map Location (Optional)</h3>
                    <button id="detect-location" type="button" class="px-3 py-1.5 rounded-full border border-slate-300 text-sm">Use Current Location</button>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-semibold mb-1">Google Maps Link</label>
                    <input id="maps_url" name="map_url" type="text" value="<?php echo htmlspecialchars((string)($row["map_url"] ?? "")); ?>" placeholder="Paste Google Maps URL to auto-fill coordinates" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                </div>
                <div class="grid md:grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Latitude</label>
                        <input id="latitude" type="text" name="latitude" value="<?php echo isset($row["latitude"]) && $row["latitude"] !== null ? htmlspecialchars((string)$row["latitude"]) : ""; ?>" placeholder="28.6139" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Longitude</label>
                        <input id="longitude" type="text" name="longitude" value="<?php echo isset($row["longitude"]) && $row["longitude"] !== null ? htmlspecialchars((string)$row["longitude"]) : ""; ?>" placeholder="77.2090" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                    </div>
                </div>
                <p id="geo-status" class="mt-2 text-xs text-slate-500 dark:text-slate-300">Add coordinates for exact map pin. Otherwise map is based on title + sector.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Description</label>
                <textarea name="description" rows="4" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700"><?php echo htmlspecialchars($row["description"]); ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Amenities</label>
                <textarea name="amenities" rows="3" class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-cyan-600 bg-white dark:bg-slate-900 dark:border-slate-700"><?php echo htmlspecialchars($row["amenities"]); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Furnishing</label>
                <?php $currentFurnishing = (string)($row["furnishing"] ?? ""); ?>
                <select name="furnishing" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white dark:bg-slate-900 dark:border-slate-700">
                    <option value="" <?php echo $currentFurnishing === "" ? "selected" : ""; ?>>Select furnishing</option>
                    <option value="Fully Furnished" <?php echo $currentFurnishing === "Fully Furnished" ? "selected" : ""; ?>>Fully Furnished</option>
                    <option value="Semi Furnished" <?php echo $currentFurnishing === "Semi Furnished" ? "selected" : ""; ?>>Semi Furnished</option>
                    <option value="Unfurnished" <?php echo $currentFurnishing === "Unfurnished" ? "selected" : ""; ?>>Unfurnished</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Listing Images</label>
                <?php if (!empty($existingImages)) { ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
                        <?php foreach ($existingImages as $img) { ?>
                            <label class="rounded-xl border border-slate-200 p-2 bg-white dark:bg-slate-900 dark:border-slate-700">
                                <img src="uploads/<?php echo htmlspecialchars((string)$img["image_name"]); ?>" alt="Listing image" class="w-full h-28 object-cover rounded-lg">
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span class="text-slate-500"><?php echo (int)$img["is_cover"] === 1 ? "Cover" : "Image"; ?></span>
                                    <span class="flex items-center gap-1">
                                        <input type="checkbox" name="remove_images[]" value="<?php echo (int)$img["id"]; ?>" class="rounded">
                                        Remove
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <label class="inline-flex items-center gap-1">
                                        <input type="radio" name="cover_image_id" value="<?php echo (int)$img["id"]; ?>" <?php echo (int)$img["is_cover"] === 1 ? "checked" : ""; ?>>
                                        Cover
                                    </label>
                                    <input type="number" name="image_order[<?php echo (int)$img["id"]; ?>]" value="<?php echo (int)($img["sort_order"] ?? 0); ?>" class="w-16 border border-slate-200 rounded px-2 py-1 text-xs" title="Order">
                                </div>
                                <div class="mt-2">
                                    <input type="text" name="image_label[<?php echo (int)$img["id"]; ?>]" value="<?php echo htmlspecialchars((string)($img["label"] ?? "")); ?>" placeholder="Tag (e.g. Bedroom)" class="w-full border border-slate-200 rounded px-2 py-1 text-xs">
                                </div>
                            </label>
                        <?php } ?>
                    </div>
                <?php } ?>
                <input type="file" name="images[]" multiple class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:bg-slate-900 dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-300 mt-1">Upload more images. Select cover and order above.</p>
                <textarea name="new_image_labels" rows="2" placeholder="Tags for new images (one per line)" class="mt-2 w-full border border-slate-200 rounded px-3 py-2 text-xs bg-white dark:bg-slate-900 dark:border-slate-700"></textarea>
            </div>

            <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Save Changes</button>
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
            const detectLocationBtn = document.getElementById("detect-location");
            const mapsUrlInput = document.getElementById("maps_url");
            const latitudeInput = document.getElementById("latitude");
            const longitudeInput = document.getElementById("longitude");
            const geoStatus = document.getElementById("geo-status");

            function fillFromMapsUrl(raw) {
                const value = (raw || "").trim();
                if (!value) return false;
                const decoded = decodeURIComponent(value);
                const patterns = [
                    /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
                    /[?&]q=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
                    /[?&]ll=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
                    /[?&]center=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/
                ];
                for (const pattern of patterns) {
                    const match = decoded.match(pattern);
                    if (!match) continue;
                    const lat = parseFloat(match[1]);
                    const lng = parseFloat(match[2]);
                    if (Number.isFinite(lat) && Number.isFinite(lng)) {
                        if (latitudeInput) latitudeInput.value = lat.toFixed(7);
                        if (longitudeInput) longitudeInput.value = lng.toFixed(7);
                        if (geoStatus) geoStatus.textContent = "Coordinates extracted from Google Maps link.";
                        return true;
                    }
                }
                return false;
            }

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
            if (detectLocationBtn) {
                detectLocationBtn.addEventListener("click", function () {
                    if (!navigator.geolocation) {
                        if (geoStatus) geoStatus.textContent = "Geolocation not supported in this browser.";
                        return;
                    }
                    if (geoStatus) geoStatus.textContent = "Detecting location...";
                    navigator.geolocation.getCurrentPosition(function (pos) {
                        const lat = pos.coords.latitude.toFixed(7);
                        const lng = pos.coords.longitude.toFixed(7);
                        if (latitudeInput) latitudeInput.value = lat;
                        if (longitudeInput) longitudeInput.value = lng;
                        if (geoStatus) geoStatus.textContent = "Location captured successfully.";
                    }, function () {
                        if (geoStatus) geoStatus.textContent = "Could not detect location. Please enter coordinates manually.";
                    }, { enableHighAccuracy: true, timeout: 12000 });
                });
            }
            if (mapsUrlInput) {
                mapsUrlInput.addEventListener("change", function () {
                    if (!fillFromMapsUrl(mapsUrlInput.value) && geoStatus) {
                        geoStatus.textContent = "Could not read coordinates from URL. Enter latitude/longitude manually.";
                    }
                });
            }
        })();
    </script>
    <script src="assets/js/back-button.js"></script>
    <script src="assets/js/nestoida-loader.js"></script>
    <script src="assets/js/mobile-bottom-nav.js"></script>
</body>
</html>
