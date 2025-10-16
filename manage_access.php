<?php
// File: manage_access.php
// Manage student access permissions
ob_start();
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$conn = get_db_connection();

// Page name mapping
$page_names = [
    'enter_scores.php' => 'ورود نمرات',
    'report_card.php' => 'نمایش کارنامه',
    'request_edit.php' => 'درخواست ویرایش'
];

// Fetch students and their access
$students_query = $conn->query("SELECT id, name FROM users WHERE role = 'student'");
$students = [];
while ($row = $students_query->fetch_assoc()) {
    $students[$row['id']] = $row['name'];
}

$access_query = $conn->prepare("SELECT user_id, page, allowed FROM access_controls WHERE user_id IN (SELECT id FROM users WHERE role = 'student')");
$access_query->execute();
$access_result = $access_query->get_result();
$access_data = [];
while ($row = $access_result->fetch_assoc()) {
    $access_data[$row['user_id']][$row['page']] = $row['allowed'];
}
$access_query->close();

// Handle access update
if (isset($_POST['update_access'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) die("CSRF invalid");
    foreach ($_POST['access'] as $user_id => $pages) {
        foreach ($pages as $page => $allowed) {
            $allowed = intval($allowed);
            $stmt = $conn->prepare("INSERT INTO access_controls (user_id, page, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = ?");
            $stmt->bind_param("isii", $user_id, $page, $allowed, $allowed);
            $stmt->execute();
            $stmt->close();
            log_activity($_SESSION['admin_id'], "Updated access", "User: $user_id, Page: $page, Allowed: $allowed");
        }
    }
    header("Refresh:0");
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دسترسی دانش‌آموزان</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <h2 class="text-center mb-4 text-primary">مدیریت دسترسی دانش‌آموزان</h2>
            <a href="admin_panel.php" class="btn btn-primary btn-custom mb-4">بازگشت به پنل ادمین</a>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>نام دانش‌آموز</th>
                            <?php foreach ($page_names as $page => $name): ?>
                                <th><?php echo htmlspecialchars($name); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $user_id => $name): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($name); ?></td>
                                <?php foreach ($page_names as $page => $name): ?>
                                    <td>
                                        <input type="checkbox" name="access[<?php echo $user_id; ?>][<?php echo $page; ?>]" value="1" <?php echo (isset($access_data[$user_id][$page]) && $access_data[$user_id][$page] == 1) ? 'checked' : ''; ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" name="update_access" class="btn btn-primary btn-custom w-100">ذخیره تغییرات</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>