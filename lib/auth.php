<?php
/**
 * lib/auth.php — مساعد التحقق من الجلسة
 */

function requireLogin(string $redirectTo = '../login.php'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

function requireAdmin(string $redirectTo = '../login.php'): void
{
    requireLogin($redirectTo);
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<p style="font-family:\'Segoe UI\',sans-serif;text-align:center;margin-top:60px;font-size:20px;">⛔ غير مصرح لك بالوصول إلى هذه الصفحة.</p>');
    }
}
