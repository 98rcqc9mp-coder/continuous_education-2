<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

/* Helpers */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$search = $_GET['search'] ?? "";
$search = trim((string)$search);

/* جلب المشاركين مع الدورات (NO CHANGE in logic) */
if ($search != "") {

    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.full_name,
            p.job_title,
            GROUP_CONCAT(c.course_name SEPARATOR ' , ') AS courses
        FROM participants p
        LEFT JOIN course_participants cp ON p.id = cp.participant_id
        LEFT JOIN courses c ON cp.course_id = c.id
        WHERE p.full_name LIKE ?
        GROUP BY p.id
        ORDER BY p.id DESC
    ");

    $stmt->execute(["%$search%"]);
    $participants = $stmt->fetchAll();

} else {

    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.full_name,
            p.job_title,
            GROUP_CONCAT(c.course_name SEPARATOR ' , ') AS courses
        FROM participants p
        LEFT JOIN course_participants cp ON p.id = cp.participant_id
        LEFT JOIN courses c ON cp.course_id = c.id
        GROUP BY p.id
        ORDER BY p.id DESC
    ");

    $participants = $stmt->fetchAll();
}

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>بحث المشتركين | نظام إدارة ��لتعليم المستمر</title>
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
    background:linear-gradient(135deg,var(--violet1, #a855f7),var(--violet2, #6d28d9));
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

.searchbar{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
    width:100%;
}
.searchbar .field{
    flex: 1 1 320px;
    display:flex;
    gap:10px;
    align-items:center;
    padding:10px 12px;
    border-radius: 14px;
    border:1px solid rgba(255,255,255,.16);
    background: rgba(0,0,0,.18);
}
.searchbar input{
    width:100%;
    border:none;
    outline:none;
    background:transparent;
    color:#fff;
    font-weight:1000;
}
.searchbar input::placeholder{ color: rgba(255,255,255,.55); font-weight:900; }
.searchbar .hint{ color: var(--muted); font-weight:800; font-size:12px; }

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
    transition:.18s ease;
}
.btn-mini:hover{ transform:translateY(-2px); background:rgba(255,255,255,.14); }
.btn-mini.danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.14); }

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
body.light-mode .btn-soft,
body.light-mode .btn-mini,
body.light-mode .badge-soft{
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
          <div class="brand-badge" title="المشتركين">👥</div>
          <div>
            <h1>إدارة المشتركين</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">الإجمالي: <?= (int)count($participants) ?></span>

          <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <a class="btn-soft" href="../dashboard.php">⬅ رجوع</a>
          <a class="btn-soft danger" href="../../auth/logout.php">🚪 خروج</a>
        </div>
      </div>

      <hr class="sep">

      <!-- Searchbar -->
      <form class="searchbar" method="get" action="">
        <div class="field">
          <span aria-hidden="true">🔎</span>
          <input type="text"
                 name="search"
                 placeholder="ابحث باسم المشترك (full_name)..."
                 value="<?= e($search) ?>"
                 autocomplete="off">
        </div>
        <button class="btn-soft" type="submit">بحث</button>
        <a class="btn-soft" href="list.php">عرض الكل</a>
        <div class="hint">البحث للعرض فقط — لا يغيّر البيانات.</div>
      </form>
    </div>

    <!-- Table -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 النتائج</h2>
        <div class="meta"><?= ($search !== '' ? 'نتائج مطابقة' : 'عرض الكل') ?></div>
      </div>

      <div class="table-responsive">
        <table class="table-soft">
          <thead>
            <tr>
              <th style="width:80px;">#</th>
              <th style="text-align:right;">الاسم الكامل</th>
              <th style="width:160px;">الوظيفة</th>
              <th style="text-align:right;">الدورات</th>
              <th style="width:140px;">التحكم</th>
            </tr>
          </thead>

          <tbody>
          <?php if(count($participants) > 0): ?>
            <?php foreach($participants as $i => $p): ?>
              <tr>
                <td><?= (int)($i + 1) ?></td>
                <td style="text-align:right;"><?= e($p['full_name']) ?></td>
                <td><?= e($p['job_title']) ?></td>
                <td style="text-align:right;"><?= $p['courses'] ? e($p['courses']) : "—" ?></td>
                <td>
                  <a href="delete.php?id=<?= e($p['id']) ?>"
                     class="btn-mini danger"
                     onclick="return confirm('هل أنت متأكد من حذف هذا المشترك؟');"
                     title="حذف">
                     🗑 حذف
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5">لا توجد نتائج</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <hr class="sep">

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="footer-note">هذه الصفحة للعرض والبحث فقط.</div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn-soft" href="../dashboard.php">🏠 لوحة التحكم</a>
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