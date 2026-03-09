<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

/* Helpers */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* البحث */
$search = "";
if (isset($_GET['search'])) {
    $search = (string)$_GET['search'];
}

/* جلب الدورات (NO CHANGE) */
$stmtCourses = $pdo->prepare("
    SELECT id, course_name 
    FROM courses 
    WHERE course_name LIKE ?
    ORDER BY id DESC
");
$stmtCourses->execute(["%$search%"]);
$courses = $stmtCourses->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {

        $pdo->beginTransaction();

        $course_id = $_POST['course_id'];
        $full_name = $_POST['full_name'];
        $phone = $_POST['phone'] ?? null;
        $gender = $_POST['gender'];
        $inside_university = $_POST['inside_university'];

        /* اللقب العلمي / الوظيفي */
        $academic_title     = $_POST['academic_title'];
        $job_specialization = $_POST['job_specialization'] ?? null;
        $academic_rank      = $_POST['academic_rank'] ?? null;

        $general_specialization = $_POST['general_specialization'] ?? null;
        $specific_specialization = $_POST['specific_specialization'] ?? null;
        $work_place = $_POST['work_place'];

        /* إضافة المشارك (NO CHANGE) */
        $stmt = $pdo->prepare("
            INSERT INTO participants
            (full_name, gender, inside_university, work_place,
             phone, general_specialization, specific_specialization,
             academic_title, job_specialization, academic_rank)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $full_name,
            $gender,
            $inside_university,
            $work_place,
            $phone,
            $general_specialization,
            $specific_specialization,
            $academic_title,
            $job_specialization,
            $academic_rank
        ]);

        $participant_id = $pdo->lastInsertId();

        /* ربطه بالدورة (NO CHANGE) */
        $stmt2 = $pdo->prepare("
            INSERT INTO course_participants
            (course_id, participant_id)
            VALUES (?,?)
        ");
        $stmt2->execute([$course_id,$participant_id]);

        $pdo->commit();

        $success = "✅ تمت إضافة المشارك وربطه بالدورة بنجاح";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>إضافة مشارك إلى دورة | نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* -------------------- Design System (matches dashboard/list/edit) -------------------- */
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
    --info1:#06b6d4; --info2:#0e7490;
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
    max-width: 980px;
    margin:0 auto;
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

/* Form */
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
.form-control::placeholder{ color: rgba(255,255,255,.55); font-weight:800; }
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

.footer-note{
    color:var(--muted);
    font-weight:900;
    font-size:12px;
    text-align:center;
}

/* Light mode */
body.light-mode{
    background:#f1f5f9 !important;
    color:#0f172a !important;
}
body.light-mode .overlay{
    background:linear-gradient(180deg, rgba(255,255,255,.86), rgba(255,255,255,.92));
}
body.light-mode .glass,
body.light-mode .glass.soft,
body.light-mode .card-soft{
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
          <div class="brand-badge" title="مشارك">👤</div>
          <div>
            <h1>إضافة مشارك وربطه بدورة</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
          <a class="btn-soft" href="../courses/list.php">📋 الدورات</a>
          <a class="btn-soft danger" href="../../auth/logout.php">🚪 خروج</a>
        </div>
      </div>

      <?php if(isset($success)): ?>
        <hr class="sep">
        <div class="alert alert-success mb-0 text-center fw-bold"><?= e($success) ?></div>
      <?php endif; ?>

      <?php if(isset($error)): ?>
        <hr class="sep">
        <div class="alert alert-danger mb-0 text-center fw-bold"><?= e($error) ?></div>
      <?php endif; ?>
    </div>

    <!-- Search course -->
    <div class="glass soft">
      <div class="section-title">
        <h2>🔎 بحث عن دورة</h2>
        <div class="meta">فلترة قائمة الدورات بالاسم (عرض فقط)</div>
      </div>

      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-9">
          <label class="form-label">اسم الدورة</label>
          <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="اكتب اسم الدورة...">
        </div>
        <div class="col-lg-3 d-flex gap-2">
          <button class="btn-soft w-100 justify-content-center" type="submit" style="padding:12px 14px;">بحث</button>
          <a class="btn-soft w-100 justify-content-center" href="add.php" style="padding:12px 14px;">إعادة</a>
        </div>
      </form>
    </div>

    <!-- Add participant form -->
    <div class="glass soft">
      <div class="section-title">
        <h2>🧾 بيانات المشارك</h2>
        <div class="meta">سيتم إنشاء مشارك جديد ثم ربطه بالدورة</div>
      </div>

      <form method="post">

        <div class="card-soft mb-3">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">اختيار الدورة</label>
              <select name="course_id" class="form-select" required>
                <option value="">اختر الدورة</option>
                <?php foreach($courses as $course): ?>
                  <option value="<?= e($course['id']) ?>"><?= e($course['course_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="card-soft mb-3">
          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label">الاسم الكامل (full_name)</label>
              <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="col-lg-6">
              <label class="form-label">رقم الهاتف</label>
              <input type="text" name="phone" class="form-control">
            </div>

            <div class="col-lg-6">
              <label class="form-label">الجنس</label>
              <select name="gender" class="form-select" required>
                <option value="">اختر</option>
                <option value="ذكر">ذكر</option>
                <option value="أنثى">أنثى</option>
              </select>
            </div>

            <div class="col-lg-6">
              <label class="form-label">داخل / خارج الجامعة</label>
              <select name="inside_university" class="form-select" required>
                <option value="">اختر</option>
                <option value="داخل">داخل الجامعة</option>
                <option value="خارج">خارج الجامعة</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card-soft mb-3">
          <div class="row g-3">
            <div class="col-lg-6">
              <label class="form-label">اللقب العلمي / الوظيفي</label>
              <select name="academic_title" id="titleSelect" class="form-select" required>
                <option value="">اختر</option>
                <option value="فني">فني</option>
                <option value="إداري">إداري</option>
                <option value="لقب علمي">لقب علمي</option>
              </select>
            </div>

            <div class="col-lg-6" id="jobBox" style="display:none;">
              <label class="form-label" id="jobLabel">الاختصاص</label>
              <input type="text" name="job_specialization" class="form-control">
            </div>

            <div class="col-lg-6" id="academicRankBox" style="display:none;">
              <label class="form-label">المرتبة العلمية</label>
              <select name="academic_rank" class="form-select">
                <option value="">اختر</option>
                <option value="مدرس مساعد">مدرس مساعد</option>
                <option value="مدرس">مدرس</option>
                <option value="استاذ مساعد">استاذ مساعد</option>
                <option value="استاذ">استاذ</option>
              </select>
            </div>

            <div class="col-lg-6">
              <label class="form-label">الاختصاص العام</label>
              <input type="text" name="general_specialization" class="form-control">
            </div>

            <div class="col-lg-6">
              <label class="form-label">الاختصاص الدقيق</label>
              <input type="text" name="specific_specialization" class="form-control">
            </div>

            <div class="col-12">
              <label class="form-label">مكان العمل</label>
              <input type="text" name="work_place" class="form-control mb-0" required>
            </div>
          </div>
        </div>

        <hr class="sep">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="footer-note">لن يتم تعديل بيانات قديمة — سيتم إدراج مشارك جديد فقط.</div>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn-soft success" type="submit">💾 حفظ</button>
            <a href="../dashboard.php" class="btn-soft">⬅ رجوع</a>
          </div>
        </div>

      </form*
