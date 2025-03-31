<?php
$host = "localhost";
$user = "admin";       // Change if different
$pass = "Bobiphpmyadmin1!";           // Your MySQL password
$db = "tuesfest";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
