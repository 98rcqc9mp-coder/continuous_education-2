<?php
/**
 * lib/mail.php — مساعد إرسال البريد الإلكتروني
 * يعتمد على config/mail.php لإعدادات SMTP
 */

function sendMail(string $to, string $subject, string $body): bool
{
    // تحميل إعدادات البريد
    $configFile = __DIR__ . '/../config/mail.php';
    $config = file_exists($configFile) ? require $configFile : [];

    // إذا كان الإرسال معطلاً أو البريد فارغاً → تخطّ
    if (empty($config['enabled']) || $config['enabled'] === false) {
        return false;
    }
    if (empty($to)) {
        return false;
    }

    $fromEmail = $config['from_email'] ?? 'noreply@example.com';
    $fromName  = $config['from_name']  ?? 'نظام التعليم المستمر';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

    return @mail($to, $encodedSubject, $body, $headers);
}

/**
 * إرسال إشعار تسجيل دورة
 */
function sendCourseRegistrationEmail(string $to, string $participantName, string $courseName, string $startDate): bool
{
    $currentYear = date('Y');
    $subject = "تم تسجيلك في الدورة: {$courseName}";
    $body = <<<HTML
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"></head>
<body style="font-family:'Segoe UI',Tahoma,sans-serif;background:#f8fafc;padding:30px;direction:rtl;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);overflow:hidden;">
    <div style="background:linear-gradient(135deg,#2563eb,#1e40af);padding:24px;text-align:center;">
      <h1 style="color:#fff;margin:0;font-size:22px;">🎓 نظام التعليم المستمر</h1>
    </div>
    <div style="padding:28px 24px;">
      <p style="font-size:16px;margin:0 0 16px;">السيد/ة <strong>{$participantName}</strong>،</p>
      <p style="font-size:15px;line-height:1.7;color:#374151;">
        يسرنا إبلاغكم بأنه تم تسجيلكم بنجاح في الدورة التدريبية:
      </p>
      <div style="background:#eff6ff;border-right:4px solid #2563eb;padding:14px 16px;border-radius:8px;margin:16px 0;">
        <strong style="font-size:17px;color:#1e40af;">📚 {$courseName}</strong><br>
        <span style="color:#4b5563;font-size:14px;">📅 تاريخ البدء: {$startDate}</span>
      </div>
      <p style="color:#6b7280;font-size:13px;margin-top:24px;">
        إذا لم تطلب هذا التسجيل، يرجى التواصل مع الإدارة.
      </p>
    </div>
    <div style="background:#f9fafb;padding:14px;text-align:center;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;">
      نظام إدارة التعليم المستمر © {$currentYear}
    </div>
  </div>
</body>
</html>
HTML;

    return sendMail($to, $subject, $body);
}

/**
 * إرسال إشعار قبول طلب التسجيل
 */
function sendApprovalEmail(string $to, string $participantName, string $courseName, string $startDate): bool
{
    $currentYear = date('Y');
    $subject = "✅ تمت الموافقة على طلب تسجيلك في دورة: {$courseName}";
    $body = <<<HTML
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"></head>
<body style="font-family:'Segoe UI',Tahoma,sans-serif;background:#f8fafc;padding:30px;direction:rtl;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);overflow:hidden;">
    <div style="background:linear-gradient(135deg,#16a34a,#15803d);padding:24px;text-align:center;">
      <h1 style="color:#fff;margin:0;font-size:22px;">✅ تأكيد القبول</h1>
    </div>
    <div style="padding:28px 24px;">
      <p style="font-size:16px;margin:0 0 16px;">السيد/ة <strong>{$participantName}</strong>،</p>
      <p style="font-size:15px;line-height:1.7;color:#374151;">
        يسرنا إبلاغكم بأنه تمت الموافقة على طلب تسجيلكم في الدورة التدريبية:
      </p>
      <div style="background:#f0fdf4;border-right:4px solid #16a34a;padding:14px 16px;border-radius:8px;margin:16px 0;">
        <strong style="font-size:17px;color:#15803d;">📚 {$courseName}</strong><br>
        <span style="color:#4b5563;font-size:14px;">📅 تاريخ البدء: {$startDate}</span>
      </div>
      <p style="color:#6b7280;font-size:13px;margin-top:24px;">
        يرجى الحضور في الموعد المحدد. للاستفسار تواصل مع الإدارة.
      </p>
    </div>
    <div style="background:#f9fafb;padding:14px;text-align:center;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;">
      نظام إدارة التعليم المستمر © {$currentYear}
    </div>
  </div>
</body>
</html>
HTML;

    return sendMail($to, $subject, $body);
}
