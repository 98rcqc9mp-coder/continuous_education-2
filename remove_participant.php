<?php
require_once "../../config/db.php";

/**
 * ملاحظة مهمة:
 * هذا الملف وظيفته "تنفيذ حذف ثم إعادة توجيه" فقط (بدون واجهة).
 * لذلك لا يوجد شيء لترتيبه/تصميمه مثل لوحة التحكم إلا إذا أردت صفحة تأكيد UI.
 *
 * ✅ مع ذلك: أضفت حماية بسيطة بدون تغيير منطق البيانات:
 * - تحقق وجود course_id و participant_id
 * - تحويل القيم إلى أرقام لمنع إدخالات غير صحيحة
 * - نفس DELETE ونفس redirect
 */

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$participant_id = isset($_GET['participant_id']) ? (int)$_GET['participant_id'] : 0;

if ($course_id <= 0 || $participant_id <= 0) {
    header("Location: participants.php?course_id=".$course_id);
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM course_participants
    WHERE course_id=? AND participant_id=?
");
$stmt->execute([$course_id, $participant_id]);

header("Location: participants.php?course_id=".$course_id);
exit;