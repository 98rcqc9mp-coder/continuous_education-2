<?php
/**
 * lib/qr.php — توليد رمز QR باستخدام Google Chart API
 *
 * ملاحظة: Google Chart API رسمياً deprecated لكن لا يزال يعمل.
 * للاستخدام في بيئة الإنتاج يُنصح بالانتقال إلى مكتبة محلية
 * مثل: endroid/qr-code أو bacon/bacon-qr-code
 */

/**
 * إرجاع رابط صورة QR عبر Google Chart API
 */
function qrImageUrl(string $data, int $size = 200): string
{
    return 'https://chart.googleapis.com/chart?cht=qr&chs='
        . (int)$size . 'x' . (int)$size
        . '&chl=' . urlencode($data)
        . '&choe=UTF-8&chld=M|1';
}

/**
 * إرجاع وسم <img> لرمز QR
 */
function qrImageTag(string $data, int $size = 200, string $alt = 'QR Code'): string
{
    $url = qrImageUrl($data, $size);
    return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" '
        . 'width="' . (int)$size . '" height="' . (int)$size . '" '
        . 'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="display:block;" loading="lazy">';
}
