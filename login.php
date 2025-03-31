<?php
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);
  $password = $_POST["password"];
  $remember = isset($_POST["remember"]);

  // Prepare and execute user lookup
  $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  // If user exists
  if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $hashed_password);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
      // Cookie expiry: 0 for session, or 30 days
      $expiry = $remember ? time() + (86400 * 30) : 0;

      // Set cookies
      setcookie("loggedin", "1", $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("username", $username, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);
      setcookie("user_id", $id, $expiry, "/", "", isset($_SERVER["HTTPS"]), false);

      header("Location: index.html");
      exit;
    }
  }

  // On error
  setcookie("form_message", "Invalid username or password.", time() + 5, "/");
  setcookie("form_type", "error", time() + 5, "/");
  header("Location: login.html");
  exit;
}
?>
