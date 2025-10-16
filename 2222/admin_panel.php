<?php
// File: admin_panel.php
// Admin panel with tabbed interface
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE code = ? AND role = 'admin'");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->bind_result($id, $hashed_pass);
        if ($stmt->fetch() && password_verify($password, $hashed_pass)) {
            $_SESSION['admin_id'] = $id;
            log_activity($id, "Admin login");
        } else {
            echo "<script>alert('اطلاعات ورود نادرست است');</script>";
            header("Location: admin_login.php");
            exit;
        }
        $stmt->close();
        $conn->close();
    } else {
        header("Location: admin_login.php");
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    log_activity($_SESSION['admin_id'], "Admin logout");
    session_destroy();
    header("Location: index.php");
    exit;
}

// Add student
if (isset($_POST['add_student'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $code = filter_input(INPUT_POST, 'student_code', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'student_name', FILTER_SANITIZE_STRING);
    $password = password_hash(filter_input(INPUT_POST, 'student_pass', FILTER_SANITIZE_STRING), PASSWORD_BCRYPT);

    $conn = get_db_connection();
    $check = $conn->prepare("SELECT id FROM users WHERE code = ?");
    $check->bind_param("s", $code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "<script>alert('کد دانش‌آموزی تکراری است');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (code, password, name, role) VALUES (?, ?, ?, 'student')");
        $stmt->bind_param("sss", $code, $password, $name);
        $stmt->execute();
        log_activity($_SESSION['admin_id'], "Added student", "Code: $code");
        $stmt->close();
    }
    $check->close();
    $conn->close();
}

// Add exam
if (isset($_POST['add_exam'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $name = filter_input(INPUT_POST, 'exam_name', FILTER_SANITIZE_STRING);
    $jalali_date = filter_input(INPUT_POST, 'jalali_date', FILTER_SANITIZE_STRING);
    if (!preg_match("/^\d{4}\/\d{2}\/\d{2}$/", $jalali_date)) {
        echo "<script>alert('فرمت تاریخ شمسی نامعتبر است');</script>";
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare("INSERT INTO exams (name, jalali_date) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $jalali_date);
        $stmt->execute();
        log_activity($_SESSION['admin_id'], "Added exam", "Name: $name");
        $stmt->close();
        $conn->close();
    }
}

// Manage lessons
if (isset($_POST['add_lesson'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $name = filter_input(INPUT_POST, 'lesson_name', FILTER_SANITIZE_STRING);
    $total = intval($_POST['total_questions']);

    $conn = get_db_connection();
    $stmt = $conn->prepare("INSERT INTO lessons (name, total_questions) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $total);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Added lesson", "Name: $name");
    $stmt->close();
    $conn->close();
}

if (isset($_POST['edit_lesson'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $id = intval($_POST['lesson_id']);
    $total = intval($_POST['total_questions']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE lessons SET total_questions = ? WHERE id = ?");
    $stmt->bind_param("ii", $total, $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Edited lesson", "ID: $id, Total Questions: $total");
    $stmt->close();
    $conn->close();
}

if (isset($_GET['delete_lesson'])) {
    $id = intval($_GET['delete_lesson']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Deleted lesson", "ID: $id");
    $stmt->close();
    $conn->close();
}

// Manage exam status
if (isset($_POST['update_exam_status'])) {
    $id = intval($_POST['exam_id']);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE exams SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Updated exam status", "ID: $id, Status: $status");
    $stmt->close();
    $conn->close();
}

// Approve ALL scores
if (isset($_POST['approve_all_scores'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE scores SET approved = 1 WHERE approved = 0");
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    log_activity($_SESSION['admin_id'], "Approved all pending scores", "Rows affected: $affected_rows");
    $stmt->close();
    $conn->close();
    echo "<script>alert('تمام نمرات در انتظار تأیید شدند.');</script>";
}

// Approve all scores for a specific student
if (isset($_POST['approve_student_scores'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $user_id = intval($_POST['user_id']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE scores SET approved = 1 WHERE user_id = ? AND approved = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    log_activity($_SESSION['admin_id'], "Approved all scores for student", "User ID: $user_id, Rows affected: $affected_rows");
    $stmt->close();
    $conn->close();
    echo "<script>alert('تمام نمرات دانش‌آموز تأیید شدند.');</script>";
}

// Approve single score
if (isset($_GET['approve_score'])) {
    $id = intval($_GET['approve_score']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE scores SET approved = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Approved score", "ID: $id");
    $stmt->close();
    $conn->close();
}

// Manage edit requests
if (isset($_GET['approve_request'])) {
    $id = intval($_GET['approve_request']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE edit_requests SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Approved edit request", "ID: $id");
    $stmt->close();
    $conn->close();
}

if (isset($_GET['reject_request'])) {
    $id = intval($_GET['reject_request']);
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE edit_requests SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Rejected edit request", "ID: $id");
    $stmt->close();
    $conn->close();
}

// Manage access
if (isset($_POST['update_access'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $user_id = intval($_POST['user_id']);
    $page = filter_input(INPUT_POST, 'page', FILTER_SANITIZE_STRING);
    $allowed = intval($_POST['allowed']);

    $conn = get_db_connection();
    $stmt = $conn->prepare("INSERT INTO access_controls (user_id, page, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = ?");
    $stmt->bind_param("isii", $user_id, $page, $allowed, $allowed);
    $stmt->execute();
    log_activity($_SESSION['admin_id'], "Updated access", "User: $user_id, Page: $page");
    $stmt->close();
    $conn->close();
}

// Fetch data for display
$conn = get_db_connection();
$students = $conn->query("SELECT * FROM users WHERE role = 'student'");
$lessons = $conn->query("SELECT * FROM lessons");
$exams = $conn->query("SELECT * FROM exams");
$pending_scores = $conn->query("SELECT s.*, u.name AS student, e.name AS exam, l.name AS lesson FROM scores s JOIN users u ON s.user_id = u.id JOIN exams e ON s.exam_id = e.id JOIN lessons l ON s.lesson_id = l.id WHERE s.approved = 0");
$edit_requests = $conn->query("SELECT r.*, u.name AS student, e.name AS exam FROM edit_requests r JOIN users u ON r.user_id = u.id JOIN exams e ON r.exam_id = e.id WHERE r.status = 'pending'");
$logs = $conn->query("SELECT * FROM logs ORDER BY logged_at DESC LIMIT 50");
$report_total_students = $students->num_rows;
$report_total_exams = $exams->num_rows;
$report_avg_percent = $conn->query("SELECT AVG(percent) AS avg FROM scores")->fetch_assoc()['avg'];
$pages = ['index.php', 'enter_scores.php', 'report_card.php', 'request_edit.php'];
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f8f9fa; }
        .admin-container { padding: 2rem; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .card:hover { transform: translateY(-5px); }
        .btn-custom { border-radius: 50px; }
        .error { color: red; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0; } }
        .nav-tabs .nav-link { border-radius: 10px; margin: 0 5px; }
        .nav-tabs .nav-link.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1 class="text-center mb-4">پنل مدیریت</h1>
        <a href="?logout" class="btn btn-danger btn-custom mb-4">خروج</a>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#students">مدیریت دانش‌آموزان</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#exams">مدیریت آزمون‌ها</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#lessons">مدیریت دروس</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#scores">تأیید نتایج</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#edit_requests">درخواست‌های ویرایش</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#access">مدیریت دسترسی</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#reports">گزارشات</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#logs">لاگ‌ها</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#report_cards">کارنامه‌ها</a></li>
        </ul>

        <div class="tab-content">
            <!-- Students -->
            <div class="tab-pane fade show active" id="students">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>افزودن دانش‌آموز</h5>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="mb-3"><input type="text" name="student_code" placeholder="کد" class="form-control" required></div>
                            <div class="mb-3"><input type="text" name="student_name" placeholder="نام" class="form-control" required></div>
                            <div class="mb-3"><input type="password" name="student_pass" placeholder="رمز" class="form-control" required></div>
                            <button type="submit" name="add_student" class="btn btn-primary btn-custom">افزودن</button>
                        </form>
                        <h5 class="mt-4">لیست دانش‌آموزان</h5>
                        <a href="manage_students.php" class="btn btn-info btn-custom">مدیریت و ویرایش دانش‌آموزان</a>
                    </div>
                </div>
            </div>

            <!-- Exams -->
            <div class="tab-pane fade" id="exams">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>افزودن آزمون</h5>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="mb-3"><input type="text" name="exam_name" placeholder="نام آزمون" class="form-control" required></div>
                            <div class="mb-3"><input type="text" name="jalali_date" placeholder="تاریخ شمسی (1403/07/25)" class="form-control" required></div>
                            <button type="submit" name="add_exam" class="btn btn-primary btn-custom">افزودن</button>
                        </form>
                        <h5 class="mt-4">مدیریت وضعیت آزمون‌ها</h5>
                        <ul class="list-group">
                            <?php while ($exam = $exams->fetch_assoc()): ?>
                                <li class="list-group-item">
                                    <?php echo $exam['name'] . ' - ' . $exam['jalali_date'] . ' (' . $exam['status'] . ')'; ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                        <select name="status" class="form-select d-inline-block w-auto">
                                            <option value="active">فعال</option>
                                            <option value="inactive">غیرفعال</option>
                                            <option value="pending">در انتظار</option>
                                        </select>
                                        <button type="submit" name="update_exam_status" class="btn btn-sm btn-primary">به‌روزرسانی</button>
                                    </form>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Lessons -->
            <div class="tab-pane fade" id="lessons">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>مدیریت دروس</h5>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="mb-3"><input type="text" name="lesson_name" placeholder="نام درس" class="form-control" required></div>
                            <div class="mb-3"><input type="number" name="total_questions" placeholder="تعداد سوالات" class="form-control" required></div>
                            <button type="submit" name="add_lesson" class="btn btn-primary btn-custom">افزودن درس</button>
                        </form>
                        <ul class="list-group mt-3">
                            <?php while ($lesson = $lessons->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo $lesson['name'] . ' (' . $lesson['total_questions'] . ')'; ?>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editLessonModal<?php echo $lesson['id']; ?>">ویرایش</button>
                                        <a href="?delete_lesson=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید؟');">حذف</a>
                                    </div>
                                </li>
                                <!-- Edit Lesson Modal -->
                                <div class="modal fade" id="editLessonModal<?php echo $lesson['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">ویرایش درس: <?php echo $lesson['name']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="post">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">تعداد سوالات</label>
                                                        <input type="number" name="total_questions" value="<?php echo $lesson['total_questions']; ?>" class="form-control" required>
                                                    </div>
                                                    <button type="submit" name="edit_lesson" class="btn btn-primary btn-custom">ذخیره</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Approve Scores -->
            <div class="tab-pane fade" id="scores">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>تأیید نتایج</h5>
                        <form method="post" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" name="approve_all_scores" class="btn btn-success btn-custom">تأیید تمام نمرات</button>
                        </form>
                        <ul class="list-group">
                            <?php 
                            $current_student_id = null;
                            while ($score = $pending_scores->fetch_assoc()): 
                                if ($current_student_id !== $score['user_id']): 
                                    if ($current_student_id !== null): ?>
                                        </ul>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $current_student_id; ?>">
                                            <button type="submit" name="approve_student_scores" class="btn btn-success btn-sm btn-custom">تأیید تمام نمرات این دانش‌آموز</button>
                                        </form>
                                        <ul class="list-group mt-3">
                                    <?php endif; 
                                    $current_student_id = $score['user_id']; ?>
                                    <li class="list-group-item fw-bold bg-light"><?php echo htmlspecialchars($score['student']); ?></li>
                            <?php endif; ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?php echo $score['exam'] . ' - ' . $score['lesson'] . ' (صحیح: ' . $score['correct'] . ', غلط: ' . $score['wrong'] . ', نزده: ' . $score['unanswered'] . ')'; ?>
                                    <a href="?approve_score=<?php echo $score['id']; ?>" class="btn btn-sm btn-success">تأیید</a>
                                </li>
                            <?php endwhile; ?>
                            <?php if ($current_student_id !== null): ?>
                                </ul>
                                <form method="post" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $current_student_id; ?>">
                                    <button type="submit" name="approve_student_scores" class="btn btn-success btn-sm btn-custom">تأیید تمام نمرات این دانش‌آموز</button>
                                </form>
                                <ul class="list-group">
                            <?php endif; ?>
                        </ul>
                        <?php if ($pending_scores->num_rows == 0): ?>
                            <div class="alert alert-info mt-3">هیچ نمره‌ای برای تأیید وجود ندارد.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Requests -->
            <div class="tab-pane fade" id="edit_requests">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>مدیریت درخواست‌های ویرایش</h5>
                        <ul class="list-group">
                            <?php while ($req = $edit_requests->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?php echo $req['student'] . ' - ' . $req['exam']; ?>
                                    <div>
                                        <a href="?approve_request=<?php echo $req['id']; ?>" class="btn btn-sm btn-success">تأیید</a>
                                        <a href="?reject_request=<?php echo $req['id']; ?>" class="btn btn-sm btn-danger">رد</a>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php if ($edit_requests->num_rows == 0): ?>
                            <div class="alert alert-info mt-3">هیچ درخواست ویرایشی در انتظار نیست.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Access Control -->
            <div class="tab-pane fade" id="access">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>مدیریت دسترسی دانش‌آموزان</h5>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="mb-3">
                                <label class="form-label">دانش‌آموز</label>
                                <select name="user_id" class="form-select" required>
                                    <?php $students->data_seek(0); while ($student = $students->fetch_assoc()): ?>
                                        <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">صفحه</label>
                                <select name="page" class="form-select" required>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo $page; ?>"><?php echo $page; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">دسترسی</label>
                                <select name="allowed" class="form-select">
                                    <option value="1">اجازه دسترسی</option>
                                    <option value="0">عدم اجازه</option>
                                </select>
                            </div>
                            <button type="submit" name="update_access" class="btn btn-primary btn-custom">به‌روزرسانی</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reports -->
            <div class="tab-pane fade" id="reports">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>گزارشات و نتایج</h5>
                        <p>تعداد دانش‌آموزان: <?php echo $report_total_students; ?></p>
                        <p>تعداد آزمون‌ها: <?php echo $report_total_exams; ?></p>
                        <p>میانگین درصد: <?php echo round($report_avg_percent, 2); ?>%</p>
                    </div>
                </div>
            </div>

            <!-- Logs -->
            <div class="tab-pane fade" id="logs">
                <div class="card">
                    <div class="card-body">
                        <h5>لاگ فعالیت‌ها</h5>
                        <ul class="list-group">
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <li class="list-group-item"><?php echo $log['logged_at'] . ' - ' . $log['action'] . ' - ' . $log['details']; ?></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Report Cards -->
            <div class="tab-pane fade" id="report_cards">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>کارنامه‌های دانش‌آموزان</h5>
                        <ul class="list-group">
                            <?php $students->data_seek(0); while ($student = $students->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <?php echo htmlspecialchars($student['name']); ?>
                                    <a href="report_card.php?user_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">مشاهده کارنامه</a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>