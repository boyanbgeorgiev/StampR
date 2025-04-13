<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $recaptchaSecret = "6LdguQQrAAAAAHxKVxKZ1qbIUlkQj9LBDERq-m36";
  $recaptchaResponse = $_POST['g-recaptcha-response'];
  $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptchaSecret&response=$recaptchaResponse");
  $captchaSuccess = json_decode($verify);

  if (!$captchaSuccess->success) {
    setcookie("form_message", "Моля, потвърдете, че не сте робот.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  // Извличане на данните от формата
  $first_name = trim($_POST["first_name"]);
  $last_name = trim($_POST["last_name"]);
  $phone = trim($_POST["phone"]);
  $email = trim($_POST["email"]);
  $username = trim($_POST["username"]);
  $password = $_POST["password"];

  // Проверка дали всички полета са попълнени
  if (empty($first_name) || empty($last_name) || empty($phone) || empty($email) || empty($username) || empty($password)) {
    setcookie("form_message", "Моля, попълнете всички полета.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  // Проверка дали потребителското име е заето
  $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
  $check->bind_param("s", $username);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    setcookie("form_message", "Потребителското име вече е заето.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  // Хеширане на паролата
  $hashed_password = password_hash($password, PASSWORD_DEFAULT);

  // Подготовка и изпълнение на заявката за регистрация
  $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, phone, email, username, password) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssssss", $first_name, $last_name, $phone, $email, $username, $hashed_password);

  if ($stmt->execute()) {
    setcookie("form_message", "Акаунтът беше създаден! Можете да влезете.", time() + 5, "/");
    setcookie("form_type", "success", time() + 5, "/");
    header("Location: login.html");
    exit;
  } else {
    setcookie("form_message", "Нещо се обърка. Моля, опитайте отново.", time() + 5, "/");
    setcookie("form_type", "error", time() + 5, "/");
    header("Location: signup.html");
    exit;
  }
}
?>
