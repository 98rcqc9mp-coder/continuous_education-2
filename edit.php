<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

/**
 * ✅ edit.php بنفس بيانات add.php (نفس الحقول) + نفس تصميم الواجهة (Glass/Topbar)
 * ✅ بدون تغيير قاعدة البيانات أو تخريب الاستعلامات:
 * - نستخدم UPDATE لنفس الأعمدة الموجودة في INSERT بصفحة add.php.
 * - نفس أسماء الحقول name="" لضمان عدم كسر الـ POST.
 * - نفس فكرة location للحضوري/للرابط في الالكتروني (نفس العمود).
 * - تحديث المرفقات PDF اختياري: إذا لم ترفع ملف جديد يبقى القديم كما هو.
 * - CSRF إضافي للحماية.
 */

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = $_GET['id'];

/* -------------------- Helpers -------------------- */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function cleanFileName($filename){
    return preg_replace('/[^a-zA-Z0-9._-]/','_', $filename);
}

/* Upload PDF (returns "" if not uploaded/invalid) */
function uploadFile($input,$prefix,$dir){

    if(empty($_FILES[$input]['name'])) return "";

    $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
    if($ext != "pdf") return "";

    if (function_exists('finfo_open') && !empty($_FILES[$input]['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $_FILES[$input]['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['application/pdf','application/octet-stream'])) {
                return "";
            }
        }
    }

    $maxBytes = 20 * 1024 * 1024;
    if (!empty($_FILES[$input]['size']) && $_FILES[$input]['size'] > $maxBytes) {
        return "";
    }

    $name = time()."_".$prefix."_".cleanFileName($_FILES[$input]['name']);
    $target = $dir.$name;

    if(move_uploaded_file($_FILES[$input]['tmp_name'], $target)){
        return $name;
    }
    return "";
}

/* Fetch course */
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id=?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: list.php");
    exit;
}

/* Save */
$errors = [];
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "فشل التحقق الأمني (CSRF). أعد تحميل الصفحة وحاول مرة أخرى.";
    }

    if (empty($errors)) {

        // Same fields as add.php
        $course_name      = $_POST['course_name'];
        $course_manager   = $_POST['course_manager'];
        $lecturer         = $_POST['lecturer'];
        $lecturer_hours   = $_POST['lecturer_hours'];

        $start_date       = $_POST['start_date'];
        $end_date         = $_POST['end_date'];
        $days_count       = $_POST['days_count'];
        $lecture_time     = $_POST['lecture_time'];

        $course_plan_type = $_POST['course_plan_type'];
        $course_category  = $_POST['course_category'];
        $course_type      = $_POST['course_type'];

        $platform         = $_POST['platform'] ?? null;
        $location         = $_POST['location']; // same name used for physical location or meeting link

        $participation_fee= $_POST['participation_fee'];
        $beneficiary      = $_POST['beneficiary'];
        $target_group     = $_POST['target_group'];

        $max_participants = $_POST['max_participants'];
        $status           = $_POST['status'];

        if($course_type === "حضوري"){
            $platform = null;
        }

        // Uploads (optional). If not uploaded, keep old values.
        $uploadDir="../../attachments/uploads/";
        if(!is_dir($uploadDir)){
            mkdir($uploadDir,0777,true);
        }

        $new_admin_order   = uploadFile("admin_order_file","admin",$uploadDir);
        $new_form_file     = uploadFile("form_file","form",$uploadDir);
        $new_curriculum    = uploadFile("curriculum_file","curriculum",$uploadDir);
        $new_executing_pdf = uploadFile("executing_entity_file","entity",$uploadDir);
        $new_axes_pdf      = uploadFile("axes_file","axes",$uploadDir);

        $admin_order_file       = ($new_admin_order   !== "") ? $new_admin_order   : ($course['admin_order_file'] ?? "");
        $form_file              = ($new_form_file     !== "") ? $new_form_file     : ($course['form_file'] ?? "");
        $curriculum_file        = ($new_curriculum    !== "") ? $new_curriculum    : ($course['curriculum_file'] ?? "");
        $executing_entity_file  = ($new_executing_pdf !== "") ? $new_executing_pdf : ($course['executing_entity_file'] ?? "");
        $axes_file              = ($new_axes_pdf      !== "") ? $new_axes_pdf      : ($course['axes_file'] ?? "");

        $stmt = $pdo->prepare("
            UPDATE courses SET
                course_name=?,
                course_manager=?,
                lecturer=?,
                lecturer_hours=?,
                start_date=?,
                end_date=?,
                days_count=?,
                lecture_time=?,
                course_plan_type=?,
                course_category=?,
                course_type=?,
                platform=?,
                location=?,
                participation_fee=?,
                beneficiary=?,
                target_group=?,
                max_participants=?,
                status=?,
                admin_order_file=?,
                form_file=?,
                curriculum_file=?,
                executing_entity_file=?,
                axes_file=?
            WHERE id=?
        ");

        $stmt->execute([
            $course_name,
            $course_manager,
            $lecturer,
            $lecturer_hours,
            $start_date,
            $end_date,
            $days_count,
            $lecture_time,
            $course_plan_type,
            $course_category,
            $course_type,
            $platform,
            $location,
            $participation_fee,
            $beneficiary,
            $target_group,
            $max_participants,
            $status,
            $admin_order_file,
            $form_file,
            $curriculum_file,
            $executing_entity_file,
            $axes_file,
            $id
        ]);

        header("Location: list.php?updated=1");
        exit;
    }
}

