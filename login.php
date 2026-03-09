<?php
session_start();
require_once "../config/db.php";

$error = "";

/* Helper */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* Process login */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        header("Location: ../admin/dashboard.php");
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}

$todayLabel = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>تسجيل الدخول | نظام إدارة التعليم المستمر</title>
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
  grid-template-columns: 1fr 460px;
  gap:24px;
  align-items:center;
  max-width:1140px;
  margin:0 auto;
  padding:28px 14px;
}

/* Login card */
.card{
  width:100%;
  border-radius:var(--radius);
  border:1px solid var(--border);
  background:rgba(255,255,255,.10);
  backdrop-filter: blur(18px);
  box-shadow: var(--shadow);
  overflow:hidden;
  transform: translateY(12px) scale(.985);
  opacity:0;
  transition: .7s ease;
}
.card.show{ transform: translateY(0) scale(1); opacity:1; }

/* Secret effect */
.card.secret-active{
  border-color: rgba(34,197,94,.35);
  box-shadow: 0 0 0 2px rgba(34,197,94,.18), 0 26px 90px rgba(0,0,0,.62);
  background: linear-gradient(135deg, rgba(34,197,94,.14), rgba(255,255,255,.08));
}
.card.secret-active .card-header{
  background: linear-gradient(135deg, rgba(34,197,94,.22), rgba(6,182,212,.10));
}

.card-header{
  padding:18px 18px 12px;
  border-bottom:1px solid rgba(255,255,255,.12);
  background: linear-gradient(135deg, rgba(37,99,235,.22), rgba(6,182,212,.10));
}
.brand{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}
.brand-left{ display:flex; align-items:center; gap:12px; }
.badge{
  width:46px; height:46px;
  border-radius:16px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:linear-gradient(135deg,var(--primary1),var(--primary2));
  box-shadow:0 16px 32px rgba(0,0,0,.40);
  font-size:20px;
  transition:.25s ease;
}
.card.secret-active .badge{
  background:linear-gradient(135deg,var(--success1),var(--success2));
}

.brand h2{ margin:0; font-size:16px; font-weight:1000; }
.brand small{ display:block; margin-top:2px; color:var(--muted); font-weight:800; font-size:12px; }
.card-body{ padding:18px; }

.alert{ border-radius:16px; border:1px solid rgba(255,255,255,.12); }

/* Form */
.form-label{ font-weight:1000; color: rgba(255,255,255,.92); margin-bottom:6px; }
.form-control{
  border-radius:14px !important;
  padding:12px 12px;
  font-weight:900;
  color:#fff;
  background: rgba(0,0,0,.22);
  border:1px solid rgba(255,255,255,.16);
}
.form-control:focus{
  border-color: rgba(37,99,235,.55);
  box-shadow: 0 0 0 .2rem rgba(37,99,235,.20);
  background: rgba(0,0,0,.26);
  color:#fff;
}

.pw-wrap{ position:relative; }
.pw-toggle{
  position:absolute;
  left:10px;
  top:50%;
  transform:translateY(-50%);
  border:1px solid rgba(255,255,255,.14);
  background:rgba(255,255,255,.08);
  color:#fff;
  border-radius:12px;
  padding:6px 10px;
  font-weight:1000;
  cursor:pointer;
}

.btn-main{
  width:100%;
  border:none;
  border-radius:14px;
  padding:12px 14px;
  font-weight:1000;
  color:#fff;
  background: linear-gradient(135deg, rgba(37,99,235,.95), rgba(30,64,175,.88));
  box-shadow:0 18px 40px rgba(0,0,0,.40);
  transition:.2s ease;
}
.btn-main:hover{ transform: translateY(-2px); }

.meta-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  margin-top:12px;
  color:var(--muted);
  font-weight:800;
  font-size:12px;
}

.footer{
  padding:14px 18px;
  border-top:1px solid rgba(255,255,255,.12);
  color:var(--muted);
  font-weight:800;
  font-size:12px;
  text-align:center;
  background: rgba(255,255,255,.06);
}

