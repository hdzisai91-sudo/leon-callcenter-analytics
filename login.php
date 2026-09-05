<?php
require "db.php";
session_start();

// Si ya tiene sesión activa, va directo al dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error_login = "";
$error_register = "";
$success_register = "";
$active_tab = "login";

// PROCESAR FORMULARIO
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // 1. INICIAR SESIÓN
    if ($action === "login") {
        $active_tab = "login";
        $email = trim($_POST["email"] ?? "");
        $password = trim($_POST["password"] ?? "");

        if ($email && $password) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user["password_hash"])) {
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_role"] = $user["role"] ?? "Analista";
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error_login = "Correo o contraseña incorrectos.";
                }
            } catch (PDOException $e) {
                $error_login = "Error en base de datos: " . $e->getMessage();
            }
        } else {
            $error_login = "Por favor ingresa tu correo y contraseña.";
        }
    }

    // 2. REGISTRARSE
    if ($action === "register") {
        $active_tab = "register";
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["reg_email"] ?? "");
        $password = trim($_POST["reg_password"] ?? "");
        $confirm = trim($_POST["confirm_password"] ?? "");

        if ($name && $email && $password) {
            if ($password !== $confirm) {
                $error_register = "Las contraseñas no coinciden.";
            } elseif (strlen($password) < 6) {
                $error_register = "La contraseña debe tener al menos 6 caracteres.";
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    
                    if ($stmt->fetch()) {
                        $error_register = "Este correo ya está registrado. Por favor inicia sesión.";
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'Analista')");
                        $stmt->execute([$name, $email, $hash]);

                        $success_register = "Cuenta creada con éxito. Inicia sesión con tus credenciales.";
                        $active_tab = "login";
                    }
                } catch (PDOException $e) {
                    $error_register = "Error en base de datos: " . $e->getMessage();
                }
            }
        } else {
            $error_register = "Por favor completa todos los campos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso — León SA de CV</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg-main: #0a0e14;
      --bg-card: #0f1722;
      
      /* RECUADROS CLAROS */
      --bg-input: #ffffff;
      --border-input: #cbd5e1;
      --border-focus: #c9a24d;
      --text-input: #0f172a;
      --text-placeholder: #94a3b8;
      
      --border-card: rgba(201, 162, 77, 0.35);
      --text-main: #ffffff;
      --text-label: #cbd5e1;
      
      --gold: #c9a24d;
      --gold-hover: #dfba69;
      --gold-soft: rgba(201, 162, 77, 0.25);
      
      --danger: #ef4444;
      --danger-bg: rgba(239, 68, 68, 0.12);
      --success: #22c55e;
      --success-bg: rgba(34, 197, 94, 0.12);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg-main);
      color: var(--text-main);
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background-image: radial-gradient(circle at 50% 20%, rgba(201, 162, 77, 0.08) 0%, transparent 65%);
    }

    .auth-card {
      background: var(--bg-card);
      border: 1px solid var(--border-card);
      border-radius: 16px;
      padding: 36px 32px;
      max-width: 440px;
      width: 100%;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.75);
    }

    .auth-logo-center {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 22px;
      text-align: center;
    }

    .auth-logo-img {
      width: 82px;
      height: 82px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--gold);
      box-shadow: 0 0 18px rgba(201, 162, 77, 0.35);
      margin-bottom: 12px;
    }

    .auth-company-name {
      font-size: 1.25rem;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 3px;
      letter-spacing: 0.02em;
    }

    .auth-company-sub {
      font-size: 0.78rem;
      color: var(--text-label);
    }

    /* PESTAÑAS (TABS) */
    .auth-tabs {
      display: flex;
      background: #090e15;
      border: 1px solid #1e293b;
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 22px;
    }

    .auth-tab-btn {
      flex: 1;
      background: transparent;
      border: none;
      color: var(--text-label);
      font-family: inherit;
      font-weight: 600;
      font-size: 0.85rem;
      padding: 10px;
      border-radius: 7px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .auth-tab-btn:hover {
      color: #ffffff;
    }

    .auth-tab-btn.active {
      background: linear-gradient(135deg, rgba(201, 162, 77, 0.25), rgba(223, 186, 105, 0.12));
      border: 1px solid var(--gold);
      color: var(--gold-hover);
      box-shadow: 0 2px 8px rgba(0,0,0,0.4);
    }

    .tab-pane { display: none; }
    .tab-pane.active { display: block; }

    .alert {
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 0.84rem;
      margin-bottom: 18px;
      font-weight: 500;
    }
    .alert-danger { background: var(--danger-bg); border: 1px solid var(--danger); color: #fca5a5; }
    .alert-success { background: var(--success-bg); border: 1px solid var(--success); color: #86efac; }

    .form-group { margin-bottom: 16px; }
    
    .form-group label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-label);
      margin-bottom: 7px;
      letter-spacing: 0.01em;
    }

    /* ESTILO DE LOS RECUADROS CLAROS */
    .form-control {
      width: 100%;
      background: var(--bg-input);
      border: 1.5px solid var(--border-input);
      border-radius: 9px;
      padding: 12px 14px;
      color: var(--text-input);
      font-size: 0.92rem;
      font-weight: 500;
      font-family: inherit;
      outline: none;
      transition: all 0.2s ease;
    }

    .form-control::placeholder {
      color: var(--text-placeholder);
      font-weight: 400;
    }

    .form-control:focus {
      border-color: var(--border-focus);
      background: #ffffff;
      box-shadow: 0 0 0 3px var(--gold-soft);
    }

    .btn-submit {
      width: 100%;
      background: linear-gradient(135deg, #c9a24d, #dfba69);
      color: #0c0a04;
      border: none;
      padding: 13px;
      border-radius: 9px;
      font-size: 0.92rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      transition: all 0.2s ease;
      margin-top: 6px;
    }

    .btn-submit:hover {
      opacity: 0.94;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(201, 162, 77, 0.35);
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

    <!-- PESTAÑAS: INICIAR SESIÓN / REGISTRARSE -->
    <div class="auth-tabs">
      <button class="auth-tab-btn <?= $active_tab === 'login' ? 'active' : '' ?>" id="tabBtnLogin" onclick="switchAuthTab('login')">
        Iniciar Sesión
      </button>
      <button class="auth-tab-btn <?= $active_tab === 'register' ? 'active' : '' ?>" id="tabBtnRegister" onclick="switchAuthTab('register')">
        Registrarse
      </button>
    </div>

    <!-- ==========================================
         PANEL 1: INICIAR SESIÓN
         ========================================== -->
    <div id="paneLogin" class="tab-pane <?= $active_tab === 'login' ? 'active' : '' ?>">
      
      <?php if ($success_register): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_register) ?></div>
      <?php endif; ?>

      <?php if ($error_login): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_login) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="login">

        <div class="form-group">
          <label for="email">Correo Corporativo</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="usuario@leonsa.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Ingresa tu contraseña" required>
        </div>

        <button type="submit" class="btn-submit">Iniciar Sesión</button>
      </form>
    </div>

    <!-- ==========================================
         PANEL 2: REGISTRARSE
         ========================================== -->
    <div id="paneRegister" class="tab-pane <?= $active_tab === 'register' ? 'active' : '' ?>">
      
      <?php if ($error_register): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_register) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="register">

        <div class="form-group">
          <label for="reg_name">Nombre Completo</label>
          <input type="text" id="reg_name" name="name" class="form-control" placeholder="Nombre y Apellido" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="reg_email">Correo Corporativo</label>
          <input type="email" id="reg_email" name="reg_email" class="form-control" placeholder="usuario@leonsa.com" required value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="reg_password">Contraseña</label>
          <input type="password" id="reg_password" name="reg_password" class="form-control" placeholder="Mínimo 6 caracteres" required>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirmar Contraseña</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repite la contraseña" required>
        </div>

        <button type="submit" class="btn-submit">Crear Cuenta</button>
      </form>
    </div>

  </div>

  <script>
    function switchAuthTab(tab) {
      document.getElementById('tabBtnLogin').classList.toggle('active', tab === 'login');
      document.getElementById('tabBtnRegister').classList.toggle('active', tab === 'register');
      document.getElementById('paneLogin').classList.toggle('active', tab === 'login');
      document.getElementById('paneRegister').classList.toggle('active', tab === 'register');
    }
  </script>

</body>
</html>