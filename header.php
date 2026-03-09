<?php
/* header.php (Public site) - same order & design system like Admin (glass/topbar) */
function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
$todayLabel = date('Y-m-d');
$nowLabel   = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education-public">
<head>
<meta charset="UTF-8">
<title>نظام التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;600;800;900&display=swap" rel="stylesheet">

<style>
/* ✅ نفس روح تصميم الأدمن (Glass + Topbar + Buttons) */
:root{
    --overlay1: rgba(0,0,0,.55);
    --overlay2: rgba(0,0,0,.75);

    --card: rgba(255,255,255,.10);
    --card2: rgba(255,255,255,.08);
    --border: rgba(255,255,255,.14);
    --text: #ffffff;
    --muted: rgba(255,255,255,.74);

    --shadow: 0 20px 60px rgba(0,0,0,.55);
    --shadow2: 0 14px 35px rgba(0,0,0,.35);

    --primary1:#2563eb; --primary2:#1e40af;
    --warn1:#f59e0b; --warn2:#d97706;

    --radius: 18px;
}
*{ box-sizing:border-box; }

body{
    margin:0;
    font-family:"Cairo","Segoe UI",Tahoma,system-ui,-apple-system,sans-serif;
    background:url("/continuous_education/assets/img/bg.jpg") no-repeat center center fixed;
    background-size:cover;
    color:var(--text);
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
    min-width:260px;
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
}
.brand small{
    display:block;
    color:var(--muted);
    font-weight:800;
    margin-top:2px;
    font-size:12px;
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
    background:linear-gradient(135deg, rgba(37,99,235,.55), rgba(30,64,175,.25));
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
</style>
</head>

<body>
<div class="overlay">
  <div class="content">

    <!-- ✅ Topbar like admin -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge" title="التعليم المستمر">🎓</div>
          <div>
            <h1>نظام التعليم المستمر</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>

        <div class="actions">
          <a class="btn-soft" href="index.php">🏠 الرئيسية</a>
          <a class="btn-soft primary" href="courses.php">📚 الدورات</a>
          <a class="btn-soft" href="../auth/login.php">🎛 دخول الإدارة</a>
        </div>
      </div>
    </div>