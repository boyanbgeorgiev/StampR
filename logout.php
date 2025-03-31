<?php
// Clear cookies
setcookie("loggedin", "", time() - 3600, "/");
setcookie("username", "", time() - 3600, "/");

// Optional: destroy session if you're using it
session_start();
session_destroy();

// Redirect
header("Location: index.html");
exit;
?>
