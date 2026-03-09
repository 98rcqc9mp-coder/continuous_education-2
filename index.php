<?php
session_start();
require_once __DIR__."/../../config/db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: ../../auth/login.php");
    exit;
}

/* Helpers */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* ===============================
   احصائيات الدورات (NO CHANGE)
================================ */

$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

$todayCourses = $pdo->query("
SELECT COUNT(*) FROM courses
WHERE DATE(start_date)=CURDATE()
")->fetchColumn();

$runningCourses = $pdo->query("
SELECT COUNT(*) FROM courses
WHERE CURDATE() BETWEEN start_date AND end_date
")->fetchColumn();

$upcomingCourses = $pdo->query("
SELECT COUNT(*) FROM courses
WHERE DATE(start_date)>CURDATE()
")->fetchColumn();

/* ===============================
   احصائيات المشاركين (NO CHANGE)
================================ */

$totalParticipants = $pdo->query("SELECT COUNT(*) FROM participants")->fetchColumn();

$males = $pdo->query("SELECT COUNT(*) FROM participants WHERE gender='ذكر'")->fetchColumn();

$females = $pdo->query("SELECT COUNT(*) FROM participants WHERE gender='أنثى'")->fetchColumn();

$inside = $pdo->query("SELECT COUNT(*) FROM participants WHERE inside_university='داخل'")->fetchColumn();

$outside = $pdo->query("SELECT COUNT(*) FROM participants WHERE inside_university='خارج'")->fetchColumn();

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>لوحة الإحصائيات | نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* (نفس الـ CSS الذي أرسلته بدون تغيير) */
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
}
.content{
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
    background:linear-gradient(135deg,var(--warn1),var(--warn2));
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
.grid{
    display:grid;
    grid-template-columns: repeat(12, 1fr);
    gap:12px;
}
.stat{
    grid-column: span 3;
    padding:18px;
    border-radius:18px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.18);
    box-shadow: var(--shadow2);
    cursor:pointer;
    transition:.25s ease;
}
.stat:hover{ transform: translateY(-4px); }
.stat .icon{
    width:44px; height:44px;
    border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px;
    background: rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.14);
}
.stat .value{
    font-size:34px;
    font-weight:1000;
    margin-top:10px;
    line-height:1;
}
.stat .label{
    margin-top:6px;
    color: rgba(255,255,255,.82);
    font-weight:900;
    font-size:13px;
}
.stat.primary{ background: linear-gradient(135deg, rgba(37,99,235,.28), rgba(30,64,175,.18)); border-color: rgba(37,99,235,.30); }
.stat.success{ background: linear-gradient(135deg, rgba(34,197,94,.24), rgba(22,163,74,.16)); border-color: rgba(34,197,94,.26); }
.stat.info{ background: linear-gradient(135deg, rgba(6,182,212,.24), rgba(14,116,144,.16)); border-color: rgba(6,182,212,.26); }
.stat.warn{ background: linear-gradient(135deg, rgba(245,158,11,.24), rgba(217,119,6,.16)); border-color: rgba(245,158,11,.26); }
.form-control{
    border-radius:14px !important;
    padding:12px 12px;
    font-weight:900;
    color:#fff;
    background: rgba(0,0,0,.18);
    border:1px solid rgba(255,255,255,.16);
}
.form-control:focus{
    border-color: rgba(37,99,235,.55);
    box-shadow: 0 0 0 .2rem rgba(37,99,235,.20);
    background: rgba(0,0,0,.22);
    color:#fff;
}
.form-control::placeholder{ color: rgba(255,255,255,.55); font-weight:800; }
@media (max-width: 992px){ .stat{ grid-column: span 6; } }
@media (max-width: 576px){ .stat{ grid-column: span 12; } }
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
body.light-mode .stat{
    background:rgba(255,255,255,.72);
    border:1px solid rgba(15,23,42,.10);
    color:#0f172a;
}
body.light-mode .stat .label{ color: rgba(15,23,42,.75); }
body.light-mode .form-control{
    background: rgba(15,23,42,.05);
    border:1px solid rgba(15,23,42,.12);
    color:#0f172a;
}
body.light-mode .badge-soft,
body.light-mode .btn-soft{ color:#0f172a; }
</style>
</head>

<body>
<div class="overlay">
  <div class="content">

    <!-- Topbar -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge" title="الإحصائيات">📊</div>
          <div>
            <!-- ✅ إصلاح الترميز الذي ظهر عندك: "ال��ستمر" -->
            <h1>إحصائيات نظام التعليم المستمر</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">جاهز</span>

          <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل الوضع">
            <span id="themeIcon">🌙</span>
            <span id="themeText">الوضع الليلي</span>
          </button>

          <a class="btn-soft" href="../dashboard.php">⬅ رجوع للواجهة الرئيسية</a>
        </div>
      </div>
    </div>

    <!-- Courses stats -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📚 إحصائيات الدورات</h2>
        <div class="meta">اضغط على أي بطاقة لعرض التفاصيل</div>
      </div>

      <div class="grid">
        <div class="stat primary" onclick="showCourses('all')">
          <div class="icon">📚</div>
          <div class="value"><?= (int)$totalCourses ?></div>
          <div class="label">إجمالي الدورات</div>
        </div>

        <div class="stat info" onclick="showCourses('today')">
          <div class="icon">📅</div>
          <div class="value"><?= (int)$todayCourses ?></div>
          <div class="label">دورات اليوم</div>
        </div>

        <div class="stat success" onclick="showCourses('running')">
          <div class="icon">✅</div>
          <div class="value"><?= (int)$runningCourses ?></div>
          <div class="label">الدورات الجارية</div>
        </div>

        <div class="stat warn" onclick="showCourses('upcoming')">
          <div class="icon">⏳</div>
          <div class="value"><?= (int)$upcomingCourses ?></div>
          <div class="label">الدورات القادمة</div>
        </div>
      </div>
    </div>

    <!-- Participants stats -->
    <div class="glass soft">
      <div class="section-title">
        <h2>👥 إحصائيات المشتركين</h2>
        <div class="meta">اضغط على أي بطاقة لعرض التفاصيل</div>
      </div>

      <div class="grid">
        <div class="stat primary" onclick="showParticipants('all')">
          <div class="icon">👥</div>
          <div class="value"><?= (int)$totalParticipants ?></div>
          <div class="label">إجمالي المشاركين</div>
        </div>

        <div class="stat info" onclick="showParticipants('male')">
          <div class="icon">👨</div>
          <div class="value"><?= (int)$males ?></div>
          <div class="label">ذكور</div>
        </div>

        <div class="stat info" onclick="showParticipants('female')">
          <div class="icon">👩</div>
          <div class="value"><?= (int)$females ?></div>
          <div class="label">إناث</div>
        </div>

        <div class="stat success" onclick="showParticipants('inside')">
          <div class="icon">🏫</div>
          <div class="value"><?= (int)$inside ?></div>
          <div class="label">داخل الجامعة</div>
        </div>

        <div class="stat warn" onclick="showParticipants('outside')">
          <div class="icon">🌐</div>
          <div class="value"><?= (int)$outside ?></div>
          <div class="label">خارج الجامعة</div>
        </div>
      </div>
    </div>

    <!-- Details -->
    <div class="glass soft" id="detailsBox" style="display:none">
      <div class="section-title">
        <h2>📋 التفاصيل</h2>
        <div class="meta">يمكنك البحث داخل النتائج</div>
      </div>

      <input type="text"
             id="detailsSearch"
             class="form-control mb-3"
             placeholder="🔎 بحث داخل النتائج...">

      <div id="detailsContent"></div>
    </div>

  </div>
</div>

<script>
/* ✅ حفظ آخر نوع كارت دورات/مشاركين حتى زر الرجوع يشتغل */
window.__lastCoursesType = "all";
window.__lastParticipantsType = "all";

/* ===== Theme toggle ===== */
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

/* ===== الدورات ===== */
function showCourses(type){
  window.__lastCoursesType = type || "all";
  fetch("load_courses.php?type="+encodeURIComponent(window.__lastCoursesType))
  .then(r=>r.text())
  .then(data=>{
    document.getElementById("detailsBox").style.display="block";
    document.getElementById("detailsContent").innerHTML=data;
  });
}

/* ✅ هذه كانت ناقصة عندك: عند الضغط على اسم الدورة يفتح التفاصيل */
function showCourseDetails(courseId){
  fetch("load_courses.php?course_id="+encodeURIComponent(courseId))
  .then(r=>r.text())
  .then(data=>{
    document.getElementById("detailsBox").style.display="block";
    document.getElementById("detailsContent").innerHTML=data;
  });
}

/* ✅ زر الرجوع من تفاصيل الدورة إلى جدول الدورات */
function backToCoursesTable(){
  showCourses(window.__lastCoursesType || "all");
}

/* ===== المشاركين ===== */
function showParticipants(type){
  window.__lastParticipantsType = type || "all";
  fetch("load_participants.php?type="+encodeURIComponent(window.__lastParticipantsType))
  .then(r=>r.text())
  .then(data=>{
    document.getElementById("detailsBox").style.display="block";
    document.getElementById("detailsContent").innerHTML=data;
  });
}

/* ===== البحث داخل النتائج ===== */
document.addEventListener("keyup",function(e){
  if(e.target.id==="detailsSearch"){
    let v=e.target.value.toLowerCase();

    document.querySelectorAll("#detailsContent tbody tr")
    .forEach(r=>{
      r.style.display =
        r.innerText.toLowerCase().includes(v) ? '' : 'none';
    });
  }
});
</script>

</body>
</html>