$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');

/* For form defaults */
function val($course, $key, $fallback=''){
    return isset($course[$key]) ? (string)$course[$key] : (string)$fallback;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>تعديل الدورة | نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
    --overlay1: rgba(0,0,0,.55);
    --overlay2: rgba(0,0,0,.70);

    --card: rgba(255,255,255,.10);
    --card2: rgba(255,255,255,.08);
    --border: rgba(255,255,255,.14);
    --text: #ffffff;
    --muted: rgba(255,255,255,.74);

    --shadow: 0 20px 60px rgba(0,0,0,.55);
    --shadow2: 0 14px 35px rgba(0,0,0,.35);

    --primary1:#2563eb; --primary2:#1e40af;
    --success1:#22c55e; --success2:#16a34a;
    --warn1:#f59e0b; --warn2:#d97706;
    --danger1:#ef4444; --danger2:#b91c1c;

    --radius: 18px;
}

*{ box-sizing:border-box; }

body{
    margin:0;
    font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Noto Kufi Arabic","Cairo",sans-serif;
    background:url("/continuous_education/assets/img/dashboard-bg.jpg") no-repeat center center fixed;
    background-size:cover;
    color:var(--text);
    transition:.25s ease;
    font-size:14px;
}

a{ color:inherit; text-decoration:none; }
a:hover{ color:inherit; }

.overlay{
    background:linear-gradient(180deg, var(--overlay1), var(--overlay2));
    min-height:100vh;
    padding:22px 14px 34px;
    position:relative;
    overflow:hidden;
}

.content{
    position:relative;
    z-index:2;
    max-width: 1100px;
    margin: 0 auto;
}

.glass{
    background:var(--card);
    border:1px solid var(--border);
    backdrop-filter: blur(16px);
    border-radius:var(--radius);
    padding:18px;
    box-shadow: var(--shadow);
    margin-bottom:14px;
}
.glass.soft{
    background:var(--card2);
    box-shadow: var(--shadow2);
}

.topbar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
}

.brand{
    display:flex;
    align-items:center;
    gap:12px;
    min-width:260px;
}
.brand-badge{
    width:46px; height:46px;
    border-radius:16px;
    background:linear-gradient(135deg,var(--primary1),var(--primary2));
    box-shadow:0 12px 25px rgba(0,0,0,.35);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
    flex:0 0 auto;
}
.brand h1{
    margin:0;
    font-size:18px;
    font-weight:1000;
}
.brand small{
    display:block;
    color:var(--muted);
    font-weight:800;
    margin-top:2px;
}

.actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.btn-soft{
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.10);
    color:#fff;
    padding:10px 14px;
    border-radius:14px;
    font-weight:1000;
    text-decoration:none;
    transition:.2s ease;
    display:inline-flex;
    align-items:center;
    gap:8px;
    cursor:pointer;
}
.btn-soft:hover{ transform:translateY(-2px); background:rgba(255,255,255,.14); }
.btn-soft.success{
    background:linear-gradient(135deg, rgba(34,197,94,.40), rgba(22,163,74,.22));
    border-color: rgba(34,197,94,.40);
}
.btn-soft.danger{ border-color: rgba(239,68,68,.35); }

