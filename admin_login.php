<?php
// File: admin_login.php
ob_start();
session_start();
require 'db_connect.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: admin_panel.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = filter_input(INPUT_POST, 'code', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if (empty($code) || empty($password)) {
        $error = 'لطفاً کد و رمز عبور را وارد کنید.';
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE code = ? AND role = 'admin'");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->bind_result($id, $hashed_pass);
        if ($stmt->fetch() && password_verify($password, $hashed_pass)) {
            $_SESSION['admin_id'] = $id;
            log_activity($id, "Admin login");
            header("Location: admin_panel.php");
            exit;
        } else {
            $error = 'اطلاعات ورود نادرست است.';
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود ادمین</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f8f9fa; }
        .login-container { max-width: 400px; margin: 100px auto; }
        .card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-custom { border-radius: 50px; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card p-4">
            <h2 class="text-center mb-4">ورود ادمین</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label for="code" class="form-label">کد ادمین</label>
                    <input type="text" name="code" id="code" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">رمز عبور</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-custom w-100">ورود</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>