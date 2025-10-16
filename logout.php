<?php
// File: logout.php
// Logout for all
session_start();
require 'db_connect.php';
log_activity(isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : NULL), "Logout");
session_destroy();
header("Location: index.php");
exit;
?>