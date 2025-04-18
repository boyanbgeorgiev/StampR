<?php
session_start();

if (!isset($_COOKIE['loggedin']) || $_COOKIE['loggedin'] !== "1") {
    header("Location: login.html");
    exit;
}

require 'db.php';

$userId = $_COOKIE['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT username, first_name, last_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($username, $firstName, $lastName, $email, $phone);
$stmt->fetch();
$stmt->close();

// Fetch files with TSA method
$stmt = $conn->prepare("SELECT timestamp, serial_number, file_hash, file_name, tsa_type FROM timestamps WHERE user_id = ? ORDER BY timestamp DESC");
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

// TSA type label formatter
function formatTsaType($type) {
    return match ($type) {
        'borica_test' => 'Borica Test',
        'borica_prod' => 'Borica Real',
        default => 'Free TSA',
    };
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8" />
    <title>Моят профил</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .file-toggle { margin-bottom: 1em; }
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
        .dev-mode {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .dev-mode select, .dev-mode input[type="password"] {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            width: 100%;
        }
        .dev-mode button {
            margin-top: 10px;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .dev-mode button:hover {
            background-color: #45a049;
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
    <h2>Моят профил</h2>
    <div>
        <p><strong>Потребителско име:</strong> <?= htmlspecialchars($username) ?></p>
        <p><strong>Име:</strong> <?= htmlspecialchars($firstName . ' ' . $lastName) ?></p>
        <p><strong>Имейл:</strong> <?= htmlspecialchars($email) ?></p>
        <p><strong>Телефон:</strong> <?= htmlspecialchars($phone) ?></p>
    </div>

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
                    <th>Дата и час</th>
                    <th>Метод TSA</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr class="<?= $file['is_public'] ? 'row-public' : 'row-private'; ?>">
                        <td><?= htmlspecialchars($file['file_name']) ?></td>
                        <td><?= htmlspecialchars($file['serial_number']) ?></td>
                        <td><?= htmlspecialchars($file['file_hash']) ?></td>
                        <td><?= htmlspecialchars($file['timestamp']) ?></td>
                        <td><?= htmlspecialchars(formatTsaType($file['tsa_type'])) ?></td>
                        <td>
                            <?php if ($file['is_public']): ?>
                                <a href="download.php?file=<?= urlencode($file['file_name']) ?>">⬇️ Изтегли</a>
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

    <div class="dev-mode">
        <h3>Режим за разработчици</h3>
        <form id="devModeForm">
            <label for="devPin">Въведете PIN за достъп:</label>
            <input type="password" id="devPin" placeholder="Въведете PIN" required />

            <label for="tsaUrl">Изберете TSA адрес:</label>
            <select id="tsaUrl" disabled>
                <option value="http://freetsa.org/tsr">Free TSA</option>
                <option value="http://@tsatest.b-trust.org">Borica Test</option>
                <option value="http://@tsa.b-trust.org">Borica Real</option>
            </select>

            <button type="button" id="enableDevMode">Активирай режим</button>
            <button type="submit" id="saveTsaUrl" disabled>Запази адрес</button>
        </form>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", () => {
    function getCookie(name) {
        const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
        return match ? decodeURIComponent(match[2]) : null;
    }

    const toggle = document.getElementById("dropdownToggle");
    const menu = document.getElementById("dropdownMenu");

    const loggedIn = getCookie("loggedin") === "1";
    const firstName = getCookie("first_name");
    const lastName = getCookie("last_name");
    const fallbackUsername = getCookie("username");

    if (loggedIn && firstName && lastName) {
        toggle.innerHTML = `Здравей, ${firstName} ${lastName} ▾`;
        menu.innerHTML = `<a href="account.php">Моят профил</a><a href="logout.php">Изход</a>`;
    } else if (loggedIn && fallbackUsername) {
        toggle.innerHTML = `Здравей, ${fallbackUsername} ▾`;
        menu.innerHTML = `<a href="account.php">Моят профил</a><a href="logout.php">Изход</a>`;
    } else {
        toggle.innerHTML = "Акаунт ▾";
        menu.innerHTML = `<a href="login.html">Вход</a><a href="signup.html">Регистрация</a>`;
    }

    toggle.addEventListener("click", (e) => {
        e.stopPropagation();
        menu.classList.toggle("show");
    });

    document.addEventListener("click", (e) => {
        if (!document.getElementById("userDropdown").contains(e.target)) {
            menu.classList.remove("show");
        }
    });

    // Filter buttons
    const btnPublic = document.getElementById("togglePublic");
    const btnPrivate = document.getElementById("togglePrivate");
    const rowsPublic = document.querySelectorAll(".row-public");
    const rowsPrivate = document.querySelectorAll(".row-private");

    btnPublic.addEventListener("click", () => {
        btnPublic.classList.add("active");
        btnPrivate.classList.remove("active");
        rowsPublic.forEach(row => row.style.display = "table-row");
        rowsPrivate.forEach(row => row.style.display = "none");
    });

    btnPrivate.addEventListener("click", () => {
        btnPrivate.classList.add("active");
        btnPublic.classList.remove("active");
        rowsPrivate.forEach(row => row.style.display = "table-row");
        rowsPublic.forEach(row => row.style.display = "none");
    });

    btnPublic.click();

    // Developer mode
    const devPinInput = document.getElementById("devPin");
    const tsaUrlSelect = document.getElementById("tsaUrl");
    const enableDevModeButton = document.getElementById("enableDevMode");
    const saveTsaUrlButton = document.getElementById("saveTsaUrl");

    enableDevModeButton.addEventListener("click", () => {
        if (devPinInput.value === "1234") {
            alert("Режим за разработчици активиран.");
            tsaUrlSelect.disabled = false;
            saveTsaUrlButton.disabled = false;
        } else {
            alert("Грешен PIN.");
        }
    });

    document.getElementById("devModeForm").addEventListener("submit", (e) => {
        e.preventDefault();
        const selectedUrl = tsaUrlSelect.value;
        document.cookie = `tsa_url=${encodeURIComponent(selectedUrl)}; path=/; max-age=86400`;
        alert(`TSA адресът е променен на: ${selectedUrl}`);
    });
});
</script>
</body>
</html>
