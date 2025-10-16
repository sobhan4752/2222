<?php
// File: report_card.php
// Student report card with unified progress chart and pie charts
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$conn = get_db_connection();
$user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : (isset($_GET['user_id']) ? intval($_GET['user_id']) : 0);

// Fetch student details
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($student_name);
$student_name = $stmt->fetch() ? $student_name : 'دانش‌آموز';
$stmt->close();

// Recalculate percent and traz for existing scores
$scores_query = $conn->prepare("SELECT s.id, s.exam_id, s.lesson_id, s.correct, s.wrong, s.unanswered, l.total_questions
                                FROM scores s
                                JOIN lessons l ON s.lesson_id = l.id
                                WHERE s.user_id = ? AND s.approved = 1");
$scores_query->bind_param("i", $user_id);
$scores_query->execute();
$scores_result = $scores_query->get_result();
while ($score = $scores_result->fetch_assoc()) {
    $percent = ($score['total_questions'] > 0) ? (($score['correct'] - ($score['wrong'] / 3)) / $score['total_questions']) * 100 : 0; // Allow negative percent
    $traz = max(0, min(10000, $percent * 100)); // Traz between 0 and 10000
    $update_stmt = $conn->prepare("UPDATE scores SET percent = ?, traz = ? WHERE id = ?");
    $update_stmt->bind_param("ddi", $percent, $traz, $score['id']);
    $update_stmt->execute();
    $update_stmt->close();
}
$scores_query->close();

// Fetch scores with lesson and exam details
$scores_query = $conn->prepare("SELECT s.exam_id, s.lesson_id, s.correct, s.wrong, s.unanswered, s.percent, s.traz, e.name AS exam_name, e.jalali_date, l.name AS lesson_name
                                FROM scores s
                                JOIN exams e ON s.exam_id = e.id
                                JOIN lessons l ON s.lesson_id = l.id
                                WHERE s.user_id = ? AND s.approved = 1
                                ORDER BY e.jalali_date, l.name");
$scores_query->bind_param("i", $user_id);
$scores_query->execute();
$scores_result = $scores_query->get_result();

$scores_by_exam = [];
$lessons = [];
$exam_list = [];
while ($score = $scores_result->fetch_assoc()) {
    $exam_id = $score['exam_id'];
    if (!isset($scores_by_exam[$exam_id])) {
        $scores_by_exam[$exam_id] = [
            'exam_name' => $score['exam_name'],
            'jalali_date' => $score['jalali_date'],
            'lessons' => []
        ];
        $exam_list[] = ['id' => $exam_id, 'name' => $score['exam_name'], 'jalali_date' => $score['jalali_date']];
    }
    $scores_by_exam[$exam_id]['lessons'][] = $score;
    if (!in_array($score['lesson_name'], $lessons)) {
        $lessons[] = $score['lesson_name'];
    }
}
$scores_query->close();

// Calculate rank and overall traz per exam
foreach ($scores_by_exam as $exam_id => &$exam) {
    // Calculate overall traz (average of lesson traz)
    $total_traz = 0;
    $lesson_count = count($exam['lessons']);
    foreach ($exam['lessons'] as $score) {
        $total_traz += $score['traz'];
    }
    $exam['overall_traz'] = $lesson_count > 0 ? round($total_traz / $lesson_count, 2) : 0;

    // Calculate rank
    $rank_query = $conn->prepare("SELECT (
        SELECT COUNT(*) + 1
        FROM (
            SELECT user_id, AVG(traz) AS avg_traz
            FROM scores
            WHERE exam_id = ? AND approved = 1
            GROUP BY user_id
            HAVING avg_traz > (
                SELECT AVG(traz)
                FROM scores
                WHERE exam_id = ? AND user_id = ? AND approved = 1
            )
        ) AS higher_ranks
    ) AS student_rank");
    $rank_query->bind_param("iii", $exam_id, $exam_id, $user_id);
    $rank_query->execute();
    $rank_query->bind_result($rank);
    $rank_query->fetch();
    $exam['rank'] = $rank ?: 1;
    $rank_query->close();
}

// Prepare chart data for unified progress chart
$chart_labels = array_map(fn($exam) => $exam['exam_name'] . ' (' . $exam['jalali_date'] . ')', $scores_by_exam);
$datasets = [];
foreach ($lessons as $lesson) {
    $percent_data = [];
    $traz_data = [];
    foreach ($scores_by_exam as $exam_id => $exam) {
        $percent = 0;
        $traz = 0;
        foreach ($exam['lessons'] as $score) {
            if ($score['lesson_name'] === $lesson) {
                $percent = $score['percent'];
                $traz = $score['traz'];
                break;
            }
        }
        $percent_data[] = $percent;
        $traz_data[] = $traz;
    }
    $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    $datasets[] = [
        'label' => $lesson . ' (درصد)',
        'data' => $percent_data,
        'borderColor' => $color,
        'yAxisID' => 'y_percent',
        'fill' => false
    ];
    $datasets[] = [
        'label' => $lesson . ' (تراز)',
        'data' => $traz_data,
        'borderColor' => $color,
        'borderDash' => [5, 5],
        'yAxisID' => 'y_traz',
        'fill' => false
    ];
}
$conn->close();

// Handle selected exam
$selected_exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : (empty($exam_list) ? 0 : $exam_list[0]['id']);

// Validate selected_exam_id
if (!isset($scores_by_exam[$selected_exam_id]) && !empty($exam_list)) {
    $selected_exam_id = $exam_list[0]['id']; // Fallback to first valid exam
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کارنامه دانش‌آموز</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4 text-primary">کارنامه <?php echo htmlspecialchars($student_name); ?></h2>
            <a href="<?php echo isset($_SESSION['admin_id']) ? 'admin_panel.php' : 'student_login.php'; ?>" class="btn btn-primary btn-custom mb-4">بازگشت</a>
            <?php if (empty($scores_by_exam)): ?>
                <div class="alert alert-info text-center">هیچ نمره تأییدشده‌ای برای نمایش وجود ندارد.</div>
            <?php else: ?>
                <!-- Exam selection list -->
                <h4 class="mt-4">انتخاب آزمون</h4>
                <ul class="list-group mb-4">
                    <?php foreach ($exam_list as $exam): ?>
                        <li class="list-group-item">
                            <a href="?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-<?php echo $exam['id'] == $selected_exam_id ? 'primary' : 'outline-primary'; ?>">
                                <?php echo htmlspecialchars($exam['name']) . ' - ' . $exam['jalali_date']; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Selected exam details -->
                <?php if (isset($scores_by_exam[$selected_exam_id]) && !empty($scores_by_exam[$selected_exam_id]['lessons'])): ?>
                    <h4 class="mt-4"><?php echo htmlspecialchars($scores_by_exam[$selected_exam_id]['exam_name']) . ' - ' . $scores_by_exam[$selected_exam_id]['jalali_date']; ?></h4>
                    <p><strong>تراز کلی:</strong> <?php echo $scores_by_exam[$selected_exam_id]['overall_traz']; ?></p>
                    <p><strong>رتبه:</strong> <?php echo $scores_by_exam[$selected_exam_id]['rank']; ?></p>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>درس</th>
                                <th>صحیح</th>
                                <th>غلط</th>
                                <th>نزده</th>
                                <th>درصد</th>
                                <th>تراز</th>
                                <th>تحلیل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scores_by_exam[$selected_exam_id]['lessons'] as $score): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($score['lesson_name']); ?></td>
                                    <td><?php echo $score['correct']; ?></td>
                                    <td><?php echo $score['wrong']; ?></td>
                                    <td><?php echo $score['unanswered']; ?></td>
                                    <td><?php echo round($score['percent'], 2); ?>%</td>
                                    <td><?php echo round($score['traz'], 2); ?></td>
                                    <td>
                                        <canvas id="pie_<?php echo $score['lesson_id'] . '_' . $score['exam_id']; ?>" height="100"></canvas>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-warning text-center">هیچ داده‌ای برای آزمون انتخاب‌شده وجود ندارد.</div>
                <?php endif; ?>

                <!-- Unified progress chart -->
                <div class="chart-container">
                    <h4 class="text-center">نمودار پیشرفت (درصد و تراز)</h4>
                    <canvas id="progressChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Unified progress chart
        const progressCtx = document.getElementById('progressChart').getContext('2d');
        new Chart(progressCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: <?php echo json_encode($datasets); ?>
            },
            options: {
                responsive: true,
                scales: {
                    y_percent: {
                        type: 'linear',
                        position: 'left',
                        title: { display: true, text: 'درصد' },
                        grid: { drawOnChartArea: true }
                    },
                    y_traz: {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        max: 10000,
                        title: { display: true, text: 'تراز' },
                        grid: { drawOnChartArea: false }
                    }
                },
                plugins: { legend: { position: 'top' } }
            }
        });

        // Pie charts for each lesson
        <?php if (isset($scores_by_exam[$selected_exam_id]) && !empty($scores_by_exam[$selected_exam_id]['lessons'])): ?>
            <?php foreach ($scores_by_exam[$selected_exam_id]['lessons'] as $score): ?>
                new Chart(document.getElementById('pie_<?php echo $score['lesson_id'] . '_' . $score['exam_id']; ?>').getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['صحیح', 'غلط', 'نزده'],
                        datasets: [{
                            data: [<?php echo $score['correct']; ?>, <?php echo $score['wrong']; ?>, <?php echo $score['unanswered']; ?>],
                            backgroundColor: ['#28a745', '#dc3545', '#ffffff'],
                            borderColor: '#000000',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.raw;
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endforeach; ?>
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>