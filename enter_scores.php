<?php
// File: enter_scores.php
// Student enter scores page
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

// Check access
$conn = get_db_connection();
$access_check = $conn->prepare("SELECT allowed FROM access_controls WHERE user_id = ? AND page = 'enter_scores.php'");
$access_check->bind_param("i", $_SESSION['student_id']);
$access_check->execute();
$access_check->bind_result($allowed);
if ($access_check->fetch() && $allowed == 0) {
    echo "<script>alert('دسترسی به این صفحه ندارید');</script>";
    header("Location: student_login.php");
    exit;
}
$access_check->close();

// Handle exam selection
$selected_exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
if ($selected_exam_id) {
    $check = $conn->prepare("SELECT id FROM scores WHERE user_id = ? AND exam_id = ?");
    $check->bind_param("ii", $_SESSION['student_id'], $selected_exam_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $req_check = $conn->prepare("SELECT status FROM edit_requests WHERE user_id = ? AND exam_id = ? AND status = 'approved' ORDER BY requested_at DESC LIMIT 1");
        $req_check->bind_param("ii", $_SESSION['student_id'], $selected_exam_id);
        $req_check->execute();
        if ($req_check->get_result()->num_rows == 0) {
            echo "<script>alert('شما قبلاً نمرات این آزمون را وارد کرده‌اید. برای ویرایش، به صفحه درخواست ویرایش بروید.'); window.location.href='request_edit.php';</script>";
            $check->close();
            $req_check->close();
            $conn->close();
            ob_end_flush();
            exit;
        }
        $req_check->close();
    }
    $check->close();
}

// Fetch exams
$exams_result = $conn->query("SELECT * FROM exams WHERE status = 'active' ORDER BY created_at DESC");

// Fetch lessons
$lessons_result = $conn->query("SELECT * FROM lessons");

