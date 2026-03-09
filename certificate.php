<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$course_id = $_GET['course_id'] ?? null;
$participant_id = $_GET['participant_id'] ?? null;

if (!$course_id || !$participant_id) {
    die("بيانات غير صحيحة");
}

/* جلب بيانات الدورة */
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id=?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

/* جلب بيانات المشارك */
$stmt = $pdo->prepare("SELECT * FROM participants WHERE id=?");
$stmt->execute([$participant_id]);
$participant = $stmt->fetch();

if (!$course || !$participant) {
    die("خطأ في البيانات");
}

/* =====================================================
   (ميزة جديدة) التحقق من نسبة الحضور
   ===================================================== */
$attendanceWarning = '';
$minPct = isset($course['min_attendance_pct']) ? (int)$course['min_attendance_pct'] : 80;

/* التحقق من وجود جدول attendance قبل الاستعلام */
$tableCheck = $pdo->query("SHOW TABLES LIKE 'attendance'")->fetchColumn();
if ($tableCheck) {
    $totalDays = isset($course['days_count']) ? (int)$course['days_count'] : 0;
    if ($totalDays > 0) {
        $presentStmt = $pdo->prepare("
            SELECT COUNT(*) FROM attendance
            WHERE course_id=? AND participant_id=? AND status='present'
        ");
        $presentStmt->execute([$course_id, $participant_id]);
        $presentDays = (int)$presentStmt->fetchColumn();
        $actualPct = (int)round(($presentDays / $totalDays) * 100);

        if ($actualPct < $minPct) {
            $attendanceWarning = "⚠️ تنبيه: نسبة حضور المشارك ({$actualPct}%) أقل من الحد الأدنى المطلوب ({$minPct}%). "
                               . "يمكنك طباعة الشهادة لكن يُنصح بمراجعة السجلات.";
        }
    }
}

/* =====================================================
   (ميزة جديدة) إنشاء سجل الشهادة إذا لم يكن موجوداً
   ===================================================== */
$certCode   = null;
$issuerName = 'نظام التعليم المستمر';

$certTableCheck = $pdo->query("SHOW TABLES LIKE 'certificates'")->fetchColumn();
if ($certTableCheck) {
    /* جلب اسم الجهة من جدول design */
    try {
        $designRow = $pdo->query("SELECT site_name FROM design ORDER BY id LIMIT 1")->fetch();
        if ($designRow && !empty($designRow['site_name'])) {
            $issuerName = $designRow['site_name'];
        }
    } catch (Exception $e) { /* تجاهل */ }

    /* البحث عن سجل موجود */
    $certStmt = $pdo->prepare("
        SELECT certificate_code FROM certificates
        WHERE course_id=? AND participant_id=?
        LIMIT 1
    ");
    $certStmt->execute([$course_id, $participant_id]);
    $certRow = $certStmt->fetch();

    if ($certRow) {
        $certCode = $certRow['certificate_code'];
    } else {
        /* توليد رمز فريد */
        do {
            $certCode = strtoupper(bin2hex(random_bytes(6))) . '-' . date('Y');
            $dupCheck = $pdo->prepare("SELECT 1 FROM certificates WHERE certificate_code=? LIMIT 1");
            $dupCheck->execute([$certCode]);
        } while ($dupCheck->fetchColumn());

        /* حفظ السجل */
        try {
            $insStmt = $pdo->prepare("
                INSERT INTO certificates
                    (course_id, participant_id, certificate_code, issuer_name)
                VALUES (?,?,?,?)
            ");
            $insStmt->execute([$course_id, $participant_id, $certCode, $issuerName]);
        } catch (Exception $e) {
            $certCode = null; /* تجاهل الخطأ وأكمل */
        }
    }
}

/* رابط التحقق */
$verifyUrl = '';
if ($certCode) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    /* الرابط يشير إلى verify_certificate.php في جذر الموقع */
    $verifyUrl = $protocol . '://' . $host . '/verify_certificate.php?code=' . urlencode($certCode);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>شهادة مشاركة</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#e5e7eb;
    font-family:"Segoe UI",Tahoma;
}

.controls{
    text-align:center;
    margin:20px;
}

.certificate-wrapper{
    width:1100px;
    margin:20px auto;
    padding:20px;
    background:#fff;
    border:8px solid #1f2937;
}

.certificate{
    border:4px solid #d4af37;
    padding:60px;
    text-align:center;
}

h1{
    font-size:48px;
    font-weight:bold;
    margin-bottom:10px;
}

.subtitle{
    font-size:20px;
    margin-bottom:30px;
}

.name{
    font-size:38px;
    font-weight:bold;
    color:#1f2937;
    margin:30px 0;
}

.course{
    font-size:26px;
    margin:20px 0;
}

.footer{
    margin-top:40px;
    font-size:18px;
}

.signature-section{
    margin-top:70px;
    display:flex;
    justify-content:space-between;
}

.signature{
    width:40%;
    text-align:center;
}

.signature-line{
    margin-top:50px;
    border-top:2px solid #000;
    padding-top:10px;
}

/* (إضافة جديدة) QR + رمز الشهادة */
.cert-qr-section{
    margin-top:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:20px;
    flex-wrap:wrap;
    border-top:1px dashed #d4af37;
    padding-top:24px;
}
.cert-code-label{
    font-size:13px;
    color:#6b7280;
    font-family:monospace;
    letter-spacing:.5px;
}

/* (إضافة جديدة) تحذير حضور */
.attendance-alert{
    max-width:1100px;
    margin:10px auto;
    padding:12px 18px;
    background:#fef9c3;
    border:1px solid #fbbf24;
    border-radius:8px;
    color:#92400e;
    font-size:14px;
    font-weight:600;
}

@media print{
    body{
        background:#fff;
    }
    .controls{
        display:none;
    }
    .attendance-alert{
        display:none;
    }
    .certificate-wrapper{
        width:100%;
        margin:0;
        border:8px solid #000;
    }
}
</style>
</head>

<body>

<?php if ($attendanceWarning): ?>
<div class="attendance-alert"><?= htmlspecialchars($attendanceWarning) ?></div>
<?php endif; ?>

<div class="controls">
<button onclick="window.print()" class="btn btn-dark">
🖨 طباعة / حفظ PDF
</button>

<a href="participants.php?course_id=<?= $course_id ?>" 
class="btn btn-secondary">
⬅ رجوع
</a>
</div>

<div class="certificate-wrapper">
<div class="certificate">

<h1>شهادة مشاركة</h1>

<div class="subtitle">
تمنح هذه الشهادة إلى
</div>

<div class="name">
<?= htmlspecialchars($participant['full_name']) ?>
</div>

<div class="subtitle">
تقديراً لمشاركته في دورة
</div>

<div class="course">
<?= htmlspecialchars($course['course_name']) ?>
</div>

<div class="footer">
المنعقدة للفترة من  
<strong><?= $course['start_date'] ?></strong>  
إلى  
<strong><?= $course['end_date'] ?></strong>
</div>

<div class="signature-section">
<div class="signature">
<div class="signature-line">توقيع المدير</div>
</div>

<div class="signature">
<div class="signature-line">ختم المؤسسة</div>
</div>
</div>

<div style="margin-top:50px; font-size:16px;">
تاريخ الإصدار: <?= date("Y-m-d") ?>
</div>

<?php if ($certCode): ?>
<!-- (إضافة جديدة) QR + رمز الشهادة -->
<div class="cert-qr-section">
  <?php if ($verifyUrl): ?>
  <div>
    <img src="https://chart.googleapis.com/chart?cht=qr&chs=120x120&chl=<?= urlencode($verifyUrl) ?>&choe=UTF-8&chld=M|1"
         width="120" height="120" alt="QR التحقق" style="display:block;border:3px solid #d4af37;border-radius:8px;">
  </div>
  <?php endif; ?>
  <div style="text-align:right;">
    <div style="font-size:14px;color:#374151;margin-bottom:6px;">رمز التحقق من الشهادة:</div>
    <div class="cert-code-label"><?= htmlspecialchars($certCode) ?></div>
    <?php if ($verifyUrl): ?>
    <div style="font-size:12px;color:#9ca3af;margin-top:4px;">
      للتحقق: امسح QR أو زر<br>
      <span style="word-break:break-all;"><?= htmlspecialchars($verifyUrl) ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</div>
</div>

</body>
</html>