<?php
session_start();
require_once __DIR__."/../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$type = $_GET['type'] ?? 'all';

/* ✅ ملاحظة: منطق الفلترة نفسه كما هو */
$sql="SELECT * FROM participants";

if($type=='male')
    $sql.=" WHERE gender='ذكر'";
elseif($type=='female')
    $sql.=" WHERE gender='أنثى'";
elseif($type=='inside')
    $sql.=" WHERE inside_university='داخل'";
elseif($type=='outside')
    $sql.=" WHERE inside_university='خارج'";

$stmt=$pdo->query($sql);
$participants=$stmt->fetchAll(PDO::FETCH_ASSOC);

/* Helpers */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');

$typeLabel = 'الكل';
if ($type === 'male') $typeLabel = 'ذكور';
elseif ($type === 'female') $typeLabel = 'إناث';
elseif ($type === 'inside') $typeLabel = 'داخل الجامعة';
elseif ($type === 'outside') $typeLabel = 'خارج الجامعة';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>قائمة المشاركين | نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* (نفس تصميمك السابق) */
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
    background:url("../../assets/img/dashboard-bg.jpg") no-repeat center center fixed;
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
.btn-soft.primary{
    background:linear-gradient(135deg, rgba(37,99,235,.45), rgba(30,64,175,.22));
    border-color: rgba(37,99,235,.45);
}

.badge-soft{
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.16);
    color:#fff;
    padding:6px 10px;
    border-radius:999px;
    font-weight:1000;
    font-size:12px;
}

.tabs{ display:flex; gap:8px; flex-wrap:wrap; }
.tablink{
    border:1px solid rgba(255,255,255,.16);
    background:rgba(255,255,255,.10);
    color:#fff;
    padding:8px 12px;
    border-radius:12px;
    font-weight:1000;
    font-size:12px;
    transition:.2s ease;
}
.tablink:hover{ transform:translateY(-2px); }
.tablink.active{
    background: linear-gradient(135deg, rgba(6,182,212,.45), rgba(14,116,144,.25));
    border-color: rgba(6,182,212,.45);
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

.footer-note{
    color:var(--muted);
    font-weight:900;
    font-size:12px;
    text-align:center;
}

/* Print/PDF styling */
@media print {
    body{ background:#fff !important; color:#000 !important; }
    .overlay{ background:#fff !important; padding:0 !important; }
    .glass{ box-shadow:none !important; border:1px solid #ddd !important; background:#fff !important; color:#000 !important; }
    .glass.soft{ background:#fff !important; }
    .btn-soft, .tabs, #themeToggle, .no-print{ display:none !important; }
    .badge-soft{ color:#000 !important; border-color:#ddd !important; background:#f5f5f5 !important; }
    .table-soft{ border-color:#ddd !important; }
    .table-soft th{ color:#000 !important; background:#f3f4f6 !important; }
    .table-soft td{ color:#000 !important; }
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
body.light-mode .badge-soft,
body.light-mode .tablink{
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
          <div class="brand-badge" title="المشاركين">👥</div>
          <div>
            <h1>قائمة المشاركين</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">الفلتر: <?= e($typeLabel) ?></span>
          <span class="badge-soft">العدد: <?= (int)count($participants) ?></span>

          <button id="themeToggle" class="btn-soft no-print" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <!-- ✅ طباعة / حفظ PDF -->
          <button class="btn-soft primary no-print" type="button" onclick="window.print()">🖨️ طباعة PDF</button>

          <a class="btn-soft no-print" href="../dashboard.php">⬅ رجوع</a>
        </div>
      </div>

      <hr class="sep">

      <!-- Filters -->
      <div class="no-print">
        <div style="font-weight:1000; margin-bottom:10px;">🧭 الفلاتر</div>
        <div class="tabs">
          <a class="tablink <?= ($type==='all'?'active':'') ?>" href="participants_report.php?type=all">الكل</a>
          <a class="tablink <?= ($type==='male'?'active':'') ?>" href="participants_report.php?type=male">ذكور</a>
          <a class="tablink <?= ($type==='female'?'active':'') ?>" href="participants_report.php?type=female">إناث</a>
          <a class="tablink <?= ($type==='inside'?'active':'') ?>" href="participants_report.php?type=inside">داخل الجامعة</a>
          <a class="tablink <?= ($type==='outside'?'active':'') ?>" href="participants_report.php?type=outside">خارج الجامعة</a>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="glass soft">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        <div style="font-weight:1000;">📋 النتائج</div>
        <div class="footer-note">يمكنك اختيار "Save as PDF" من نافذة الطباعة.</div>
      </div>

      <div class="table-responsive">
        <table class="table-soft">
          <thead>
            <tr>
              <th style="width:80px;">#</th>
              <th style="text-align:right;">الاسم</th>
              <th style="width:140px;">الجنس</th>
              <th style="width:170px;">الجهة</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $i=1;
          foreach($participants as $row){

              $name =
              $row['name'] ??
              $row['full_name'] ??
              $row['participant_name'] ??
              $row['student_name'] ??
              $row['trainee_name'] ??
              '---';

              $gender = $row['gender'] ?? '---';
              $inside = $row['inside_university'] ?? '---';

              echo "<tr>
              <td>".($i++)."</td>
              <td style='text-align:right;'>".e($name)."</td>
              <td>".e($gender)."</td>
              <td>".e($inside)."</td>
              </tr>";
          }
          if(count($participants)===0){
              echo "<tr><td colspan='4'>لا توجد بيانات</td></tr>";
          }
          ?>
          </tbody>
        </table>
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