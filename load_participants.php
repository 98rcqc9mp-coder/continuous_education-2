<?php
require_once __DIR__ . "/../../config/db.php";

$type = $_GET['type'] ?? 'all';

/* Helpers */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$sql = "
SELECT
  p.id,
  p.full_name,
  p.gender,
  p.phone,
  p.work_place,
  p.academic_title,
  p.general_specialization,
  p.specific_specialization,
  p.inside_university,
  p.created_at AS participant_created_at,
  c.course_name
FROM participants p
LEFT JOIN course_participants cp ON cp.participant_id = p.id
LEFT JOIN courses c ON c.id = cp.course_id
";

$where = [];
if ($type == 'male') {
    $where[] = "p.gender='ذكر'";
} elseif ($type == 'female') {
    $where[] = "p.gender='أنثى'";
} elseif ($type == 'inside') {
    $where[] = "p.inside_university='داخل'";
} elseif ($type == 'outside') {
    $where[] = "p.inside_university='خارج'";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='table-responsive'>
<table class='table table-bordered text-center align-middle'>
<thead class='table-dark'>
<tr>
  <th style='width:70px'>#</th>
  <th style='text-align:right'>الاسم</th>
  <th style='width:90px'>الجنس</th>
  <th style='width:110px'>داخل/خارج</th>
  <th style='width:130px'>الهاتف</th>
  <th style='text-align:right'>مكان العمل</th>
  <th style='text-align:right'>اللقب العلمي</th>
  <th style='text-align:right'>التخصص العام</th>
  <th style='text-align:right'>التخصص الدقيق</th>
  <th style='text-align:right'>الدورة</th>
  <th style='width:170px'>تاريخ التسجيل</th>
</tr>
</thead><tbody>";

$i = 1;
foreach ($rows as $row) {
    echo "<tr>
    <td>" . ($i++) . "</td>
    <td style='text-align:right;'>" . e($row['full_name'] ?? '---') . "</td>
    <td>" . e($row['gender'] ?? '---') . "</td>
    <td>" . e($row['inside_university'] ?? '---') . "</td>
    <td>" . e($row['phone'] ?? '---') . "</td>
    <td style='text-align:right;'>" . e($row['work_place'] ?? '---') . "</td>
    <td style='text-align:right;'>" . e($row['academic_title'] ?? '---') . "</td>
    <td style='text-align:right;'>" . e($row['general_specialization'] ?? '---') . "</td>
    <td style='text-align:right;'>" . e($row['specific_specialization'] ?? '---') . "</td>
    <td style='text-align:right;'>" . e($row['course_name'] ?? '---') . "</td>
    <td>" . e($row['participant_created_at'] ?? '---') . "</td>
  </tr>";
}

if ($i === 1) {
    echo "<tr><td colspan='11'>لا توجد بيانات</td></tr>";
}

echo "</tbody></table></div>";
?>