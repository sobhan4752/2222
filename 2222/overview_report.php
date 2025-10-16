<?php
// File: overview_report.php
// Overview report of student scores sorted by rank, showing only overall traz
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = get_db_connection();

// Fetch exams
$exams_query = $conn->query("SELECT id, name, jalali_date FROM exams WHERE status = 'active' ORDER BY jalali_date DESC");
$exams = [];
if ($exams_query->num_rows > 0) {
    while ($row = $exams_query->fetch_assoc()) {
        $exams[$row['id']] = $row['name'] . ' - ' . $row['jalali_date'];
    }
}

// Fetch lessons
$lessons_query = $conn->query("SELECT id, name FROM lessons ORDER BY name");
$lessons = [];
if ($lessons_query->num_rows > 0) {
    while ($row = $lessons_query->fetch_assoc()) {
        $lessons[$row['id']] = $row['name'];
    }
}

// Handle selected exam
$selected_exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : (empty($exams) ? 0 : array_key_first($exams));

// Fetch overview data
$overview_data = [];
if ($selected_exam_id && isset($exams[$selected_exam_id])) {
    $scores_query = $conn->prepare("SELECT s.user_id, u.name AS student_name, s.lesson_id, s.percent, AVG(s.traz) AS avg_traz
                                   FROM scores s
                                   JOIN users u ON s.user_id = u.id
                                   WHERE s.exam_id = ? AND s.approved = 1 AND u.role = 'student'
                                   GROUP BY s.user_id, s.lesson_id
                                   ORDER BY avg_traz DESC");
    $scores_query->bind_param("i", $selected_exam_id);
    $scores_query->execute();
    $scores_result = $scores_query->get_result();

    $students = [];
    while ($row = $scores_result->fetch_assoc()) {
        $user_id = $row['user_id'];
        if (!isset($students[$user_id])) {
            $students[$user_id] = [
                'name' => $row['student_name'],
                'avg_traz' => $row['avg_traz'],
                'percentages' => []
            ];
        }
        $students[$user_id]['percentages'][$row['lesson_id']] = $row['percent'];
    }
    $scores_query->close();

    // Sort by avg_traz
    uasort($students, fn($a, $b) => $b['avg_traz'] <=> $a['avg_traz']);
    $overview_data = $students;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کارنامه اجمالی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4 text-primary">کارنامه اجمالی</h2>
            <a href="admin_panel.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل ادمین</a>
            <form method="post" class="mb-4">
                <div class="mb-3">
                    <label for="exam_id" class="form-label">انتخاب آزمون</label>
                    <select name="exam_id" id="exam_id" class="form-select" onchange="this.form.submit()" required>
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach ($exams as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $selected_exam_id == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <?php if ($selected_exam_id && !empty($overview_data)): ?>
                <h4 class="mb-4"><?php echo htmlspecialchars($exams[$selected_exam_id]); ?></h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>رتبه</th>
                            <th>نام دانش‌آموز</th>
                            <?php foreach ($lessons as $lesson_name): ?>
                                <th><?php echo htmlspecialchars($lesson_name); ?> (درصد)</th>
                            <?php endforeach; ?>
                            <th>تراز کلی</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($overview_data as $user_id => $data): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($data['name']); ?></td>
                                <?php foreach ($lessons as $lesson_id => $lesson_name): ?>
                                    <td><?php echo isset($data['percentages'][$lesson_id]) ? round($data['percentages'][$lesson_id], 2) : '-'; ?>%</td>
                                <?php endforeach; ?>
                                <td><?php echo round($data['avg_traz'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($selected_exam_id): ?>
                <div class="alert alert-warning text-center">هیچ داده‌ای برای این آزمون یافت نشد.</div>
            <?php else: ?>
                <div class="alert alert-warning text-center">لطفاً یک آزمون انتخاب کنید.</div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>