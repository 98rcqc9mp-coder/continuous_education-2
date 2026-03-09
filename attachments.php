<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

/* Helpers */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$course_id = $_GET['course_id'] ?? 0;
$course_id = (int)$course_id;

/* جلب الدورة (NO CHANGE) */
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id=?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    die("الدورة غير موجودة");
}

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');

$uploadBase = "/continuous_education/attachments/uploads/";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>مرفقات الدورة | نظام إدارة التعليم المستمر</title>
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
    background:linear-gradient(135deg,var(--success1),var(--success2));
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
    padding:12px 12px;
    border-bottom:1px solid rgba(255,255,255,.12);
    font-weight:900;
    vertical-align:top;
}
.table-soft th{
    width:260px;
    background: rgba(0,0,0,.18);
    color: rgba(255,255,255,.85);
    font-size:12px;
    text-align:right;
}
.table-soft td{
    color:#fff;
    font-size:13px;
}
.table-soft tr:last-child th,
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
.pill.info{ border-color: rgba(6,182,212,.35); background: rgba(6,182,212,.14); }

.file-btn{
    border:1px solid rgba(255,255,255,.18);
    border-radius:14px;
    padding:10px 14px;
    font-weight:1000;
    display:inline-flex;
    align-items:center;
    gap:8px;
    transition:.2s ease;
    box-shadow: var(--shadow2);
}
.file-btn:hover{ transform:translateY(-2px); }
.file-btn.admin{ background:linear-gradient(135deg,var(--primary1),var(--primary2)); color:#fff; }
.file-btn.form { background:linear-gradient(135deg,var(--success1),var(--success2)); color:#fff; }
.file-btn.cur  { background:linear-gradient(135deg,var(--warn1),var(--warn2)); color:#fff; }

.subnote{
    color: rgba(255,255,255,.70);
    font-weight:800;
    font-size:12px;
    margin-top:6px;
    white-space:pre-line;
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
body.light-mode .glass.soft{
    background:rgba(255,255,255,.72);
    border:1px solid rgba(15,23,42,.10);
    color:#0f172a;
    box-shadow:0 18px 45px rgba(2,6,23,.12);
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
body.light-mode .btn-soft{
    color:#0f172a;
}
body.light-mode .subnote{ color: rgba(15,23,42,.65); }
</style>
</head>

<body dir="rtl">
<div class="overlay">
  <div class="content">

    <!-- Topbar -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge" title="المرفقات">📂</div>
          <div>
            <h1>مرفقات دورة: <?= e($course['course_name']) ?></h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">Course ID: <?= e($course_id) ?></span>

          <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <a class="btn-soft" href="list.php">⬅ رجوع</a>
          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📎 تفاصيل المرفقات</h2>
        <div class="meta">عرض احترافي بدون تغيير البيانات</div>
      </div>

      <div class="table-responsive">
        <table class="table-soft">
          <tbody>
            <tr>
              <th>محاور الدورة</th>
              <td>
                <?php if (!empty($course['axes'])): ?>
                  <div class="subnote"><?= nl2br(e($course['axes'])) ?></div>
                <?php else: ?>
                  <span class="pill info">لا توجد محاور مكتوبة</span>
                <?php endif; ?>
              </td>
            </tr>

            <tr>
              <th>الأمر الإداري</th>
              <td>
                <?php if(!empty($course['admin_order_file'])): ?>
                  <a target="_blank" class="file-btn admin"
                     href="<?= e($uploadBase . $course['admin_order_file']) ?>">
                    📄 فتح الملف
                  </a>
                  <div class="subnote">الملف: <?= e($course['admin_order_file']) ?></div>
                <?php else: ?>
                  <span class="pill info">غير مرفوع</span>
                <?php endif; ?>
              </td>
            </tr>

            <tr>
              <th>استمارة الدورة</th>
              <td>
                <?php if(!empty($course['form_file'])): ?>
                  <a target="_blank" class="file-btn form"
                     href="<?= e($uploadBase . $course['form_file']) ?>">
                    📄 فتح الملف
                  </a>
                  <div class="subnote">الملف: <?= e($course['form_file']) ?></div>
                <?php else: ?>
                  <span class="pill info">غير مرفوعة</span>
                <?php endif; ?>
              </td>
            </tr>

            <tr>
              <th>منهاج الدورة</th>
              <td>
                <?php if(!empty($course['curriculum_file'])): ?>
                  <a target="_blank" class="file-btn cur"
                     href="<?= e($uploadBase . $course['curriculum_file']) ?>">
                    📄 فتح الملف
                  </a>
                  <div class="subnote">الملف: <?= e($course['curriculum_file']) ?></div>
                <?php else: ?>
                  <span class="pill info">غير مرفوع</span>
                <?php endif; ?>
              </td>
            </tr>

          </tbody>
        </table>
      </div>

      <hr class="sep">

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="footer-note">المرفقات للعرض فقط — لا تغيّر قاعدة البيانات.</div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="list.php" class="btn-soft">⬅ رجوع</a>
        </div>
      </div>
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
</script>

</body>
</html>