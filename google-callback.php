<?php
session_start();
include "db.php";
require_once "reset-utils.php";

function google_oauth_error($message)
{
    http_response_code(400);
    echo htmlspecialchars($message);
    exit();
}

if (isset($_GET['error'])) {
    google_oauth_error('Google sign-in was cancelled.');
}

$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['google_oauth_state'] ?? '';
if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
    google_oauth_error('Invalid OAuth state. Please try again.');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    google_oauth_error('Missing authorization code.');
}

$clientId = nestoida_env('GOOGLE_CLIENT_ID', '');
$clientSecret = nestoida_env('GOOGLE_CLIENT_SECRET', '');
$redirectUri = nestoida_env('GOOGLE_REDIRECT_URI', '');
if ($redirectUri === '') {
    $redirectUri = reset_base_url() . '/google-callback.php';
}
if ($clientId === '' || $clientSecret === '') {
    google_oauth_error('Google sign-in is not configured.');
}

$tokenPayload = http_build_query([
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
], '', '&', PHP_QUERY_RFC3986);

$tokenResponse = null;
if (function_exists('curl_init')) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $tokenPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $tokenResponse = curl_exec($ch);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $tokenPayload
        ]
    ]);
    $tokenResponse = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
}

if (!$tokenResponse) {
    google_oauth_error('Unable to fetch Google access token.');
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? '';
if ($accessToken === '') {
    google_oauth_error('Google access token missing.');
}

$userInfo = null;
$userInfoResponse = null;
if (function_exists('curl_init')) {
    $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . $accessToken . "\r\n"
        ]
    ]);
    $userInfoResponse = @file_get_contents('https://openidconnect.googleapis.com/v1/userinfo', false, $context);
}

if ($userInfoResponse) {
    $userInfo = json_decode($userInfoResponse, true);
}

$email = $userInfo['email'] ?? '';
$googleId = $userInfo['sub'] ?? '';
$fullName = $userInfo['name'] ?? '';
$photoUrl = $userInfo['picture'] ?? '';
$emailVerified = !empty($userInfo['email_verified']);

if ($email === '' || $googleId === '') {
    google_oauth_error('Google account info is incomplete.');
}

$role = $_SESSION['google_oauth_role'] ?? 'viewer';
if (!in_array($role, ['owner', 'viewer'], true)) {
    $role = 'viewer';
}

$stmt = $conn->prepare("SELECT id, full_name, email, role, email_verified_at, COALESCE(session_version, 1) AS session_version, google_id, profile_photo FROM users WHERE google_id=? OR email=? LIMIT 1");
$stmt->bind_param('ss', $googleId, $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
$stmt->close();

if ($user) {
    $userId = (int)$user['id'];
    $updateFields = [];
    $updateParams = [];
    $types = '';
    if (empty($user['google_id'])) {
        $updateFields[] = 'google_id=?';
        $updateParams[] = $googleId;
        $types .= 's';
    }
    if ($emailVerified && empty($user['email_verified_at'])) {
        $updateFields[] = 'email_verified_at=NOW()';
    }
    if ($photoUrl !== '' && empty($user['profile_photo'])) {
        $updateFields[] = 'profile_photo=?';
        $updateParams[] = $photoUrl;
        $types .= 's';
    }
    if (!empty($updateFields)) {
        $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id=?';
        $updateParams[] = $userId;
        $types .= 'i';
        $update = $conn->prepare($sql);
        $update->bind_param($types, ...$updateParams);
        $update->execute();
        $update->close();
    }
    $userRole = $user['role'];
    $userName = $user['full_name'] !== '' ? $user['full_name'] : ($fullName !== '' ? $fullName : 'User');
    $userEmail = $user['email'];
    $sessionVersion = (int)$user['session_version'];
} else {
    $userName = $fullName !== '' ? $fullName : 'User';
    $randomPass = bin2hex(random_bytes(16));
    $hash = password_hash($randomPass, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (full_name, email, password, role, google_id, profile_photo, email_verified_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insert->bind_param('ssssss', $userName, $email, $hash, $role, $googleId, $photoUrl);
    if (!$insert->execute()) {
        google_oauth_error('Unable to create account from Google sign-in.');
    }
    $userId = (int)$insert->insert_id;
    $insert->close();
    $userRole = $role;
    $userEmail = $email;
    $sessionVersion = 1;
}

unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_role']);

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $userName;
$_SESSION['user_email'] = $userEmail;
$_SESSION['user_role'] = $userRole;
$_SESSION['user_session_version'] = $sessionVersion;

if ($userRole === 'owner') {
    header('Location: owner-dashboard.php');
} else {
    header('Location: index.php');
}
exit();
