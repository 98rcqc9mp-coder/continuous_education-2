<?php
/**
 * verify_certificate.php — صفحة التحقق العامة من الشهادات
 * متاحة للعموم بدون تسجيل دخول
 */
require_once __DIR__ . '/db.php';

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$code   = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
$result = null;
$error  = null;

if ($code !== '') {
    $stmt = $pdo->prepare("
        SELECT
            c.certificate_code,
            c.issuer_name,
            c.issued_at,
            p.full_name    AS participant_name,
            co.course_name,
            co.start_date,
            co.end_date,
            co.location
        FROM certificates c
        JOIN participants p  ON p.id  = c.participant_id
        JOIN courses      co ON co.id = c.course_id
        WHERE c.certificate_code = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $result = $stmt->fetch();

    if (!$result) {
        $error = 'رمز الشهادة غير صحيح أو غير موجود. يرجى التأكد من الرمز والمحاولة مرة أخرى.';
    }
}

$todayLabel = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>التحقق من الشهادة | نظام التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --overlay1:rgba(0,0,0,.55);
    --overlay2:rgba(0,0,0,.75);
    --card:rgba(255,255,255,.10);
    --card2:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.14);
    --text:#ffffff;
    --muted:rgba(255,255,255,.74);
    --shadow:0 20px 60px rgba(0,0,0,.55);
    --shadow2:0 14px 35px rgba(0,0,0,.35);
    --primary1:#2563eb;--primary2:#1e40af;
    --success1:#22c55e;--success2:#16a34a;
    --danger1:#ef4444;--danger2:#b91c1c;
    --radius:18px;
}
*{box-sizing:border-box;}
body{
    margin:0;
    font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Cairo",sans-serif;
    background:linear-gradient(135deg,#0a0f1e,#0d1b2a,#0a0f1e);
    background-attachment:fixed;
    color:var(--text);
    font-size:14px;
}
a{color:inherit;text-decoration:none;}
a:hover{color:inherit;}
.overlay{
    background:linear-gradient(180deg,var(--overlay1),var(--overlay2));
    min-height:100vh;
    padding:22px 14px 34px;
}
.content{max-width:760px;margin:0 auto;}
.glass{
    background:var(--card);
    border:1px solid var(--border);
    backdrop-filter:blur(16px);
    border-radius:var(--radius);
    padding:18px;
    box-shadow:var(--shadow);
    margin-bottom:14px;
}
.glass.soft{background:var(--card2);box-shadow:var(--shadow2);}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-badge{
    width:46px;height:46px;border-radius:16px;
    background:linear-gradient(135deg,var(--primary1),var(--primary2));
    box-shadow:0 12px 25px rgba(0,0,0,.35);
    display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto;
}
.brand h1{margin:0;font-size:18px;font-weight:800;}
.brand small{display:block;color:var(--muted);font-weight:700;margin-top:2px;font-size:12px;}
.btn-soft{
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.10);
    color:#fff;padding:10px 14px;border-radius:14px;
    font-weight:800;transition:.2s ease;
    display:inline-flex;align-items:center;gap:8px;cursor:pointer;
}
.btn-soft:hover{transform:translateY(-2px);background:rgba(255,255,255,.14);}
.btn-soft.primary{
    background:linear-gradient(135deg,rgba(37,99,235,.55),rgba(30,64,175,.25));
    border-color:rgba(37,99,235,.45);
}
.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.section-title h2{margin:0;font-weight:800;font-size:16px;}
.form-label{font-weight:800;color:rgba(255,255,255,.92);margin-bottom:6px;}
.form-control{
    border-radius:14px !important;padding:12px;font-weight:700;
    color:#fff;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.16);
}
.form-control:focus{
    border-color:rgba(37,99,235,.55);
    box-shadow:0 0 0 .2rem rgba(37,99,235,.20);
    background:rgba(0,0,0,.22);color:#fff;
}

/* نتيجة الشهادة */
.cert-card{
    border-radius:20px;
    border:2px solid rgba(34,197,94,.35);
    background:linear-gradient(135deg,rgba(34,197,94,.14),rgba(22,163,74,.08));
    padding:28px;
    text-align:center;
}
.cert-badge{
    width:80px;height:80px;border-radius:50%;
    background:linear-gradient(135deg,var(--success1),var(--success2));
    display:flex;align-items:center;justify-content:center;
    font-size:36px;margin:0 auto 16px;
    box-shadow:0 12px 30px rgba(34,197,94,.35);
}
.cert-name{font-size:26px;font-weight:900;margin-bottom:6px;}
.cert-course{font-size:18px;color:rgba(255,255,255,.85);margin-bottom:16px;}
.cert-detail{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px;border-radius:999px;
    border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.10);
    font-size:13px;font-weight:700;margin:4px;
}
.cert-code{
    margin-top:20px;
    font-family:monospace;font-size:13px;
    color:rgba(255,255,255,.65);letter-spacing:.5px;
}

