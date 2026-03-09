<?php
require_once "../config/db.php";
include "header.php";

$id = (int)($_GET['id'] ?? 0);

/* جلب الدورة */
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

if (!$course) {
    echo "<div class='glass soft'><div class='alert alert-danger m-0 text-center fw-bold'>الدورة غير موجودة</div></div>";
    echo "</div></div></body></html>";
    exit;
}

$base = "/continuous_education/attachments/uploads/";
?>

<div class="glass soft">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
    <div style="font-weight:1000;font-size:16px;">📘 تفاصيل الدورة</div>
    <a class="btn-soft" href="courses.php">⬅ رجوع للدورات</a>
  </div>

  <div style="border-radius:18px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.18);padding:16px;">
    <h2 style="margin:0 0 10px;font-weight:1000;font-size:20px;"><?= e($course['course_name']) ?></h2>

    <div style="display:grid;gap:8px;color:rgba(255,255,255,.78);font-weight:800;">
      <div>👤 <b>مدير الدورة:</b> <?= e($course['course_manager'] ?? '-') ?></div>
      <div>🎤 <b>المحاضر:</b> <?= e($course['lecturer'] ?? '-') ?></div>
      <div>📅 <b>من:</b> <?= e($course['start_date'] ?? '-') ?> — <b>إلى:</b> <?= e($course['end_date'] ?? '-') ?></div>
      <div>⏳ <b>عدد الأيام:</b> <?= e($course['days_count'] ?? '-') ?></div>
    </div>

    <hr style="border:none;border-top:1px solid rgba(255,255,255,.14);margin:14px 0;">

    <div>
      <div style="font-weight:1000;margin-bottom:8px;">📘 محاور الدورة</div>
      <div style="color:rgba(255,255,255,.78);font-weight:800;line-height:1.9;">
        <?= nl2br(e($course['axes'] ?? 'لا توجد محاور')) ?>
      </div>
    </div>

    <hr style="border:none;border-top:1px solid rgba(255,255,255,.14);margin:14px 0;">

    <div>
      <div style="font-weight:1000;margin-bottom:8px;">📎 مرفقات الدورة</div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (!empty($course['admin_order_file'])): ?>
          <a class="btn-soft" target="_blank" href="<?= e($base.$course['admin_order_file']) ?>">📄 الأمر الإداري</a>
        <?php endif; ?>

        <?php if (!empty($course['form_file'])): ?>
          <a class="btn-soft" target="_blank" href="<?= e($base.$course['form_file']) ?>">📝 استمارة الدورة</a>
        <?php endif; ?>

        <?php if (!empty($course['curriculum_file'])): ?>
          <a class="btn-soft" target="_blank" href="<?= e($base.$course['curriculum_file']) ?>">📚 منهاج الدورة</a>
        <?php endif; ?>
      </div>

      <?php if (
        empty($course['admin_order_file']) &&
        empty($course['form_file']) &&
        empty($course['curriculum_file'])
      ): ?>
        <div style="margin-top:10px;color:rgba(255,255,255,.7);font-weight:800;">لا توجد مرفقات لهذه الدورة</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- close tags -->
  </div>
</div>
</body>
</html>