/* Character next to card */
.character-stage{
  position:relative;
  height:520px;
  display:flex;
  align-items:flex-end;
  justify-content:flex-end;
  padding-bottom:6px;
}
.character{
  width:300px;
  pointer-events:none;
  filter: drop-shadow(0 18px 25px rgba(0,0,0,.45));
  transform: translateX(-520px);
  will-change: transform;
}
.character.run{
  animation: runToSide 2.9s cubic-bezier(.2,.85,.2,1) forwards;
}
@keyframes runToSide{
  0%   { transform: translateX(-520px); }
  100% { transform: translateX(0); }
}
.character.secret{
  animation: hop 650ms ease;
  filter: drop-shadow(0 22px 40px rgba(34,197,94,.35));
}
@keyframes hop{
  0%{ transform: translateX(0) translateY(0); }
  45%{ transform: translateX(0) translateY(-18px); }
  100%{ transform: translateX(0) translateY(0); }
}

/* Eyes */
.eye-layer{
  position:absolute;
  width:300px;
  height:300px;
  bottom:6px;
  right:0;
  pointer-events:none;
  opacity:0;
  transition: opacity .35s ease;
}
.eye-layer.show{ opacity:1; }

.eye{
  position:absolute;
  width:12px;
  height:12px;
  background:#111;
  border-radius:50%;
  box-shadow: 0 0 0 2px rgba(255,255,255,.75);
  will-change: transform;
}
/* ⚠️ عدّل حسب صورتك */
.eye.left  { left: 142px; top: 118px; }
.eye.right { left: 180px; top: 118px; }

/* Secret banner */
.secret-banner{
  display:none;
  margin: 0 0 12px;
  padding:10px 12px;
  border-radius:16px;
  border:1px solid rgba(34,197,94,.28);
  background: rgba(34,197,94,.12);
  font-weight:1000;
  text-align:center;
}
.secret-banner.show{ display:block; }

/* Responsive */
@media (max-width: 980px){
  .wrap{ grid-template-columns: 1fr; max-width:560px; }
  body{ overflow:auto; }
  .character-stage{ display:none; }
}
</style>
</head>

<body>
<div class="bg-photo"></div>
<div class="overlay"></div>

<div class="wrap">

  <div class="character-stage" aria-hidden="true">
    <img src="../assets/img/student.png" class="character run" id="student" alt="">
    <div class="eye-layer" id="eyeLayer">
      <div class="eye left" id="eyeL"></div>
      <div class="eye right" id="eyeR"></div>
    </div>
  </div>

  <div class="card" id="loginCard">
    <div class="card-header">
      <div class="brand">
        <div class="brand-left">
          <div class="badge" aria-hidden="true">🔐</div>
          <div>
            <h2>تسجيل الدخول</h2>
            <small>أدخل البيانات للمتابعة</small>
          </div>
        </div>
        <div style="text-align:left; font-weight:900; color:rgba(255,255,255,.75); font-size:12px;">
          <?= e($todayLabel) ?>
        </div>
      </div>
    </div>

    <div class="card-body">
      <div class="secret-banner" id="secretBanner">✨ تم تفعيل وضع خاص!</div>

      <?php if($error): ?>
        <div class="alert alert-danger text-center fw-bold mb-3">
          <?= e($error) ?>
        </div>
      <?php else: ?>
        <div class="alert alert-secondary text-center mb-3" style="font-weight:900;">
          مرحبًا بك — أدخل بياناتك للوصول إلى لوحة التحكم
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">اسم المستخدم</label>
          <input type="text" name="username" class="form-control" required placeholder="مثال: admin" autofocus>
        </div>

        <div class="mb-3 pw-wrap">
          <label class="form-label">كلمة المرور</label>
          <input type="password" name="password" class="form-control" id="password" required placeholder="••••••••">
          <button class="pw-toggle" type="button" id="pwToggle" aria-label="إظهار/إخفاء كلمة المرور">👁</button>
        </div>

        <button class="btn-main" type="submit">دخول النظام</button>

        <div class="meta-row">
          <span>واجهة داخلية</span>
          <span>© <?= e(date("Y")) ?></span>
        </div>
      </form>
    </div>

    <div class="footer">
      نظام إدارة التعليم المستمر — تسجيل دخول آمن
    </div>
  </div>

</div>

<script>
/* card reveal */
setTimeout(() => {
  document.getElementById("loginCard").classList.add("show");
}, 220);

