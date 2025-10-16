<?php
// File: db_connect.php
// Reusable database connection file
function get_db_connection() {
    $servername = "localhost";
    $username = "xsmdyryt_user2";
    $password = "T3pjDAr94ZYH2}B";
    $dbname = "xsmdyryt_azmoon1";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Jalali date functions (simple implementation without external library)
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + (($gm != 0) ? $g_d_m[$gm - 1] : 0);
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    $jy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian($jy, $jm, $jd) {
    $gy = ($jy <= 979) ? 621 : 1600;
    $jy -= ($jy <= 979) ? 0 : 979;
    $days = (365 * $jy) + (((int)$jy / 33) * 8) + ((int)(($jy % 33) + 3) / 4) + 78 + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy += 400 * ((int)($days / 146097));
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * ((int)(--$days / 36524));
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * ((int)(($days) / 1461));
    $days %= 1461;
    $gy += (int)(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $gd = $days + 1;
    $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13; $gm++) {
        $v = $sal_a[$gm];
        if ($gd <= $v) break;
        $gd -= $v;
    }
    return [$gy, $gm, $gd];
}

function get_jalali_date() {
    $now = getdate();
    $jalali = gregorian_to_jalali($now['year'], $now['mon'], $now['mday']);
    return sprintf("%04d/%02d/%02d", $jalali[0], $jalali[1], $jalali[2]);
}

// Log function
function log_activity($user_id, $action, $details = '') {
    $conn = get_db_connection();
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO logs (user_id, action, details, ip) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// CSRF protection
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>