// Handle score submission
if (isset($_POST['submit_scores'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    $exam_id = intval($_POST['exam_id']);
    $user_id = $_SESSION['student_id'];

    $errors = [];
    foreach ($_POST['correct'] as $lesson_id => $correct) {
        $correct = intval($correct);
        $wrong = intval($_POST['wrong'][$lesson_id]);
        $lesson_query = $conn->prepare("SELECT total_questions FROM lessons WHERE id = ?");
        $lesson_query->bind_param("i", $lesson_id);
        $lesson_query->execute();
        $lesson_query->bind_result($total);
        if (!$lesson_query->fetch()) continue;
        $lesson_query->close();

        $unanswered = $total - $correct - $wrong;
        if ($unanswered < 0 || $correct < 0 || $wrong < 0 || ($correct + $wrong) > $total) {
            $errors[] = "خطا در درس $lesson_id: مجموع صحیح و غلط نمی‌تواند بیش از $total باشد.";
            continue;
        }
        $percent = ($total > 0) ? (($correct - ($wrong / 3)) / $total) * 100 : 0; // Allow negative percent
        $traz = max(0, min(10000, $percent * 100)); // Traz between 0 and 10000

        $stmt = $conn->prepare("INSERT INTO scores (user_id, exam_id, lesson_id, correct, wrong, unanswered, percent, traz) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE correct = VALUES(correct), wrong = VALUES(wrong), unanswered = VALUES(unanswered), percent = VALUES(percent), traz = VALUES(traz)");
        $stmt->bind_param("iiiiiddd", $user_id, $exam_id, $lesson_id, $correct, $wrong, $unanswered, $percent, $traz);
        $stmt->execute();
        $stmt->close();
    }
    if (!empty($errors)) {
        echo "<script>alert('" . implode('\\n', $errors) . "');</script>";
    } else {
        log_activity($user_id, "Entered scores", "Exam: $exam_id");
        echo "<script>alert('نمرات با موفقیت ارسال شد. منتظر تأیید مدیر باشید.');</script>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود نمرات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .form-container { max-width: 700px; margin: auto; background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 10px; }
        .lesson-row { background: #e9f5ff; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
        input:invalid, .invalid-input { border-color: #dc3545 !important; background: #fff5f5; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="text-center mb-4 text-primary">ورود نمرات آزمون</h2>
        <a href="student_login.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل</a>
        <a href="logout.php" class="btn btn-danger btn-custom mb-4">خروج از سیستم</a>

        <?php if ($exams_result->num_rows == 0): ?>
            <div class="alert alert-warning text-center">
                <h5>هیچ آزمونی ایجاد نشده است!</h5>
                <p>لطفاً با مدیر سیستم تماس بگیرید تا آزمون جدیدی اضافه شود.</p>
            </div>
        <?php else: ?>
            <form method="post" id="examSelectForm">
                <div class="mb-4">
                    <label for="exam" class="form-label fw-bold">انتخاب آزمون</label>
                    <select name="exam_id" id="exam" class="form-select" required onchange="this.form.submit()">
                        <option value="">-- انتخاب کنید --</option>
                        <?php while ($exam = $exams_result->fetch_assoc()): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo $exam['id'] == $selected_exam_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['name']) . ' - ' . $exam['jalali_date'] . ' (فعال)'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <?php if ($selected_exam_id): ?>
                <form method="post" id="scoresForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="exam_id" value="<?php echo $selected_exam_id; ?>">
                    <div id="lessonsContainer">
                        <?php if ($lessons_result->num_rows > 0): ?>
                            <?php $lessons_result->data_seek(0); while ($lesson = $lessons_result->fetch_assoc()): ?>
                                <div class="lesson-row lesson-<?php echo $lesson['id']; ?>" data-lesson-id="<?php echo $lesson['id']; ?>" data-total="<?php echo $lesson['total_questions']; ?>">
                                    <h5 class="mb-3"><?php echo htmlspecialchars($lesson['name']); ?> (کل سوالات: <span class="total-q"><?php echo $lesson['total_questions']; ?></span>)</h5>
                                    <div class="row input-group">
                                        <div class="col-md-4">
                                            <label class="form-label">تعداد صحیح</label>
                                            <input type="number" name="correct[<?php echo $lesson['id']; ?>]" min="0" class="form-control" placeholder="0" oninput="calculateForLesson(<?php echo $lesson['id']; ?>)">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">تعداد غلط</label>
                                            <input type="number" name="wrong[<?php echo $lesson['id']; ?>]" min="0" class="form-control" placeholder="0" oninput="calculateForLesson(<?php echo $lesson['id']; ?>)">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">تعداد نزده (خودکار)</label>
                                            <input type="number" id="unanswered_<?php echo $lesson['id']; ?>" class="form-control bg-light" readonly placeholder="0">
                                        </div>
                                    </div>
                                    <div id="error_<?php echo $lesson['id']; ?>" class="error mt-2" style="display: none;"></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">هیچ درسی تعریف نشده است!</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="submit_scores" class="btn btn-primary btn-custom w-100 mt-4" id="submitButton" disabled>ارسال نمرات</button>
                </form>
                <a href="request_edit.php" class="btn btn-warning btn-custom w-100 mt-3">درخواست ویرایش نمرات</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        let hasErrors = false;

        function calculateForLesson(lessonId) {
            const lessonDiv = document.querySelector(`.lesson-${lessonId}`);
            const total = parseInt(lessonDiv.dataset.total);
            const correctInput = document.querySelector(`input[name="correct[${lessonId}]"]`);
            const wrongInput = document.querySelector(`input[name="wrong[${lessonId}]"]`);
            const correct = parseInt(correctInput.value) || 0;
            const wrong = parseInt(wrongInput.value) || 0;
            const unanswered = total - correct - wrong;
            const errorDiv = document.getElementById(`error_${lessonId}`);

            document.getElementById(`unanswered_${lessonId}`).value = unanswered < 0 ? 0 : unanswered;

            if (correct + wrong > total || correct < 0 || wrong < 0) {
                errorDiv.textContent = `خطا: مجموع صحیح و غلط نمی‌تواند بیش از ${total} باشد!`;
                errorDiv.style.display = 'block';
                correctInput.classList.add('invalid-input');
                wrongInput.classList.add('invalid-input');
                hasErrors = true;
            } else {
                errorDiv.style.display = 'none';
                correctInput.classList.remove('invalid-input');
                wrongInput.classList.remove('invalid-input');
                hasErrors = document.querySelectorAll('.invalid-input').length > 0;
            }
            updateSubmitButton();
        }

        function updateSubmitButton() {
            const submitButton = document.getElementById('submitButton');
            const selectedExam = document.getElementById('exam').value;
            submitButton.disabled = !selectedExam || hasErrors;
        }

        document.getElementById('scoresForm')?.addEventListener('submit', function(e) {
            const selectedExam = document.getElementById('exam').value;
            if (!selectedExam) {
                alert('لطفاً آزمون را انتخاب کنید.');
                e.preventDefault();
                return;
            }
            let hasError = false;
            document.querySelectorAll('[data-lesson-id]').forEach(lesson => {
                const lessonId = lesson.dataset.lessonId;
                const correct = parseInt(document.querySelector(`input[name="correct[${lessonId}]"]`).value) || 0;
                const wrong = parseInt(document.querySelector(`input[name="wrong[${lessonId}]"]`).value) || 0;
                if (correct + wrong > parseInt(lesson.dataset.total)) {
                    hasError = true;
                }
            });
            if (hasError) {
                alert('لطفاً خطاهای ورودی را اصلاح کنید.');
                e.preventDefault();
            }
        });

        document.getElementById('exam')?.addEventListener('change', function() {
            document.getElementById('examSelectForm').submit();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>