<?php

$NESTOIDA_MAIL_LAST_ERROR = '';
$NESTOIDA_HTACCESS_ENV_CACHE = null;

function nestoida_load_htaccess_env()
{
    if ($GLOBALS['NESTOIDA_HTACCESS_ENV_CACHE'] !== null) {
        return $GLOBALS['NESTOIDA_HTACCESS_ENV_CACHE'];
    }

    $env = [];
    $htaccessPath = __DIR__ . '/.htaccess';
    if (!is_readable($htaccessPath)) {
        $GLOBALS['NESTOIDA_HTACCESS_ENV_CACHE'] = $env;
        return $env;
    }

    $lines = file($htaccessPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        $GLOBALS['NESTOIDA_HTACCESS_ENV_CACHE'] = $env;
        return $env;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (stripos($line, 'SetEnv ') !== 0) {
            continue;
        }

        $parts = preg_split('/\s+/', $line, 3);
        if (!is_array($parts) || count($parts) < 3) {
            continue;
        }

        $key = trim($parts[1]);
        $value = trim($parts[2]);

        if ($key === '') {
            continue;
        }

        // Remove optional wrapping quotes.
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $env[$key] = $value;
    }

    $GLOBALS['NESTOIDA_HTACCESS_ENV_CACHE'] = $env;
    return $env;
}

function nestoida_env($key, $default = '')
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    if (function_exists('apache_getenv')) {
        $apacheValue = apache_getenv($key, true);
        if ($apacheValue !== false && $apacheValue !== '') {
            return $apacheValue;
        }
    }

    $htaccessEnv = nestoida_load_htaccess_env();
    if (isset($htaccessEnv[$key]) && $htaccessEnv[$key] !== '') {
        return $htaccessEnv[$key];
    }

    return $default;
}

function smtp_read_response($socket)
{
    $response = "";
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_set_last_error($message)
{
    $GLOBALS['NESTOIDA_MAIL_LAST_ERROR'] = (string)$message;
}

function smtp_get_last_error()
{
    return $GLOBALS['NESTOIDA_MAIL_LAST_ERROR'] ?? '';
}

function smtp_send_command($socket, $command, $expectCodes, &$lastResponse = null)
{
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }
    $response = smtp_read_response($socket);
    $lastResponse = $response;
    if ($response === "") {
        return false;
    }

    $code = (int)substr($response, 0, 3);
    return in_array($code, $expectCodes, true);
}