/* خطأ */
.err-card{
    border-radius:20px;
    border:2px solid rgba(239,68,68,.35);
    background:linear-gradient(135deg,rgba(239,68,68,.14),rgba(185,28,28,.08));
    padding:28px;
    text-align:center;
}
.err-badge{
    width:72px;height:72px;border-radius:50%;
    background:linear-gradient(135deg,var(--danger1),var(--danger2));
    display:flex;align-items:center;justify-content:center;
    font-size:30px;margin:0 auto 14px;
    box-shadow:0 10px 25px rgba(239,68,68,.35);
}

hr.sep{border:none;border-top:1px solid rgba(255,255,255,.14);margin:14px 0;}
</style>
</head>
<body>
<div class="overlay">
  <div class="content">

    <!-- Topbar -->
    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge">🔍</div>
          <div>
            <h1>التحقق من الشهادة</h1>
            <small>أدخل رمز الشهادة للتحقق من صحتها • <?= e($todayLabel) ?></small>
          </div>
        </div>
        <a class="btn-soft" href="login.php">🎛 دخول الإدارة</a>
      </div>
    </div>

    <!-- نموذج البحث -->
    <div class="glass soft">
      <div class="section-title">
        <h2>🔎 التحقق من رمز الشهادة</h2>
      </div>
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-9">
          <label class="form-label">رمز الشهادة (Certificate Code)</label>
          <input type="text" name="code" value="<?= e($code) ?>"
                 class="form-control" placeholder="أدخل رمز الشهادة هنا..."
                 required autocomplete="off" dir="ltr"
                 style="letter-spacing:1px;font-family:monospace;font-size:15px;">
        </div>
        <div class="col-lg-3">
          <button class="btn-soft primary w-100 justify-content-center" type="submit" style="padding:12px 14px;">
            🔍 تحقق
          </button>
        </div>
      </form>
    </div>

    <?php if ($error): ?>
    <!-- رسالة خطأ -->
    <div class="glass soft">
      <div class="err-card">
        <div class="err-badge">❌</div>
        <h3 style="font-size:20px;font-weight:900;margin-bottom:8px;">الشهادة غير موجودة</h3>
        <p style="color:rgba(255,255,255,.75);font-size:14px;margin:0;"><?= e($error) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($result): ?>
    <!-- نتيجة إيجابية -->
    <div class="glass soft">
      <div class="cert-card">
        <div class="cert-badge">✅</div>
        <p style="color:rgba(255,255,255,.65);font-size:13px;margin-bottom:6px;">شهادة مشاركة موثّقة</p>
        <div class="cert-name"><?= e($result['participant_name']) ?></div>
        <div class="cert-course">📚 <?= e($result['course_name']) ?></div>

        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin-bottom:16px;">
          <span class="cert-detail">📅 من: <?= e($result['start_date']) ?></span>
          <span class="cert-detail">📅 إلى: <?= e($result['end_date']) ?></span>
          <?php if ($result['location']): ?>
          <span class="cert-detail">📍 <?= e($result['location']) ?></span>
          <?php endif; ?>
          <span class="cert-detail">🏛 <?= e($result['issuer_name']) ?></span>
          <span class="cert-detail">🗓 تاريخ الإصدار: <?= e(substr($result['issued_at'], 0, 10)) ?></span>
        </div>

        <div class="cert-code">رمز الشهادة: <?= e($result['certificate_code']) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($code === '' && !$result && !$error): ?>
    <!-- تعليمات -->
    <div class="glass soft">
      <div style="text-align:center;padding:20px 0;">
        <div style="font-size:48px;margin-bottom:14px;">🎓</div>
        <h3 style="font-weight:800;margin-bottom:10px;">كيف يعمل نظام التحقق؟</h3>
        <p style="color:rgba(255,255,255,.75);line-height:1.8;max-width:480px;margin:0 auto;">
          أدخل رمز الشهادة الموجود في أسفل الشهادة الورقية أو المطبوعة، أو
          امسح رمز QR الموجود على الشهادة للتحقق الفوري من صحتها.
        </p>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
