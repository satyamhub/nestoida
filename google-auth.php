<?php
session_start();
require_once "reset-utils.php";

$clientId = nestoida_env('GOOGLE_CLIENT_ID', '');
$clientSecret = nestoida_env('GOOGLE_CLIENT_SECRET', '');
$redirectUri = nestoida_env('GOOGLE_REDIRECT_URI', '');
if ($redirectUri === '') {
    $redirectUri = reset_base_url() . '/google-callback.php';
}

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    echo "Google sign-in is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.";
    exit();
}

$role = $_GET['role'] ?? 'viewer';
if (!in_array($role, ['owner', 'viewer'], true)) {
    $role = 'viewer';
}
$_SESSION['google_oauth_role'] = $role;
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));

$authParams = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['google_oauth_state'],
    'access_type' => 'online',
    'prompt' => 'select_account'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($authParams, '', '&', PHP_QUERY_RFC3986);
header('Location: ' . $authUrl);
exit();
