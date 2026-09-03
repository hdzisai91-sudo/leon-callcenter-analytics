<?php
require "db.php";
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Ingresa tu correo y contraseña.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Credenciales incorrectas.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>León SA de CV — Iniciar sesión</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="center-wrap">
    <div class="login-card">
      <div class="brand">
        <img src="logo.png" alt="Logo León SA de CV" class="brand-logo">
        <div>
          <h1>León SA de CV</h1>
          <p>Iniciar sesión</p>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="field">
          <label for="email">Correo</label>
          <div class="input-wrap">
            <input id="email" name="email" type="email" placeholder="correo@leon.com" required>
          </div>
        </div>

        <div class="field">
          <label for="password">Contraseña</label>
          <div class="input-wrap">
            <input id="password" name="password" type="password" placeholder="Tu contraseña" required>
          </div>
        </div>

        <button type="submit" class="submit-btn">Iniciar sesión</button>
      </form>

      <p class="footnote">
        <a href="register.php" class="link">¿No tienes cuenta? Regístrate</a>
      </p>
    </div>
  </div>
</body>
</html>