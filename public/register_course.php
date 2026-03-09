<?php
/**
 * public/register_course.php — نموذج التسجيل العام في الدورات
 * متاح للعموم بدون تسجيل دخول
 */
require_once __DIR__ . '/../db.php';

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$success = null;
$error   = null;

/* جلب الدورات المفتوحة */
$openCourses = $pdo->query("
    SELECT id, course_name, start_date, end_date, location, max_participants,
           (SELECT COUNT(*) FROM course_participants WHERE course_id = courses.id) AS registered_count
    FROM courses
    WHERE status = 'مفتوحة'
    ORDER BY start_date ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId  = (int)($_POST['course_id'] ?? 0);
    $fullName  = trim((string)($_POST['full_name'] ?? ''));
    $phone     = trim((string)($_POST['phone'] ?? '')) ?: null;
    $email     = trim((string)($_POST['email'] ?? '')) ?: null;
    $gender    = $_POST['gender'] ?? null;
    $workPlace = trim((string)($_POST['work_place'] ?? '')) ?: null;

    if (!$courseId || !$fullName) {
        $error = 'يرجى تعبئة الاسم الكامل واختيار الدورة.';
    } else {
        /* التحقق من وجود جدول الطلبات */
        $tCheck = $pdo->query("SHOW TABLES LIKE 'course_registration_requests'")->fetchColumn();
        if (!$tCheck) {
            $error = 'نظام التسجيل غير مفعّل حالياً. يرجى التواصل مع الإدارة.';
        } else {
            try {
                $ins = $pdo->prepare("
                    INSERT INTO course_registration_requests
                        (course_id, full_name, phone, email, gender, work_place)
                    VALUES (?,?,?,?,?,?)
                ");
                $ins->execute([$courseId, $fullName, $phone, $email, $gender, $workPlace]);
                $success = '✅ تم إرسال طلبك بنجاح! ستتم مراجعته من قِبل الإدارة وإبلاغك بالنتيجة.';
            } catch (Exception $ex) {
                $error = 'حدث خطأ أثناء حفظ الطلب. يرجى المحاولة لاحقاً.';
            }
        }
    }
}

$todayLabel = date('Y-m-d');
$nowLabel   = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>التسجيل في الدورات | نظام التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --overlay1:rgba(0,0,0,.55); --overlay2:rgba(0,0,0,.75);
    --card:rgba(255,255,255,.10); --card2:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.14); --text:#ffffff; --muted:rgba(255,255,255,.74);
    --shadow:0 20px 60px rgba(0,0,0,.55); --shadow2:0 14px 35px rgba(0,0,0,.35);
    --primary1:#2563eb; --primary2:#1e40af;
    --warn1:#f59e0b; --warn2:#d97706;
    --success1:#22c55e; --success2:#16a34a;
    --radius:18px;
}
*{box-sizing:border-box;}
body{
    margin:0;
    font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Cairo",sans-serif;
    background:linear-gradient(135deg,#070a12,#0b1020);
    color:var(--text);font-size:14px;
}
a{color:inherit;text-decoration:none;}
a:hover{color:inherit;}
.overlay{background:linear-gradient(180deg,var(--overlay1),var(--overlay2));min-height:100vh;padding:22px 14px 34px;}
.content{max-width:900px;margin:0 auto;}
.glass{background:var(--card);border:1px solid var(--border);backdrop-filter:blur(16px);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:14px;}
.glass.soft{background:var(--card2);box-shadow:var(--shadow2);}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-badge{width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,var(--warn1),var(--warn2));box-shadow:0 12px 25px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto;}
.brand h1{margin:0;font-size:18px;font-weight:800;}
.brand small{display:block;color:var(--muted);font-size:12px;font-weight:700;margin-top:2px;}
.actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.btn-soft{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.10);color:#fff;padding:10px 14px;border-radius:14px;font-weight:800;transition:.2s ease;display:inline-flex;align-items:center;gap:8px;cursor:pointer;}
.btn-soft:hover{transform:translateY(-2px);background:rgba(255,255,255,.14);}
.btn-soft.primary{background:linear-gradient(135deg,rgba(37,99,235,.55),rgba(30,64,175,.25));border-color:rgba(37,99,235,.45);}
.btn-soft.success{background:linear-gradient(135deg,rgba(34,197,94,.40),rgba(22,163,74,.22));border-color:rgba(34,197,94,.40);}
.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.section-title h2{margin:0;font-weight:800;font-size:16px;}
.form-label{font-weight:800;color:rgba(255,255,255,.92);margin-bottom:6px;}
.form-control,.form-select{border-radius:14px !important;padding:12px;font-weight:700;color:#fff;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.16);}
.form-control:focus,.form-select:focus{border-color:rgba(37,99,235,.55);box-shadow:0 0 0 .2rem rgba(37,99,235,.20);background:rgba(0,0,0,.22);color:#fff;}
.form-control::placeholder{color:rgba(255,255,255,.45);}
hr.sep{border:none;border-top:1px solid rgba(255,255,255,.14);margin:12px 0;}
.course-card{border-radius:14px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.18);padding:14px 16px;margin-bottom:10px;cursor:pointer;transition:.2s;}
.course-card:hover{border-color:rgba(37,99,235,.45);background:rgba(37,99,235,.10);}
.course-card.selected{border-color:rgba(34,197,94,.45);background:rgba(34,197,94,.10);}
.pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.10);font-size:12px;font-weight:700;}
.pill.success{border-color:rgba(34,197,94,.35);background:rgba(34,197,94,.12);}
.pill.warn{border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.12);}
</style>
</head>
<body>
<div class="overlay">
  <div class="content">

    <!-- Topbar -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge">📝</div>
          <div>
            <h1>التسجيل في الدورات التدريبية</h1>
            <small>أرسل طلبك وستتم مراجعته من قِبل الإدارة • <?= e($todayLabel) ?></small>
          </div>
        </div>
        <div class="actions">
          <a class="btn-soft" href="../login.php">🎛 دخول الإدارة</a>
          <a class="btn-soft" href="../verify_certificate.php">🔍 التحقق من شهادة</a>
        </div>
      </div>

      <?php if ($success): ?>
      <hr class="sep">
      <div class="alert alert-success mb-0 fw-bold text-center"><?= e($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
      <hr class="sep">
      <div class="alert alert-danger mb-0 fw-bold text-center"><?= e($error) ?></div>
      <?php endif; ?>
    </div>

    <!-- الدورات المفتوحة -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📚 الدورات المتاحة للتسجيل</h2>
        <div style="color:var(--muted);font-size:12px;"><?= count($openCourses) ?> دورة</div>
      </div>

      <?php if (empty($openCourses)): ?>
      <p style="color:var(--muted);text-align:center;padding:20px 0;">
        لا توجد دورات مفتوحة للتسجيل حالياً.
      </p>
      <?php else: ?>
      <div style="max-height:320px;overflow-y:auto;padding-left:4px;">
        <?php foreach ($openCourses as $c): ?>
        <div class="course-card" id="cc-<?= e($c['id']) ?>" onclick="selectCourse(<?= (int)$c['id'] ?>, <?= json_encode($c['course_name'], JSON_UNESCAPED_UNICODE) ?>)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
            <div>
              <div style="font-weight:800;font-size:15px;margin-bottom:4px;"><?= e($c['course_name']) ?></div>
              <div style="color:var(--muted);font-size:12px;">
                📅 <?= e($c['start_date'] ?? '') ?> – <?= e($c['end_date'] ?? '') ?>
                <?php if ($c['location']): ?>• 📍 <?= e($c['location']) ?><?php endif; ?>
              </div>
            </div>
            <span class="pill success">مفتوحة</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- نموذج التسجيل -->
    <?php if (!$success): ?>
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 نموذج طلب التسجيل</h2>
      </div>

      <form method="post">
        <input type="hidden" name="course_id" id="courseIdInput" required>

        <div class="mb-3">
          <label class="form-label">الدورة المختارة</label>
          <input type="text" id="courseNameDisplay" class="form-control" readonly
                 placeholder="اضغط على الدورة أعلاه لاختيارها..."
                 style="cursor:pointer;">
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <label class="form-label">الاسم الكامل *</label>
            <input type="text" name="full_name" class="form-control" required
                   value="<?= e($_POST['full_name'] ?? '') ?>">
          </div>

          <div class="col-lg-6">
            <label class="form-label">رقم الهاتف</label>
            <input type="text" name="phone" class="form-control"
                   value="<?= e($_POST['phone'] ?? '') ?>">
          </div>

          <div class="col-lg-6">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="email" name="email" class="form-control" dir="ltr"
                   placeholder="example@domain.com"
                   value="<?= e($_POST['email'] ?? '') ?>">
          </div>

          <div class="col-lg-6">
            <label class="form-label">الجنس</label>
            <select name="gender" class="form-select">
              <option value="">اختر (اختياري)</option>
              <option value="ذكر">ذكر</option>
              <option value="أنثى">أنثى</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">جهة العمل / المنظمة</label>
            <input type="text" name="work_place" class="form-control"
                   value="<?= e($_POST['work_place'] ?? '') ?>">
          </div>
        </div>

        <hr class="sep" style="margin-top:16px;">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <small style="color:var(--muted);">* حقل إلزامي</small>
          <button type="submit" class="btn-soft success" style="padding:12px 22px;font-size:15px;">
            📤 إرسال طلب التسجيل
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function selectCourse(id, name) {
  document.getElementById('courseIdInput').value = id;
  document.getElementById('courseNameDisplay').value = name;

  document.querySelectorAll('.course-card').forEach(el => el.classList.remove('selected'));
  const card = document.getElementById('cc-' + id);
  if (card) card.classList.add('selected');
}
</script>
</body>
</html>
