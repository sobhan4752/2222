<?php
// File: manage_students.php
// Manage and edit student information
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Handle student edit
if (isset($_POST['edit_student'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $id = intval($_POST['student_id']);
    $code = filter_input(INPUT_POST, 'student_code', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'student_name', FILTER_SANITIZE_STRING);
    $password = !empty($_POST['student_pass']) ? password_hash(filter_input(INPUT_POST, 'student_pass', FILTER_SANITIZE_STRING), PASSWORD_BCRYPT) : null;

    $conn = get_db_connection();
    $check = $conn->prepare("SELECT id FROM users WHERE code = ? AND id != ?");
    $check->bind_param("si", $code, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "<script>alert('کد دانش‌آموزی تکراری است');</script>";
    } else {
        if ($password) {
            $stmt = $conn->prepare("UPDATE users SET code = ?, name = ?, password = ? WHERE id = ? AND role = 'student'");
            $stmt->bind_param("sssi", $code, $name, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET code = ?, name = ? WHERE id = ? AND role = 'student'");
            $stmt->bind_param("ssi", $code, $name, $id);
        }
        $stmt->execute();
        log_activity($_SESSION['admin_id'], "Edited student", "ID: $id");
        $stmt->close();
    }
    $check->close();
    $conn->close();
}

// Handle student delete
if (isset($_GET['delete_student'])) {
    $id = intval($_GET['delete_student']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Deleted student", "ID: $id");
    $stmt->close();
    $conn->close();
}

// Fetch students
$conn = get_db_connection();
$students = $conn->query("SELECT * FROM users WHERE role = 'student'");
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دانش‌آموزان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: linear-gradient(135deg, #f5f7fa, #c3cfe2); padding: 2rem; }
        .container { max-width: 900px; margin: auto; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-custom { border-radius: 50px; }
        .modal-content { border-radius: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4">مدیریت دانش‌آموزان</h2>
            <a href="admin_panel.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل مدیریت</a>
            <ul class="list-group">
                <?php while ($student = $students->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($student['name']) . ' (' . $student['code'] . ')'; ?>
                        <div>
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editStudentModal<?php echo $student['id']; ?>">ویرایش</button>
                            <a href="?delete_student=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید؟');">حذف</a>
                        </div>
                    </li>
                    <!-- Edit Student Modal -->
                    <div class="modal fade" id="editStudentModal<?php echo $student['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">ویرایش دانش‌آموز: <?php echo htmlspecialchars($student['name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">کد</label>
                                            <input type="text" name="student_code" value="<?php echo htmlspecialchars($student['code']); ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">نام</label>
                                            <input type="text" name="student_name" value="<?php echo htmlspecialchars($student['name']); ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">رمز عبور جدید (اختیاری)</label>
                                            <input type="password" name="student_pass" class="form-control" placeholder="رمز جدید (خالی برای بدون تغییر)">
                                        </div>
                                        <button type="submit" name="edit_student" class="btn btn-primary btn-custom">ذخیره</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </ul>
            <?php if ($students->num_rows == 0): ?>
                <div class="alert alert-info mt-3">هیچ دانش‌آموزی ثبت نشده است.</div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>