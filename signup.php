<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);
  $password = $_POST["password"];

  if (empty($username) || empty($password)) {
    setcookie("form_message", urlencode("<div class='message error'>Please fill in all fields.</div>"), time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
  $check->bind_param("s", $username);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    setcookie("form_message", urlencode("<div class='message error'>Username already taken.</div>"), time() + 5, "/");
    header("Location: signup.html");
    exit;
  }

  $hashed_password = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
  $stmt->bind_param("ss", $username, $hashed_password);

  if ($stmt->execute()) {
    setcookie("form_message", urlencode("<div class='message success'>Account created! You can log in now.</div>"), time() + 5, "/");
    header("Location: login.html");
    exit;
  } else {
    setcookie("form_message", urlencode("<div class='message error'>Something went wrong. Please try again.</div>"), time() + 5, "/");
    header("Location: signup.html");
    exit;
  }
}
?>
