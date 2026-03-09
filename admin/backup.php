<?php
/**
 * admin/backup.php — نسخ احتياطي واسترجاع قاعدة البيانات
 * يتطلب تسجيل دخول مع دور admin
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

requireAdmin('../login.php');

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$success = null;
$error   = null;

/* ───────────────────────────────────────────
   معالجة النسخ الاحتياطي (تنزيل SQL dump)
   ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    verifyCsrf();

    /* توليد SQL dump عبر PHP بدون exec/mysqldump */
    try {
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $sql  = "-- نسخة احتياطية من قاعدة البيانات\n";
        $sql .= "-- التاريخ: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- النظام: نظام التعليم المستمر\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            /* هيكل الجدول */
            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            $sql .= "-- ────────── جدول: {$table} ──────────\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createRow[1] . ";\n\n";

            /* بيانات الجدول */
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_NUM);
            if ($rows) {
                $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
                $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));

                $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";
                $rowsSql = [];
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, $row);
                    $rowsSql[] = '(' . implode(', ', $vals) . ')';
                }
                $sql .= implode(",\n", $rowsSql) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        /* إرسال الملف للتنزيل */
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit;

    } catch (Exception $ex) {
        $error = 'فشل إنشاء النسخة الاحتياطية: ' . $ex->getMessage();
    }
}

/* ───────────────────────────────────────────
   معالجة الاسترجاع (رفع ملف SQL)
   ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    verifyCsrf();

    if (empty($_FILES['sql_file']['tmp_name'])) {
        $error = 'يرجى اختيار ملف SQL صالح.';
    } else {
        $tmpFile = $_FILES['sql_file']['tmp_name'];
        $origName = $_FILES['sql_file']['name'] ?? '';

        /* التحقق من الامتداد */
        if (strtolower(pathinfo($origName, PATHINFO_EXTENSION)) !== 'sql') {
            $error = 'يسمح فقط بملفات بامتداد .sql';
        } else {
            $sqlContent = file_get_contents($tmpFile);
            if ($sqlContent === false || strlen(trim($sqlContent)) === 0) {
                $error = 'الملف فارغ أو تعذّر قراءته.';
            } else {
                try {
                    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

                    /* تقسيم الـ SQL إلى جمل
                     * ملاحظة: هذا المحلل البسيط يعمل مع معظم ملفات dump القياسية.
                     * قد يخفق إذا احتوى الملف على فاصلة منقوطة داخل قيم نصية.
                     * يُنصح باستخدام نسخ احتياطية موثوقة من هذا النظام نفسه.
                     */
                    $delimiter = ';';
                    $stmts = array_filter(
                        array_map('trim', explode($delimiter, $sqlContent)),
                        fn($s) => $s !== '' && !preg_match('/^--/', $s)
                    );

                    foreach ($stmts as $stm) {
                        if (trim($stm) !== '') {
                            $pdo->exec($stm);
                        }
                    }

                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    $success = '✅ تم استرجاع قاعدة البيانات بنجاح من الملف: ' . e($origName);

                } catch (PDOException $ex) {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    $error = 'فشل الاسترجاع: ' . $ex->getMessage();
                }
            }
        }
    }
}

