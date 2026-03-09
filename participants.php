<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

/* -------------------- Helpers (display only) -------------------- */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$course_id = $_GET['course_id'] ?? null;
if (!$course_id) {
    header("Location: list.php");
    exit;
}

/* ✅ بحث بالاسم (GET) - للعرض فقط */
$q = trim((string)($_GET['q'] ?? ''));

/* جلب الدورة (NO CHANGE) */
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(cp.participant_id) AS registered_count
    FROM courses c
    LEFT JOIN course_participants cp 
        ON c.id = cp.course_id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: list.php");
    exit;
}

$max = $course['max_participants'];
$current = $course['registered_count'];
$isFull = ($max && $current >= $max);

/* إضافة مشارك (NO CHANGE) */
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$isFull) {

    $participant_id = $_POST['participant_id'];

    $check = $pdo->prepare("
        SELECT 1 FROM course_participants
        WHERE course_id=? AND participant_id=?
    ");
    $check->execute([$course_id, $participant_id]);

    if ($check->rowCount() == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO course_participants
            (course_id, participant_id)
            VALUES (?,?)
        ");
        $stmt->execute([$course_id, $participant_id]);
    }

    header("Location: participants.php?course_id=$course_id");
    exit;
}

/* جلب المشاركين (NO CHANGE) */
$stmt = $pdo->query("SELECT * FROM participants ORDER BY full_name");
$participants = $stmt->fetchAll();

/* ✅ المشاركون المسجلون + بحث بالاسم (بدون تغيير بيانات) */
if ($q !== '') {
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM participants p
        JOIN course_participants cp 
            ON p.id = cp.participant_id
        WHERE cp.course_id = ?
          AND p.full_name LIKE ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$course_id, "%{$q}%"]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM participants p
        JOIN course_participants cp 
            ON p.id = cp.participant_id
        WHERE cp.course_id = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$course_id]);
}
$linkedParticipants = $stmt->fetchAll();

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');

$percentage = 0;
if ($max && $max > 0) {
    $percentage = min(100, (int)round(($current / $max) * 100));
}
$barClass = 'bg-success';
if ($percentage >= 80) $barClass = 'bg-danger';
elseif ($percentage >= 50) $barClass = 'bg-warning';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>مشاركو الدورة | نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* (نفس CSS الذي أرسلته بدون تغيير) */

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
    max-width:1240px;
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
    min-width: 260px;
}
.brand-badge{
    width:46px; height:46px;
    border-radius:16px;
    background:linear-gradient(135deg,var(--info1),var(--info2));
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
    letter-spacing:.2px;
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
    margin: 12px 0;
}

.table-soft{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    overflow:hidden;
    border-radius: 16px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.08);
}
.table-soft th, .table-soft td{
    padding:10px 12px;
    border-bottom:1px solid rgba(255,255,255,.12);
    font-weight:900;
    vertical-align:middle;
    text-align:center;
}
.table-soft th{
    background: rgba(0,0,0,.18);
    color: rgba(255,255,255,.85);
    font-size:12px;
}
.table-soft td{
    color:#fff;
    font-size:13px;
}
.table-soft tr:last-child td{ border-bottom:none; }

.pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.10);
    font-weight:1000;
    font-size:12px;
    color:#fff;
}
.pill.success{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.14); }
.pill.warn{ border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.14); }
.pill.danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.14); }
.pill.info{ border-color: rgba(6,182,212,.35); background: rgba(6,182,212,.14); }

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

