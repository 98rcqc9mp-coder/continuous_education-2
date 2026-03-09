<?php
/**
 * config/mail.php — إعدادات البريد الإلكتروني
 *
 * لتفعيل الإرسال:
 *   1. بدّل enabled => true
 *   2. أدخل بريد الإرسال الصحيح في from_email
 *
 * ملاحظة: الإرسال معطّل افتراضياً حتى يتم تكوين البريد
 */
return [
    /* true = تفعيل الإرسال، false = تعطيل */
    'enabled'    => false,

    'from_email' => 'noreply@example.com',
    'from_name'  => 'نظام التعليم المستمر',

    /* إعدادات SMTP (للاستخدام المستقبلي مع PHPMailer) */
    // 'smtp_host' => 'smtp.gmail.com',
    // 'smtp_port' => 587,
    // 'smtp_user' => 'your@gmail.com',
    // 'smtp_pass' => 'your_app_password',
];
