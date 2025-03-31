<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $recaptchaSecret = "6LdguQQrAAAAAHxKVxKZ1qbIUlkQj9LBDERq-m36";
  $recaptchaResponse = $_POST['g-recaptcha-response'];
  $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptchaSecret&response=$recaptchaResponse");
  $captchaSuccess = json_decode($verify);

  if (!$captchaSuccess->success) {
    setcookie("form_message", "Please verify you're not a robot.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  $username = trim($_POST["username"]);
  $password = $_POST["password"];

  if (empty($username) || empty($password)) {
    setcookie("form_message", "Please fill in all fields.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
  $check->bind_param("s", $username);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    setcookie("form_message", "Username already taken.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  $hashed_password = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
  $stmt->bind_param("ss", $username, $hashed_password);

  if ($stmt->execute()) {
    setcookie("form_message", "Account created! You can log in now.", time() + 5, "/");
    setcookie("form_type", "success", time() + 5, "/");
    header("Location: login.html");
    exit;
  } else {
    setcookie("form_message", "Something went wrong. Please try again.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }
}
?>
