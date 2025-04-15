<?php
session_start();

// Check if the user is logged in
if (!isset($_COOKIE['loggedin']) || $_COOKIE['loggedin'] !== "1") {
    header("Location: login.html");
    exit;
}

require 'db.php';

// Get user ID from the cookie
$userId = $_COOKIE['user_id'];

// Fetch files for the logged-in user
$stmt = $conn->prepare("SELECT timestamp, serial_number, file_hash, file_name FROM timestamps WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$files = $result->fetch_all(MYSQLI_ASSOC);

// Mark public/private
foreach ($files as &$file) {
    $filePath = __DIR__ . '/uploads/' . $file['file_name'];
    $file['is_public'] = file_exists($filePath);
}
unset($file);
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8" />
    <title>Моят профил</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .file-toggle {
            margin-bottom: 1em;
        }
        .file-toggle button {
            margin-right: 0.5em;
            padding: 0.5em 1em;
            border: none;
            background-color: #ccc;
            cursor: pointer;
            border-radius: 4px;
        }
        .file-toggle button.active {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head>
<body>
<header class="header">
    <nav class="nav">
        <a href="/index.html"><button>Времево удостоверяване</button></a>
        <a href="/hash_search.html"><button>Проверка оригинал</button></a>
        <a href="about.html"><button>За нас</button></a>
        <div class="dropdown" id="userDropdown">
            <button id="dropdownToggle" class="active">👤 Акаунт ▾</button>
            <div class="dropdown-menu" id="dropdownMenu"></div>
        </div>
    </nav>
</header>

<section class="profile-section">
    <h2>Моите удостоверени оригинали</h2>

    <?php if (count($files) > 0): ?>
        <div class="file-toggle">
            <button id="togglePublic" class="active">📂 Запазени (публични)</button>
            <button id="togglePrivate">🔒 Само удостоверени</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Име на оригинал</th>
                    <th>Сериен номер</th>
                    <th>Хеш на оригинал</th>
                    <th>Дата и час на удостоверяване</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr class="<?php echo $file['is_public'] ? 'row-public' : 'row-private'; ?>">
                        <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                        <td><?php echo htmlspecialchars($file['serial_number']); ?></td>
                        <td><?php echo htmlspecialchars($file['file_hash']); ?></td>
                        <td><?php echo htmlspecialchars($file['timestamp']); ?></td>
                        <td>
                            <?php if ($file['is_public']): ?>
                                <a href="uploads/<?php echo urlencode($file['file_name']); ?>" download>⬇️ Изтегли</a>
                            <?php else: ?>
                                <em>Само удостоверен</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Нямате удостоверени оригинали.</p>
    <?php endif; ?>
</section>

<script>
document.addEventListener("DOMContentLoaded", () => {
    function getCookie(name) {
        const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
        return match ? decodeURIComponent(match[2]) : null;
    }

    const loggedIn = getCookie("loggedin") === "1";
    const firstName = getCookie("first_name");
    const lastName = getCookie("last_name");
    const fallbackUsername = getCookie("username");

    const toggle = document.getElementById("dropdownToggle");
    const menu = document.getElementById("dropdownMenu");

    if (loggedIn && firstName && lastName) {
        toggle.innerHTML = `Здравей, ${firstName} ${lastName} ▾`;
        menu.innerHTML = `
            <a href="account.php">Моят профил</a>
            <a href="logout.php">Изход</a>
        `;
    } else if (loggedIn && fallbackUsername) {
        toggle.innerHTML = `Здравей, ${fallbackUsername} ▾`;
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

    // Filter buttons
    const btnPublic = document.getElementById("togglePublic");
    const btnPrivate = document.getElementById("togglePrivate");
    const rowsPublic = document.querySelectorAll(".row-public");
    const rowsPrivate = document.querySelectorAll(".row-private");

    function showPublic() {
        btnPublic.classList.add("active");
        btnPrivate.classList.remove("active");
        rowsPublic.forEach(row => row.style.display = "table-row");
        rowsPrivate.forEach(row => row.style.display = "none");
    }

    function showPrivate() {
        btnPrivate.classList.add("active");
        btnPublic.classList.remove("active");
        rowsPrivate.forEach(row => row.style.display = "table-row");
        rowsPublic.forEach(row => row.style.display = "none");
    }

    btnPublic.addEventListener("click", showPublic);
    btnPrivate.addEventListener("click", showPrivate);

    showPublic();
});
</script>
</body>
</html>
