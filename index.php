<?php
// File: index.php
// Main landing page
ob_start();
session_start();
require 'db_connect.php';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم تصحیح آزمون دبیرستان فرهنگ جیرفت</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: linear-gradient(135deg, #e0e7ff, #c3cfe2); }
        .container { max-width: 600px; margin: 5rem auto; }
        .card { border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .btn-custom { border-radius: 50px; padding: 0.75rem 2rem; transition: transform 0.3s; }
        .btn-custom:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4">
             
                سیستم تصحیح آزمون دبیرستان  فرهنگ جیرفت
                </h2>
            <?php if (isset($_SESSION['student_id'])): ?>
                <a href="report_card.php" class="btn btn-primary btn-custom w-100 mb-3">نمایش کارنامه</a>
                <a href="enter_scores.php" class="btn btn-success btn-custom w-100 mb-3">ورود نمرات</a>
                <a href="logout.php" class="btn btn-danger btn-custom w-100">خروج</a>
            <?php elseif (isset($_SESSION['admin_id'])): ?>
                <a href="admin_panel.php" class="btn btn-primary btn-custom w-100 mb-3">پنل مدیریت</a>
                <a href="logout.php" class="btn btn-danger btn-custom w-100">خروج</a>
            <?php else: ?>
                <a href="student_login.php" class="btn btn-primary btn-custom w-100 mb-3">ورود دانش‌آموز</a>
                <a href="admin_login.php" class="btn btn-success btn-custom w-100 mb-3">ورود مدیر</a>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>