.badge-soft{
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.16);
    color:#fff;
    padding:6px 10px;
    border-radius:999px;
    font-weight:1000;
    font-size:12px;
}

.section-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:10px;
}
.section-title h2{
    margin:0;
    font-weight:1000;
    font-size:16px;
}
.section-title .meta{
    color:var(--muted);
    font-weight:900;
    font-size:12px;
}

hr.sep{
    border:none;
    border-top:1px solid rgba(255,255,255,.14);
    margin:12px 0;
}

.form-label{
    font-weight:1000;
    color: rgba(255,255,255,.92);
    margin-bottom:6px;
}
.form-control, .form-select{
    border-radius:14px !important;
    padding:12px 12px;
    font-weight:900;
    color:#fff;
    background: rgba(0,0,0,.18);
    border:1px solid rgba(255,255,255,.16);
}
.form-control:focus, .form-select:focus{
    border-color: rgba(37,99,235,.55);
    box-shadow: 0 0 0 .2rem rgba(37,99,235,.20);
    background: rgba(0,0,0,.22);
    color:#fff;
}
.form-text{
    color: rgba(255,255,255,.65) !important;
    font-weight:800;
}

.card-soft{
    border-radius:16px;
    padding:14px 16px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.18);
}

.file-box{
    border-radius:16px;
    padding:14px 16px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.08);
}

.footer-note{
    color:var(--muted);
    font-weight:900;
    font-size:12px;
    text-align:center;
}

