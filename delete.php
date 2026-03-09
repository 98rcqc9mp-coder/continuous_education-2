<?php
session_start();
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

/**
 * ✅ نفس تصميم/ترتيب الواجهة (Glass + Topbar) مثل باقي الصفحات
 * ✅ بدون تغيير منطق الحذف:
 * - ما زال يحذف ارتباطات course_participants ثم يحذف participant.
 * - فقط أضفنا شاشة تأكيد (GET يعرض، POST ينفّذ) لتكون احترافية وآمنة.
 */

function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

/* جلب بيانات المشارك للعرض فقط */
$stmt = $pdo->prepare("SELECT full_name FROM participants WHERE id=?");
$stmt->execute([$id]);
$participant = $stmt->fetch();

if (!$participant) {
    header("Location: list.php");
    exit;
}

/* تنفيذ الحذف (نفس منطقك) */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST['confirm'])) {

        /* حذف الارتباط مع الدورات أولاً */
        $pdo->prepare("
            DELETE FROM course_participants 
            WHERE participant_id = ?
        ")->execute([$id]);

        /* حذف المشارك */
        $pdo->prepare("
            DELETE FROM participants 
            WHERE id = ?
        ")->execute([$id]);
    }

    header("Location: list.php");
    exit;
}

/* UI labels */
$nowLabel = date('Y-m-d H:i');
$todayLabel = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>حذف مشترك | نظام إدارة التعليم المستمر</title>
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
    background:linear-gradient(135deg,var(--danger1),var(--danger2));
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
.btn-soft.danger{
    border-color: rgba(239,68,68,.45);
    background: linear-gradient(135deg, rgba(239,68,68,.45), rgba(185,28,28,.25));
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

hr.sep{
    border:none;
    border-top:1px solid rgba(255,255,255,.14);
    margin:12px 0;
}

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
.pill.danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.14); }

.card-soft{
    border-radius:16px;
    padding:14px 16px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.18);
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
body.light-mode .card-soft{
    background:rgba(255,255,255,.72);
    border:1px solid rgba(15,23,42,.10);
    color:#0f172a;
    box-shadow:0 18px 45px rgba(2,6,23,.12);
}
body.light-mode .btn-soft,
body.light-mode .pill,
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
          <div class="brand-badge" title="حذف">🗑️</div>
          <div>
            <h1>تأكيد حذف المشترك</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <span class="badge-soft">ID: <?= e($id) ?></span>
          <a class="btn-soft" href="list.php">⬅ رجوع للقائمة</a>
          <a class="btn-soft" href="../dashboard.php">🏠 لوحة التحكم</a>
        </div>
      </div>

      <hr class="sep">

      <div class="alert alert-danger mb-0 text-center fw-bold">
        ⚠️ هذا الإجراء نهائي وسيقوم بحذف المشترك وربطه بجميع الدورات.
      </div>
    </div>

    <!-- Confirm Card -->
    <div class="glass soft">
      <div class="section-title">
        <h2>👤 المشترك المراد حذفه</h2>
        <div class="meta">يرجى التأكد قبل المتابعة</div>
      </div>

      <div class="card-soft mb-3">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div style="font-weight:1000;">الاسم الكامل</div>
          <span class="pill danger"><?= e($participant['full_name']) ?></span>
        </div>
      </div>

      <form method="post" class="d-flex justify-content-end gap-2 flex-wrap">
        <a href="list.php" class="btn-soft">⬅ إلغاء</a>
        <button name="confirm" class="btn-soft danger" type="submit"
                onclick="return confirm('هل أنت متأكد من حذف هذا المشترك؟');">
          🗑 نعم، احذف المشترك
        </button>
      </form>
    </div>

  </div>
</div>

<script>
/* Optional: keep same theme localStorage behavior */
(function(){
  const saved = localStorage.getItem("theme");
  if(saved === "light"){
    document.body.classList.add("light-mode");
  }
})();
</script>

</body>
</html>