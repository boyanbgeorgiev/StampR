<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);
  $password = $_POST["password"];

  $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $hashed_password);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
      setcookie("loggedin", "1", time() + 3600, "/");
      setcookie("username", $username, time() + 3600, "/");
      header("Location: index.php");
      exit;
    }
  }

  setcookie("form_message", urlencode("<div class='message error'>Invalid username or password.</div>"), time() + 5, "/");
  header("Location: login.html");
  exit;
}
?>