.btn-mini{
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.10);
    color:#fff;
    border-radius:12px;
    padding:6px 10px;
    font-weight:1000;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:44px;
    transition:.18s ease;
}
.btn-mini:hover{ transform:translateY(-2px); background:rgba(255,255,255,.14); }
.btn-mini.success{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.14); }
.btn-mini.danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.14); }
.btn-mini.info{ border-color: rgba(6,182,212,.35); background: rgba(6,182,212,.14); }

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
body.light-mode .glass.soft{
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
body.light-mode .table-soft{ border:1px solid rgba(15,23,42,.10); }
body.light-mode .table-soft th{
    background: rgba(15,23,42,.05);
    color: rgba(15,23,42,.75);
}
body.light-mode .table-soft td{
    color:#0f172a;
    border-bottom:1px solid rgba(15,23,42,.08);
}
body.light-mode .pill,
body.light-mode .badge-soft,
body.light-mode .btn-soft,
body.light-mode .btn-mini{
    color:#0f172a;
}
</style>
</head>

<body dir="rtl">
<div class="overlay">
  <div class="content">

    <!-- Topbar -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge" title="المشاركون">👥</div>
          <div>
            <h1>مشاركو دورة: <?= e($course['course_name']) ?></h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">Course ID: <?= e($course_id) ?></span>

          <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <a class="btn-soft" href="list.php">📋 قائمة الدورات</a>
          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
          <a class="btn-soft danger" href="../../auth/logout.php">🚪 خروج</a>
        </div>
      </div>

      <hr class="sep">

      <div class="row g-3 align-items-center">
        <div class="col-lg-6">
          <?php if ($isFull): ?>
            <div class="alert alert-danger mb-0 text-center fw-bold">
              🚫 الدورة مكتملة ولا يمكن إضافة مشاركين جدد
            </div>
          <?php else: ?>
            <div class="alert alert-secondary mb-0" style="font-weight:900;">
              ✅ يمكنك إضافة مشاركين للدورة.
            </div>
          <?php endif; ?>
        </div>

        <div class="col-lg-6">
          <div class="p-3 rounded-4" style="border:1px solid rgba(255,255,255,.14); background: rgba(0,0,0,.18);">
            <div class="d-flex justify-content-between align-items-center gap-2">
              <div style="font-weight:1000;">السعة</div>
              <span class="pill <?= $isFull ? 'danger' : 'info' ?>">
                <?= (int)$current ?> / <?= ($max ?: "غير محدد") ?>
              </span>
            </div>

            <?php if ($max && $max > 0): ?>
              <div class="progress mt-2" style="height:18px; border-radius:999px; overflow:hidden;">
                <div class="progress-bar <?= e($barClass) ?>" style="width: <?= (int)$percentage ?>%;">
                  <?= (int)$percentage ?>%
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Add participant -->
    <div class="glass soft">
      <div class="section-title">
        <h2>➕ إضافة مشارك</h2>
        <div class="meta">لن يتم إضافة مشارك إذا كان مسجلًا مسبقًا</div>
      </div>

      <form method="post" class="row g-3 align-items-end">
        <div class="col-lg-9">
          <label class="form-label">اختر مشارك</label>
          <select name="participant_id" class="form-select" required <?= $isFull ? 'disabled' : '' ?>>
            <option value="">اختر مشارك</option>
            <?php foreach($participants as $p): ?>
              <option value="<?= e($p['id']) ?>"><?= e($p['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-3">
          <button class="btn-soft w-100 justify-content-center" style="padding:12px 14px;" <?= $isFull ? 'disabled' : '' ?>>
            ➕ إضافة
          </button>
        </div>
      </form>
    </div>

    <!-- ✅ Search registered participants by name -->
    <div class="glass soft">
      <div class="section-title">
        <h2>🔎 بحث باسم المشترك</h2>
        <div class="meta">يبحث داخل المشاركين المسجلين في هذه الدورة فقط</div>
      </div>

      <form method="get" class="row g-3 align-items-end">
        <input type="hidden" name="course_id" value="<?= e($course_id) ?>">
        <div class="col-lg-9">
          <label class="form-label">اسم المشترك (full_name)</label>
          <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="اكتب جزء من الاسم...">
        </div>
        <div class="col-lg-3 d-flex gap-2">
          <button class="btn-soft w-100 justify-content-center" type="submit" style="padding:12px 14px;">بحث</button>
          <a class="btn-soft w-100 justify-content-center" href="participants.php?course_id=<?= e($course_id) ?>" style="padding:12px 14px;">إعادة</a>
        </div>
      </form>
    </div>

    <!-- Linked participants -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 المشاركون المسجلون</h2>
        <div class="meta">إجمالي: <?= (int)count($linkedParticipants) ?><?= ($q !== '' ? ' • نتائج البحث' : '') ?></div>
      </div>

      <div class="table-responsive">
        <table class="table-soft">
          <thead>
            <tr>
              <th style="width:70px;">#</th>
              <th style="text-align:right;">الاسم</th>
              <th style="width:160px;">الشهادة</th>
              <th style="width:140px;">حذف</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($linkedParticipants) > 0): ?>
              <?php foreach($linkedParticipants as $i => $p): ?>
                <tr>
                  <td><?= (int)($i+1) ?></td>
                  <td style="text-align:right;"><?= e($p['full_name']) ?></td>
                  <td>
                    <a class="btn-mini success"
                       href="certificate.php?course_id=<?= e($course_id) ?>&participant_id=<?= e($p['id']) ?>"
                       target="_blank"
                       title="فتح الشهادة">
                      🎓 شهادة
                    </a>
                  </td>
                  <td>
                    <a class="btn-mini danger"
                       href="remove_participant.php?course_id=<?= e($course_id) ?>&participant_id=<?= e($p['id']) ?>"
                       onclick="return confirm('حذف المشترك؟')"
                       title="حذف">
                      🗑
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4">
                  <?= ($q !== '') ? 'لا توجد نتائج مطابقة لعبارة البحث.' : 'لا يوجد مشاركون مسجلون لهذه الدورة.' ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <hr class="sep">

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="footer-note">إدارة المشاركين لا تغيّر بيانات المشاركين الأساسية — فقط الربط مع الدورة.</div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="list.php" class="btn-soft">⬅ رجوع</a>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
/* Theme toggle (same feel) */
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
</script>

</body>
</html>