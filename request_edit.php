<?php
// File: request_edit.php
// Student request edit scores page
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

// Check access
$conn = get_db_connection();
$access_check = $conn->prepare("SELECT allowed FROM access_controls WHERE user_id = ? AND page = 'request_edit.php'");
$access_check->bind_param("i", $_SESSION['student_id']);
$access_check->execute();
$access_check->bind_result($allowed);
if ($access_check->fetch() && $allowed == 0) {
    echo "<script>alert('دسترسی به این صفحه ندارید');</script>";
    header("Location: student_login.php");
    exit;
}
$access_check->close();

// Fetch exams with scores
$scores_query = $conn->prepare("SELECT DISTINCT e.id, e.name, e.jalali_date
                                FROM scores s
                                JOIN exams e ON s.exam_id = e.id
                                WHERE s.user_id = ? AND s.approved = 1");
$scores_query->bind_param("i", $_SESSION['student_id']);
$scores_query->execute();
$exams_result = $scores_query->get_result();
$scores_query->close();

// Fetch existing edit requests
$requests_query = $conn->prepare("SELECT e.id, e.name, e.jalali_date, r.status
                                  FROM edit_requests r
                                  JOIN exams e ON r.exam_id = e.id
                                  WHERE r.user_id = ?");
$requests_query->bind_param("i", $_SESSION['student_id']);
$requests_query->execute();
$requests_result = $requests_query->get_result();
$existing_requests = [];
while ($req = $requests_result->fetch_assoc()) {
    $existing_requests[$req['id']] = $req['status'];
}
$requests_query->close();

// Handle edit request
if (isset($_POST['request_edit'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $exam_id = intval($_POST['exam_id']);
    $user_id = $_SESSION['student_id'];
    $check_existing = $conn->prepare("SELECT id FROM edit_requests WHERE user_id = ? AND exam_id = ? AND status = 'pending'");
    $check_existing->bind_param("ii", $user_id, $exam_id);
    $check_existing->execute();
    if ($check_existing->get_result()->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO edit_requests (user_id, exam_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $exam_id);
        $stmt->execute();
        $stmt->close();
        log_activity($user_id, "Requested edit", "Exam: $exam_id");
        echo "<script>alert('درخواست ویرایش ارسال شد.');</script>";
        header("Refresh:0"); // Refresh to update button state
    } else {
        echo "<script>alert('درخواست ویرایش برای این آزمون قبلاً ارسال شده است.');</script>";
    }
    $check_existing->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست ویرایش نمرات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4 text-primary">درخواست ویرایش نمرات</h2>
            <a href="student_login.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل</a>
            <?php if ($exams_result->num_rows == 0): ?>
                <div class="alert alert-info text-center">هیچ نمره تأییدشده‌ای برای ویرایش وجود ندارد.</div>
            <?php else: ?>
                <ul class="list-group">
                    <?php while ($exam = $exams_result->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($exam['name']) . ' - ' . $exam['jalali_date']; ?>
                            <?php if (isset($existing_requests[$exam['id']])): ?>
                                <span class="badge bg-<?php echo $existing_requests[$exam['id']] == 'pending' ? 'warning' : ($existing_requests[$exam['id']] == 'approved' ? 'success' : 'danger'); ?>">
                                    <?php echo $existing_requests[$exam['id']] == 'pending' ? 'در انتظار' : ($existing_requests[$exam['id']] == 'approved' ? 'تأیید شده' : 'رد شده'); ?>
                                </span>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                    <button type="submit" name="request_edit" class="btn btn-sm btn-warning">درخواست ویرایش</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>