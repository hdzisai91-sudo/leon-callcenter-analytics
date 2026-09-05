<?php
require "db.php";
session_start();

// Si ya inició sesión previamente, enviarlo directo al dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm_password = trim($_POST["confirm_password"] ?? "");

    if ($name && $email && $password) {
        if ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden.";
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } else {
            try {
                // Verificar si el correo ya existe
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error = "El correo ya se encuentra registrado. Inicia sesión directamente.";
                } else {
                    // Cifrar contraseña y guardar en MySQL
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'Analista')");
                    $stmt->execute([$name, $email, $hash]);

                    // INICIAR SESIÓN AUTOMÁTICAMENTE
                    $_SESSION["user_id"] = $pdo->lastInsertId();
                    $_SESSION["user_name"] = $name;
                    $_SESSION["user_role"] = "Analista";

                    // REDIRIGIR DIRECTO AL DASHBOARD
                    header("Location: dashboard.php");
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Error en base de datos: " . $e->getMessage();
            }
        }
    } else {
        $error = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Crear Cuenta — León SA de CV</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --ink: #080c10;
      --surface: #0f151d;
      --card: #151d27;
      --border: #232f3e;
      --text: #f0f4f8;
      --text-muted: #8b98a8;
      --gold: #c9a24d;
      --gold-hover: #dfba69;
      --gold-soft: rgba(201, 162, 77, 0.14);
      --gold-border: rgba(201, 162, 77, 0.35);
      --danger: #e06c75;
      --danger-soft: rgba(224, 108, 117, 0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--ink);
      color: var(--text);
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background-image: radial-gradient(circle at top center, rgba(201, 162, 77, 0.08) 0%, transparent 60%);
    }

    .auth-card {
      background: var(--surface);
      border: 1px solid var(--gold-border);
      border-radius: 18px;
      padding: 34px 32px;
      max-width: 440px;
      width: 100%;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
    }

    .auth-logo-center {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 20px;
      text-align: center;
    }

    .auth-logo-img {
      width: 84px;
      height: 84px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--gold);
      box-shadow: 0 0 20px rgba(201, 162, 77, 0.35);
      margin-bottom: 10px;
    }

    .auth-company-name {
      font-size: 1.25rem;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 3px;
    }

    .auth-company-sub {
      font-size: 0.78rem;
      color: var(--text-muted);
    }

    .form-title {
      font-size: 1.05rem;
      font-weight: 600;
      color: var(--gold-hover);
      text-align: center;
      margin-bottom: 18px;
    }

    .alert-danger {
      background: var(--danger-soft);
      border: 1px solid var(--danger);
      color: #ff8582;
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 0.84rem;
      margin-bottom: 16px;
      font-weight: 500;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--text-muted);
      margin-bottom: 6px;
    }

    .form-control {
      width: 100%;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 11px 13px;
      color: var(--text);
      font-size: 0.9rem;
      font-family: inherit;
      outline: none;
      transition: all 0.2s ease;
    }

    .form-control:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px var(--gold-soft);
    }

    .btn-submit {
      width: 100%;
      background: linear-gradient(135deg, #c9a24d, #dfba69);
      color: #0f0d06;
      border: none;
      padding: 13px;
      border-radius: 10px;
      font-size: 0.92rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      transition: all 0.2s ease;
      margin-top: 8px;
    }

    .btn-submit:hover {
      opacity: 0.94;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(201, 162, 77, 0.3);
    }

    .auth-footer {
      margin-top: 20px;
      text-align: center;
      font-size: 0.84rem;
      color: var(--text-muted);
    }

    .auth-footer a {
      color: var(--gold-hover);
      text-decoration: none;
      font-weight: 600;
    }

    .auth-footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

  <div class="auth-card">
    <div class="auth-logo-center">
      <img src="logo.png.jpeg" alt="Logo León SA de CV" class="auth-logo-img">
      <h1 class="auth-company-name">León SA de CV</h1>
      <span class="auth-company-sub">Sistema de Inteligencia & Control de Fraudes</span>
    </div>

    <h2 class="form-title">Crear Cuenta de Analista</h2>

    <?php if ($error): ?>
      <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label for="name">Nombre Completo</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="Isai Hernández" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="email">Correo Corporativo</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="ejemplo@leonsa.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirmar Contraseña</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repite tu contraseña" required>
      </div>

      <button type="submit" class="btn-submit">Registrar Cuenta y Entrar</button>
    </form>

    <div class="auth-footer">
      ¿Ya tienes cuenta? <a href="login.php">Iniciar Sesión</a>
    </div>
  </div>

</body>
</html>