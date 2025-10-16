<?php
// File: db_setup.php
// This file creates the necessary tables in the existing database automatically.
// Run this file once by accessing it via browser, e.g., https://schfarhang.ir/db_setup.php

$servername = "localhost"; // Assuming standard cPanel localhost
$username = "xsmdyryt_user2";
$password = "T3pjDAr94ZYH2}B";
$dbname = "xsmdyryt_azmoon1";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist

// Table: users (for students and admins)
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_users);

// Add default admin if not exists
$admin_code = 'admin';
$admin_name = 'Administrator';
$admin_hash = '$2b$12$0F1lsIZliWGWheTzIheApetGiM1/BF/MgRYuCCxFdXwDCFOzRMXO6'; // Hash for 'admin123'
$check_admin = $conn->query("SELECT id FROM users WHERE code = '$admin_code'");
if ($check_admin->num_rows == 0) {
    $conn->query("INSERT INTO users (code, password, name, role) VALUES ('$admin_code', '$admin_hash', '$admin_name', 'admin')");
}

// Table: lessons (for managing lessons dynamically)
$sql_lessons = "CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    total_questions INT DEFAULT 20
)";
$conn->query($sql_lessons);

// Insert default lessons if not exist
$default_lessons = [
    ['ریاضی', 20],
    ['علوم', 20],
    ['پیام', 20],
    ['ادبیات', 20],
    ['مطالعات', 20]
];
foreach ($default_lessons as $lesson) {
    $check = $conn->query("SELECT id FROM lessons WHERE name = '{$lesson[0]}'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO lessons (name, total_questions) VALUES ('{$lesson[0]}', {$lesson[1]})");
    }
}

// Table: exams (for managing exams with Jalali date)
$sql_exams = "CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    jalali_date VARCHAR(10) NOT NULL, -- e.g., 1403/07/25
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    show_rank TINYINT(1) DEFAULT 1,
    show_traz TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_exams);

// Table: scores (for student scores per exam and lesson)
$sql_scores = "CREATE TABLE IF NOT EXISTS scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    lesson_id INT NOT NULL,
    correct INT DEFAULT 0,
    wrong INT DEFAULT 0,
    unanswered INT DEFAULT 0,
    percent FLOAT DEFAULT 0,
    traz FLOAT DEFAULT 0,
    approved TINYINT(1) DEFAULT 0, -- 0: pending, 1: approved by admin
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id)
)";
$conn->query($sql_scores);

// Table: edit_requests (for student edit requests)
$sql_requests = "CREATE TABLE IF NOT EXISTS edit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES exams(id)
)";
$conn->query($sql_requests);

// Table: logs (for logging activities)
$sql_logs = "CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip VARCHAR(45),
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_logs);

// Table: access_controls (for managing student page access)
$sql_access = "CREATE TABLE IF NOT EXISTS access_controls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page VARCHAR(100) NOT NULL,
    allowed TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";
$conn->query($sql_access);

echo "Database tables created successfully! Default admin added with code: 'admin' and password: 'admin123'";
$conn->close();
?>