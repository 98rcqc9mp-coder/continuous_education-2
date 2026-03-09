<?php
/**
 * admin/rooms.php — إدارة القاعات التدريبية وجدولتها
 * يتطلب تسجيل دخول
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

requireLogin('../login.php');

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

$roomsExist    = (bool)$pdo->query("SHOW TABLES LIKE 'rooms'")->fetchColumn();
$scheduleExist = (bool)$pdo->query("SHOW TABLES LIKE 'course_room_schedule'")->fetchColumn();

$success = null;
$error   = null;
$view    = $_GET['view'] ?? 'rooms'; // rooms | schedule

/* ──────────────────────────────────────────────────────────
   إجراءات القاعات
   ────────────────────────────────────────────────────────── */
if ($roomsExist && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_room') {
        $name     = trim((string)($_POST['name'] ?? ''));
        $capacity = (int)($_POST['capacity'] ?? 0) ?: null;
        $location = trim((string)($_POST['location'] ?? '')) ?: null;

        if (!$name) {
            $error = 'يرجى إدخال اسم القاعة.';
        } else {
            try {
                $pdo->prepare("INSERT INTO rooms (name, capacity, location) VALUES (?,?,?)")
                    ->execute([$name, $capacity, $location]);
                $success = '✅ تمت إضافة القاعة بنجاح.';
            } catch (Exception $ex) {
                $error = 'فشل إضافة القاعة: ' . $ex->getMessage();
            }
        }
    }

    if ($action === 'delete_room') {
        $rid = (int)($_POST['room_id'] ?? 0);
        if ($rid > 0) {
            try {
                $pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$rid]);
                $success = '✅ تم حذف القاعة.';
            } catch (Exception $ex) {
                $error = 'فشل الحذف: ' . $ex->getMessage();
            }
        }
    }

    /* ────── جدولة قاعة لدورة ────── */
    if ($action === 'add_schedule' && $scheduleExist) {
        $courseId     = (int)($_POST['course_id'] ?? 0);
        $roomId       = (int)($_POST['room_id']   ?? 0);
        $startDate    = $_POST['start_date']    ?? '';
        $endDate      = $_POST['end_date']      ?? '';
        $lectureTime  = trim((string)($_POST['lecture_time'] ?? '')) ?: null;

        if (!$courseId || !$roomId || !$startDate || !$endDate) {
            $error = 'يرجى تعبئة جميع الحقول المطلوبة.';
        } elseif ($startDate > $endDate) {
            $error = 'تاريخ البدء يجب أن يكون قبل تاريخ الانتهاء.';
        } else {
            /* التحقق من تعارض الجدولة
             * المنطق: تعارض إذا لم تكن الفترتان منفصلتين تماماً
             * (الفترة الجديدة تنتهي قبل بدء الموجودة) OR (تبدأ بعد انتهاء الموجودة)
             * ويتحقق التعارض بنفي ذلك: NOT (end < start2 OR start > end2)
             */
            $conflict = $pdo->prepare("
                SELECT crs.id, c.course_name
                FROM course_room_schedule crs
                JOIN courses c ON c.id = crs.course_id
                WHERE crs.room_id = ?
                  AND crs.course_id != ?
                  AND NOT (crs.end_date < ? OR crs.start_date > ?)
                LIMIT 1
            ");
            $conflict->execute([$roomId, $courseId, $startDate, $endDate]);
            $conflictRow = $conflict->fetch();

            if ($conflictRow) {
                $error = '⚠️ تعارض في الجدولة! القاعة محجوزة لدورة: ' . e($conflictRow['course_name'])
                       . '. يرجى اختيار قاعة أخرى أو تغيير التواريخ.';
            } else {
                /* التحقق من عدم جدولة نفس الدورة مرتين في نفس القاعة */
                $dupCheck = $pdo->prepare("
                    SELECT 1 FROM course_room_schedule
                    WHERE course_id=? AND room_id=?
                    LIMIT 1
                ");
                $dupCheck->execute([$courseId, $roomId]);
                if ($dupCheck->fetchColumn()) {
                    $error = 'هذه الدورة مجدولة بالفعل في هذه القاعة.';
                } else {
                    try {
                        $pdo->prepare("
                            INSERT INTO course_room_schedule
                                (course_id, room_id, start_date, end_date, lecture_time)
                            VALUES (?,?,?,?,?)
                        ")->execute([$courseId, $roomId, $startDate, $endDate, $lectureTime]);
                        $success = '✅ تم جدولة القاعة للدورة بنجاح.';
                    } catch (Exception $ex) {
                        $error = 'فشل الجدولة: ' . $ex->getMessage();
                    }
                }
            }
        }
    }

    if ($action === 'delete_schedule' && $scheduleExist) {
        $sid = (int)($_POST['schedule_id'] ?? 0);
        if ($sid > 0) {
            $pdo->prepare("DELETE FROM course_room_schedule WHERE id=?")->execute([$sid]);
            $success = '✅ تم حذف الجدولة.';
        }
    }
}

/* جلب البيانات */
$rooms   = $roomsExist    ? $pdo->query("SELECT * FROM rooms ORDER BY name")->fetchAll()              : [];
$courses = $pdo->query("SELECT id, course_name, start_date, end_date FROM courses ORDER BY id DESC")->fetchAll();

$schedules = [];
if ($scheduleExist) {
    $schedules = $pdo->query("
        SELECT crs.*, r.name AS room_name, c.course_name, c.start_date AS c_start, c.end_date AS c_end
        FROM course_room_schedule crs
        JOIN rooms r ON r.id = crs.room_id
        JOIN courses c ON c.id = crs.course_id
        ORDER BY crs.start_date DESC
    ")->fetchAll();
}

$todayLabel = date('Y-m-d');
$nowLabel   = date('Y-m-d H:i');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إدارة القاعات | نظام التعليم المستمر</title>
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
body{margin:0;font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Cairo",sans-serif;background:url("/continuous_education/assets/img/dashboard-bg.jpg") no-repeat center center fixed;background-size:cover;color:var(--text);font-size:14px;}
a{color:inherit;text-decoration:none;}a:hover{color:inherit;}
.overlay{background:linear-gradient(180deg,var(--overlay1),var(--overlay2));min-height:100vh;padding:22px 14px 34px;}
.content{max-width:1100px;margin:0 auto;}
.glass{background:var(--card);border:1px solid var(--border);backdrop-filter:blur(16px);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:14px;}
.glass.soft{background:var(--card2);box-shadow:var(--shadow2);}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-badge{width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,var(--info1,#06b6d4),var(--info2,#0e7490));box-shadow:0 12px 25px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto;}
.brand h1{margin:0;font-size:18px;font-weight:800;}
.brand small{display:block;color:var(--muted);font-size:12px;font-weight:700;margin-top:2px;}
.actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.btn-soft{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.10);color:#fff;padding:10px 14px;border-radius:14px;font-weight:800;transition:.2s ease;display:inline-flex;align-items:center;gap:8px;cursor:pointer;text-decoration:none;}
.btn-soft:hover{transform:translateY(-2px);background:rgba(255,255,255,.14);}
.btn-soft.success{background:linear-gradient(135deg,rgba(34,197,94,.40),rgba(22,163,74,.22));border-color:rgba(34,197,94,.40);}
.btn-soft.danger{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.10);}
.btn-soft.active{background:rgba(37,99,235,.35);border-color:rgba(37,99,235,.50);}
.section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.section-title h2{margin:0;font-weight:800;font-size:16px;}
hr.sep{border:none;border-top:1px solid rgba(255,255,255,.14);margin:12px 0;}
.form-label{font-weight:800;color:rgba(255,255,255,.92);margin-bottom:6px;}
.form-control,.form-select{border-radius:14px !important;padding:10px 12px;font-weight:700;color:#fff;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.16);}
.form-control:focus,.form-select:focus{border-color:rgba(37,99,235,.55);box-shadow:0 0 0 .2rem rgba(37,99,235,.20);background:rgba(0,0,0,.22);color:#fff;}
.table-soft{width:100%;border-collapse:separate;border-spacing:0;border-radius:16px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.08);}
.table-soft th,.table-soft td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.12);font-weight:800;vertical-align:middle;}
.table-soft th{background:rgba(0,0,0,.18);color:rgba(255,255,255,.85);font-size:12px;}
.table-soft td{color:#fff;font-size:13px;}
.table-soft tr:last-child td{border-bottom:none;}
.btn-mini{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.10);color:#fff;border-radius:10px;padding:5px 10px;font-size:12px;font-weight:800;cursor:pointer;transition:.18s;}
.btn-mini.danger{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.12);}
</style>
</head>
<body>
<div class="overlay">
  <div class="content">

    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge">🏫</div>
          <div>
            <h1>إدارة القاعات التدريبية</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • <?= e($nowLabel) ?></small>
          </div>
        </div>
        <div class="actions">
          <a class="btn-soft <?= $view === 'rooms' ? 'active' : '' ?>" href="?view=rooms">🏫 القاعات</a>
          <a class="btn-soft <?= $view === 'schedule' ? 'active' : '' ?>" href="?view=schedule">📅 الجدولة</a>
          <a class="btn-soft" href="../dashboard.php">🏠 الرئيسية</a>
          <a class="btn-soft danger" href="../logout.php">🚪 خروج</a>
        </div>
      </div>

      <?php if ($success): ?>
      <hr class="sep">
      <div class="alert alert-success mb-0 fw-bold text-center"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
      <hr class="sep">
      <div class="alert alert-danger mb-0 fw-bold text-center"><?= $error ?></div>
      <?php endif; ?>
    </div>

    <?php if (!$roomsExist): ?>
    <div class="glass soft">
      <div class="alert alert-warning mb-0 text-center fw-bold">
        ⚠️ جداول القاعات غير موجودة. يرجى تطبيق ملف الترقية: <code>sql/upgrade_2026-03-09.sql</code>
      </div>
    </div>
    <?php elseif ($view === 'rooms'): ?>

    <!-- إضافة قاعة جديدة -->
    <div class="glass soft">
      <div class="section-title"><h2>➕ إضافة قاعة جديدة</h2></div>
      <form method="post" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_room">
        <div class="col-lg-5">
          <label class="form-label">اسم القاعة *</label>
          <input type="text" name="name" class="form-control" required placeholder="مثال: قاعة المؤتمرات">
        </div>
        <div class="col-lg-3">
          <label class="form-label">السعة (عدد المقاعد)</label>
          <input type="number" name="capacity" class="form-control" min="1" placeholder="مثال: 30">
        </div>
        <div class="col-lg-2">
          <label class="form-label">الموقع / الطابق</label>
          <input type="text" name="location" class="form-control" placeholder="مثال: الطابق الثاني">
        </div>
        <div class="col-lg-2">
          <button type="submit" class="btn-soft success w-100 justify-content-center" style="padding:10px;">💾 إضافة</button>
        </div>
      </form>
    </div>

    <!-- قائمة القاعات -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 قائمة القاعات</h2>
        <div style="color:var(--muted);font-size:12px;"><?= count($rooms) ?> قاعة</div>
      </div>
      <?php if (empty($rooms)): ?>
      <p style="text-align:center;color:var(--muted);padding:20px 0;">لا توجد قاعات مضافة بعد.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table-soft">
          <thead>
            <tr>
              <th>#</th>
              <th style="text-align:right;">اسم القاعة</th>
              <th>السعة</th>
              <th>الموقع</th>
              <th>حذف</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rooms as $i => $room): ?>
            <tr>
              <td><?= ($i+1) ?></td>
              <td style="text-align:right;"><?= e($room['name']) ?></td>
              <td><?= $room['capacity'] ? (int)$room['capacity'] . ' مقعد' : '—' ?></td>
              <td><?= e($room['location'] ?? '—') ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_room">
                  <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
                  <button type="submit" class="btn-mini danger"
                          onclick="return confirm('حذف القاعة؟')">🗑 حذف</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($view === 'schedule'): ?>

    <!-- جدولة قاعة -->
    <?php if ($scheduleExist): ?>
    <div class="glass soft">
      <div class="section-title"><h2>📅 جدولة قاعة لدورة</h2></div>
      <form method="post" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_schedule">
        <div class="col-lg-4">
          <label class="form-label">الدورة *</label>
          <select name="course_id" class="form-select" required>
            <option value="">اختر الدورة</option>
            <?php foreach ($courses as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['course_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3">
          <label class="form-label">القاعة *</label>
          <select name="room_id" class="form-select" required>
            <option value="">اختر القاعة</option>
            <?php foreach ($rooms as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label class="form-label">تاريخ البدء *</label>
          <input type="date" name="start_date" class="form-control" required>
        </div>
        <div class="col-lg-2">
          <label class="form-label">تاريخ الانتهاء *</label>
          <input type="date" name="end_date" class="form-control" required>
        </div>
        <div class="col-lg-3">
          <label class="form-label">وقت المحاضرة</label>
          <input type="text" name="lecture_time" class="form-control" placeholder="مثال: 9:00 - 12:00">
        </div>
        <div class="col-lg-2 d-flex align-items-end">
          <button type="submit" class="btn-soft success w-100 justify-content-center" style="padding:10px;">📅 جدولة</button>
        </div>
      </form>
    </div>

    <!-- جدول الجدولة -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 الجدول الحالي</h2>
        <div style="color:var(--muted);font-size:12px;"><?= count($schedules) ?> جدولة</div>
      </div>
      <?php if (empty($schedules)): ?>
      <p style="text-align:center;color:var(--muted);padding:20px 0;">لا توجد جدولة حالياً.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table-soft">
          <thead>
            <tr>
              <th>#</th>
              <th style="text-align:right;">الدورة</th>
              <th>القاعة</th>
              <th>من</th>
              <th>إلى</th>
              <th>وقت المحاضرة</th>
              <th>حذف</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($schedules as $i => $s): ?>
            <tr>
              <td><?= ($i+1) ?></td>
              <td style="text-align:right;"><?= e($s['course_name']) ?></td>
              <td><?= e($s['room_name']) ?></td>
              <td><?= e($s['start_date']) ?></td>
              <td><?= e($s['end_date']) ?></td>
              <td><?= e($s['lecture_time'] ?? '—') ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_schedule">
                  <input type="hidden" name="schedule_id" value="<?= (int)$s['id'] ?>">
                  <button type="submit" class="btn-mini danger"
                          onclick="return confirm('حذف هذه الجدولة؟')">🗑 حذف</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