function smtp_send_mail_attempt($toEmail, $subject, $body, $host, $port, $secure, $user, $pass, $fromEmail, $fromName)
{
    $connectHost = ($secure === 'ssl') ? "ssl://{$host}" : $host;
    $socket = @stream_socket_client($connectHost . ":" . $port, $errno, $errstr, 20);
    if (!$socket) {
        smtp_set_last_error("Connection failed: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, 20);

    $response = '';

    if (!smtp_send_command($socket, null, [220], $response)) {
        smtp_set_last_error("SMTP greeting failed: " . trim($response));
        fclose($socket);
        return false;
    }
    if (!smtp_send_command($socket, "EHLO nestoida.local", [250], $response)) {
        smtp_set_last_error("EHLO failed: " . trim($response));
        fclose($socket);
        return false;
    }

    if ($secure === 'tls') {
        if (!smtp_send_command($socket, "STARTTLS", [220], $response)) {
            smtp_set_last_error("STARTTLS failed: " . trim($response));
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            smtp_set_last_error("TLS crypto negotiation failed.");
            fclose($socket);
            return false;
        }
        if (!smtp_send_command($socket, "EHLO nestoida.local", [250], $response)) {
            smtp_set_last_error("EHLO after STARTTLS failed: " . trim($response));
            fclose($socket);
            return false;
        }
    }

    if (!smtp_send_command($socket, "AUTH LOGIN", [334], $response)) {
        smtp_set_last_error("AUTH LOGIN failed: " . trim($response));
        fclose($socket);
        return false;
    }
    if (!smtp_send_command($socket, base64_encode($user), [334], $response)) {
        smtp_set_last_error("SMTP username rejected: " . trim($response));
        fclose($socket);
        return false;
    }
    if (!smtp_send_command($socket, base64_encode($pass), [235], $response)) {
        smtp_set_last_error("SMTP password rejected: " . trim($response));
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, "MAIL FROM:<{$fromEmail}>", [250], $response)) {
        smtp_set_last_error("MAIL FROM failed: " . trim($response));
        fclose($socket);
        return false;
    }
    if (!smtp_send_command($socket, "RCPT TO:<{$toEmail}>", [250, 251], $response)) {
        smtp_set_last_error("RCPT TO failed: " . trim($response));
        fclose($socket);
        return false;
    }
    if (!smtp_send_command($socket, "DATA", [354], $response)) {
        smtp_set_last_error("DATA command failed: " . trim($response));
        fclose($socket);
        return false;
    }

    $headers = [];
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    $headers[] = "To: <{$toEmail}>";
    $headers[] = "Subject: {$subject}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";
    $headers[] = "";

    $message = implode("\r\n", $headers) . "\r\n" . str_replace(["\r\n.\r\n", "\n.\n"], ["\r\n..\r\n", "\n..\n"], $body);
    fwrite($socket, $message . "\r\n.\r\n");

    if (!smtp_send_command($socket, null, [250], $response)) {
        smtp_set_last_error("Message rejected: " . trim($response));
        fclose($socket);
        return false;
    }

    smtp_send_command($socket, "QUIT", [221]);
    fclose($socket);
    smtp_set_last_error('');
    return true;
}

function smtp_send_mail($toEmail, $subject, $body)
{
    $host = nestoida_env('SMTP_HOST', '');
    $port = (int)nestoida_env('SMTP_PORT', 587);
    $user = nestoida_env('SMTP_USER', '');
    $pass = nestoida_env('SMTP_PASS', '');
    $secure = strtolower(nestoida_env('SMTP_SECURE', 'tls'));
    $fromEmail = nestoida_env('SMTP_FROM_EMAIL', $user);
    $fromName = nestoida_env('SMTP_FROM_NAME', 'Nestoida');

    // Gmail app passwords are often copied with spaces; remove them safely.
    $pass = str_replace(' ', '', trim((string)$pass));

    if ($host === '' || $user === '' || $pass === '' || $fromEmail === '') {
        smtp_set_last_error('SMTP env vars missing. Check SMTP_HOST/USER/PASS/FROM_EMAIL.');
        return false;
    }

    if (smtp_send_mail_attempt($toEmail, $subject, $body, $host, $port, $secure, $user, $pass, $fromEmail, $fromName)) {
        return true;
    }

    // Gmail fallback: if 587/TLS path fails, try 465/SSL automatically.
    if ($host === 'smtp.gmail.com' && !($port === 465 && $secure === 'ssl')) {
        if (smtp_send_mail_attempt($toEmail, $subject, $body, $host, 465, 'ssl', $user, $pass, $fromEmail, $fromName)) {
            return true;
        }
    }

    return false;
}

function nestoida_send_email($toEmail, $subject, $body)
{
    // Prefer explicit SMTP (e.g., Gmail), fallback to local mail() transport.
    if (smtp_send_mail($toEmail, $subject, $body)) {
        return true;
    }

    $fromEmail = nestoida_env('RESET_FROM_EMAIL', nestoida_env('SMTP_FROM_EMAIL', nestoida_env('SMTP_USER', 'no-reply@nestoida.local')));
    $headers = "From: Nestoida <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    return @mail($toEmail, $subject, $body, $headers);
}

function reset_requester_hash()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function reset_base_url()
{
    $appUrl = nestoida_env('APP_URL', '');
    if ($appUrl) {
        return rtrim($appUrl, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

function reset_is_rate_limited($conn, $accountType, $email, $requesterHash)
{
    $emailCount = 0;
    $requesterCount = 0;

    $emailStmt = $conn->prepare("SELECT COUNT(*) AS total FROM password_resets WHERE account_type=? AND email=? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $emailStmt->bind_param("ss", $accountType, $email);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    if ($emailResult) {
        $emailCount = (int)$emailResult->fetch_assoc()['total'];
    }
    $emailStmt->close();

    $requesterStmt = $conn->prepare("SELECT COUNT(*) AS total FROM password_resets WHERE account_type=? AND requester_hash=? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $requesterStmt->bind_param("ss", $accountType, $requesterHash);
    $requesterStmt->execute();
    $requesterResult = $requesterStmt->get_result();
    if ($requesterResult) {
        $requesterCount = (int)$requesterResult->fetch_assoc()['total'];
    }
    $requesterStmt->close();

    // Per-email and per-requester hourly caps.
    return ($emailCount >= 3 || $requesterCount >= 10);
}

function reset_create_token($conn, $accountType, $email, $requesterHash)
{
    $cleanup = $conn->prepare("DELETE FROM password_resets WHERE (account_type=? AND email=?) OR expires_at < NOW() OR used_at IS NOT NULL");
    $cleanup->bind_param("ss", $accountType, $email);
    $cleanup->execute();
    $cleanup->close();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $resetTtl = (int)nestoida_env('RESET_TOKEN_TTL_SECONDS', 1800);
    if ($resetTtl < 300) {
        $resetTtl = 300;
    }
    $expiresAt = date('Y-m-d H:i:s', time() + $resetTtl);

    $insert = $conn->prepare("INSERT INTO password_resets (account_type, email, requester_hash, token_hash, expires_at) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("sssss", $accountType, $email, $requesterHash, $tokenHash, $expiresAt);

    if (!$insert->execute()) {
        $insert->close();
        return null;
    }

    $insert->close();
    return $token;
}

function reset_verify_token($conn, $accountType, $email, $token)
{
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id FROM password_resets WHERE account_type=? AND email=? AND token_hash=? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("sss", $accountType, $email, $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();

    $resetId = null;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $resetId = (int)$row['id'];
    }

    $stmt->close();
    return $resetId;
}

function reset_mark_used($conn, $resetId, $accountType, $email)
{
    $markUsed = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?");
    $markUsed->bind_param("i", $resetId);
    $markUsed->execute();
    $markUsed->close();

    $expireOthers = $conn->prepare("UPDATE password_resets SET used_at=NOW() WHERE account_type=? AND email=? AND used_at IS NULL");
    $expireOthers->bind_param("ss", $accountType, $email);
    $expireOthers->execute();
    $expireOthers->close();
}

function reset_send_email($toEmail, $resetLink, $accountType)
{
    $subject = ($accountType === 'admin') ? 'Nestoida Admin Password Reset' : 'Nestoida Password Reset';

    $body = "Hello,\n\n";
    $body .= "A password reset was requested for your Nestoida " . ($accountType === 'admin' ? 'admin' : 'user') . " account.\n";
    $body .= "If this was you, click the link below (valid for 30 minutes):\n\n";
    $body .= $resetLink . "\n\n";
    $body .= "If you did not request this, you can ignore this email.\n\n";
    $body .= "- Nestoida\n";

    return nestoida_send_email($toEmail, $subject, $body);
}

function verification_is_rate_limited($conn, $email, $requesterHash)
{
    $emailCount = 0;
    $requesterCount = 0;

    $emailStmt = $conn->prepare("SELECT COUNT(*) AS total FROM email_verifications WHERE email=? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $emailStmt->bind_param("s", $email);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    if ($emailResult) {
        $emailCount = (int)$emailResult->fetch_assoc()['total'];
    }
    $emailStmt->close();

    $requesterStmt = $conn->prepare("SELECT COUNT(*) AS total FROM email_verifications WHERE requester_hash=? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $requesterStmt->bind_param("s", $requesterHash);
    $requesterStmt->execute();
    $requesterResult = $requesterStmt->get_result();
    if ($requesterResult) {
        $requesterCount = (int)$requesterResult->fetch_assoc()['total'];
    }
    $requesterStmt->close();

    return ($emailCount >= 3 || $requesterCount >= 10);
}

function verification_create_token($conn, $userId, $email, $requesterHash)
{
    $cleanup = $conn->prepare("DELETE FROM email_verifications WHERE user_id=? OR expires_at < NOW() OR used_at IS NOT NULL");
    $cleanup->bind_param("i", $userId);
    $cleanup->execute();
    $cleanup->close();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $verifyTtl = (int)nestoida_env('VERIFICATION_TOKEN_TTL_SECONDS', 86400);
    if ($verifyTtl < 900) {
        $verifyTtl = 900;
    }
    $expiresAt = date('Y-m-d H:i:s', time() + $verifyTtl);

    $insert = $conn->prepare("INSERT INTO email_verifications (user_id, email, token_hash, requester_hash, expires_at) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("issss", $userId, $email, $tokenHash, $requesterHash, $expiresAt);

    if (!$insert->execute()) {
        $insert->close();
        return null;
    }

    $insert->close();
    return $token;
}

function verification_verify_token($conn, $email, $token)
{
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id, user_id FROM email_verifications WHERE email=? AND token_hash=? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();

    $verification = null;
    if ($result && $result->num_rows > 0) {
        $verification = $result->fetch_assoc();
    }

    $stmt->close();
    return $verification;
}

function verification_mark_used($conn, $verificationId, $email)
{
    $markUsed = $conn->prepare("UPDATE email_verifications SET used_at=NOW() WHERE id=?");
    $markUsed->bind_param("i", $verificationId);
    $markUsed->execute();
    $markUsed->close();

    $expireOthers = $conn->prepare("UPDATE email_verifications SET used_at=NOW() WHERE email=? AND used_at IS NULL");
    $expireOthers->bind_param("s", $email);
    $expireOthers->execute();
    $expireOthers->close();
}

function verification_send_email($toEmail, $verifyLink)
{
    $subject = 'Verify your Nestoida account';

    $body = "Hello,\n\n";
    $body .= "Please verify your Nestoida account email by clicking the link below:\n\n";
    $body .= $verifyLink . "\n\n";
    $body .= "If you did not create this account, you can ignore this email.\n\n";
    $body .= "- Nestoida\n";

    return nestoida_send_email($toEmail, $subject, $body);
}
