<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$sql = "
SELECT job_title, COUNT(*) AS total
FROM participants
WHERE inside_university = 'داخل'
GROUP BY job_title
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>تقرير المشاركين</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:url("/continuous_education/assets/img/dashboard-bg.jpg")
    no-repeat center center fixed;
    background-size:cover;
    font-family:"Segoe UI",Tahoma;
}

.overlay{
    background:rgba(0,0,0,.35);
    min-height:100vh;
    padding:40px;
}

.glass{
    background:rgba(255,255,255,.18);
    backdrop-filter:blur(14px);
    border-radius:25px;
    padding:35px;
    box-shadow:0 20px 50px rgba(0,0,0,.35);
    color:#fff;
}

.table{
    background:#fff;
    border-radius:18px;
    overflow:hidden;
}

.table thead{
    background:#1f2937;
    color:#fff;
}

.btn-glass{
    background:rgba(255,255,255,.28);
    border:1px solid rgba(255,255,255,.35);
    border-radius:16px;
    padding:12px 28px;
    font-size:16px;
    color:#fff;
    transition:.25s;
    text-decoration:none;
}

.btn-glass:hover{
    background:#fff;
    color:#000;
    transform:translateY(-3px);
}
</style>
</head>

<body dir="rtl">

<div class="overlay">

<div class="glass">

<!-- العنوان -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>📊 تقرير المشاركين داخل الجامعة</h4>

    <a href="../dashboard.php" class="btn-glass">
        ⬅ رجوع
    </a>
</div>

<!-- الجدول -->
<table class="table table-bordered table-hover text-center align-middle">

<thead>
<tr>
    <th>الوظيفة</th>
    <th>عدد المشاركين</th>
</tr>
</thead>

<tbody>
<?php if(count($data) > 0): ?>
<?php foreach($data as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['job_title']); ?></td>
    <td><?= $row['total']; ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
    <td colspan="2">لا توجد بيانات</td>
</tr>
<?php endif; ?>
</tbody>

</table>

</div>
</div>

</body>
</html>