body.light-mode{
    background:#f1f5f9 !important;
    color:#0f172a !important;
}
body.light-mode .overlay{
    background:linear-gradient(180deg, rgba(255,255,255,.86), rgba(255,255,255,.92));
}
body.light-mode .glass,
body.light-mode .glass.soft,
body.light-mode .card-soft,
body.light-mode .file-box{
    background:rgba(255,255,255,.72);
    border:1px solid rgba(15,23,42,.10);
    color:#0f172a;
    box-shadow:0 18px 45px rgba(2,6,23,.12);
}
body.light-mode .form-control,
body.light-mode .form-select{
    background: rgba(15,23,42,.05);
    border:1px solid rgba(15,23,42,.12);
    color:#0f172a;
}
body.light-mode .form-text,
body.light-mode .footer-note{ color: rgba(15,23,42,.65) !important; }
body.light-mode .form-label{ color:#0f172a; }
</style>
</head>

<body dir="rtl">
<div class="overlay">
  <div class="content">

    <!-- Topbar -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge" title="تعديل">✏️</div>
          <div>
            <h1>تعديل الدورة</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">ID: <?= e($id) ?></span>

          <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
          <a class="btn-soft" href="list.php">📋 قائمة الدورات</a>
          <a class="btn-soft danger" href="../../auth/logout.php">🚪 خروج</a>
        </div>
      </div>

      <?php if (!empty($errors)): ?>
        <hr class="sep">
        <div class="alert alert-danger mb-0" style="font-weight:900;">
          <?= e(implode(" | ", $errors)) ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="glass soft">
      <div class="section-title">
        <h2>🧾 نفس حقول الإضافة</h2>
        <div class="meta">رفع ملفات جديد = استبدال • بدون رفع = الاحتفاظ بالقديم</div>
      </div>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

        <div class="card-soft mb-3">
          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label">اسم الدورة</label>
              <input name="course_name" class="form-control" required value="<?= e(val($course,'course_name')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">مدير الدورة</label>
              <input name="course_manager" class="form-control" required value="<?= e(val($course,'course_manager')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">محاضر الدورة</label>
              <input name="lecturer" class="form-control" required value="<?= e(val($course,'lecturer')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">عدد ساعات المحاضر</label>
              <input type="number" name="lecturer_hours" class="form-control" min="0" step="1" value="<?= e(val($course,'lecturer_hours')) ?>">
            </div>
          </div>
        </div>

        <div class="card-soft mb-3">
          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label">تاريخ البداية</label>
              <input type="date" name="start_date" class="form-control" value="<?= e(val($course,'start_date')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">تاريخ النهاية</label>
              <input type="date" name="end_date" class="form-control" value="<?= e(val($course,'end_date')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">عدد أيام الدورة</label>
              <input type="number" name="days_count" class="form-control" min="0" step="1" value="<?= e(val($course,'days_count')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">وقت المحاضرة</label>
              <input name="lecture_time" class="form-control" value="<?= e(val($course,'lecture_time')) ?>">
            </div>

            <div class="col-lg-4">
              <label class="form-label">نوع الدورة (الإدراج)</label>
              <select name="course_plan_type" class="form-select">
                <?php $v = val($course,'course_plan_type'); ?>
                <option <?= ($v==='مخطط'?'selected':'') ?>>مخطط</option>
                <option <?= ($v==='مضاف'?'selected':'') ?>>مضاف</option>
              </select>
            </div>

            <div class="col-lg-4">
              <label class="form-label">تصنيف الدورة</label>
              <?php $v = val($course,'course_category'); ?>
              <select name="course_category" class="form-select">
                <option <?= ($v==='دورة'?'selected':'') ?>>دورة</option>
                <option <?= ($v==='ورشة'?'selected':'') ?>>ورشة</option>
                <option <?= ($v==='مخصص'?'selected':'') ?>>مخصص</option>
              </select>
            </div>

            <div class="col-lg-4">
              <label class="form-label">نوع التنفيذ</label>
              <?php $v = val($course,'course_type'); ?>
              <select name="course_type" id="courseType" class="form-select">
                <option value="حضوري" <?= ($v==='حضوري'?'selected':'') ?>>حضوري</option>
                <option value="الكتروني" <?= ($v==='الكتروني'?'selected':'') ?>>الكتروني</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card-soft mb-3">
          <div class="row g-3">
            <div class="col-lg-6" id="locationBox">
              <label class="form-label">مكان الدورة (حضوري)</label>
              <input name="location" class="form-control" value="<?= e(val($course,'location')) ?>">
              <div class="form-text">سيتم حفظه في location.</div>
            </div>

            <div class="col-lg-6" id="onlineSection" style="display:none;">
              <label class="form-label">نوع البرنامج</label>
              <?php $v = val($course,'platform'); ?>
              <select name="platform" class="form-select mb-2">
                <option value="Zoom" <?= ($v==='Zoom'?'selected':'') ?>>Zoom</option>
                <option value="Google Meet" <?= ($v==='Google Meet'?'selected':'') ?>>Google Meet</option>
              </select>

              <label class="form-label">رابط المحاضرة (الكتروني)</label>
              <div class="d-flex gap-2">
                <input type="url" id="meetingLink" name="location" class="form-control" value="<?= e(val($course,'location')) ?>" placeholder="https://...">
                <button type="button" id="openLinkBtn" class="btn btn-light" style="font-weight:900;">فتح</button>
              </div>
              <div class="form-text">سيتم حفظ الرابط في location (كما في الإضافة).</div>
            </div>

            <div class="col-lg-6">
              <label class="form-label">أجور المشاركة</label>
              <input name="participation_fee" class="form-control" value="<?= e(val($course,'participation_fee')) ?>">
            </div>

            <div class="col-lg-6">
              <label class="form-label">حالة الدورة</label>
              <?php $v = val($course,'status'); ?>
              <select name="status" class="form-select">
                <option <?= ($v==='قيد التسجيل'?'selected':'') ?>>قيد التسجيل</option>
                <option <?= ($v==='جارية'?'selected':'') ?>>جارية</option>
                <option <?= ($v==='منتهية'?'selected':'') ?>>منتهية</option>
              </select>
            </div>

            <div class="col-lg-6">
              <label class="form-label">الجهة المستفيدة</label>
              <?php $v = val($course,'beneficiary'); ?>
              <select name="beneficiary" class="form-select">
                <option <?= ($v==='قطاع حكومي'?'selected':'') ?>>قطاع حكومي</option>
                <option <?= ($v==='قطاع خاص'?'selected':'') ?>>قطاع خاص</option>
                <option <?= ($v==='افراد'?'selected':'') ?>>افراد</option>
              </select>
            </div>

            <div class="col-lg-6">
              <label class="form-label">فئة الأفراد المستهدفة</label>
              <?php $v = val($course,'target_group'); ?>
              <select name="target_group" class="form-select">
                <option <?= ($v==='تدريسيين'?'selected':'') ?>>تدريسيين</option>
                <option <?= ($v==='موظفين'?'selected':'') ?>>موظفين</option>
              </select>
            </div>

            <div class="col-lg-6">
              <label class="form-label">الحد الأقصى للمشاركين</label>
              <input type="number" name="max_participants" class="form-control" min="0" step="1" value="<?= e(val($course,'max_participants')) ?>">
            </div>
          </div>
        </div>

        <hr class="sep">

        <div class="section-title">
          <h2>📎 المرفقات (PDF)</h2>
          <div class="meta">رفع جديد يستبدل القديم</div>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="file-box">
              <label class="form-label">الأمر الإداري (PDF)</label>
              <input type="file" name="admin_order_file" class="form-control" accept="application/pdf">
              <div class="form-text">الحالي: <?= e(val($course,'admin_order_file','—')) ?></div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="file-box">
              <label class="form-label">استمارة الدورة (PDF)</label>
              <input type="file" name="form_file" class="form-control" accept="application/pdf">
              <div class="form-text">الحالي: <?= e(val($course,'form_file','—')) ?></div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="file-box">
              <label class="form-label">منهاج الدورة (PDF)</label>
              <input type="file" name="curriculum_file" class="form-control" accept="application/pdf">
              <div class="form-text">الحالي: <?= e(val($course,'curriculum_file','—')) ?></div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="file-box">
              <label class="form-label">الجهة المنفذة (PDF)</label>
              <input type="file" name="executing_entity_file" class="form-control" accept="application/pdf">
              <div class="form-text">الحالي: <?= e(val($course,'executing_entity_file','—')) ?></div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="file-box">
              <label class="form-label">محاور الدورة (PDF)</label>
              <input type="file" name="axes_file" class="form-control" accept="application/pdf">
              <div class="form-text">الحالي: <?= e(val($course,'axes_file','—')) ?></div>
            </div>
          </div>
        </div>

        <hr class="sep">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
          <div class="footer-note">سيتم تحديث نفس السجل فقط (courses.id = <?= e($id) ?>).</div>
          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn-soft success">💾 حفظ التعديلات</button>
            <a href="list.php" class="btn-soft">⬅ رجوع</a>
          </div>
        </div>

      </form>
    </div>

  </div>
</div>

<script>
/* Theme toggle */
(function(){
  const toggleBtn = document.getElementById("themeToggle");
  const themeIcon = document.getElementById("themeIcon");
  const themeText = document.getElementById("themeText");

  function applyTheme(mode){
    if(mode === "light"){
      document.body.classList.add("light-mode");
      themeIcon.textContent = "☀️";
      themeText.textContent = "الوضع الفاتح";
    }else{
      document.body.classList.remove("light-mode");
      themeIcon.textContent = "🌙";
      themeText.textContent = "الوضع الليلي";
    }
  }

  const saved = localStorage.getItem("theme");
  applyTheme(saved === "light" ? "light" : "dark");

  toggleBtn.addEventListener("click", ()=>{
    const isLight = document.body.classList.toggle("light-mode");
    localStorage.setItem("theme", isLight ? "light" : "dark");
    applyTheme(isLight ? "light" : "dark");
  });
})();

/* Course type sync (same as add.php behavior) */
(function(){
  const courseType=document.getElementById("courseType");
  const onlineSection=document.getElementById("onlineSection");
  const locationBox=document.getElementById("locationBox");
  const openLinkBtn=document.getElementById("openLinkBtn");
  const meetingLink=document.getElementById("meetingLink");

  function sync(){
    if(courseType.value==="الكتروني"){
      onlineSection.style.display="block";
      locationBox.style.display="none";
    }else{
      onlineSection.style.display="none";
      locationBox.style.display="block";
    }
  }

  courseType.addEventListener("change", sync);

  openLinkBtn.addEventListener("click",function(){
    const link=(meetingLink.value || '').trim();
    if(link!==""){
      window.open(link,"_blank");
    }else{
      alert("ادخل رابط المحاضرة اولاً");
    }
  });

  sync();
})();
</script>

</body>
</html>