$todayLabel = date('Y-m-d');
$nowLabel   = date('Y-m-d H:i');
$token      = csrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>النسخ الاحتياطي | نظام التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --overlay1:rgba(0,0,0,.55); --overlay2:rgba(0,0,0,.70);
    --card:rgba(255,255,255,.10); --card2:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.14); --text:#ffffff; --muted:rgba(255,255,255,.74);
    --shadow:0 20px 60px rgba(0,0,0,.55); --shadow2:0 14px 35px rgba(0,0,0,.35);
    --primary1:#2563eb; --primary2:#1e40af;
    --success1:#22c55e; --success2:#16a34a;
    --warn1:#f59e0b; --warn2:#d97706;
    --danger1:#ef4444; --danger2:#b91c1c;
    --radius:18px;
}
*{box-sizing:border-box;}
body{
    margin:0;
    font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Cairo",sans-serif;
    background:url("/continuous_education/assets/img/dashboard-bg.jpg") no-repeat center center fixed;
    background-size:cover; color:var(--text); font-size:14px;
}
a{color:inherit;text-decoration:none;}
a:hover{color:inherit;}
.overlay{background:linear-gradient(180deg,var(--overlay1),var(--overlay2));min-height:100vh;padding:22px 14px 34px;}
.content{max-width:860px;margin:0 auto;}
.glass{background:var(--card);border:1px solid var(--border);backdrop-filter:blur(16px);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:14px;}
.glass.soft{background:var(--card2);box-shadow:var(--shadow2);}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-badge{width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,var(--warn1),var(--warn2));box-shadow:0 12px 25px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto;}
.brand h1{margin:0;font-size:18px;font-weight:800;}
.brand small{display:block;color:var(--muted);font-weight:700;margin-top:2px;font-size:12px;}
.actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.btn-soft{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.10);color:#fff;padding:10px 14px;border-radius:14px;font-weight:800;transition:.2s ease;display:inline-flex;align-items:center;gap:8px;cursor:pointer;}
.btn-soft:hover{transform:translateY(-2px);background:rgba(255,255,255,.14);}
.btn-soft.success{background:linear-gradient(135deg,rgba(34,197,94,.40),rgba(22,163,74,.22));border-color:rgba(34,197,94,.40);}
.btn-soft.danger{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10);}
.btn-soft.primary{background:linear-gradient(135deg,rgba(37,99,235,.55),rgba(30,64,175,.25));border-color:rgba(37,99,235,.45);}
.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;}
.section-title h2{margin:0;font-weight:800;font-size:16px;}
.section-title .meta{color:var(--muted);font-size:12px;}
.form-label{font-weight:800;color:rgba(255,255,255,.92);margin-bottom:6px;}
.form-control{border-radius:14px !important;padding:12px;font-weight:700;color:#fff;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.16);}
.form-control:focus{border-color:rgba(37,99,235,.55);box-shadow:0 0 0 .2rem rgba(37,99,235,.20);background:rgba(0,0,0,.22);color:#fff;}
hr.sep{border:none;border-top:1px solid rgba(255,255,255,.14);margin:12px 0;}
.warning-box{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.30);border-radius:14px;padding:14px 16px;font-size:13px;color:rgba(255,255,255,.85);}
</style>
</head>
<body>
<div class="overlay">
  <div class="content">

    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge">💾</div>
          <div>
            <h1>النسخ الاحتياطي والاسترجاع</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • آخر تحديث: <?= e($nowLabel) ?></small>
          </div>
        </div>
        <div class="actions">
          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
          <a class="btn-soft danger" href="../logout.php">🚪 خروج</a>
        </div>
      </div>

      <?php if ($success): ?>
      <hr class="sep">
      <div class="alert alert-success mb-0 fw-bold text-center"><?= $success ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
      <hr class="sep">
      <div class="alert alert-danger mb-0 fw-bold text-center"><?= e($error) ?></div>
      <?php endif; ?>
    </div>

    <!-- Backup -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📤 إنشاء نسخة احتياطية</h2>
        <div class="meta">يُنشئ ملف SQL يحتوي على هيكل وبيانات قاعدة البيانات</div>
      </div>

      <p style="color:rgba(255,255,255,.75);font-size:13px;margin-bottom:16px;">
        اضغط الزر أدناه لتنزيل نسخة كاملة من قاعدة البيانات بصيغة <code>.sql</code>.
        احتفظ بها في مكان آمن.
      </p>

      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="backup">
        <button type="submit" class="btn-soft success" style="font-size:15px;padding:12px 20px;">
          💾 تنزيل النسخة الاحتياطية (.sql)
        </button>
      </form>
    </div>

    <!-- Restore -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📥 استرجاع من نسخة احتياطية</h2>
        <div class="meta">استيراد ملف .sql لاسترجاع البيانات</div>
      </div>

      <div class="warning-box mb-3">
        ⚠️ <strong>تحذير:</strong> عملية الاسترجاع ستحذف البيانات الحالية وتستبدلها بما في الملف.
        تأكد من أنك ترفع الملف الصحيح وأن لديك نسخة من البيانات الحالية قبل المتابعة.
      </div>

      <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="restore">

        <div class="mb-3">
          <label class="form-label">اختر ملف SQL للاسترجاع</label>
          <input type="file" name="sql_file" accept=".sql" class="form-control" required>
        </div>

        <button type="submit" class="btn-soft danger"
                style="font-size:15px;padding:12px 20px;"
                onclick="return confirm('هل أنت متأكد من استرجاع قاعدة البيانات؟ ستُحذف البيانات الحالية.')">
          📥 استرجاع قاعدة البيانات
        </button>
      </form>
    </div>

  </div>
</div>
</body>
</html>
