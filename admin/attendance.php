<?php
/**
 * admin/attendance.php — إدارة الحضور اليومي للمشاركين في الدورات
 * يتطلب تسجيل دخول
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

requireLogin('../login.php');

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$tableExists = (bool)$pdo->query("SHOW TABLES LIKE 'attendance'")->fetchColumn();
$success = null;
$error   = null;

$courseId  = (int)($_GET['course_id'] ?? 0);
$attDate   = $_GET['att_date'] ?? date('Y-m-d');

/* التحقق من صحة التاريخ */
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attDate)) {
    $attDate = date('Y-m-d');
}

/* ──────────────────────────────────────────────────────────
   حفظ الحضور
   ────────────────────────────────────────────────────────── */
if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    verifyCsrf();

    $postedCourse = (int)($_POST['course_id'] ?? 0);
    $postedDate   = (string)($_POST['att_date'] ?? '');
    $attendances  = $_POST['attendance'] ?? []; // [participant_id => 'present'|'absent']

    if (!$postedCourse || !$postedDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        $error = 'بيانات غير صحيحة.';
    } else {
        try {
            foreach ($attendances as $pid => $status) {
                $pid    = (int)$pid;
                $status = in_array($status, ['present', 'absent']) ? $status : 'absent';

                $pdo->prepare("
                    INSERT INTO attendance (course_id, participant_id, att_date, status)
                    VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status)
                ")->execute([$postedCourse, $pid, $postedDate, $status]);
            }
            $success = '✅ تم حفظ سجل الحضور بنجاح ليوم ' . e($postedDate) . '.';
            $courseId = $postedCourse;
            $attDate  = $postedDate;
        } catch (Exception $ex) {
            $error = 'فشل حفظ الحضور: ' . $ex->getMessage();
        }
    }
}

/* جلب الدورات */
$courses = $pdo->query("SELECT id, course_name, start_date, end_date, days_count FROM courses ORDER BY id DESC")->fetchAll();

/* جلب المشاركين في الدورة المحددة مع إحصائيات الحضور */
$participants  = [];
$existingAttendance = [];
$attendanceStats = [];

