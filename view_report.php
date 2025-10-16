<?php
// File: view_report.php
// View report card page for students
session_start();
require 'db_connect.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

// Fetch approved scores
$user_id = $_SESSION['student_id'];
$conn = get_db_connection();
$scores_query = $conn->prepare("SELECT s.*, e.name AS exam, e.jalali_date, l.name AS lesson FROM scores s JOIN exams e ON s.exam_id = e.id JOIN lessons l ON s.lesson_id = l.id WHERE user_id = ? AND approved = 1");
$scores_query->bind_param("i", $user_id);
$scores_query->execute();
$scores = $scores_query->get_result();

// Calculate rank and overall traz (simple: average)
$overall_traz = 0;
$score_count = $scores->num_rows;
if ($score_count > 0) {
    while ($score = $scores->fetch_assoc()) {
        $overall_traz += $score['traz'];
    }
    $overall_traz /= $score_count;
}
// Rank: dummy, assume 1 for now

$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهده کارنامه</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f8f9fa; padding: 2rem; }
        .report-container { max-width: 800px; margin: auto; background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="report-container">
        <h2 class="text-center mb-4">کارنامه</h2>
        <a href="logout.php" class="btn btn-danger btn-custom mb-4">خروج</a>
        <?php if ($scores->num_rows == 0): ?>
            <p class="text-center">نتایج هنوز توسط مدیر تایید نشده است</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr><th>آزمون</th><th>تاریخ</th><th>درس</th><th>درصد</th><th>تراز</th></tr>
                </thead>
                <tbody>
                    <?php while ($score = $scores->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $score['exam']; ?></td>
                            <td><?php echo $score['jalali_date']; ?></td>
                            <td><?php echo $score['lesson']; ?></td>
                            <td><?php echo $score['percent']; ?>%</td>
                            <td><?php echo $score['traz']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <p>تراز کلی: <?php echo round($overall_traz, 2); ?></p>
            <p>رتبه: 1</p> <!-- Adjust logic -->
            <canvas id="progressChart" height="200"></canvas>
        <?php endif; ?>
    </div>
    <script>
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['درس 1', 'درس 2'], // Dynamic
                datasets: [{ label: 'درصد', data: [65, 59], backgroundColor: 'rgba(75, 192, 192, 0.2)' }]
            },
            options: { responsive: true }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>