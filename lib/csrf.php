<?php
/**
 * lib/csrf.php — مساعد حماية CSRF
 */

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = (string)($_POST['csrf_token'] ?? '');
    $stored = (string)($_SESSION['csrf_token'] ?? '');
    if ($stored === '' || !hash_equals($stored, $token)) {
        http_response_code(403);
        die('طلب غير صالح (CSRF). <a href="javascript:history.back()">رجوع</a>');
    }
}