if ($courseId && $tableExists) {
    /* المشاركون المسجلون في الدورة */
    $pStmt = $pdo->prepare("
        SELECT p.*
        FROM participants p
        JOIN course_participants cp ON cp.participant_id = p.id
        WHERE cp.course_id = ?
        ORDER BY p.full_name
    ");
    $pStmt->execute([$courseId]);
    $participants = $pStmt->fetchAll();

    /* حضور اليوم المحدد */
    $aStmt = $pdo->prepare("
        SELECT participant_id, status
        FROM attendance
        WHERE course_id=? AND att_date=?
    ");
    $aStmt->execute([$courseId, $attDate]);
    foreach ($aStmt->fetchAll() as $a) {
        $existingAttendance[(int)$a['participant_id']] = $a['status'];
    }

    /* إحصائيات الحضور لكل مشارك */
    $statsStmt = $pdo->prepare("
        SELECT participant_id,
               SUM(status='present') AS present_count,
               COUNT(*) AS total_days
        FROM attendance
        WHERE course_id=?
        GROUP BY participant_id
    ");
    $statsStmt->execute([$courseId]);
    foreach ($statsStmt->fetchAll() as $s) {
        $attendanceStats[(int)$s['participant_id']] = $s;
    }
}

/* جلب نسبة الحضور الدنيا للدورة */
$minPct = 80;
if ($courseId) {
    $cRow = $pdo->prepare("SELECT days_count, min_attendance_pct FROM courses WHERE id=? LIMIT 1");
    $cRow->execute([$courseId]);
    $cData = $cRow->fetch();
    if ($cData) {
        $minPct = isset($cData['min_attendance_pct']) ? (int)$cData['min_attendance_pct'] : 80;
    }
}

$todayLabel = date('Y-m-d');
$nowLabel   = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>الحضور | نظام التعليم المستمر</title>
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
    --danger1:#ef4444; --danger2:#b91c1c;
    --radius:18px;
}
*{box-sizing:border-box;}
body{margin:0;font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Cairo",sans-serif;background:url("/continuous_education/assets/img/dashboard-bg.jpg") no-repeat center center fixed;background-size:cover;color:var(--text);font-size:14px;}
a{color:inherit;text-decoration:none;}a:hover{color:inherit;}
.overlay{background:linear-gradient(180deg,var(--overlay1),var(--overlay2));min-height:100vh;padding:22px 14px 34px;}
.content{max-width:1100px;margin:0 auto;}
.glass{background:var(--card);border:1px solid var(--border);backdrop-filter:blur(16px);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:14px;}
.glass.soft{background:var(--card2);box-shadow:var(--shadow2);}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-badge{width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 12px 25px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto;}
.brand h1{margin:0;font-size:18px;font-weight:800;}
.brand small{display:block;color:var(--muted);font-size:12px;font-weight:700;margin-top:2px;}
.actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.btn-soft{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.10);color:#fff;padding:10px 14px;border-radius:14px;font-weight:800;transition:.2s ease;display:inline-flex;align-items:center;gap:8px;cursor:pointer;text-decoration:none;}
.btn-soft:hover{transform:translateY(-2px);background:rgba(255,255,255,.14);}
.btn-soft.success{background:linear-gradient(135deg,rgba(34,197,94,.40),rgba(22,163,74,.22));border-color:rgba(34,197,94,.40);}
.btn-soft.danger{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10);}
.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.section-title h2{margin:0;font-weight:800;font-size:16px;}
hr.sep{border:none;border-top:1px solid rgba(255,255,255,.14);margin:12px 0;}
.form-label{font-weight:800;color:rgba(255,255,255,.92);margin-bottom:6px;}
.form-control,.form-select{border-radius:14px !important;padding:10px 12px;font-weight:700;color:#fff;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.16);}
.form-control:focus,.form-select:focus{border-color:rgba(37,99,235,.55);box-shadow:0 0 0 .2rem rgba(37,99,235,.20);background:rgba(0,0,0,.22);color:#fff;}
.table-soft{width:100%;border-collapse:separate;border-spacing:0;border-radius:16px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.08);}
.table-soft th,.table-soft td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.12);font-weight:800;vertical-align:middle;text-align:center;}
.table-soft th{background:rgba(0,0,0,.18);color:rgba(255,255,255,.85);font-size:12px;}
.table-soft td{color:#fff;font-size:13px;}
.table-soft tr:last-child td{border-bottom:none;}
.att-btn{border-radius:12px;padding:6px 16px;font-weight:800;font-size:13px;cursor:pointer;border:2px solid transparent;transition:.15s;}
.att-btn.present-btn{border-color:rgba(34,197,94,.50);background:rgba(34,197,94,.14);color:#4ade80;}
.att-btn.absent-btn{border-color:rgba(239,68,68,.50);background:rgba(239,68,68,.14);color:#f87171;}
.att-btn.selected-present{background:rgba(34,197,94,.55);border-color:#22c55e;color:#fff;}
.att-btn.selected-absent{background:rgba(239,68,68,.55);border-color:#ef4444;color:#fff;}
.pct-bar{height:8px;border-radius:999px;background:rgba(255,255,255,.12);overflow:hidden;}
.pct-fill{height:100%;border-radius:999px;transition:.3s;}
</style>
</head>
<body>
<div class="overlay">
  <div class="content">

    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge">✅</div>
          <div>
            <h1>سجل الحضور اليومي</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • <?= e($nowLabel) ?></small>
          </div>
        </div>
        <div class="actions">
          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
          <a class="btn-soft danger" href="../logout.php">🚪 خروج</a>
        </div>
      </div>
      <?php if ($success): ?><hr class="sep"><div class="alert alert-success mb-0 fw-bold text-center"><?= e($success) ?></div><?php endif; ?>
      <?php if ($error): ?><hr class="sep"><div class="alert alert-danger mb-0 fw-bold text-center"><?= e($error) ?></div><?php endif; ?>
    </div>

    <?php if (!$tableExists): ?>
    <div class="glass soft">
      <div class="alert alert-warning mb-0 text-center fw-bold">
        ⚠️ جدول الحضور غير موجود. يرجى تطبيق: <code>sql/upgrade_2026-03-09.sql</code>
      </div>
    </div>
    <?php else: ?>

    <!-- اختيار الدورة والتاريخ -->
    <div class="glass soft">
      <div class="section-title"><h2>🔎 اختيار الدورة والتاريخ</h2></div>
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-7">
          <label class="form-label">الدورة</label>
          <select name="course_id" class="form-select" required>
            <option value="">اختر الدورة</option>
            <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($courseId === (int)$c['id'] ? 'selected' : '') ?>>
              <?= e($c['course_name']) ?>
              <?php if ($c['start_date']): ?>(<?= e($c['start_date']) ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3">
          <label class="form-label">تاريخ الحضور</label>
          <input type="date" name="att_date" class="form-control" value="<?= e($attDate) ?>">
        </div>
        <div class="col-lg-2">
          <button type="submit" class="btn-soft w-100 justify-content-center" style="padding:10px;">🔍 عرض</button>
        </div>
      </form>
    </div>

    <?php if ($courseId && !empty($participants)): ?>
    <!-- نموذج تسجيل الحضور -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 تسجيل الحضور — <?= e($attDate) ?></h2>
        <div style="color:var(--muted);font-size:12px;"><?= count($participants) ?> مشارك</div>
      </div>

      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="save_attendance" value="1">
        <input type="hidden" name="course_id" value="<?= (int)$courseId ?>">
        <input type="hidden" name="att_date" value="<?= e($attDate) ?>">

        <!-- أزرار تحديد الكل -->
        <div style="display:flex;gap:8px;margin-bottom:14px;">
          <button type="button" onclick="setAll('present')" class="btn-soft" style="padding:7px 12px;font-size:12px;">✅ تحضير الكل</button>
          <button type="button" onclick="setAll('absent')"  class="btn-soft danger" style="padding:7px 12px;font-size:12px;">❌ تغيب الكل</button>
        </div>

        <div class="table-responsive">
          <table class="table-soft">
            <thead>
              <tr>
                <th style="width:50px;">#</th>
                <th style="text-align:right;">اسم المشارك</th>
                <th style="width:220px;">الحضور</th>
                <th style="width:180px;">نسبة الحضور الإجمالية</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($participants as $i => $p): ?>
              <?php
                $pid = (int)$p['id'];
                $currentStatus = $existingAttendance[$pid] ?? 'present';
                $stats = $attendanceStats[$pid] ?? null;
                $presentCount = $stats ? (int)$stats['present_count'] : 0;
                $totalDays    = $stats ? (int)$stats['total_days']    : 0;
                $pct = $totalDays > 0 ? (int)round(($presentCount / $totalDays) * 100) : 0;
                $barColor = $pct >= $minPct ? '#22c55e' : ($pct >= 60 ? '#f59e0b' : '#ef4444');
              ?>
              <tr>
                <td><?= ($i+1) ?></td>
                <td style="text-align:right;"><?= e($p['full_name']) ?></td>
                <td>
                  <input type="hidden" name="attendance[<?= $pid ?>]" id="att_<?= $pid ?>" value="<?= e($currentStatus) ?>">
                  <div style="display:flex;gap:6px;justify-content:center;">
                    <button type="button"
                            class="att-btn present-btn <?= $currentStatus === 'present' ? 'selected-present' : '' ?>"
                            id="btn_p_<?= $pid ?>"
                            onclick="setStatus(<?= $pid ?>, 'present')">✅ حاضر</button>
                    <button type="button"
                            class="att-btn absent-btn <?= $currentStatus === 'absent' ? 'selected-absent' : '' ?>"
                            id="btn_a_<?= $pid ?>"
                            onclick="setStatus(<?= $pid ?>, 'absent')">❌ غائب</button>
                  </div>
                </td>
                <td>
                  <?php if ($totalDays > 0): ?>
                  <div style="font-size:12px;margin-bottom:4px;color:<?= $pct >= $minPct ? '#4ade80' : '#f87171' ?>;">
                    <?= $presentCount ?>/<?= $totalDays ?> يوم (<?= $pct ?>%)
                  </div>
                  <div class="pct-bar">
                    <div class="pct-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                  </div>
                  <?php else: ?>
                  <span style="color:var(--muted);font-size:12px;">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <hr class="sep">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <small style="color:var(--muted);">الحد الأدنى للحضور لإصدار الشهادة: <?= (int)$minPct ?>%</small>
          <button type="submit" class="btn-soft success" style="padding:12px 22px;font-size:15px;">💾 حفظ الحضور</button>
        </div>
      </form>
    </div>

    <?php elseif ($courseId): ?>
    <div class="glass soft">
      <p style="text-align:center;color:var(--muted);padding:20px 0;">لا يوجد مشاركون مسجلون في هذه الدورة.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script>
function setStatus(pid, status) {
  document.getElementById('att_' + pid).value = status;
  const pb = document.getElementById('btn_p_' + pid);
  const ab = document.getElementById('btn_a_' + pid);
  if (status === 'present') {
    pb.classList.add('selected-present');
    ab.classList.remove('selected-absent');
  } else {
    ab.classList.add('selected-absent');
    pb.classList.remove('selected-present');
  }
}
function setAll(status) {
  document.querySelectorAll('[id^="att_"]').forEach(el => {
    const pid = el.id.replace('att_', '');
    setStatus(pid, status);
  });
}
</script>
</body>
</html>
