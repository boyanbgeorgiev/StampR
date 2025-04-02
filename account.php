<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_COOKIE['loggedin']) || $_COOKIE['loggedin'] !== "1") {
    header("Location: login.html"); // Redirect to login if not logged in
    exit;
}

require 'db.php'; // Database connection

// Get user ID from the cookie
$userId = $_COOKIE['user_id'];

// Fetch files for the logged-in user
$stmt = $conn->prepare("SELECT timestamp, serial_number, file_hash FROM timestamps WHERE user_id = ?");
$stmt->bind_param("i", $userId); // Bind user_id as an integer
$stmt->execute();
$result = $stmt->get_result();

// Store the user's files
$files = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8" />
    <title>Моят профил</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <!-- Navigation -->
  <header class="header">
    <nav class="nav">
      <a href="/index.html"><button>Времево удостоверяване</button></a>
      <a href="/hash_search.html"><button class="active">Проверка оригинал</button></a>
      <a href="about.html"><button>За нас</button></a> <!-- Link to About page -->

      <div class="dropdown" id="userDropdown">
        <button id="dropdownToggle">👤 Акаунт ▾</button>
        <div class="dropdown-menu" id="dropdownMenu">
          <!-- JS injects menu -->
        </div>
      </div>
    </nav>
  </header>

  <section class="profile-section">
      <h2>Моите удостоверени файлове</h2>

      <?php if (count($files) > 0): ?>
          <table>
              <thead>
                  <tr>
                      <th>Дата и час на удостоверяване</th>
                      <th>Сериен номер</th>
                      <th>Хеш на файл</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($files as $file): ?>
                      <tr>
                          <td><?php echo htmlspecialchars($file['timestamp']); ?></td>
                          <td><?php echo htmlspecialchars($file['serial_number']); ?></td>
                          <td><?php echo htmlspecialchars($file['file_hash']); ?></td>
                      </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
      <?php else: ?>
          <p>Нямате удостоверени файлове.</p>
      <?php endif; ?>
  </section>

  <!-- Scripts -->
  <script>
      document.addEventListener("DOMContentLoaded", () => {
        function getCookie(name) {
          const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
          return match ? decodeURIComponent(match[2]) : null;
        }

        const loggedIn = getCookie("loggedin") === "1";
        const username = getCookie("username");
        const toggle = document.getElementById("dropdownToggle");
        const menu = document.getElementById("dropdownMenu");

        if (!toggle || !menu) return;

        if (loggedIn && username) {
          toggle.innerHTML = `Здравей, ${username} ▾`;
          menu.innerHTML = `
            <a href="account.php">Моят профил</a>
            <a href="logout.php">Изход</a>
          `;
        } else {
          toggle.innerHTML = "Акаунт ▾";
          menu.innerHTML = `
            <a href="login.html">Вход</a>
            <a href="signup.html">Регистрация</a>
          `;
        }

        toggle.addEventListener("click", (e) => {
          e.stopPropagation();
          menu.classList.toggle("show");
        });

        document.addEventListener("click", (e) => {
          const dropdown = document.getElementById("userDropdown");
          if (!dropdown.contains(e.target)) {
            menu.classList.remove("show");
          }
        });
      });
  </script>
</body>
</html>
