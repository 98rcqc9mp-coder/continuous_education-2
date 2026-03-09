<?php
require_once "../config/db.php";

// دورات اليوم
$todayCourses = $pdo->query("
    SELECT course_name, start_date
    FROM courses
    WHERE start_date = CURDATE()
")->fetchAll();

// دورات قادمة (بعد 3 أيام)
$upcomingCourses = $pdo->query("
    SELECT course_name, start_date
    FROM courses
    WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
")->fetchAll();
