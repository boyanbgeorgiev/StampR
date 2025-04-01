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

    <?php if ($serial): ?>
      <div class="message success" style="margin-top: 20px;">
        ✅ Сертификат с номер <strong><?= htmlspecialchars($serial) ?></strong> е валиден.<br>
        📄 <a href="generate_certificate.php?serial=<?= urlencode($serial) ?>" target="_blank">Изтегли сертификат</a>
      </div>
    <?php endif; ?>

    <p style="text-align: center; margin-top: 20px;">
      <a href="index.html">⬅ Обратно към началната страница</a>
    </p>
  </div>
</body>
</html>
