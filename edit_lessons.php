<?php
// File: edit_lessons.php
// Manage lessons with fixed input handling
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = get_db_connection();
$errors = [];
$success = '';

if (isset($_POST['add_lesson'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $name = trim($_POST['name']);
    $total_questions = intval($_POST['total_questions']);
    
    if (empty($name)) {
        $errors[] = "نام درس الزامی است.";
    } elseif ($total_questions < 1) {
        $errors[] = "تعداد سوالات باید حداقل 1 باشد.";
    } else {
        $stmt = $conn->prepare("INSERT INTO lessons (name, total_questions) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $total_questions);
        if ($stmt->execute()) {
            $success = "درس با موفقیت اضافه شد.";
            log_activity($_SESSION['admin_id'], "Added lesson", "Lesson: $name");
        } else {
            $errors[] = "خطا در اضافه کردن درس.";
        }
        $stmt->close();
    }
}

if (isset($_POST['edit_lesson'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $total_questions = intval($_POST['total_questions']);
    
    if (empty($name)) {
        $errors[] = "نام درس الزامی است.";
    } elseif ($total_questions < 1) {
        $errors[] = "تعداد سوالات باید حداقل 1 باشد.";
    } else {
        $stmt = $conn->prepare("UPDATE lessons SET name = ?, total_questions = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $total_questions, $id);
        if ($stmt->execute()) {
            $success = "درس با موفقیت ویرایش شد.";
            log_activity($_SESSION['admin_id'], "Edited lesson", "Lesson ID: $id");
        } else {
            $errors[] = "خطا در ویرایش درس.";
        }
        $stmt->close();
    }
}

$lessons_query = $conn->query("SELECT id, name, total_questions FROM lessons ORDER BY name");
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دروس</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .form-container { max-width: 500px; margin: auto; background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 10px; }
        .success { color: #28a745; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 10px; }
        .modal-content { border-radius: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4 text-primary">مدیریت دروس</h2>
            <a href="admin_panel.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل ادمین</a>
            <?php if (!empty($errors)): ?>
                <div class="error mb-3"><?php echo implode('<br>', $errors); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success mb-3"><?php echo $success; ?></div>
            <?php endif; ?>
            <div class="form-container mb-4">
                <h4>افزودن درس جدید</h4>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">نام درس</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_questions" class="form-label">تعداد کل سوالات</label>
                        <input type="number" name="total_questions" id="total_questions" class="form-control" min="1" required>
                    </div>
                    <button type="submit" name="add_lesson" class="btn btn-primary btn-custom w-100">افزودن درس</button>
                </form>
            </div>
            <h4 class="mb-4">لیست دروس</h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>نام درس</th>
                        <th>تعداد سوالات</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($lesson = $lessons_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lesson['name']); ?></td>
                            <td><?php echo $lesson['total_questions']; ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $lesson['id']; ?>">ویرایش</button>
                            </td>
                        </tr>
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?php echo $lesson['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $lesson['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editModalLabel<?php echo $lesson['id']; ?>">ویرایش درس</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="id" value="<?php echo $lesson['id']; ?>">
                                            <div class="mb-3">
                                                <label for="name_<?php echo $lesson['id']; ?>" class="form-label">نام درس</label>
                                                <input type="text" name="name" id="name_<?php echo $lesson['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($lesson['name']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="total_questions_<?php echo $lesson['id']; ?>" class="form-label">تعداد کل سوالات</label>
                                                <input type="number" name="total_questions" id="total_questions_<?php echo $lesson['id']; ?>" class="form-control" value="<?php echo $lesson['total_questions']; ?>" min="1" required>
                                            </div>
                                            <button type="submit" name="edit_lesson" class="btn btn-primary btn-custom w-100">ذخیره تغییرات</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent excessive event triggers
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value < 1) {
                    this.value = 1;
                }
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>