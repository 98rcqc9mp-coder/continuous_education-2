<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function intv($v) { return (int)($v ?? 0); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ================== Flash ================== */
$flash = '';
$flashTone = 'info';

/* ================== POST: Account settings (users) ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $flash = 'CSRF';
        $flashTone = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'change_username') {
            $newUsername = trim((string)($_POST['new_username'] ?? ''));
            if ($newUsername === '' || mb_strlen($newUsername) < 3) {
                $flash = 'اسم قصير';
                $flashTone = 'warn';
            } else {
                try {
                    $st = $pdo->prepare("UPDATE users SET username = :u WHERE id = :id");
                    $st->execute([':u' => $newUsername, ':id' => (int)$_SESSION['user_id']]);
                    $flash = 'تم';
                    $flashTone = 'success';
                } catch (Throwable $e) {
                    $flash = 'موجود';
                    $flashTone = 'danger';
                }
            }
        }

        if ($action === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $newPass = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if ($newPass === '' || strlen($newPass) < 6) {
                $flash = 'كلمة قصيرة';
                $flashTone = 'warn';
            } elseif ($newPass !== $confirm) {
                $flash = 'غير مطابق';
                $flashTone = 'warn';
            } else {
                try {
                    $st = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
                    $st->execute([':id' => (int)$_SESSION['user_id']]);
                    $hash = (string)$st->fetchColumn();

                    if ($hash === '' || !password_verify($current, $hash)) {
                        $flash = 'حالي خطأ';
                        $flashTone = 'danger';
                    } else {
                        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                        $up = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                        $up->execute([':p' => $newHash, ':id' => (int)$_SESSION['user_id']]);
                        $flash = 'تم';
                        $flashTone = 'success';
                    }
                } catch (Throwable $e) {
                    $flash = 'فشل';
                    $flashTone = 'danger';
                }
            }
        }
    }
}

/* ================== current username ================== */
$currentUsername = '';
try {
    $uSt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
    $uSt->execute([':id' => (int)$_SESSION['user_id']]);
    $currentUsername = (string)$uSt->fetchColumn();
} catch (Throwable $e) {
    $currentUsername = '';
}

/* ================== Search (SQL) ================== */
$q = trim((string)($_GET['q'] ?? ''));
$qLike = '%' . $q . '%';

function colExists(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
        $st->execute([':c' => $col]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/* ====== Courses search: flexible + extended ====== */
$courseCols = [
    'course_name',
    'start_date',
    'course_type',
    'course_category',
    'beneficiary',
    'target_group',
    'lecturer',
    'course_manager',
    'trainer_name',
    'location',
    'platform',
    'status',
    'description'
];

$cWhere = "1=1";
$cParams = [];
if ($q !== '') {
    $parts = [];

    // Always present in most schemas (keep as-is)
    $parts[] = "course_name LIKE :q1";
    $cParams[':q1'] = $qLike;

    // date as text
    $parts[] = "DATE_FORMAT(start_date,'%Y-%m-%d') LIKE :q2";
    $cParams[':q2'] = $qLike;

    // Optional columns
    $idx = 3;
    foreach ($courseCols as $col) {
        if (in_array($col, ['course_name','start_date'], true)) continue;
        if (colExists($pdo, 'courses', $col)) {
            $key = ":q{$idx}";
            $parts[] = "`$col` LIKE $key";
            $cParams[$key] = $qLike;
            $idx++;
        }
    }
    $cWhere = '(' . implode(' OR ', $parts) . ')';
}

/* ====== Participants search: flexible + extended ====== */
$nameCol = null;
if (colExists($pdo,'participants','name')) $nameCol = 'name';
elseif (colExists($pdo,'participants','full_name')) $nameCol = 'full_name';

$participantCols = [
    'phone','email','organization','notes',
    'work_place','academic_title','academic_rank','job_title',
    'general_specialization','specific_specialization','job_specialization',
    'inside_university'
];

$pWhere = "1=1";
$pParams = [];
if ($q !== '') {
    $pp = [];
    $pidx = 1;

    if ($nameCol) {
        $pp[] = "`$nameCol` LIKE :pq{$pidx}";
        $pParams[":pq{$pidx}"] = $qLike;
        $pidx++;
    }

    foreach ($participantCols as $col) {
        if (colExists($pdo, 'participants', $col)) {
            $pp[] = "`$col` LIKE :pq{$pidx}";
            $pParams[":pq{$pidx}"] = $qLike;
            $pidx++;
        }
    }

    $pWhere = count($pp) ? ('(' . implode(' OR ', $pp) . ')') : "1=0";
}

/* ================== Stats (بدون فلترة) ================== */
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalParticipants = (int)$pdo->query("SELECT COUNT(*) FROM participants")->fetchColumn();
$todayCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE start_date = CURDATE()")->fetchColumn();

$todayLabel = date('Y-m-d');
$nowLabel = date('Y-m-d H:i');

/* ================== Courses data (يتأثر بالبحث) ================== */
$todayStmt = $pdo->prepare("
    SELECT course_name
    FROM courses
    WHERE start_date = CURDATE()
      AND $cWhere
");
$todayStmt->execute($cParams);
$todayList = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

$upcomingStmt = $pdo->prepare("
    SELECT course_name, start_date
    FROM courses
    WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND $cWhere
    ORDER BY start_date ASC
");
$upcomingStmt->execute($cParams);
$upcomingList = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

$recentStmt = $pdo->prepare("
    SELECT course_name, start_date
    FROM courses
    WHERE $cWhere
    ORDER BY start_date DESC
    LIMIT 8
");
$recentStmt->execute($cParams);
$recentCourses = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================== Participants data (يتأثر بالبحث) ================== */
$participantsRows = [];
$participantsCount = 0;
try {
    $pSelect = [];
    if (colExists($pdo,'participants','id')) $pSelect[] = "id";
    if ($nameCol) $pSelect[] = $nameCol;

    foreach (['phone','email','organization','work_place','inside_university'] as $col) {
        if (colExists($pdo,'participants',$col)) $pSelect[] = $col;
    }

    if (count($pSelect) === 0) $pSelect[] = "*";

    $participantsStmt = $pdo->prepare("
        SELECT " . implode(",", array_map(fn($c)=>"`$c`", $pSelect)) . "
        FROM participants
        WHERE $pWhere
        ORDER BY " . (colExists($pdo,'participants','id') ? "id DESC" : "1") . "
        LIMIT 12
    ");
    $participantsStmt->execute($pParams);
    $participantsRows = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
    $participantsCount = count($participantsRows);
} catch (Throwable $e) {
    $participantsRows = [];
    $participantsCount = 0;
}

/* ================== Charts (بدون فلترة) ================== */
$trendDays = 14;
$trendStmt = $pdo->prepare("
    SELECT start_date AS d, COUNT(*) AS c
    FROM courses
    WHERE start_date BETWEEN DATE_SUB(CURDATE(), INTERVAL :days DAY) AND CURDATE()
    GROUP BY start_date
    ORDER BY start_date ASC
");
$trendStmt->bindValue(':days', $trendDays, PDO::PARAM_INT);
$trendStmt->execute();
$trendRaw = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

$trendMap = [];
for ($i = $trendDays; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $trendMap[$d] = 0;
}
foreach ($trendRaw as $row) {
    $d = (string)($row['d'] ?? '');
    if (isset($trendMap[$d])) $trendMap[$d] = (int)($row['c'] ?? 0);
}
$trendLabels = array_keys($trendMap);
$trendValues = array_values($trendMap);

$weekdayDist = $pdo->query("
    SELECT DAYOFWEEK(start_date) AS w, COUNT(*) AS c
    FROM courses
    WHERE start_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND CURDATE()
    GROUP BY DAYOFWEEK(start_date)
    ORDER BY w
")->fetchAll(PDO::FETCH_ASSOC);

$weekdayNames = [
    1 => 'الأحد', 2 => 'الإثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء',
    5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'
];
$weekdayLabels = [];
$weekdayValues = [];
foreach ($weekdayDist as $r) {
    $w = (int)($r['w'] ?? 0);
    if ($w >= 1 && $w <= 7) {
        $weekdayLabels[] = $weekdayNames[$w];
        $weekdayValues[] = (int)($r['c'] ?? 0);
    }
}

/* ================== UI small data ================== */
$insights = [];
$insights[] = [
    'title'=>'30 يوم',
    'value'=>(int)$pdo->query("SELECT COUNT(*) FROM courses WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'hint'=>'تخطيط.',
    'tone'=>'info'
];
$insights[] = [
    'title'=>'اليوم',
    'value'=>(int)$todayCourses,
    'hint'=>($todayCourses>0?'يوجد.':'لا يوجد.'),
    'tone'=>($todayCourses>0?'warn':'success')
];
$insights[] = [
    'title'=>'المشاركون',
    'value'=>(int)$totalParticipants,
    'hint'=>'تحديث.',
    'tone'=>'primary'
];

$systemNotices = [
    ['type'=>'info', 'title'=>'تذكير', 'text'=>'راجع التقارير.'],
    ['type'=>'warn', 'title'=>'تنبيه', 'text'=>'تحقق من الدورات القريبة.'],
];

/* ================== Notify payload (مبني على نتائج الدورات الحالية/البحث) ================== */
$hasToday = count($todayList) > 0;
$todayCoursesNames = array_map(fn($r) => (string)($r['course_name'] ?? ''), $todayList);

$upcomingCoursesForNotify = [];
foreach ($upcomingList as $r) {
    $sd = (string)($r['start_date'] ?? '');
    if ($sd !== $todayLabel) {
        $upcomingCoursesForNotify[] = [
            'course_name' => (string)($r['course_name'] ?? ''),
            'start_date' => $sd
        ];
    }
}

$notifyPayload = [
    'todayLabel' => $todayLabel,
    'hasToday' => $hasToday,
    'todayCount' => (int)$todayCourses, // stats count (not filtered) like original
    'todayNames' => $todayCoursesNames,
    'upcomingCount' => count($upcomingCoursesForNotify),
    'upcoming' => $upcomingCoursesForNotify,
];

$trendLabelsJson = json_encode($trendLabels, JSON_UNESCAPED_UNICODE);
$trendValuesJson = json_encode($trendValues, JSON_UNESCAPED_UNICODE);
$weekdayLabelsJson = json_encode($weekdayLabels, JSON_UNESCAPED_UNICODE);
$weekdayValuesJson = json_encode($weekdayValues, JSON_UNESCAPED_UNICODE);
$notifyPayloadJson = json_encode($notifyPayload, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-app="continuous-education">
<head>
<meta charset="UTF-8">
<title>نظام إدارة التعليم المستمر</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
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
    --violet1:#a855f7; --violet2:#6d28d9;
    --slate1:#64748b; --slate2:#334155;
    --radius: 18px;

    --accent: rgba(37,99,235,1);
    --accentSoft: rgba(37,99,235,.18);
}
*{ box-sizing:border-box; }
body{
    margin:0;
    font-family:"Segoe UI",Tahoma,system-ui,-apple-system,"Noto Kufi Arabic","Cairo",sans-serif;
    background:url("/continuous_education/assets/img/dashboard-bg.jpg") no-repeat center center fixed;
    background-size:cover;
    color:var(--text);
    transition:.25s ease;
    font-size: 14px;
}
.overlay{
    background:linear-gradient(180deg, var(--overlay1), var(--overlay2));
    min-height:100vh;
    padding:22px 14px 34px;
    position:relative;
    overflow:hidden;
}
#particles-js{ position:absolute; inset:0; z-index:0; }
.content{ position:relative; z-index:2; max-width: 1240px; margin: 0 auto; }

.glass{
    background:var(--card);
    border:1px solid var(--border);
    backdrop-filter: blur(16px);
    border-radius:var(--radius);
    padding:18px;
    box-shadow: var(--shadow);
    margin-bottom:14px;
}
.glass.soft{ background:var(--card2); box-shadow: var(--shadow2); }

.topbar{ display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
.brand{ display:flex; align-items:center; gap:12px; min-width: 260px; }
.brand-badge{
    width:46px; height:46px; border-radius:16px;
    background:linear-gradient(135deg, var(--accent), rgba(30,64,175,1));
    box-shadow:0 12px 25px rgba(0,0,0,.35);
    display:flex; align-items:center; justify-content:center;
    font-size:20px; flex:0 0 auto;
}
.brand h1{ margin:0; font-size:18px; font-weight:1000; }
.brand small{ display:block; color:var(--muted); font-weight:800; margin-top:2px; }

.actions{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
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
}
.btn-soft:hover{ transform:translateY(-2px); background:rgba(255,255,255,.14); }
.btn-soft.danger{ border-color: rgba(239,68,68,.35); }

.badge-soft{
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.16);
    color:#fff;
    padding:6px 10px;
    border-radius:999px;
    font-weight:1000;
    font-size:12px;
}

.searchbar{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%; }
.searchbar .field{
    flex: 1 1 320px;
    display:flex; gap:10px; align-items:center;
    padding:10px 12px;
    border-radius: 14px;
    border:1px solid rgba(255,255,255,.16);
    background: rgba(0,0,0,.18);
}
.searchbar input{
    width:100%;
    border:none;
    outline:none;
    background:transparent;
    color:#fff;
    font-weight:1000;
}
.searchbar input::placeholder{ color: rgba(255,255,255,.55); font-weight:900; }

.section-title{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
.section-title h2{ margin:0; font-weight:1000; font-size:16px; }
.section-title .meta{ color:var(--muted); font-weight:900; font-size:12px; }

hr.sep{ border:none; border-top:1px solid rgba(255,255,255,.14); margin: 12px 0; }

.kpi{
    border-radius:var(--radius);
    padding:16px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.10);
    box-shadow: var(--shadow2);
    transition:.25s ease;
    height:100%;
}
.kpi:hover{ transform: translateY(-4px); }
.kpi-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.kpi .label{ color:var(--muted); font-weight:1000; font-size:12px; }
.kpi .value{ font-size:34px; font-weight:1000; margin-top:6px; line-height:1.1; }
.kpi .sub{ margin-top:8px; color: rgba(255,255,255,.75); font-weight:900; font-size:12px; }
.kpi .icon{
    width:46px; height:46px; border-radius:16px;
    display:flex; align-items:center; justify-content:center;
    font-size:20px;
    border:1px solid rgba(255,255,255,.16);
    background:rgba(0,0,0,.18);
    flex:0 0 auto;
}

.tile{
    border-radius:var(--radius);
    padding:14px 16px;
    font-weight:1000;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    color:#fff;
    transition:.22s ease;
    box-shadow:0 14px 30px rgba(0,0,0,.38);
    height:100%;
    border:1px solid rgba(255,255,255,.10);
}
.tile:hover{ transform: translateY(-4px); }
.tile .left{ display:flex; align-items:center; gap:10px; }
.tile .i{
    width:38px;height:38px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.16);
}

.list-clean{ margin: 10px 0 0 0; padding:0 18px 0 0; }
.list-clean li{ margin-bottom:6px; }

.table-soft{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    overflow:hidden;
    border-radius: 16px;
    border:1px solid rgba(255,255,255,.14);
}
.table-soft th, .table-soft td{
    padding:10px 12px;
    border-bottom:1px solid rgba(255,255,255,.12);
    font-weight:900;
}
.table-soft th{
    background: rgba(0,0,0,.18);
    color: rgba(255,255,255,.85);
    font-size:12px;
}
.table-soft td{ color:#fff; font-size:13px; }
.table-soft tr:last-child td{ border-bottom:none; }

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
.pill.success{ border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.14); }
.pill.warn{ border-color: rgba(245,158,11,.35); background: rgba(245,158,11,.14); }
.pill.info{ border-color: rgba(6,182,212,.35); background: rgba(6,182,212,.14); }
.pill.danger{ border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.14); }
.pill.muted{ color: rgba(255,255,255,.80); }

.alert-soft{
    border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.09);
    color:#fff;
    border-radius:16px;
    padding:14px 16px;
}

.tabs{ display:flex; gap:8px; flex-wrap:wrap; }
.tab-btn{
    border:1px solid rgba(255,255,255,.16);
    background:rgba(255,255,255,.10);
    color:#fff;
    padding:8px 12px;
    border-radius:12px;
    font-weight:1000;
    font-size:12px;
    cursor:pointer;
    transition:.2s ease;
}
.tab-btn:hover{ transform: translateY(-2px); }
.tab-btn.active{
    background: linear-gradient(135deg, rgba(37,99,235,.45), rgba(30,64,175,.25));
    border-color: rgba(37,99,235,.45);
}
.tab-panel{ display:none; }
.tab-panel.active{ display:block; }

.chart-wrap{
    border-radius:16px;
    padding:12px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(0,0,0,.18);
}
.footer-note{ color:var(--muted); font-weight:900; font-size:12px; text-align:center; }

html[data-contrast="high"] .glass,
html[data-contrast="high"] .kpi,
html[data-contrast="high"] .alert-soft,
html[data-contrast="high"] .chart-wrap,
html[data-contrast="high"] .table-soft{ border-color: rgba(255,255,255,.30); }

html[data-density="compact"] .glass{ padding:14px; }
html[data-density="compact"] .kpi{ padding:12px; }
html[data-motion="reduced"] *{ animation: none !important; transition: none !important; }

html[data-font="sm"] body{ font-size: 13px; }
html[data-font="md"] body{ font-size: 14px; }
html[data-font="lg"] body{ font-size: 15px; }
html[data-font="xl"] body{ font-size: 16px; }

body.light-mode{ background:#f1f5f9 !important; color:#0f172a !important; }
body.light-mode .overlay{ background:linear-gradient(180deg, rgba(255,255,255,.86), rgba(255,255,255,.92)); }
body.light-mode .glass,
body.light-mode .kpi,
body.light-mode .alert-soft{
    background:rgba(255,255,255,.72);
    border:1px solid rgba(15,23,42,.10);
    color:#0f172a;
    box-shadow:0 18px 45px rgba(2,6,23,.12);
}
body.light-mode .btn-soft,
body.light-mode .tab-btn{
    background: rgba(15,23,42,.06);
    border: 1px solid rgba(15,23,42,.10);
    color:#0f172a;
}
body.light-mode .searchbar .field{ background: rgba(15,23,42,.05); border:1px solid rgba(15,23,42,.10); }
body.light-mode .searchbar input{ color:#0f172a; }
body.light-mode .badge-soft{ background: rgba(15,23,42,.06); border: 1px solid rgba(15,23,42,.10); color:#0f172a; }
body.light-mode .brand small,
body.light-mode .section-title .meta,
body.light-mode .footer-note,
body.light-mode .kpi .label{ color: rgba(15,23,42,.70); }
body.light-mode .table-soft{ border:1px solid rgba(15,23,42,.10); }
body.light-mode .table-soft th{ background: rgba(15,23,42,.05); color: rgba(15,23,42,.75); }
body.light-mode .table-soft td{ color:#0f172a; border-bottom:1px solid rgba(15,23,42,.08); }
body.light-mode .chart-wrap{ background: rgba(15,23,42,.05); border:1px solid rgba(15,23,42,.10); }
body.light-mode .pill{ border:1px solid rgba(15,23,42,.10); background: rgba(15,23,42,.05); color:#0f172a; }

/* ===== Settings Modal pro ===== */
.modal-content{
    background: rgba(15,23,42,.94);
    border: 1px solid rgba(255,255,255,.14);
    color: #fff;
}
body.light-mode .modal-content{
    background: rgba(255,255,255,.98);
    border: 1px solid rgba(15,23,42,.10);
    color:#0f172a;
}
.modal-header, .modal-footer{ border-color: rgba(255,255,255,.10) !important; }
body.light-mode .modal-header, body.light-mode .modal-footer{ border-color: rgba(15,23,42,.10) !important; }
.modal .btn-close{ filter: invert(1); }
body.light-mode .modal .btn-close{ filter: none; }
.modal .form-select, .modal .form-control{
    background: rgba(0,0,0,.22);
    border: 1px solid rgba(255,255,255,.14);
    color: #fff;
    font-weight:900;
    border-radius: 14px;
}
body.light-mode .modal .form-select,
body.light-mode .modal .form-control{
    background: rgba(15,23,42,.04);
    border: 1px solid rgba(15,23,42,.10);
    color:#0f172a;
}
.modal .form-control::placeholder{ color: rgba(255,255,255,.55); }
.modal .form-check-label{ font-weight:900; }
.modal .form-text{ color: rgba(255,255,255,.70); font-weight:800; }
body.light-mode .modal .form-text{ color: rgba(15,23,42,.70); }

/* settings layout */
.settings-grid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
@media (max-width: 992px){ .settings-grid{ grid-template-columns: 1fr; } }
.settings-card{ border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.08); border-radius:16px; padding:14px; }
body.light-mode .settings-card{ border:1px solid rgba(15,23,42,.10); background:rgba(15,23,42,.03); }
.settings-card h3{ margin:0 0 10px 0; font-size:14px; font-weight:1000; }
.settings-row{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 10px; border-radius:14px; border:1px solid rgba(255,255,255,.10); background: rgba(0,0,0,.16); }
body.light-mode .settings-row{ border:1px solid rgba(15,23,42,.10); background: rgba(15,23,42,.03); }
.settings-row .left{ display:flex; flex-direction:column; gap:2px; }
.settings-row .label{ font-weight:1000; }
.settings-row .desc{ font-size:12px; font-weight:800; color: rgba(255,255,255,.70); }
body.light-mode .settings-row .desc{ color: rgba(15,23,42,.70); }

@media (max-width: 576px){
    .overlay{ padding:16px 10px 26px; }
    .kpi .value{ font-size:28px; }
    .brand h1{ font-size:16px; }
}
</style>
</head>
<body dir="rtl">
<div class="overlay">
    <div id="particles-js" aria-hidden="true"></div>
    <div class="content">

        <?php if($flash !== ''): ?>
            <div class="alert-soft" style="margin-bottom:12px;">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div style="font-weight:1000;"><?= e($flash) ?></div>
                    <span class="pill <?= e($flashTone) ?>"><?= e($flashTone) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="glass">
            <div class="topbar">
                <div class="brand">
                    <div class="brand-badge">🎓</div>
                    <div>
                        <h1>نظام إدارة التعليم المستمر</h1>
                        <small><?= e($todayLabel) ?> • <?= e($nowLabel) ?></small>
                    </div>
                </div>

                <div class="actions">
                    <span class="badge-soft" id="statusBadge"><?= $currentUsername !== '' ? e($currentUsername) : 'يعمل' ?></span>
                    <button id="openSettings" class="btn-soft" type="button">⚙️</button>
                    <button id="themeToggle" class="btn-soft" type="button" aria-label="تبديل"><span id="themeIcon">🌙</span></button>
                    <a class="btn-soft" href="reports/index.php">📊</a>
                    <a class="btn-soft" href="statistics/index.php">📈</a>
                    <a class="btn-soft danger" href="../auth/logout.php">🚪</a>
                </div>
            </div>

            <hr class="sep">

            <form class="searchbar" method="GET" action="">
                <div class="field">
                    <span aria-hidden="true">🔎</span>
                    <input name="q" value="<?= e($q) ?>" placeholder="بحث..." autocomplete="off">
                </div>
                <button class="btn-soft" type="submit">بحث</button>
                <a class="btn-soft" href="dashboard.php">إلغاء</a>
            </form>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-1" id="panel-kpis">
            <div class="col-md-4">
                <div class="kpi">
                    <div class="kpi-head">
                        <div>
                            <div class="label">الدورات</div>
                            <div class="value counter" data-target="<?= intv($totalCourses) ?>">0</div>
                            <div class="sub">courses</div>
                        </div>
                        <div class="icon">📚</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="kpi-head">
                        <div>
                            <div class="label">المشاركون</div>
                            <div class="value counter" data-target="<?= intv($totalParticipants) ?>">0</div>
                            <div class="sub">participants</div>
                        </div>
                        <div class="icon">👥</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="kpi-head">
                        <div>
                            <div class="label">اليوم</div>
                            <div class="value counter" data-target="<?= intv($todayCourses) ?>">0</div>
                            <div class="sub">CURDATE()</div>
                        </div>
                        <div class="icon">📅</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main -->
        <div class="row g-3" id="panel-main">
            <div class="col-lg-7">
                <div class="glass soft" id="panel-insights">
                    <div class="section-title"><h2>ملخص</h2><div class="meta"></div></div>
                    <div class="row g-3">
                        <?php foreach($insights as $it): ?>
                            <?php
                                $tone = (string)$it['tone'];
                                $pillClass = 'muted';
                                if ($tone === 'success') $pillClass = 'success';
                                elseif ($tone === 'warn') $pillClass = 'warn';
                                elseif ($tone === 'info') $pillClass = 'info';
                                elseif ($tone === 'primary') $pillClass = 'info';
                            ?>
                            <div class="col-md-4">
                                <div class="alert-soft" style="height:100%;">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div style="font-weight:1000;"><?= e($it['title']) ?></div>
                                        <span class="pill <?= e($pillClass) ?>"><?= e($it['value']) ?></span>
                                    </div>
                                    <div style="margin-top:8px;color:rgba(255,255,255,.78);font-weight:900;font-size:12px;">
                                        <?= e($it['hint']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="sep">
                    <div class="section-title"><h2>اختصارات</h2><div class="meta"></div></div>

                    <div class="row g-3" id="panel-actions">
                        <div class="col-md-4 col-6">
                            <a href="courses/add.php" class="tile" style="background:linear-gradient(135deg,var(--success1),var(--success2));">
                                <span class="left"><span class="i">➕</span>إضافة</span><span>›</span>
                            </a>
                        </div>
                        <div class="col-md-4 col-6">
                            <a href="courses/list.php" class="tile" style="background:linear-gradient(135deg,var(--primary1),var(--primary2));">
                                <span class="left"><span class="i">📋</span>الدورات</span><span>›</span>
                            </a>
                        </div>
                        <div class="col-md-4 col-6">
                            <a href="participants/add.php" class="tile" style="background:linear-gradient(135deg,var(--violet1),var(--violet2));">
                                <span class="left"><span class="i">👤</span>مشارك</span><span>›</span>
                            </a>
                        </div>
                        <div class="col-md-4 col-6">
                            <a href="participants/list.php" class="tile" style="background:linear-gradient(135deg,var(--slate1),var(--slate2));">
                                <span class="left"><span class="i">🔎</span>بحث</span><span>›</span>
                            </a>
                        </div>
                        <div class="col-md-4 col-6">
                            <a href="statistics/index.php" class="tile" style="background:linear-gradient(135deg,var(--info1),var(--info2));">
                                <span class="left"><span class="i">📈</span>إحصاء</span><span>›</span>
                            </a>
                        </div>
                        <div class="col-md-4 col-6">
                            <a href="reports/index.php" class="tile" style="background:linear-gradient(135deg,var(--warn1),var(--warn2));">
                                <span class="left"><span class="i">📊</span>تقارير</span><span>›</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5" id="panel-notices">
                <div class="glass soft">
                    <div class="section-title"><h2>إشعارات</h2><div class="meta"></div></div>
                    <?php foreach($systemNotices as $n): ?>
                        <?php
                            $type = (string)$n['type'];
                            $pill = 'info';
                            if ($type === 'warn') $pill = 'warn';
                            if ($type === 'danger') $pill = 'danger';
                            if ($type === 'success') $pill = 'success';
                        ?>
                        <div class="alert-soft mb-2">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div style="font-weight:1000;"><?= e($n['title']) ?></div>
                                <span class="pill <?= e($pill) ?>"><?= e($type) ?></span>
                            </div>
                            <div style="margin-top:6px;color:rgba(255,255,255,.78);font-weight:900;font-size:12px;">
                                <?= e($n['text']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <hr class="sep">

                    <div class="alert-soft">
                        <div style="font-weight:1000;">CSRF</div>
                        <div style="margin-top:6px;color:rgba(255,255,255,.78);font-weight:900;font-size:12px;">
                            <span class="pill muted"><?= e(substr($csrf, 0, 10)) ?>…</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="glass" id="panel-results">
            <div class="section-title"><h2>نتائج</h2><div class="meta"><?= $q !== '' ? 'بحث' : '' ?></div></div>

            <div class="tabs" role="tablist" aria-label="Tabs">
                <button class="tab-btn active" type="button" data-tab="tab-today">اليوم (<?= count($todayList) ?>)</button>
                <button class="tab-btn" type="button" data-tab="tab-upcoming">قريبة (<?= count($upcomingList) ?>)</button>
                <button class="tab-btn" type="button" data-tab="tab-recent">الأحدث (<?= count($recentCourses) ?>)</button>
                <button class="tab-btn" type="button" data-tab="tab-participants">المشاركون (<?= (int)$participantsCount ?>)</button>
            </div>

            <hr class="sep">

            <div id="tab-today" class="tab-panel active" role="tabpanel">
                <div class="alert-soft">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div style="font-weight:1000;">اليوم</div>
                        <span class="pill <?= count($todayList) ? 'warn' : 'success' ?>"><?= count($todayList) ? 'يوجد' : 'لا يوجد' ?></span>
                    </div>
                    <ul class="list-clean">
                        <?php foreach($todayList as $c): ?>
                            <li><?= e($c['course_name'] ?? '') ?></li>
                        <?php endforeach; ?>
                        <?php if(count($todayList)==0): ?><li>لا يوجد</li><?php endif; ?>
                    </ul>
                </div>
            </div>

            <div id="tab-upcoming" class="tab-panel" role="tabpanel">
                <div class="alert-soft">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div style="font-weight:1000;">قريبة</div>
                        <span class="pill info">+3</span>
                    </div>

                    <div class="mt-3">
                        <?php if(count($upcomingList) > 0): ?>
                            <table class="table-soft">
                                <thead>
                                <tr>
                                    <th>الدورة</th>
                                    <th style="width:160px;">التاريخ</th>
                                    <th style="width:140px;">حالة</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($upcomingList as $c): ?>
                                    <?php
                                        $sd = (string)($c['start_date'] ?? '');
                                        $status = ($sd === $todayLabel) ? 'اليوم' : 'قريبة';
                                        $pill = ($sd === $todayLabel) ? 'warn' : 'info';
                                    ?>
                                    <tr>
                                        <td><?= e($c['course_name'] ?? '') ?></td>
                                        <td><?= e($sd) ?></td>
                                        <td><span class="pill <?= e($pill) ?>"><?= e($status) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="pill success">لا يوجد</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-recent" class="tab-panel" role="tabpanel">
                <div class="alert-soft">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div style="font-weight:1000;">الأحدث</div>
                        <span class="pill muted">8</span>
                    </div>

                    <div class="mt-3">
                        <?php if(count($recentCourses) > 0): ?>
                            <table class="table-soft">
                                <thead>
                                <tr>
                                    <th>الدورة</th>
                                    <th style="width:160px;">التاريخ</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($recentCourses as $c): ?>
                                    <tr>
                                        <td><?= e($c['course_name'] ?? '') ?></td>
                                        <td><?= e($c['start_date'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="pill warn">لا يوجد</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-participants" class="tab-panel" role="tabpanel">
                <div class="alert-soft">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div style="font-weight:1000;">المشاركون</div>
                        <span class="pill info"><?= (int)$participantsCount ?></span>
                    </div>

                    <div class="mt-3">
                        <?php if($participantsCount > 0): ?>
                            <table class="table-soft">
                                <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th style="width:170px;">الهاتف</th>
                                    <th style="width:240px;">البريد</th>
                                    <th style="width:200px;">الجهة/العمل</th>
                                    <th style="width:140px;">داخل/خارج</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($participantsRows as $p): ?>
                                    <?php
                                        $nameVal = $nameCol ? ($p[$nameCol] ?? '') : ($p['name'] ?? ($p['full_name'] ?? ''));
                                        $phoneVal = $p['phone'] ?? '';
                                        $emailVal = $p['email'] ?? '';
                                        $orgVal = $p['organization'] ?? ($p['work_place'] ?? '');
                                        $insideVal = $p['inside_university'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?= e($nameVal) ?></td>
                                        <td><?= e($phoneVal) ?></td>
                                        <td><?= e($emailVal) ?></td>
                                        <td><?= e($orgVal) ?></td>
                                        <td><?= e($insideVal) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="pill warn"><?= $q !== '' ? 'لا يوجد' : 'اكتب كلمة بحث' ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Charts -->
        <div class="row g-3" id="panel-analytics">
            <div class="col-lg-7">
                <div class="glass">
                    <div class="section-title"><h2>اتجاه (<?= (int)$trendDays ?>)</h2><div class="meta"></div></div>
                    <div class="chart-wrap"><canvas id="trendChart" height="120"></canvas></div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="glass">
                    <div class="section-title"><h2>أيام (60)</h2><div class="meta"></div></div>
                    <div class="chart-wrap"><canvas id="weekdayChart" height="160"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Tools -->
        <div class="glass soft" id="panel-tools">
            <div class="section-title"><h2>أدوات</h2><div class="meta"></div></div>

            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <button class="btn-soft w-100 justify-content-center" type="button" onclick="window.print()">طباعة</button>
                </div>
                <div class="col-md-3 col-6">
                    <button class="btn-soft w-100 justify-content-center" type="button" id="copyStatsBtn">نسخ</button>
                </div>
                <div class="col-md-3 col-6">
                    <button class="btn-soft w-100 justify-content-center" type="button" id="helpBtn">مساعدة</button>
                </div>
                <div class="col-md-3 col-6">
                    <button class="btn-soft w-100 justify-content-center" type="button" id="checkConnBtn">اتصال</button>
                </div>
            </div>

            <div id="toastArea" style="margin-top:12px;"></div>
        </div>

        <div class="footer-note glass">© <?= e(date('Y')) ?></div>
    </div>
</div>

<!-- Settings Modal (Pro) -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:18px; overflow:hidden;">
      <div class="modal-header">
        <div>
          <div class="modal-title" style="font-weight:1000;">⚙️ الإعدادات</div>
          <div class="form-text" style="margin-top:4px;">تُحفظ محليًا (localStorage)</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>

      <div class="modal-body">

        <div class="settings-grid">

          <!-- Appearance -->
          <div class="settings-card">
            <h3>المظهر</h3>

            <div class="settings-row">
              <div class="left">
                <div class="label">الوضع</div>
                <div class="desc">system / dark / light</div>
              </div>
              <select class="form-select" id="setTheme" style="max-width:220px;">
                <option value="system">system</option>
                <option value="dark">dark</option>
                <option value="light">light</option>
              </select>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">الخط</div>
                <div class="desc">sm / md / lg / xl</div>
              </div>
              <select class="form-select" id="setFont" style="max-width:220px;">
                <option value="sm">sm</option>
                <option value="md" selected>md</option>
                <option value="lg">lg</option>
                <option value="xl">xl</option>
              </select>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">الكثافة</div>
                <div class="desc">comfortable / compact</div>
              </div>
              <select class="form-select" id="setDensity" style="max-width:220px;">
                <option value="comfortable" selected>comfortable</option>
                <option value="compact">compact</option>
              </select>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">تباين عالي</div>
                <div class="desc">تحسين وضوح الحدود</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setContrast">
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">لون مميز</div>
                <div class="desc">يغيّر لون الشعار والتبويبات</div>
              </div>
              <input class="form-control" type="color" id="setAccent" value="#2563eb" style="width:84px;height:42px;padding:6px;">
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">شفافية الخلفية</div>
                <div class="desc">overlay</div>
              </div>
              <input class="form-range" type="range" id="setOverlay" min="35" max="90" step="1" value="55" style="max-width:220px;">
            </div>

          </div>

          <!-- Performance -->
          <div class="settings-card">
            <h3>الأداء</h3>

            <div class="settings-row">
              <div class="left">
                <div class="label">الحركة</div>
                <div class="desc">normal / reduced</div>
              </div>
              <select class="form-select" id="setMotion" style="max-width:220px;">
                <option value="normal" selected>normal</option>
                <option value="reduced">reduced</option>
              </select>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">Particles</div>
                <div class="desc">خلفية متحركة</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setParticles" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">Charts</div>
                <div class="desc">الرسوم</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setCharts" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">Counters</div>
                <div class="desc">عدادات الأرقام</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setCounters" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">Enterprise</div>
                <div class="desc">compact + reduced + no particles</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setEnterprise">
              </div>
            </div>

          </div>

          <!-- Layout -->
          <div class="settings-card">
            <h3>الواجهة</h3>

            <div class="settings-row">
              <div class="left">
                <div class="label">إظهار: ا  ملخص</div>
                <div class="desc">panel</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setShowInsights" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">إظهار: الإشعارات</div>
                <div class="desc">panel</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setShowNotices" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">إظهار: النتائج</div>
                <div class="desc">courses/participants</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setShowResults" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">إظهار: التحليلات</div>
                <div class="desc">charts area</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setShowAnalytics" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">إظهار: الأدوات</div>
                <div class="desc">tools</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setShowTools" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">إخفاء شريط البحث</div>
                <div class="desc">top search</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setHideSearch">
              </div>
            </div>

          </div>

          <!-- Notifications -->
          <div class="settings-card">
            <h3>التنبيهات</h3>

            <div class="settings-row">
              <div class="left">
                <div class="label">إشعار الدخول</div>
                <div class="desc">banner + toast</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setNotifyOnLoad" checked>
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">الصوت</div>
                <div class="desc">beep</div>
              </div>
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="setSound">
              </div>
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">قوة الصوت</div>
                <div class="desc">gain</div>
              </div>
              <input class="form-range" type="range" id="setSoundGain" min="5" max="40" step="1" value="22" style="max-width:220px;">
            </div>

            <div class="settings-row mt-2">
              <div class="left">
                <div class="label">اختبار</div>
                <div class="desc">صوت الآن</div>
              </div>
              <button class="btn btn-outline-light" type="button" id="testSoundBtn">تشغيل</button>
            </div>

          </div>

          <!-- Account -->
          <div class="settings-card" style="grid-column: 1 / -1;">
            <h3>الحساب</h3>

            <div class="row g-3">
              <div class="col-lg-6">
                <form method="POST" action="">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="change_username">

                  <label class="form-label">اسم مستخدم جديد</label>
                  <input class="form-control" name="new_username" placeholder="username" autocomplete="off">
                  <div class="form-text">3 أحرف أو أكثر</div>

                  <button class="btn btn-primary mt-2" type="submit">حفظ</button>
                </form>
              </div>

              <div class="col-lg-6">
                <form method="POST" action="">
                  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="change_password">

                  <label class="form-label">كلمة المرور الحالية</label>
                  <input class="form-control" type="password" name="current_password" placeholder="******" autocomplete="current-password">

                  <div class="mt-2">
                    <label class="form-label">كلمة مرور جديدة</label>
                    <input class="form-control" type="password" name="new_password" placeholder="******" autocomplete="new-password">
                  </div>

                  <div class="mt-2">
                    <label class="form-label">تأكيد</label>
                    <input class="form-control" type="password" name="confirm_password" placeholder="******" autocomplete="new-password">
                  </div>

                  <button class="btn btn-primary mt-2" type="submit">حفظ</button>
                </form>
              </div>
            </div>
          </div>

        </div>

      </div>

      <div class="modal-footer d-flex justify-content-between gap-2 flex-wrap">
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-primary" type="button" id="saveSettingsBtn">حفظ</button>
          <button class="btn btn-outline-secondary" type="button" id="resetSettingsBtn">إعادة</button>
          <button class="btn btn-outline-danger" type="button" id="clearSettingsBtn">مسح</button>
        </div>
        <span class="badge bg-secondary align-self-center"><?= e(substr($csrf,0,8)) ?>…</span>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
(function(){
    const STORAGE_KEY = "ce.dashboard.settings.pro.merged.v3";

    const defaultSettings = {
        theme: "system",
        font: "md",
        contrast: false,
        density: "comfortable",
        motion: "normal",

        accent: "#2563eb",
        overlay: 55,          // 35..90

        particles: true,
        charts: true,
        counters: true,
        enterprise: false,

        showInsights: true,
        showNotices: true,
        showResults: true,
        showAnalytics: true,
        showTools: true,
        hideSearch: false,

        notifyOnLoad: true,
        sound: false,
        soundGain: 22
    };

    function loadSettings(){
        try{
            const raw = localStorage.getItem(STORAGE_KEY);
            if(!raw) return {...defaultSettings};
            return {...defaultSettings, ...JSON.parse(raw)};
        }catch(e){
            return {...defaultSettings};
        }
    }
    function saveSettings(obj){ localStorage.setItem(STORAGE_KEY, JSON.stringify(obj)); }
    function clearSettings(){ localStorage.removeItem(STORAGE_KEY); }

    function clamp(n, min, max){
        n = Number(n);
        if (Number.isNaN(n)) return min;
        return Math.max(min, Math.min(max, n));
    }

    function hexToRgba(hex, a){
        try{
            const h = String(hex).replace('#','').trim();
            if (h.length !== 6) return `rgba(37,99,235,${a})`;
            const r = parseInt(h.substring(0,2),16);
            const g = parseInt(h.substring(2,4),16);
            const b = parseInt(h.substring(4,6),16);
            return `rgba(${r},${g},${b},${a})`;
        }catch(e){
            return `rgba(37,99,235,${a})`;
        }
    }

    function applyRootFlags(s){
        document.documentElement.setAttribute("data-contrast", s.contrast ? "high" : "normal");
        document.documentElement.setAttribute("data-density", s.density);
        document.documentElement.setAttribute("data-motion", s.motion);
        document.documentElement.setAttribute("data-font", s.font);

        document.documentElement.style.setProperty('--accent', s.accent);
        document.documentElement.style.setProperty('--accentSoft', hexToRgba(s.accent, 0.18));

        const ov1 = clamp(s.overlay, 35, 90) / 100;
        const ov2 = Math.min(0.95, ov1 + 0.15);
        document.documentElement.style.setProperty('--overlay1', `rgba(0,0,0,${ov1.toFixed(2)})`);
        document.documentElement.style.setProperty('--overlay2', `rgba(0,0,0,${ov2.toFixed(2)})`);
    }

    // Theme
    const themeToggle = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");

    function systemPrefersLight(){
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
    }
    function applyThemeMode(mode){
        let final = mode === "system" ? (systemPrefersLight() ? "light" : "dark") : mode;
        if(final === "light"){
            document.body.classList.add("light-mode");
            themeIcon.textContent = "☀️";
        }else{
            document.body.classList.remove("light-mode");
            themeIcon.textContent = "🌙";
        }
    }

    // Sections
    const panels = {
        insights: document.getElementById('panel-insights'),
        notices: document.getElementById('panel-notices'),
        results: document.getElementById('panel-results'),
        analytics: document.getElementById('panel-analytics'),
        tools: document.getElementById('panel-tools'),
        searchbar: document.querySelector('.searchbar')
    };
    function setVisible(el, show){
        if(!el) return;
        el.style.display = show ? '' : 'none';
    }

    // Particles / counters / charts
    let particlesEnabled = true;
    let chartsEnabled = true;
    let countersEnabled = true;

    function initParticlesIfNeeded(){
        const container = document.getElementById("particles-js");
        if(!container) return;

        if(!particlesEnabled){
            container.innerHTML = "";
            container.style.display = "none";
            return;
        }
        container.style.display = "block";
        container.innerHTML = "";
        particlesJS("particles-js", {
            particles:{
                number:{ value:65 },
                size:{ value:3 },
                move:{ speed:2.4 },
                line_linked:{ enable:true, distance:150, color:"#ffffff", opacity:0.22, width:1 },
                opacity:{ value:0.55 }
            }
        });
    }

    function animateCountersIfNeeded(){
        if(!countersEnabled){
            document.querySelectorAll('.counter').forEach(counter=>{
                const target = Number(counter.getAttribute('data-target')) || 0;
                counter.innerText = String(target);
            });
            return;
        }

        document.querySelectorAll('.counter').forEach(counter=>{
            const target = Number(counter.getAttribute('data-target')) || 0;
            const duration = 800;
            const start = performance.now();
            function tick(now){
                const p = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                counter.innerText = Math.round(eased * target);
                if(p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        });
    }

    const trendLabels = <?= $trendLabelsJson ?: '[]' ?>;
    const trendValues = <?= $trendValuesJson ?: '[]' ?>;
    const weekdayLabels = <?= $weekdayLabelsJson ?: '[]' ?>;
    const weekdayValues = <?= $weekdayValuesJson ?: '[]' ?>;

    let trendChart, weekdayChart;

    function gridColor(){
        return document.body.classList.contains('light-mode') ? 'rgba(15,23,42,.12)' : 'rgba(255,255,255,.12)';
    }
    function textColor(){
        return document.body.classList.contains('light-mode') ? 'rgba(15,23,42,.80)' : 'rgba(255,255,255,.82)';
    }

    function destroyCharts(){
        if(trendChart){ trendChart.destroy(); trendChart = null; }
        if(weekdayChart){ weekdayChart.destroy(); weekdayChart = null; }
    }

    function buildCharts(){
        if(!chartsEnabled){ destroyCharts(); return; }

        const trendCtx = document.getElementById('trendChart');
        const weekdayCtx = document.getElementById('weekdayChart');
        if(!trendCtx || !weekdayCtx) return;

        destroyCharts();

        trendChart = new Chart(trendCtx, {
            type: 'line',
            data: { labels: trendLabels, datasets: [{
                label: 'الدورات',
                data: trendValues,
                borderColor: getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || 'rgba(37,99,235,1)',
                backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--accentSoft').trim() || 'rgba(37,99,235,.18)',
                fill: true,
                tension: 0.35,
                pointRadius: 2,
                pointHoverRadius: 4
            }]},
            options: {
                responsive: true,
                plugins: { legend: { labels: { color: textColor(), font: { weight: '700' } } } },
                scales: {
                    x: { ticks: { color: textColor(), maxRotation: 0, autoSkip: true }, grid: { color: gridColor() } },
                    y: { ticks: { color: textColor() }, grid: { color: gridColor() }, beginAtZero: true }
                }
            }
        });

        weekdayChart = new Chart(weekdayCtx, {
            type: 'bar',
            data: { labels: weekdayLabels, datasets: [{
                label: 'الدورات',
                data: weekdayValues,
                borderColor: 'rgba(6, 182, 212, 1)',
                backgroundColor: 'rgba(6, 182, 212, .22)',
                borderWidth: 1,
                borderRadius: 10
            }]},
            options: {
                responsive: true,
                plugins: { legend: { labels: { color: textColor(), font: { weight: '700' } } } },
                scales: {
                    x: { ticks: { color: textColor() }, grid: { color: gridColor() } },
                    y: { ticks: { color: textColor() }, grid: { color: gridColor() }, beginAtZero: true }
                }
            }
        });
    }

    // Tabs
    const tabButtons = Array.from(document.querySelectorAll('.tab-btn'));
    const tabPanels = Array.from(document.querySelectorAll('.tab-panel'));
    tabButtons.forEach(btn=>{
        btn.addEventListener('click', ()=>{
            tabButtons.forEach(b=>b.classList.remove('active'));
            tabPanels.forEach(p=>p.classList.remove('active'));
            btn.classList.add('active');
            const id = btn.getAttribute('data-tab');
            const panel = document.getElementById(id);
            if(panel) panel.classList.add('active');
        });
    });

    // Toasts
    const toastArea = document.getElementById('toastArea');
    function escapeHtml(unsafe){
        return String(unsafe)
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'",'&#039;');
    }
    function toast(msg, type){
        const pill = type || 'info';
        const el = document.createElement('div');
        el.className = 'alert-soft';
        el.style.marginTop = '10px';
        el.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div style="font-weight:1000;">${escapeHtml(msg)}</div>
                <span class="pill ${escapeHtml(pill)}">${escapeHtml(pill)}</span>
            </div>
        `;
        toastArea.prepend(el);
        setTimeout(()=>{ el.style.opacity = '0'; el.style.transition = 'opacity .4s ease'; }, 3200);
        setTimeout(()=>{ el.remove(); }, 3800);
    }

    // Notifications + Sound
    const notifyData = <?= $notifyPayloadJson ?: '{}' ?>;

    function ensureBanner(){
        let b = document.getElementById('courseBanner');
        if (b) return b;

        b = document.createElement('div');
        b.id = 'courseBanner';
        b.className = 'alert-soft';
        b.style.position = 'sticky';
        b.style.top = '10px';
        b.style.zIndex = '9999';
        b.style.marginBottom = '12px';
        b.style.border = '1px solid rgba(255,255,255,.18)';
        b.style.background = 'rgba(245,158,11,.18)';
        b.style.display = 'none';

        const content = document.querySelector('.content');
        if (content) content.prepend(b);
        return b;
    }

    function showBanner(msg, pill){
        const b = ensureBanner();
        b.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <div style="font-weight:1000;">${escapeHtml(msg)}</div>
                <span class="pill ${escapeHtml(pill || 'warn')}">${escapeHtml(pill || 'warn')}</span>
            </div>
        `;
        b.style.display = '';
    }

    function playAlertSound(s){
        if(!s.sound) return;

        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if(!AudioCtx) return;

        const ctx = new AudioCtx();
        const now = ctx.currentTime;
        const freqs = [880, 988, 880];
        const gap = 0.18;
        const gainValue = clamp(s.soundGain, 5, 40) / 100;

        freqs.forEach((f, i)=>{
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'square';
            o.frequency.value = f;
            const t0 = now + i * gap;

            g.gain.setValueAtTime(0.0001, t0);
            g.gain.exponentialRampToValueAtTime(gainValue, t0 + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.14);

            o.connect(g);
            g.connect(ctx.destination);

            o.start(t0);
            o.stop(t0 + 0.15);
        });

        setTimeout(()=>{ try{ ctx.close(); }catch(e){} }, 1000);
    }

    function showCourseNoticeOnLoad(s){
        if(!s.notifyOnLoad) return;
        if(!notifyData) return;

        if (notifyData.hasToday) {
            const firstName = (notifyData.todayNames && notifyData.todayNames[0]) ? notifyData.todayNames[0] : '';
            const msg = `دورة اليوم (${notifyData.todayLabel}) ${firstName}${notifyData.todayCount > 1 ? ` +${notifyData.todayCount - 1}` : ''}`;
            showBanner(msg, 'warn');
            toast(msg, 'warn');
            playAlertSound(s);
            return;
        }

        if (notifyData.upcomingCount > 0) {
            const first = notifyData.upcoming[0];
            const msg = `دورة قريبة ${first.start_date} ${first.course_name}${notifyData.upcomingCount > 1 ? ` +${notifyData.upcomingCount - 1}` : ''}`;
            showBanner(msg, 'info');
            toast(msg, 'info');
            playAlertSound(s);
        }
    }

    // Tools
    document.getElementById('copyStatsBtn').addEventListener('click', async ()=>{
        const counters = document.querySelectorAll('.counter');
        const summary = [
            `الدورات: ${counters[0]?.innerText || ''}`,
            `المشاركون: ${counters[1]?.innerText || ''}`,
            `اليوم: ${counters[2]?.innerText || ''}`,
            `التاريخ: <?= e($todayLabel) ?>`
        ].join('\n');

        try{
            await navigator.clipboard.writeText(summary);
            toast('نسخ', 'success');
        }catch(e){
            toast('فشل', 'warn');
        }
    });
    document.getElementById('helpBtn').addEventListener('click', ()=> toast('الإعدادات', 'info'));
    document.getElementById('checkConnBtn').addEventListener('click', ()=> toast(navigator.onLine ? 'متصل' : 'غير متصل', navigator.onLine ? 'success' : 'danger'));

    // Settings modal wiring
    const settingsModalEl = document.getElementById('settingsModal');
    const settingsModal = new bootstrap.Modal(settingsModalEl);
    document.getElementById('openSettings').addEventListener('click', ()=> settingsModal.show());

    const ui = {
        theme: document.getElementById('setTheme'),
        font: document.getElementById('setFont'),
        contrast: document.getElementById('setContrast'),
        density: document.getElementById('setDensity'),
        motion: document.getElementById('setMotion'),
        accent: document.getElementById('setAccent'),
        overlay: document.getElementById('setOverlay'),

        particles: document.getElementById('setParticles'),
        charts: document.getElementById('setCharts'),
        counters: document.getElementById('setCounters'),
        enterprise: document.getElementById('setEnterprise'),

        showInsights: document.getElementById('setShowInsights'),
        showNotices: document.getElementById('setShowNotices'),
        showResults: document.getElementById('setShowResults'),
        showAnalytics: document.getElementById('setShowAnalytics'),
        showTools: document.getElementById('setShowTools'),
        hideSearch: document.getElementById('setHideSearch'),

        notifyOnLoad: document.getElementById('setNotifyOnLoad'),
        sound: document.getElementById('setSound'),
        soundGain: document.getElementById('setSoundGain'),
        testSoundBtn: document.getElementById('testSoundBtn')
    };

    function syncFormFromSettings(s){
        ui.theme.value = s.theme;
        ui.font.value = s.font;
        ui.contrast.checked = !!s.contrast;
        ui.density.value = s.density;
        ui.motion.value = s.motion;

        ui.accent.value = s.accent;
        ui.overlay.value = clamp(s.overlay, 35, 90);

        ui.particles.checked = !!s.particles;
        ui.charts.checked = !!s.charts;
        ui.counters.checked = !!s.counters;
        ui.enterprise.checked = !!s.enterprise;

        ui.showInsights.checked = !!s.showInsights;
        ui.showNotices.checked = !!s.showNotices;
        ui.showResults.checked = !!s.showResults;
        ui.showAnalytics.checked = !!s.showAnalytics;
        ui.showTools.checked = !!s.showTools;
        ui.hideSearch.checked = !!s.hideSearch;

        ui.notifyOnLoad.checked = !!s.notifyOnLoad;
        ui.sound.checked = !!s.sound;
        ui.soundGain.value = clamp(s.soundGain, 5, 40);
    }

    function readSettingsFromForm(){
        return {
            theme: ui.theme.value,
            font: ui.font.value,
            contrast: ui.contrast.checked,
            density: ui.density.value,
            motion: ui.motion.value,

            accent: ui.accent.value,
            overlay: clamp(ui.overlay.value, 35, 90),

            particles: ui.particles.checked,
            charts: ui.charts.checked,
            counters: ui.counters.checked,
            enterprise: ui.enterprise.checked,

            showInsights: ui.showInsights.checked,
            showNotices: ui.showNotices.checked,
            showResults: ui.showResults.checked,
            showAnalytics: ui.showAnalytics.checked,
            showTools: ui.showTools.checked,
            hideSearch: ui.hideSearch.checked,

            notifyOnLoad: ui.notifyOnLoad.checked,
            sound: ui.sound.checked,
            soundGain: clamp(ui.soundGain.value, 5, 40),
        };
    }

    function enforceEnterprise(s){
        if(!s.enterprise) return s;
        return { ...s, density: "compact", motion: "reduced", particles: false };
    }

    function applySettings(s){
        const finalSettings = enforceEnterprise(s);

        applyRootFlags(finalSettings);
        applyThemeMode(finalSettings.theme);

        particlesEnabled = !!finalSettings.particles;
        chartsEnabled = !!finalSettings.charts;
        countersEnabled = !!finalSettings.counters;

        setVisible(panels.insights, !!finalSettings.showInsights);
        setVisible(panels.notices, !!finalSettings.showNotices);
        setVisible(panels.results, !!finalSettings.showResults);
        setVisible(panels.analytics, !!finalSettings.showAnalytics);
        setVisible(panels.tools, !!finalSettings.showTools);
        setVisible(panels.searchbar, !finalSettings.hideSearch);

        initParticlesIfNeeded();
        animateCountersIfNeeded();
        buildCharts();
    }

    // Live preview
    Object.values(ui).forEach(el=>{
        if(!el || !el.addEventListener) return;
        const evt = (el.type === 'checkbox' || el.tagName === 'SELECT' || el.type === 'range' || el.type === 'color') ? 'change' : 'input';
        el.addEventListener(evt, ()=>{
            const s = readSettingsFromForm();
            applySettings(s);
        });
    });

    ui.testSoundBtn.addEventListener('click', ()=>{
        const s = readSettingsFromForm();
        playAlertSound({...s, sound: true});
    });

    document.getElementById('saveSettingsBtn').addEventListener('click', ()=>{
        const s = readSettingsFromForm();
        saveSettings(s);
        applySettings(loadSettings());
        toast('حفظ', 'success');
        settingsModal.hide();
    });

    document.getElementById('resetSettingsBtn').addEventListener('click', ()=>{
        syncFormFromSettings({...defaultSettings});
        applySettings({...defaultSettings});
        toast('إعادة', 'info');
    });

    document.getElementById('clearSettingsBtn').addEventListener('click', ()=>{
        clearSettings();
        const s = loadSettings();
        syncFormFromSettings(s);
        applySettings(s);
        toast('مسح', 'warn');
    });

    function cycleThemeMode(){
        const s = loadSettings();
        const order = ["system","dark","light"];
        const idx = Math.max(0, order.indexOf(s.theme));
        const next = order[(idx + 1) % order.length];
        saveSettings({...s, theme: next});
        const s2 = loadSettings();
        syncFormFromSettings(s2);
        applySettings(s2);
        toast(next, 'info');
    }
    themeToggle.addEventListener('click', cycleThemeMode);

    // Init
    const settings = loadSettings();
    syncFormFromSettings(settings);
    applySettings(settings);

    setTimeout(()=> showCourseNoticeOnLoad(settings), 650);

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', ()=>{
            const s = loadSettings();
            applySettings(s);
        });
    }
})();
</script>
</body>
</html>