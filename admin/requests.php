<?php
/**
 * admin/requests.php — إدارة طلبات التسجيل الواردة عبر الإنترنت
 * يتطلب تسجيل دخول
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

requireLogin('../login.php');

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

/* التحقق من وجود الجدول */
$tableExists = $pdo->query("SHOW TABLES LIKE 'course_registration_requests'")->fetchColumn();

$success = null;
$error   = null;

if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action    = $_POST['action']     ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $userId    = (int)($_SESSION['user_id']);

    if ($action === 'approve' && $requestId > 0) {
        /* جلب بيانات الطلب */
        $req = $pdo->prepare("SELECT * FROM course_registration_requests WHERE id=? AND status='pending' LIMIT 1");
        $req->execute([$requestId]);
        $request = $req->fetch();

        if (!$request) {
            $error = 'الطلب غير موجود أو تمت معالجته مسبقاً.';
        } else {
            try {
                $pdo->beginTransaction();

                /* التحقق من وجود عمود email في participants */
                $hasEmail = (bool)$pdo->query("SHOW COLUMNS FROM participants LIKE 'email'")->fetch();

                /* البحث عن مشارك موجود بنفس الهاتف أو البريد */
                $participantId = null;
                if ($request['phone']) {
                    $existing = $pdo->prepare("SELECT id FROM participants WHERE phone=? LIMIT 1");
                    $existing->execute([$request['phone']]);
                    $row = $existing->fetch();
                    if ($row) $participantId = (int)$row['id'];
                }
                if (!$participantId && !empty($request['email'])) {
                    $existing = $pdo->prepare("SELECT id FROM participants WHERE email IS NOT NULL AND email=? LIMIT 1");
                    $existing->execute([$request['email']]);
                    $row = $existing->fetch();
                    if ($row) $participantId = (int)$row['id'];
                }

                /* إنشاء مشارك جديد إن لم يكن موجوداً */
                if (!$participantId) {
                    $gender  = $request['gender'] ?? 'ذكر';
                    $inside  = 'خارج';
                    $work    = $request['work_place'] ?? '';
                    $phone   = $request['phone'] ?? null;

                    if ($hasEmail) {
                        $ins = $pdo->prepare("
                            INSERT INTO participants
                                (full_name, gender, inside_university, work_place, phone, email)
                            VALUES (?,?,?,?,?,?)
                        ");
                        $ins->execute([$request['full_name'], $gender, $inside, $work, $phone, $request['email']]);
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO participants
                                (full_name, gender, inside_university, work_place, phone)
                            VALUES (?,?,?,?,?)
                        ");
                        $ins->execute([$request['full_name'], $gender, $inside, $work, $phone]);
                    }
                    $participantId = (int)$pdo->lastInsertId();
                }

                /* ربط المشارك بالدورة (تجاهل التكرار) */
                $cpCheck = $pdo->prepare("SELECT 1 FROM course_participants WHERE course_id=? AND participant_id=?");
                $cpCheck->execute([$request['course_id'], $participantId]);
                if (!$cpCheck->fetchColumn()) {
                    $cpIns = $pdo->prepare("INSERT INTO course_participants (course_id, participant_id) VALUES (?,?)");
                    $cpIns->execute([$request['course_id'], $participantId]);
                }

                /* تحديث حالة الطلب */
                $upd = $pdo->prepare("
                    UPDATE course_registration_requests
                    SET status='approved', handled_at=NOW(), handled_by=?
                    WHERE id=?
                ");
                $upd->execute([$userId, $requestId]);

                $pdo->commit();

                /* إرسال بريد تأكيد */
                if ($request['email']) {
                    $mailLib = __DIR__ . '/../lib/mail.php';
                    if (file_exists($mailLib)) {
                        require_once $mailLib;
                        $courseRow = $pdo->prepare("SELECT course_name, start_date FROM courses WHERE id=? LIMIT 1");
                        $courseRow->execute([$request['course_id']]);
                        $cd = $courseRow->fetch();
                        if ($cd) {
                            @sendApprovalEmail($request['email'], $request['full_name'], $cd['course_name'], $cd['start_date'] ?? '');
                        }
                    }
                }

                $success = '✅ تمت الموافقة على الطلب وإضافة المشارك بنجاح.';

            } catch (Exception $ex) {
                $pdo->rollBack();
                $error = 'فشل معالجة الطلب: ' . $ex->getMessage();
            }
        }

    } elseif ($action === 'reject' && $requestId > 0) {
        try {
            $upd = $pdo->prepare("
                UPDATE course_registration_requests
                SET status='rejected', handled_at=NOW(), handled_by=?
                WHERE id=? AND status='pending'
            ");
            $upd->execute([$userId, $requestId]);
            $success = '❌ تم رفض الطلب.';
        } catch (Exception $ex) {
            $error = 'فشل الرفض: ' . $ex->getMessage();
        }
    }
}

/* جلب الطلبات مع تفاصيل الدورة */
$requests = [];
if ($tableExists) {
    $filter = $_GET['filter'] ?? 'pending';
    $allowedFilters = ['pending', 'approved', 'rejected', 'all'];
    if (!in_array($filter, $allowedFilters)) $filter = 'pending';

    if ($filter === 'all') {
        $stmt = $pdo->query("
            SELECT r.*, c.course_name, c.start_date
            FROM course_registration_requests r
            LEFT JOIN courses c ON c.id = r.course_id
            ORDER BY r.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, c.course_name, c.start_date
            FROM course_registration_requests r
            LEFT JOIN courses c ON c.id = r.course_id
            WHERE r.status = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$filter]);
    }
    $requests = $stmt->fetchAll();
}

/* إحصائيات */
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
if ($tableExists) {
    $cStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM course_registration_requests GROUP BY status");
    foreach ($cStmt->fetchAll() as $row) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
}

$todayLabel = date('Y-m-d');
$nowLabel   = date('Y-m-d H:i');
$filter     = $_GET['filter'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طلبات التسجيل | نظام التعليم المستمر</title>
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
.content{max-width:1200px;margin:0 auto;}
.glass{background:var(--card);border:1px solid var(--border);backdrop-filter:blur(16px);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);margin-bottom:14px;}
.glass.soft{background:var(--card2);box-shadow:var(--shadow2);}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-badge{width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,var(--primary1),var(--primary2));box-shadow:0 12px 25px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:20px;flex:0 0 auto;}
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
.table-soft{width:100%;border-collapse:separate;border-spacing:0;border-radius:16px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.08);}
.table-soft th,.table-soft td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.12);font-weight:800;vertical-align:middle;}
.table-soft th{background:rgba(0,0,0,.18);color:rgba(255,255,255,.85);font-size:12px;}
.table-soft td{color:#fff;font-size:13px;}
.table-soft tr:last-child td{border-bottom:none;}
.pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800;}
.pill.pending{border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.14);color:#fbbf24;}
.pill.approved{border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.14);color:#4ade80;}
.pill.rejected{border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.14);color:#f87171;}
.stat-mini{padding:14px 18px;border-radius:16px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.18);text-align:center;}
.stat-mini .val{font-size:28px;font-weight:900;}
.stat-mini .lbl{font-size:12px;color:var(--muted);margin-top:2px;}
</style>
</head>
<body>
<div class="overlay">
  <div class="content">

    <div class="glass">
      <div class="topbar">
        <div class="brand">
          <div class="brand-badge">📬</div>
          <div>
            <h1>طلبات التسجيل عبر الإنترنت</h1>
            <small>تاريخ اليوم: <?= e($todayLabel) ?> • <?= e($nowLabel) ?></small>
          </div>
        </div>
        <div class="actions">
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
      <div class="alert alert-danger mb-0 fw-bold text-center"><?= e($error) ?></div>
      <?php endif; ?>
    </div>

    <?php if (!$tableExists): ?>
    <div class="glass soft">
      <div class="alert alert-warning mb-0 text-center fw-bold">
        ⚠️ جدول الطلبات غير موجود. يرجى تطبيق ملف الترقية:
        <code>sql/upgrade_2026-03-09.sql</code>
      </div>
    </div>
    <?php else: ?>

    <!-- إحصائيات -->
    <div class="glass soft">
      <div class="row g-3">
        <div class="col-4">
          <div class="stat-mini" style="border-color:rgba(245,158,11,.30);background:rgba(245,158,11,.10);">
            <div class="val"><?= $counts['pending'] ?></div>
            <div class="lbl">⏳ قيد الانتظار</div>
          </div>
        </div>
        <div class="col-4">
          <div class="stat-mini" style="border-color:rgba(34,197,94,.30);background:rgba(34,197,94,.10);">
            <div class="val"><?= $counts['approved'] ?></div>
            <div class="lbl">✅ مقبول</div>
          </div>
        </div>
        <div class="col-4">
          <div class="stat-mini" style="border-color:rgba(239,68,68,.30);background:rgba(239,68,68,.10);">
            <div class="val"><?= $counts['rejected'] ?></div>
            <div class="lbl">❌ مرفوض</div>
          </div>
        </div>
      </div>
    </div>

    <!-- فلاتر -->
    <div class="glass soft">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <span style="font-weight:800;color:var(--muted);">فلتر:</span>
        <?php foreach (['pending' => '⏳ قيد الانتظار', 'approved' => '✅ مقبول', 'rejected' => '❌ مرفوض', 'all' => '📋 الكل'] as $val => $lbl): ?>
        <a class="btn-soft <?= ($filter === $val ? 'active' : '') ?>"
           href="?filter=<?= e($val) ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- جدول الطلبات -->
    <div class="glass soft">
      <div class="section-title">
        <h2>📋 الطلبات</h2>
        <div style="color:var(--muted);font-size:12px;"><?= count($requests) ?> طلب</div>
      </div>

      <?php if (empty($requests)): ?>
      <p style="text-align:center;color:var(--muted);padding:20px 0;">لا توجد طلبات لعرضها.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table-soft">
          <thead>
            <tr>
              <th>#</th>
              <th style="text-align:right;">الاسم</th>
              <th>الدورة</th>
              <th>الهاتف</th>
              <th>البريد</th>
              <th>تاريخ الطلب</th>
              <th>الحالة</th>
              <th>إجراء</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $i => $r): ?>
            <tr>
              <td><?= ($i + 1) ?></td>
              <td style="text-align:right;"><?= e($r['full_name']) ?></td>
              <td><?= e($r['course_name'] ?? '-') ?></td>
              <td dir="ltr"><?= e($r['phone'] ?? '-') ?></td>
              <td dir="ltr"><?= e($r['email'] ?? '-') ?></td>
              <td><?= e(substr($r['created_at'], 0, 10)) ?></td>
              <td>
                <span class="pill <?= e($r['status']) ?>">
                  <?= $r['status'] === 'pending' ? '⏳ انتظار' : ($r['status'] === 'approved' ? '✅ مقبول' : '❌ مرفوض') ?>
                </span>
              </td>
              <td>
                <?php if ($r['status'] === 'pending'): ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn-soft success" style="padding:6px 10px;font-size:12px;"
                            onclick="return confirm('الموافقة على هذا الطلب؟')">✅ قبول</button>
                  </form>
                  <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn-soft danger" style="padding:6px 10px;font-size:12px;"
                            onclick="return confirm('رفض هذا الطلب؟')">❌ رفض</button>
                  </form>
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
      <?php endif; ?>
    </div>

    <?php endif; ?>

  </div>
</div>
</body>
</html>
