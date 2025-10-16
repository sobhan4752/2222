<?php
// File: student_login.php
// Student login and dashboard
ob_start();
session_start();
require 'db_connect.php';

if (isset($_SESSION['student_id'])) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $_SESSION['student_id']);
    $stmt->execute();
    $stmt->bind_result($student_name);
    $student_name = $stmt->fetch() ? $student_name : 'دانش‌آموز';
    $stmt->close();
    $conn->close();
} else {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE code = ? AND role = 'student'");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->bind_result($id, $hashed_pass);
        if ($stmt->fetch() && password_verify($password, $hashed_pass)) {
            $_SESSION['student_id'] = $id;
            log_activity($id, "Student login");
        } else {
            echo "<script>alert('اطلاعات ورود نادرست است');</script>";
        }
        $stmt->close();
        $conn->close();
        header("Location: student_login.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل دانش‌آموز</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <?php if (isset($_SESSION['student_id'])): ?>
                <h2 class="text-center mb-4 text-primary">خوش آمدید، <?php echo htmlspecialchars($student_name); ?></h2>
                <a href="report_card.php" class="btn btn-primary btn-custom w-100 mb-3">نمایش کارنامه</a>
                <a href="enter_scores.php" class="btn btn-success btn-custom w-100 mb-3">ورود تعداد درست و غلط</a>
                <a href="request_edit.php" class="btn btn-warning btn-custom w-100 mb-3">درخواست ویرایش نمرات</a>
                <a href="logout.php" class="btn btn-danger btn-custom w-100">خروج</a>
            <?php else: ?>
                <h2 class="text-center mb-4 text-primary">ورود دانش‌آموز</h2>
                <form method="post">
                    <div class="mb-3">
                        <label for="code" class="form-label">کد دانش‌آموزی</label>
                        <input type="text" name="code" id="code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">رمز عبور</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-custom w-100">ورود</button>
                </form>
                <a href="index.php" class="btn btn-secondary btn-custom w-100 mt-3">بازگشت</a>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>