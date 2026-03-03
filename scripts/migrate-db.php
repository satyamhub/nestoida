<?php
// One-time migration script. Run from CLI: php scripts/migrate-db.php

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

if ($dbHost === false || $dbUser === false || $dbPass === false || $dbName === false) {
    fwrite(STDERR, "Missing DB env vars. Set DB_HOST/DB_USER/DB_PASS/DB_NAME.\n");
    exit(1);
}

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    fwrite(STDERR, "Connection failed: " . $conn->connect_error . "\n");
    exit(1);
}

try {
    $hasSeater = false;
    $hasBhk = false;
    $hasUserPhoto = false;
    $hasAdminPhoto = false;
    $hasUserSessionVersion = false;
    $hasFeedbackRole = false;
    $hasFeedbackRating = false;
    $hasPropertyLatitude = false;
    $hasPropertyLongitude = false;
    $hasPropertyAddress = false;
    $hasPropertyFurnishing = false;
    $hasPropertyAvailableFrom = false;
    $hasPropertyMapUrl = false;
    $hasOwnerVerified = false;
    $hasPropertyUpdatedAt = false;

    $seaterRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'seater_option'");
    if ($seaterRes && $seaterRes->num_rows > 0) {
        $hasSeater = true;
    }

    $bhkRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'bhk_option'");
    if ($bhkRes && $bhkRes->num_rows > 0) {
        $hasBhk = true;
    }

    $userPhotoRes = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    if ($userPhotoRes && $userPhotoRes->num_rows > 0) {
        $hasUserPhoto = true;
    }

    $adminPhotoRes = $conn->query("SHOW COLUMNS FROM admin LIKE 'profile_photo'");
    if ($adminPhotoRes && $adminPhotoRes->num_rows > 0) {
        $hasAdminPhoto = true;
    }

    $userSessionVersionRes = $conn->query("SHOW COLUMNS FROM users LIKE 'session_version'");
    if ($userSessionVersionRes && $userSessionVersionRes->num_rows > 0) {
        $hasUserSessionVersion = true;
    }

    $feedbackRoleRes = $conn->query("SHOW COLUMNS FROM listing_feedback LIKE 'commenter_role'");
    if ($feedbackRoleRes && $feedbackRoleRes->num_rows > 0) {
        $hasFeedbackRole = true;
    }

    $feedbackRatingRes = $conn->query("SHOW COLUMNS FROM listing_feedback LIKE 'feedback_rating'");
    if ($feedbackRatingRes && $feedbackRatingRes->num_rows > 0) {
        $hasFeedbackRating = true;
    }

    $latitudeRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'latitude'");
    if ($latitudeRes && $latitudeRes->num_rows > 0) {
        $hasPropertyLatitude = true;
    }

    $longitudeRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'longitude'");
    if ($longitudeRes && $longitudeRes->num_rows > 0) {
        $hasPropertyLongitude = true;
    }
    $addressRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'address_line'");
    if ($addressRes && $addressRes->num_rows > 0) {
        $hasPropertyAddress = true;
    }
    $furnishingRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'furnishing'");
    if ($furnishingRes && $furnishingRes->num_rows > 0) {
        $hasPropertyFurnishing = true;
    }
    $availableFromRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'available_from'");
    if ($availableFromRes && $availableFromRes->num_rows > 0) {
        $hasPropertyAvailableFrom = true;
    }
    $mapUrlRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'map_url'");
    if ($mapUrlRes && $mapUrlRes->num_rows > 0) {
        $hasPropertyMapUrl = true;
    }
    $updatedAtRes = $conn->query("SHOW COLUMNS FROM properties LIKE 'updated_at'");
    if ($updatedAtRes && $updatedAtRes->num_rows > 0) {
        $hasPropertyUpdatedAt = true;
    }
    $ownerVerifiedRes = $conn->query("SHOW COLUMNS FROM users LIKE 'owner_verified'");
    if ($ownerVerifiedRes && $ownerVerifiedRes->num_rows > 0) {
        $hasOwnerVerified = true;
    }

    if (!$hasSeater) {
        $conn->query("ALTER TABLE properties ADD COLUMN seater_option VARCHAR(30) NULL AFTER type");
    }
    if (!$hasBhk) {
        $conn->query("ALTER TABLE properties ADD COLUMN bhk_option VARCHAR(30) NULL AFTER seater_option");
    }
    if (!$hasUserPhoto) {
        $conn->query("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER role");
    }
    if (!$hasAdminPhoto) {
        $conn->query("ALTER TABLE admin ADD COLUMN profile_photo VARCHAR(255) NULL AFTER email");
    }
    if (!$hasUserSessionVersion) {
        $conn->query("ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 1 AFTER profile_photo");
    }
    if (!$hasFeedbackRole) {
        $conn->query("ALTER TABLE listing_feedback ADD COLUMN commenter_role VARCHAR(20) NULL AFTER commenter_email");
    }
    if (!$hasFeedbackRating) {
        $conn->query("ALTER TABLE listing_feedback ADD COLUMN feedback_rating TINYINT NULL AFTER commenter_role");
    }
    if (!$hasPropertyLatitude) {
        $conn->query("ALTER TABLE properties ADD COLUMN latitude DECIMAL(10,7) NULL AFTER bhk_option");
    }
    if (!$hasPropertyLongitude) {
        $conn->query("ALTER TABLE properties ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude");
    }
    if (!$hasPropertyAddress) {
        $conn->query("ALTER TABLE properties ADD COLUMN address_line VARCHAR(255) NULL AFTER sector");
    }
    if (!$hasPropertyFurnishing) {
        $conn->query("ALTER TABLE properties ADD COLUMN furnishing VARCHAR(30) NULL AFTER amenities");
    }
    if (!$hasPropertyAvailableFrom) {
        $conn->query("ALTER TABLE properties ADD COLUMN available_from DATE NULL AFTER furnishing");
    }
    if (!$hasPropertyMapUrl) {
        $conn->query("ALTER TABLE properties ADD COLUMN map_url TEXT NULL AFTER longitude");
    }
    if (!$hasPropertyUpdatedAt) {
        $conn->query("ALTER TABLE properties ADD COLUMN updated_at DATETIME NULL AFTER status");
    }
    if (!$hasOwnerVerified) {
        $conn->query("ALTER TABLE users ADD COLUMN owner_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER session_version");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS listing_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            owner_user_id INT NULL,
            user_id INT NULL,
            commenter_name VARCHAR(120) NULL,
            commenter_email VARCHAR(190) NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedback_property (property_id),
            INDEX idx_feedback_owner (owner_user_id),
            INDEX idx_feedback_user (user_id),
            CONSTRAINT fk_feedback_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS user_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            property_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_property (user_id, property_id),
            INDEX idx_favorites_property (property_id),
            CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_favorites_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS listing_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            user_id INT NULL,
            reporter_name VARCHAR(120) NULL,
            reporter_email VARCHAR(190) NULL,
            reason VARCHAR(100) NOT NULL,
            details TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            INDEX idx_reports_property (property_id),
            INDEX idx_reports_status (status),
            CONSTRAINT fk_reports_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS property_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            user_id INT NULL,
            event_type VARCHAR(20) NOT NULL,
            user_agent VARCHAR(255) NULL,
            ip_address VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_events_property_type (property_id, event_type),
            INDEX idx_events_created (created_at),
            CONSTRAINT fk_events_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS property_inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            owner_user_id INT NULL,
            user_id INT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(30) NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_inquiries_property (property_id),
            INDEX idx_inquiries_owner (owner_user_id),
            CONSTRAINT fk_inquiries_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            CONSTRAINT fk_inquiries_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_inquiries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS property_visit_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            owner_user_id INT NULL,
            user_id INT NULL,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(30) NULL,
            visit_date DATE NOT NULL,
            visit_time TIME NOT NULL,
            message TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_visit_owner (owner_user_id),
            INDEX idx_visit_property (property_id),
            CONSTRAINT fk_visit_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
            CONSTRAINT fk_visit_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_visit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS roommate_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            gender VARCHAR(20) NULL,
            budget_min INT NULL,
            budget_max INT NULL,
            sector VARCHAR(120) NULL,
            occupation VARCHAR(120) NULL,
            schedule_pref VARCHAR(120) NULL,
            food_pref VARCHAR(120) NULL,
            smoking_pref VARCHAR(60) NULL,
            bio TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_roommate_user (user_id),
            CONSTRAINT fk_roommate_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS owner_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner_user_id INT NOT NULL,
            doc_type VARCHAR(60) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            remarks VARCHAR(255) NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at DATETIME NULL,
            verified_by_admin VARCHAR(120) NULL,
            INDEX idx_doc_owner (owner_user_id),
            INDEX idx_doc_status (status),
            CONSTRAINT fk_doc_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(140) NOT NULL,
            message VARCHAR(255) NOT NULL,
            url VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notifications_user (user_id),
            INDEX idx_notifications_read (user_id, is_read),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            title VARCHAR(140) NOT NULL,
            message VARCHAR(255) NOT NULL,
            url VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_notifications_admin (admin_id),
            INDEX idx_admin_notifications_read (admin_id, is_read),
            CONSTRAINT fk_admin_notifications_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS property_change_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            changed_by_role VARCHAR(20) NOT NULL,
            changed_by_user_id INT NULL,
            field_name VARCHAR(60) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_change_property (property_id),
            INDEX idx_change_created (created_at),
            CONSTRAINT fk_change_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS property_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            property_id INT NOT NULL,
            image_name VARCHAR(255) NOT NULL,
            is_cover TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            label VARCHAR(80) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_property_images_property (property_id),
            CONSTRAINT fk_property_images_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_email (email),
            INDEX idx_login_ip (ip_address),
            INDEX idx_login_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    try {
        $sortRes = $conn->query("SHOW COLUMNS FROM property_images LIKE 'sort_order'");
        if ($sortRes && $sortRes->num_rows === 0) {
            $conn->query("ALTER TABLE property_images ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_cover");
        }
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $labelRes = $conn->query("SHOW COLUMNS FROM property_images LIKE 'label'");
        if ($labelRes && $labelRes->num_rows === 0) {
            $conn->query("ALTER TABLE property_images ADD COLUMN label VARCHAR(80) NULL AFTER sort_order");
        }
    } catch (Throwable $e) {
        // ignore
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Migrations complete.\n";
