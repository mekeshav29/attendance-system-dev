<?php
$conn = mysqli_connect('localhost', 'root', 'root', 'attendance_system');
if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}
echo 'Connection successful!';