/* password toggle */
(function(){
  const btn = document.getElementById("pwToggle");
  const input = document.getElementById("password");
  if(!btn || !input) return;

  btn.addEventListener("click", ()=>{
    const isPwd = input.type === "password";
    input.type = isPwd ? "text" : "password";
    btn.textContent = isPwd ? "🙈" : "👁";
  });
})();

/* eyes follow mouse (synced to image rect) */
(function(){
  const student = document.getElementById("student");
  const eyeLayer = document.getElementById("eyeLayer");
  const eyeL = document.getElementById("eyeL");
  const eyeR = document.getElementById("eyeR");
  if(!student || !eyeLayer || !eyeL || !eyeR) return;

  let raf = null;
  let mouseX = window.innerWidth/2;
  let mouseY = window.innerHeight/2;

  // make fixed so it matches viewport like getBoundingClientRect
  eyeLayer.style.position = "fixed";

  function syncEyeLayer(){
    const r = student.getBoundingClientRect();
    eyeLayer.style.left = r.left + "px";
    eyeLayer.style.top  = r.top + "px";
    eyeLayer.style.width  = r.width + "px";
    eyeLayer.style.height = r.height + "px";
  }

  function movePupil(pupilEl, centerX, centerY){
    const dx = mouseX - centerX;
    const dy = mouseY - centerY;

    const max = 6;
    const angle = Math.atan2(dy, dx);
    const dist = Math.min(max, Math.hypot(dx, dy) / 55 * max);

    pupilEl.style.transform = `translate(${Math.cos(angle)*dist}px, ${Math.sin(angle)*dist}px)`;
  }

  function tick(){
    raf = null;
    const r = eyeLayer.getBoundingClientRect();
    if(r.width <= 0 || r.height <= 0) return;

    // scale from design 300px
    const lx = r.left + (142/300)*r.width + 6;
    const ly = r.top  + (118/300)*r.height + 6;
    const rx = r.left + (180/300)*r.width + 6;
    const ry = r.top  + (118/300)*r.height + 6;

    movePupil(eyeL, lx, ly);
    movePupil(eyeR, rx, ry);
  }

  document.addEventListener("mousemove", (ev)=>{
    mouseX = ev.clientX;
    mouseY = ev.clientY;
    if(!raf) raf = requestAnimationFrame(tick);
  });

  window.addEventListener("resize", ()=>{
    syncEyeLayer();
    if(!raf) raf = requestAnimationFrame(tick);
  });

  // show after run ends
  setTimeout(()=>{
    syncEyeLayer();
    eyeLayer.classList.add("show");
    if(!raf) raf = requestAnimationFrame(tick);
  }, 2200);

  syncEyeLayer();
})();

/* ✅ تحسين “الرمز السري”: تأثير أنعم + اهتزاز خفيف + صوت اختياري */
(function(){
  const password = document.getElementById("password");
  const card = document.getElementById("loginCard");
  const student = document.getElementById("student");
  const banner = document.getElementById("secretBanner");

  if(!password || !card || !student || !banner) return;

  // غيّر الرمز هنا
  const SECRET_CODE = "2026";

  let active = false;

  function beep(){
    try{
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = "triangle";
      o.frequency.value = 740;
      g.gain.value = 0.02;
      o.connect(g); g.connect(ctx.destination);
      o.start();
      setTimeout(()=>{ o.stop(); ctx.close(); }, 120);
    }catch(e){}
  }

  password.addEventListener("input", ()=>{
    const v = password.value || "";
    const shouldActivate = v.includes(SECRET_CODE);

    if(shouldActivate && !active){
      active = true;

      card.classList.add("secret-active");
      banner.classList.add("show");

      student.classList.add("secret");
      setTimeout(()=> student.classList.remove("secret"), 700);

      // اهتزاز خفيف للكارت
      card.animate(
        [{transform:"translateY(0) scale(1)"},{transform:"translateY(-2px) scale(1)"},{transform:"translateY(0) scale(1)"}],
        {duration:320, easing:"ease-out"}
      );

      beep();
    }

    if(!shouldActivate && active){
      active = false;
      card.classList.remove("secret-active");
      banner.classList.remove("show");
    }
  });
})();
</script>

</body>
</html>