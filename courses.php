<?php
require_once __DIR__ . '/../config/db.php';
include "header.php";

$stmt = $pdo->prepare("SELECT * FROM courses ORDER BY start_date DESC");
$stmt->execute();
$courses = $stmt->fetchAll();

function e($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
?>

<div class="glass soft">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
    <div style="font-weight:1000;font-size:16px;">📚 الدورات</div>
    <span class="badge-soft">عدد الدورات: <?= (int)count($courses) ?></span>
  </div>

  <input type="text" id="search" class="form-control"
         style="border-radius:14px;padding:12px 12px;font-weight:900;color:#fff;background:rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.16);"
         placeholder="🔍 ابحث عن دورة تدريبية">
</div>

<div class="glass soft">
  <div class="row g-3" id="courseList">
    <?php if(count($courses) == 0): ?>
      <div style="text-align:center;color:rgba(255,255,255,.75);font-weight:900;">لا توجد دورات حالياً</div>
    <?php endif; ?>

    <?php foreach($courses as $course): ?>
      <div class="col-md-4 course-card">
        <div style="height:100%;border-radius:18px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.18);padding:16px;">
          <div style="font-weight:1000;margin-bottom:8px;"><?= e($course['course_name']) ?></div>
          <div style="color:rgba(255,255,255,.74);font-weight:800;">📅 البداية: <?= e($course['start_date']) ?></div>
          <div style="color:rgba(255,255,255,.74);font-weight:800;">⏳ عدد الأيام: <?= e($course['days_count']) ?></div>

          <div style="margin-top:12px;">
            <a class="btn-soft primary" style="width:100%;justify-content:center;"
               href="course_details.php?id=<?= (int)$course['id'] ?>">
              عرض التفاصيل
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
document.getElementById("search").addEventListener("keyup", function(){
  let value = this.value.toLowerCase();
  document.querySelectorAll(".course-card").forEach(card=>{
    card.style.display = card.innerText.toLowerCase().includes(value) ? "block" : "none";
  });
});
</script>

<!-- close tags -->
  </div>
</div>
</body>
</html>