
<?php
$conn = mysqli_connect("localhost", "root", "1234");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
echo "MySQL Connected successfully";
?>