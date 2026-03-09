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

@media print{
    body{
        background:#fff;
    }
    .controls{
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

</div>
</div>

</body>
</html>