<?php
require_once __DIR__."/../../config/db.php";

$type = $_GET['type'] ?? '';
$course_id = $_GET['course_id'] ?? null;

/* ===============================
   Helpers
================================ */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* ===============================
   تنسيق التاريخ
================================ */
function arDate($date){
    return $date ? date("Y / m / d", strtotime($date)) : "-";
}

/* =================================================
   تفاصيل دورة (آمنة بدون أخطاء)
   ✅ نفس منطقك لكن مع escape لمنع كسر الـ HTML
   ✅ نفس المخرجات داخل detailsContent
================================================= */
if($course_id){

    $stmt=$pdo->prepare("SELECT * FROM courses WHERE id=?");
    $stmt->execute([$course_id]);
    $c=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$c){
        echo "<div class='alert alert-danger'>الدورة غير موجودة</div>";
        exit;
    }

    $base="../../attachments/uploads/";

    echo "
    <div class='card p-4 shadow'>

    <div class='d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3'>
      <h4 class='m-0 text-primary'>📘 تفاصيل الدورة</h4>
      <button class='btn btn-outline-secondary btn-sm' onclick='backToCoursesTable()'>⬅ رجوع</button>
    </div>

    <table class='table table-bordered'>
    <tr><th>اسم الدورة</th><td>".e($c['course_name'] ?? '-')."</td></tr>
    <tr><th>مدير الدورة</th><td>".e($c['course_manager'] ?? '-')."</td></tr>
    <tr><th>المحاضر</th><td>".e($c['lecturer'] ?? '-')."</td></tr>
    <tr><th>ساعات المحاضر</th><td>".e($c['lecturer_hours'] ?? '-')."</td></tr>

    <tr><th>تاريخ البداية</th><td>".e(arDate($c['start_date'] ?? null))."</td></tr>
    <tr><th>تاريخ النهاية</th><td>".e(arDate($c['end_date'] ?? null))."</td></tr>
    <tr><th>عدد الأيام</th><td>".e($c['days_count'] ?? '-')."</td></tr>
    <tr><th>وقت المحاضرة</th><td>".e($c['lecture_time'] ?? '-')."</td></tr>

    <tr><th>نوع الإدراج</th><td>".e($c['course_plan_type'] ?? '-')."</td></tr>
    <tr><th>تصنيف الدورة</th><td>".e($c['course_category'] ?? '-')."</td></tr>
    <tr><th>نوع التنفيذ</th><td>".e($c['course_type'] ?? '-')."</td></tr>

    <tr><th>المنصة</th><td>".e($c['platform'] ?? '-')."</td></tr>
    <tr><th>المكان / الرابط</th><td>".e($c['location'] ?? '-')."</td></tr>

    <tr><th>أجور المشاركة</th><td>".e($c['participation_fee'] ?? '-')."</td></tr>
    <tr><th>الجهة المستفيدة</th><td>".e($c['beneficiary'] ?? '-')."</td></tr>
    <tr><th>الفئة المستهدفة</th><td>".e($c['target_group'] ?? '-')."</td></tr>

    <tr><th>الحد الأقصى للمشاركين</th><td>".e($c['max_participants'] ?? '-')."</td></tr>
    <tr><th>الحالة</th><td>".e($c['status'] ?? '-')."</td></tr>

    </table>

    <h5 class='mt-4'>📎 المرفقات</h5>
    <ul>";

    if(!empty($c['admin_order_file']))
        echo "<li><a target='_blank' href='".e($base.$c['admin_order_file'])."'>الأمر الإداري</a></li>";

    if(!empty($c['form_file']))
        echo "<li><a target='_blank' href='".e($base.$c['form_file'])."'>استمارة الدورة</a></li>";

    if(!empty($c['curriculum_file']))
        echo "<li><a target='_blank' href='".e($base.$c['curriculum_file'])."'>منهاج الدورة</a></li>";

    if(!empty($c['executing_entity_file']))
        echo "<li><a target='_blank' href='".e($base.$c['executing_entity_file'])."'>الجهة المنفذة</a></li>";

    if(!empty($c['axes_file']))
        echo "<li><a target='_blank' href='".e($base.$c['axes_file'])."'>محاور الدورة</a></li>";

    echo "
    </ul>
    </div>";

    exit;
}

/* =================================================
   فلترة حسب الكارت
================================================= */
$sql="SELECT id,course_name,start_date,end_date,location FROM courses WHERE 1";

if($type=="today"){
    $sql.=" AND DATE(start_date)=CURDATE()";
}
elseif($type=="running"){
    $sql.=" AND CURDATE() BETWEEN DATE(start_date) AND DATE(end_date)";
}
elseif($type=="upcoming"){
    $sql.=" AND DATE(start_date) > CURDATE()";
}

$sql.=" ORDER BY start_date DESC";

$data=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* =================================================
   عرض جدول الدورات
   ✅ الاسم قابل للنقر ويعرض التفاصيل داخل نفس الصفحة (AJAX)
================================================= */

echo "<div class='table-responsive'>
<table class='table table-bordered text-center align-middle'>
<thead class='table-dark'>
<tr>
<th>#</th>
<th>اسم الدورة</th>
<th>تاريخ البداية</th>
<th>تاريخ النهاية</th>
<th>الموقع</th>
</tr>
</thead><tbody>";

$i=1;

foreach($data as $c){

    $cid = (int)$c['id'];

    echo "<tr>
    <td>$i</td>
    <td style='text-align:right;'>
        <a href='javascript:void(0);'
           onclick='showCourseDetails($cid)'
           style='font-weight:1000;color:#0d6efd;text-decoration:none'>
           ".e($c['course_name'])."
        </a>
    </td>
    <td>".e(arDate($c['start_date']))."</td>
    <td>".e(arDate($c['end_date']))."</td>
    <td>".e(($c['location'] ?: '-'))."</td>
    </tr>";

    $i++;
}

if($i==1){
    echo "<tr><td colspan='5'>لا توجد بيانات</td></tr>";
}

echo "</tbody></table></div>";
?>