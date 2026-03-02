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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
    $image = $_FILES["image"] ?? null;

    if ($error === "" && $image && isset($image["error"]) && (int)$image["error"] !== UPLOAD_ERR_NO_FILE) {
        $allowedMimeTypes = ["image/jpeg", "image/png"];
        if ((int)$image["error"] !== UPLOAD_ERR_OK) {
            $error = upload_error_message((int)$image["error"]);
        } elseif (!is_uploaded_file($image["tmp_name"])) {
            $error = "Invalid uploaded file.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($image["tmp_name"]);
            if (!in_array($detectedMime, $allowedMimeTypes, true)) {
                $error = "Only JPG and PNG images are allowed.";
            } else {
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
                    $extension = $extensionMap[$detectedMime] ?? "jpg";
                    $imageName = "property_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . "." . $extension;
                    $destinationPath = $uploadDir . "/" . $imageName;
                    if (!move_uploaded_file($image["tmp_name"], $destinationPath)) {
                        $error = "Image upload failed. Please check folder permissions.";
                    }
                }
            }
        }
    }

    if ($error === "") {
        $status = "pending";
        $updateStmt = $conn->prepare("UPDATE properties SET title=?, type=?, seater_option=?, bhk_option=?, latitude=?, longitude=?, map_url=?, rent=?, sector=?, address_line=?, description=?, amenities=?, furnishing=?, available_from=?, phone=?, image=?, status=? WHERE id=? AND owner_user_id=?");
        $updateStmt->bind_param(
            "ssssddsisssssssssii",
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
            $status,
            $id,
            $ownerId
        );
        $updateStmt->execute();
        $updatedRows = $updateStmt->affected_rows;
        $updateStmt->close();

        if ($updatedRows >= 0) {
            header("Location: owner-dashboard.php?updated=1");
            exit();
        }
        $error = "Unable to update listing right now.";
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
                <p class="text-xs text-slate-500 dark:text-slate-300">Any update will be sent to admin for reapproval.</p>
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
                <label class="block text-sm font-semibold mb-1">Replace Listing Image (optional)</label>
                <input type="file" name="image" class="w-full border border-slate-300 rounded-xl px-4 py-3 bg-white focus:outline-none focus:ring-2 focus:ring-cyan-600 dark:bg-slate-900 dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-300 mt-1">Leave blank to keep current image.</p>
            </div>

            <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold hover:bg-cyan-700 transition">Save and Send for Reapproval</button>
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
