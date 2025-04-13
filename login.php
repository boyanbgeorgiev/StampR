<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);
  $password = $_POST["password"];
  $remember = isset($_POST["remember"]);

  // Подготвяме заявката, като извличаме също first_name, last_name, email и phone
  $stmt = $conn->prepare("SELECT id, password, first_name, last_name, email, phone FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  // Ако потребителят съществува
  if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $hashed_password, $first_name, $last_name, $email, $phone);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
      // Времето на валидност на бисквитките: 0 за сесия или 30 дни
      $expiry = $remember ? time() + (86400 * 30) : 0;

      // Задаваме бисквитките
      setcookie("loggedin", "1", $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("username", $username, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("user_id", $id, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("first_name", $first_name, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("last_name", $last_name, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("email", $email, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("phone", $phone, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);

      header("Location: index.html");
      exit;
    }
  }

  // При грешка
  setcookie("form_message", "Invalid username or password.", time() + 5, "/");
  setcookie("form_type", "error", time() + 5, "/");
  header("Location: login.html");
  exit;
}
?>
