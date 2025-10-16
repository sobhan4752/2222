<?php
// File: change_password.php
// Admin password change page
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = get_db_connection();
$admin_id = $_SESSION['admin_id'];
$errors = [];
$success = '';

if (isset($_POST['change_password'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "تمامی فیلدها باید پر شوند.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "رمز جدید و تأیید رمز مطابقت ندارند.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "رمز جدید باید حداقل 6 کاراکتر باشد.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($current_password, $hashed_password)) {
            // Update password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_hashed_password, $admin_id);
            if ($update_stmt->execute()) {
                $success = "رمز عبور با موفقیت تغییر کرد.";
                log_activity($admin_id, "Changed password", "");
            } else {
                $errors[] = "خطا در تغییر رمز عبور.";
            }
            $update_stmt->close();
        } else {
            $errors[] = "رمز فعلی نادرست است.";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغییر رمز عبور</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        .form-container { max-width: 500px; margin: auto; background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .error { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 10px; }
        .success { color: #28a745; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="text-center mb-4 text-primary">تغییر رمز عبور</h2>
        <a href="admin_panel.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل ادمین</a>
        <?php if (!empty($errors)): ?>
            <div class="error mb-3"><?php echo implode('<br>', $errors); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success mb-3"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="mb-3">
                <label for="current_password" class="form-label">رمز فعلی</label>
                <input type="password" name="current_password" id="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">رمز جدید</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">تأیید رمز جدید</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary btn-custom w-100">تغییر رمز</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>