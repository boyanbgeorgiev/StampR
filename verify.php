<?php
$serial = $_GET['serial'] ?? null;
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8" />
  <title>Проверка на сертификат</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="auth-container">
    <h2>Провери сертификат</h2>
    
    <form method="GET" action="verify.php">
      <input type="text" name="serial" placeholder="Въведи сериен номер" value="<?= htmlspecialchars($serial) ?>" required />
      <button type="submit">Провери</button>
    </form>

    <?php
require 'db.php';

$serial = $_GET['serial'] ?? null;
$valid = false;

if ($serial) {
    $stmt = $conn->prepare("SELECT file_name FROM timestamps WHERE serial_number = ?");
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result->num_rows > 0;
}
?>

        <?php if ($serial): ?>
          <?php if ($valid): ?>
            <div class="message success" style="margin-top: 20px;">
              ✅ Сертификат с номер <strong><?= htmlspecialchars($serial) ?></strong> е валиден.<br>
            </div>
          <?php else: ?>
            <div class="message error" style="margin-top: 20px;">
              ❌ Сертификат с номер <strong><?= htmlspecialchars($serial) ?></strong> не е намерен.
            </div>
          <?php endif; ?>
        <?php endif; ?>


    <p style="text-align: center; margin-top: 20px;">
      <a href="index.html">⬅ Обратно към началната страница</a>
    </p>
  </div>
</body>
</html>
