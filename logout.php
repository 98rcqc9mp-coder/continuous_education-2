<?php
session_start();

/* تنظيف الجلسة بشكل صحيح */
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

/* Helper */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$todayLabel = date('Y-m-d');
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>تسجيل الخروج | نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800;900&display=swap" rel="stylesheet">

<style>
:root{
  --bg1:#070a12;
  --bg2:#0b1020;

  --overlay1: rgba(0,0,0,.45);
  --overlay2: rgba(0,0,0,.80);

  --border: rgba(255,255,255,.16);
  --text:#ffffff;
  --muted: rgba(255,255,255,.72);

  --shadow: 0 24px 80px rgba(0,0,0,.62);
  --radius: 24px;

  --primary1:#2563eb; --primary2:#1e40af;
  --success1:#22c55e; --success2:#16a34a;
}

*{ box-sizing:border-box; }

body{
  margin:0;
  min-height:100vh;
  font-family:"Cairo","Segoe UI",Tahoma,system-ui,-apple-system,sans-serif;
  color:var(--text);
  background:
    radial-gradient(1200px 700px at 12% 14%, rgba(37,99,235,.35), transparent 55%),
    radial-gradient(900px 600px at 88% 26%, rgba(6,182,212,.26), transparent 55%),
    linear-gradient(180deg, var(--bg1), var(--bg2));
  overflow:hidden;
}

.bg-photo{
  position:fixed;
  inset:0;
  background:url("../assets/img/login-bg.jpg") no-repeat center center/cover;
  opacity:.18;
  filter:saturate(1.06) contrast(1.06);
  z-index:0;
}
.overlay{
  position:fixed;
  inset:0;
  background:linear-gradient(180deg, var(--overlay1), var(--overlay2));
  z-index:1;
}

.wrap{
  position:relative;
  z-index:10;
  min-height:100vh;
  display:grid;
  place-items:center;
  padding:28px 14px;
}

.cardx{
  width:min(520px, 100%);
  border-radius:var(--radius);
  border:1px solid var(--border);
  background:rgba(255,255,255,.10);
  backdrop-filter: blur(18px);
  box-shadow: var(--shadow);
  overflow:hidden;
  transform: translateY(10px);
  opacity:0;
  animation: enter .7s ease forwards;
}
@keyframes enter{
  to{ transform: translateY(0); opacity:1; }
}

.cardx-header{
  padding:18px 18px 12px;
  border-bottom:1px solid rgba(255,255,255,.12);
  background: linear-gradient(135deg, rgba(34,197,94,.22), rgba(6,182,212,.10));
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}
.brand{
  display:flex;
  align-items:center;
  gap:12px;
}
.badge{
  width:46px; height:46px;
  border-radius:16px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:linear-gradient(135deg,var(--success1),var(--success2));
  box-shadow:0 16px 32px rgba(0,0,0,.40);
  font-size:20px;
}
.title h1{
  margin:0;
  font-size:16px;
  font-weight:1000;
}
.title small{
  display:block;
  margin-top:2px;
  color:var(--muted);
  font-weight:800;
  font-size:12px;
}

.cardx-body{
  padding:18px;
  text-align:center;
}

.big-icon{
  font-size:54px;
  margin:6px 0 10px;
}

.msg{
  font-weight:1000;
  font-size:16px;
  margin:0;
}
.sub{
  color:var(--muted);
  font-weight:800;
  margin-top:8px;
}

.progress{
  height:8px;
  border-radius:999px;
  overflow:hidden;
  background: rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.14);
  margin-top:16px;
}
.progress-bar{
  width:0%;
  background: linear-gradient(135deg, rgba(37,99,235,.95), rgba(30,64,175,.88));
  animation: load 3s linear forwards;
}
@keyframes load{
  to{ width:100%; }
}

.actions{
  margin-top:16px;
  display:flex;
  gap:10px;
  justify-content:center;
  flex-wrap:wrap;
}

.btn-soft{
  border:1px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.10);
  color:#fff;
  padding:10px 14px;
  border-radius:14px;
  font-weight:1000;
  transition:.2s ease;
  text-decoration:none;
}
.btn-soft:hover{ transform:translateY(-2px); background:rgba(255,255,255,.14); }

.btn-main{
  border:none;
  border-radius:14px;
  padding:10px 14px;
  font-weight:1000;
  color:#fff;
  background: linear-gradient(135deg, rgba(37,99,235,.95), rgba(30,64,175,.88));
  box-shadow:0 18px 40px rgba(0,0,0,.40);
  transition:.2s ease;
  text-decoration:none;
}
.btn-main:hover{ transform:translateY(-2px); }

.footer{
  padding:14px 18px;
  border-top:1px solid rgba(255,255,255,.12);
  color:var(--muted);
  font-weight:800;
  font-size:12px;
  text-align:center;
  background: rgba(255,255,255,.06);
}
</style>
</head>

<body>
<div class="bg-photo"></div>
<div class="overlay"></div>

<div class="wrap">
  <div class="cardx" role="status" aria-live="polite">
    <div class="cardx-header">
      <div class="brand">
        <div class="badge" aria-hidden="true">✅</div>
        <div class="title">
          <h1>تم تسجيل الخروج</h1>
          <small>نظام إدارة التعليم المستمر</small>
        </div>
      </div>
      <div style="font-weight:900;color:rgba(255,255,255,.75);font-size:12px;">
        <?= e($todayLabel) ?>
      </div>
    </div>

    <div class="cardx-body">
      <div class="big-icon" aria-hidden="true">👋</div>
      <p class="msg">تم تسجيل الخروج بنجاح</p>
      <div class="sub">سيتم تحويلك إلى صفحة تسجيل الدخول تلقائيًا</div>

      <div class="progress" aria-label="جارٍ التحويل">
        <div class="progress-bar"></div>
      </div>

      <div class="actions">
        <a class="btn-main" href="login.php">الانتقال الآن</a>
      </div>
    </div>

    <div class="footer">
      نظام إدارة التعليم المستمر © <?= e($year) ?>
    </div>
  </div>
</div>

<script>
setTimeout(() => {
  window.location.href = "login.php";
}, 3000);
</script>

</body>
</html>