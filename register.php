<?php
require "db.php";
session_start();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || strlen($password) < 6) {
        $error = "Completa nombre, correo y una contraseña de al menos 6 caracteres.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Ya existe una cuenta con ese correo.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hash]);
            $success = "Cuenta creada con éxito. Ya puedes iniciar sesión.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>León SA de CV — Crear cuenta</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="center-wrap">
    <div class="login-card">
      
      <!-- LOGO CENTRADO Y GRANDE -->
      <div class="brand brand-centered">
       <img src="logo.png.jpeg" alt="Logo León SA de CV" class="brand-logo-large">
        <div class="brand-text-centered">
          <h1>León SA de CV</h1>
          <p>Crear cuenta</p>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="success-box"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="field">
          <label for="name">Nombre</label>
          <div class="input-wrap">
            <input id="name" name="name" type="text" placeholder="Tu nombre" required>
          </div>
        </div>

        <div class="field">
          <label for="email">Correo</label>
          <div class="input-wrap">
            <input id="email" name="email" type="email" placeholder="correo@leon.com" required>
          </div>
        </div>

        <div class="field">
          <label for="password">Contraseña</label>
          <div class="input-wrap">
            <input id="password" name="password" type="password" placeholder="Mínimo 6 caracteres" required>
          </div>
        </div>

        <button type="submit" class="submit-btn">Crear cuenta</button>
      </form>

      <p class="footnote">
        <a href="login.php" class="link">¿Ya tienes cuenta? Inicia sesión</a>
      </p>
    </div>
  </div>
</body>
</html>