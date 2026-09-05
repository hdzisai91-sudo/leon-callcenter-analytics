<?php
require "db.php";
session_start();

$userName = $_SESSION["user_name"] ?? "Isai";
$msg = "";
$msgType = "";

// PROCESAR FORMULARIO PARA REGISTRAR NUEVO CASO EN MYSQL
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "new_fraud") {
    $fraud_type = trim($_POST["fraud_type"] ?? "");
    $attack_channel = trim($_POST["attack_channel"] ?? "");
    $amount_affected = floatval($_POST["amount_affected"] ?? 0);
    $amount_recovered = floatval($_POST["amount_recovered"] ?? 0);
    $state_name = trim($_POST["state_name"] ?? "");
    $victim_age = intval($_POST["victim_age"] ?? 35);
    $victim_gender = trim($_POST["victim_gender"] ?? "Masculino");
    $status = trim($_POST["status"] ?? "En Investigación");
    $description = trim($_POST["description"] ?? "");

    if ($fraud_type && $state_name && $amount_affected > 0) {
        $report_code = "#FR-" . date("Y") . "-" . str_pad(rand(100, 999), 3, "0", STR_PAD_LEFT);
        $incident_date = date("Y-m-d H:i:s");

        try {
            $stmt = $pdo->prepare("INSERT INTO fraud_reports 
                (report_code, incident_date, fraud_type, attack_channel, amount_affected, amount_recovered, state_name, victim_age, victim_gender, status, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([$report_code, $incident_date, $fraud_type, $attack_channel, $amount_affected, $amount_recovered, $state_name, $victim_age, $victim_gender, $status, $description]);
            
            $msg = "¡Caso $report_code registrado con éxito en MySQL!";
            $msgType = "success";
        } catch (PDOException $e) {
            $msg = "Error al guardar en MySQL: " . $e->getMessage();
            $msgType = "danger";
        }
    } else {
        $msg = "Por favor completa los campos obligatorios.";
        $msgType = "danger";
    }
}

// OBTENER REPORTES REALES DE MYSQL
try {
    $stmt = $pdo->query("SELECT * FROM fraud_reports ORDER BY incident_date DESC LIMIT 15");
    $recent_reports = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_reports = [];
}

// TOTALES DINÁMICOS
$total_frauds = count($recent_reports) > 0 ? count($recent_reports) : 6;
try {
    $total_amount_query = $pdo->query("SELECT SUM(amount_affected) as total, SUM(amount_recovered) as rec FROM fraud_reports")->fetch();
    $total_amount_affected = $total_amount_query['total'] ?? 3480950;
    $total_amount_recovered = $total_amount_query['rec'] ?? 1106942;
} catch (Exception $e) {
    $total_amount_affected = 3480950;
    $total_amount_recovered = 1106942;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>León SA de CV — Sistema de Inteligencia & Fraudes</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
  
  <!-- Librería para gráficos interactivos -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root {
      --ink: #080c10;
      --surface: #0f151d;
      --card: #151d27;
      --card-hover: #1b2533;
      --border: #232f3e;
      --text: #f0f4f8;
      --text-muted: #8b98a8;
      --gold: #c9a24d;
      --gold-hover: #dfba69;
      --gold-soft: rgba(201, 162, 77, 0.14);
      --gold-border: rgba(201, 162, 77, 0.35);
      --teal: #4fa3a0;
      --teal-soft: rgba(79, 163, 160, 0.15);
      --danger: #e06c75;
      --danger-soft: rgba(224, 108, 117, 0.12);
      --success: #98c379;
      --success-soft: rgba(152, 195, 121, 0.12);
      --blue: #61afef;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      background: var(--ink);
      color: var(--text);
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      min-height: 100vh;
    }

    /* NAVBAR */
    .navbar {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 12px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .nav-brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .nav-logo {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 1.5px solid var(--gold);
      flex-shrink: 0;
    }

    .nav-brand h2 { margin: 0; font-size: 1.1rem; color: #ffffff; }
    .nav-brand span { font-size: 0.78rem; color: var(--text-muted); }

    .nav-actions { display: flex; align-items: center; gap: 16px; }

    .user-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--card);
      border: 1px solid var(--border);
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.82rem;
      color: var(--text);
    }

    .status-dot {
      width: 8px;
      height: 8px;
      background: #98c379;
      border-radius: 50%;
      box-shadow: 0 0 6px #98c379;
    }

    .btn-logout {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--danger);
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 0.82rem;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    .btn-logout:hover { background: var(--danger-soft); border-color: var(--danger); }

    /* CONTENEDOR */
    .dashboard-container {
      max-width: 1360px;
      margin: 0 auto;
      padding: 24px 24px 60px;
    }

    .alert-banner {
      padding: 12px 18px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-size: 0.88rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .alert-banner.success { background: var(--success-soft); border: 1px solid var(--success); color: #a8d488; }
    .alert-banner.danger { background: var(--danger-soft); border: 1px solid var(--danger); color: #ff8582; }

    /* PESTAÑAS */
    .tabs-nav {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 12px;
      flex-wrap: wrap;
    }

    .tab-btn {
      background: var(--surface);
      border: 1px solid var(--border);
      color: var(--text-muted);
      padding: 10px 20px;
      border-radius: 10px;
      font-family: inherit;
      font-weight: 600;
      font-size: 0.88rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .tab-btn:hover { background: var(--card-hover); color: var(--text); }
    .tab-btn.active {
      background: linear-gradient(135deg, rgba(201, 162, 77, 0.2), rgba(223, 186, 105, 0.1));
      border-color: var(--gold);
      color: var(--gold-hover);
      box-shadow: 0 0 12px rgba(201, 162, 77, 0.2);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .dash-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .dash-header h1 { margin: 0 0 4px; font-size: 1.45rem; color: #ffffff; }
    .dash-header p { margin: 0; font-size: 0.86rem; color: var(--text-muted); }

    /* KPI GRID */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 18px;
      margin-bottom: 26px;
    }

    .kpi-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 20px 22px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.35);
      position: relative;
      overflow: hidden;
    }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--border); }
    .kpi-card:hover::before { background: var(--gold); }

    .kpi-title { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .kpi-value { font-size: 1.75rem; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
    .kpi-value.gold { color: var(--gold-hover); }
    .kpi-value.danger { color: var(--danger); }
    .kpi-value.teal { color: var(--teal); }

    .kpi-footer { font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; }
    .kpi-footer.positive { color: var(--success); }
    .kpi-footer.warning { color: var(--gold); }
    .kpi-footer.danger { color: var(--danger); }
    .kpi-sub { font-size: 0.75rem; color: var(--text-muted); display: block; }

    /* GRÁFICAS */
    .charts-grid-2 { display: grid; grid-template-columns: 2fr 1.2fr; gap: 20px; margin-bottom: 24px; }
    .charts-grid-equal-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    .charts-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 26px; }

    .chart-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 22px 20px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
    .chart-header h3 { margin: 0; font-size: 0.98rem; font-weight: 600; color: #ffffff; }

    .badge-tag {
      background: var(--card);
      border: 1px solid var(--border);
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 0.72rem;
      color: var(--gold-hover);
    }
    .badge-tag.critical { border-color: var(--danger); color: var(--danger); background: var(--danger-soft); }

    .chart-container { position: relative; height: 260px; width: 100%; }

    .alert-box-extra {
      background: linear-gradient(135deg, rgba(201, 162, 77, 0.12), rgba(15, 21, 29, 0.8));
      border: 1px solid var(--gold-border);
      border-radius: 14px;
      padding: 20px 24px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
    }
    .alert-box-danger {
      background: linear-gradient(135deg, rgba(224, 108, 117, 0.12), rgba(15, 21, 29, 0.8));
      border: 1px solid rgba(224, 108, 117, 0.35);
      border-radius: 14px;
      padding: 20px 24px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
    }
    .alert-box-extra h4, .alert-box-danger h4 { margin: 0 0 4px; font-size: 1rem; }
    .alert-box-extra h4 { color: var(--gold-hover); }
    .alert-box-danger h4 { color: #ff8582; }
    .alert-box-extra p, .alert-box-danger p { margin: 0; font-size: 0.84rem; color: var(--text); }

    /* TABLA */
    .table-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 24px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.35);
    }

    .table-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 14px;
    }
    .table-header h3 { margin: 0 0 4px; font-size: 1.1rem; color: #ffffff; }
    .table-header p { margin: 0; font-size: 0.8rem; color: var(--text-muted); }

    .btn-primary {
      background: linear-gradient(135deg, #c9a24d, #dfba69);
      color: #0f0d06;
      font-weight: 700;
      border: none;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }

    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; text-align: left; }
    .data-table th {
      background: var(--card);
      color: var(--text-muted);
      font-weight: 600;
      padding: 12px 14px;
      border-bottom: 1px solid var(--border);
      text-transform: uppercase;
      font-size: 0.72rem;
      letter-spacing: 0.04em;
    }
    .data-table td { padding: 14px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); color: var(--text); }
    .data-table tbody tr:hover { background: var(--card-hover); }

    .mono-gold { font-family: 'IBM Plex Mono', monospace; color: var(--gold-hover); font-weight: 600; }
    .amount { font-weight: 600; color: #ffffff; }

    .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .status-badge.alert { background: var(--danger-soft); border: 1px solid var(--danger); color: #ff8582; }
    .status-badge.process { background: var(--gold-soft); border: 1px solid var(--gold); color: var(--gold-hover); }
    .status-badge.success { background: var(--success-soft); border: 1px solid var(--success); color: #a8d488; }

    /* MODAL DE REGISTRO */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      padding: 16px;
    }

    .modal-card {
      background: var(--surface);
      border: 1px solid var(--gold-border);
      border-radius: 16px;
      max-width: 600px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      padding: 26px 28px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 12px;
    }
    .modal-header h2 { margin: 0; font-size: 1.25rem; color: #ffffff; }

    .btn-close-modal { background: transparent; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; }
    .btn-close-modal:hover { color: var(--danger); }

    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 0.78rem; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; }

    .form-input, .form-select, .form-textarea {
      width: 100%;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 12px;
      color: var(--text);
      font-family: inherit;
      font-size: 0.88rem;
      outline: none;
      transition: all 0.2s ease;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--gold); box-shadow: 0 0 0 2px var(--gold-soft); }

    .modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 16px; }
    .btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }

    @media (max-width: 900px) {
      .charts-grid-2, .charts-grid-equal-2, .form-grid-2 { grid-template-columns: 1fr; }
      .navbar { flex-direction: column; gap: 12px; align-items: flex-start; }
    }
  </style>
</head>
<body class="dashboard-body">

  <!-- BARRA DE NAVEGACIÓN SUPERIOR -->
  <header class="navbar">
    <div class="nav-brand">
      <img src="logo.png.jpeg" alt="Logo León" class="nav-logo">
      <div>
        <h2>León SA de CV</h2>
        <span>Sistema de Inteligencia Estadística — Call Center & Fraudes</span>
      </div>
    </div>

    <div class="nav-actions">
      <div class="user-badge">
        <span class="status-dot"></span>
        <span><?= htmlspecialchars($userName) ?> (Analista)</span>
      </div>
      <a href="logout.php" class="btn-logout">Cerrar sesión</a>
    </div>
  </header>

  <!-- CONTENEDOR PRINCIPAL -->
  <main class="dashboard-container">

    <!-- MENSAJE DE CONFIRMACIÓN AL INSERTAR CASO -->
    <?php if ($msg): ?>
      <div class="alert-banner <?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
        <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; font-weight:bold; cursor:pointer;">✕</button>
      </div>
    <?php endif; ?>

    <!-- BARRA DE PESTAÑAS (3 TABS) -->
    <nav class="tabs-nav">
      <button class="tab-btn active" id="btn-tab-general" onclick="switchTab('general')">
        📊 Resumen General
      </button>
      <button class="tab-btn" id="btn-tab-callcenter" onclick="switchTab('callcenter')">
        📞 Análisis Call Center
      </button>
      <button class="tab-btn" id="btn-tab-fraud" onclick="switchTab('fraud')">
        🛡️ Análisis de Fraudes & Víctimas
      </button>
    </nav>

    <!-- ========================================================
         PESTAÑA 1: RESUMEN GENERAL (EJECUTIVO)
         ======================================================== -->
    <div id="tab-general" class="tab-content active">
      
      <div class="dash-header">
        <div>
          <h1>Resumen Ejecutivo de Operaciones</h1>
          <p>Monitoreo general de volumen de llamadas, detección de patrones y reportes de fraude.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Registrar Nuevo Caso</button>
      </div>

      <!-- 4 KPIs GENERALES -->
      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Total Llamadas Atendidas</div>
          <div class="kpi-value">14,820</div>
          <div class="kpi-footer positive">↑ +8.4% vs trimestre anterior</div>
          <span class="kpi-sub">Promedio diario: 494 llamadas</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Reportes en Base de Datos</div>
          <div class="kpi-value gold"><?= number_format($total_frauds) ?></div>
          <div class="kpi-footer warning">Casos registrados activos</div>
          <span class="kpi-sub">Consultas directas a MySQL</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Monto Económico Afectado</div>
          <div class="kpi-value danger">$<?= number_format($total_amount_affected, 2) ?> <small style="font-size:0.55em; color:var(--text-muted)">MXN</small></div>
          <div class="kpi-footer">Promedio: $2,795 / caso</div>
          <span class="kpi-sub">Recuperado: $<?= number_format($total_amount_recovered, 2) ?></span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Horario & Día Mayor Demanda</div>
          <div class="kpi-value" style="font-size:1.35rem; color:var(--gold-hover);">11:00 AM - 1:00 PM</div>
          <div class="kpi-footer positive">Día pico: Martes</div>
          <span class="kpi-sub">Pico nocturno: 7:00 PM</span>
        </div>
      </section>

      <!-- GRÁFICAS FILA 1 -->
      <section class="charts-grid-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Evolución Temporal: Llamadas vs. Reportes de Fraude</h3>
            <span class="badge-tag">Trimestre</span>
          </div>
          <div class="chart-container">
            <canvas id="evolutionChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Distribución por Tipo de Fraude</h3>
            <span class="badge-tag">Categorías</span>
          </div>
          <div class="chart-container">
            <canvas id="fraudTypesChart"></canvas>
          </div>
        </div>
      </section>

      <!-- GRÁFICAS FILA 2 -->
      <section class="charts-grid-3">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Demanda por Horarios</h3>
          </div>
          <div class="chart-container">
            <canvas id="hourlyDemandChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Estados con Mayor Incidencia</h3>
          </div>
          <div class="chart-container">
            <canvas id="geoChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Perfil de Víctimas por Edad</h3>
          </div>
          <div class="chart-container">
            <canvas id="victimProfileChart"></canvas>
          </div>
        </div>
      </section>

      <!-- TABLA DE REPORTES CARGADA DINÁMICAMENTE DESDE MYSQL -->
      <section class="table-card">
        <div class="table-header">
          <div>
            <h3>Últimos Incidentes Registrados en MySQL</h3>
            <p>Monitoreo cronológico en tiempo real de la tabla `fraud_reports`.</p>
          </div>
          <button class="btn-primary" onclick="openModal()">+ Registrar Caso</button>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID Reporte</th>
                <th>Fecha y Hora</th>
                <th>Tipo de Fraude</th>
                <th>Canal</th>
                <th>Estado / Región</th>
                <th>Víctima</th>
                <th>Monto</th>
                <th>Estatus</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_reports as $r): ?>
                <?php
                  $badgeClass = 'process';
                  if ($r['status'] === 'Crítico') $badgeClass = 'alert';
                  if ($r['status'] === 'Resuelto') $badgeClass = 'success';
                ?>
                <tr>
                  <td class="mono-gold"><?= htmlspecialchars($r['report_code']) ?></td>
                  <td><?= date("d/m/Y H:i", strtotime($r['incident_date'])) ?></td>
                  <td><strong><?= htmlspecialchars($r['fraud_type']) ?></strong></td>
                  <td><?= htmlspecialchars($r['attack_channel']) ?></td>
                  <td><?= htmlspecialchars($r['state_name']) ?></td>
                  <td><?= htmlspecialchars($r['victim_age']) ?> años · <?= htmlspecialchars($r['victim_gender']) ?></td>
                  <td class="amount">$<?= number_format($r['amount_affected'], 2) ?> MXN</td>
                  <td><span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

    </div>

    <!-- ========================================================
         PESTAÑA 2: ANÁLISIS DETALLADO DEL CALL CENTER
         ======================================================== -->
    <div id="tab-callcenter" class="tab-content">
      
      <div class="dash-header">
        <div>
          <h1>Análisis Detallado de Operaciones del Call Center</h1>
          <p>Estadísticas de volumen, franjas críticas de atención, distribución horaria y geografía.</p>
        </div>
      </div>

      <!-- ALERTA DE PERÍODOS EXTRAORDINARIOS -->
      <div class="alert-box-extra">
        <div>
          <h4>📌 Detección de Patrones y Períodos Extraordinarios</h4>
          <p>Se identificó un incremento del <strong>+28.5% de llamadas</strong> durante los días 15 y 30 de cada mes (pago de quincena) y los días martes entre 11:00 y 13:00 hrs.</p>
        </div>
        <span class="badge-tag critical">Franja Crítica: 11:00 - 13:30</span>
      </div>

      <!-- 4 KPIs DE VOLUMEN CALL CENTER -->
      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Volumen Total Llamadas</div>
          <div class="kpi-value teal">14,820</div>
          <div class="kpi-footer positive">Promedio mensual: 4,940</div>
          <span class="kpi-sub">Promedio diario: 494 llamadas</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Día con Mayor Volumen</div>
          <div class="kpi-value gold" style="font-size:1.45rem;">Martes 15 Ago</div>
          <div class="kpi-footer positive">742 llamadas recibidas</div>
          <span class="kpi-sub">+50.2% sobre el promedio diario</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Día con Menor Volumen</div>
          <div class="kpi-value" style="font-size:1.45rem; color:var(--text-muted)">Domingo 10 Ago</div>
          <div class="kpi-footer">124 llamadas recibidas</div>
          <span class="kpi-sub">-74.8% por ser fin de semana</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Hora Pico vs. Hora Valle</div>
          <div class="kpi-value" style="font-size:1.28rem; color:var(--gold-hover);">11:00 AM / 03:30 AM</div>
          <div class="kpi-footer warning">Mayor: 1,240 ll / Menor: 18 ll</div>
          <span class="kpi-sub">Ratio de demanda: 68 a 1</span>
        </div>
      </section>

      <!-- SECCIÓN HORARIOS Y FRANJAS -->
      <section class="charts-grid-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Curva de Demanda por Horas (24 Horas)</h3>
            <span class="badge-tag critical">Horas Críticas Marcadas</span>
          </div>
          <div class="chart-container">
            <canvas id="cc24HoursChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Distribución por Franjas del Día</h3>
            <span class="badge-tag">Participación %</span>
          </div>
          <div class="chart-container">
            <canvas id="ccDaySlotsChart"></canvas>
          </div>
        </div>
      </section>

      <!-- SECCIÓN PATRONES Y GEOGRAFÍA -->
      <section class="charts-grid-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Patrón de Volumen por Día de la Semana</h3>
            <span class="badge-tag">Lunes a Domingo</span>
          </div>
          <div class="chart-container">
            <canvas id="ccWeekDaysChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Procedencia Geográfica (% Participación)</h3>
            <span class="badge-tag">Top Estados</span>
          </div>
          <div class="chart-container">
            <canvas id="ccGeoPercentChart"></canvas>
          </div>
        </div>
      </section>

    </div>

    <!-- ========================================================
         PESTAÑA 3: ANÁLISIS DETALLADO DE FRAUDES Y VÍCTIMAS
         ======================================================== -->
    <div id="tab-fraud" class="tab-content">
      
      <div class="dash-header">
        <div>
          <h1>Análisis Forense y Estadístico de Fraudes</h1>
          <p>Desglose de montos económicos afectados, canales de ataque y perfiles de víctimas.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Registrar Caso</button>
      </div>

      <!-- ALERTA DE AMENAZA EMERGENTE -->
      <div class="alert-box-danger">
        <div>
          <h4>⚠️ Alerta de Vector Emergente: Phishing por Falso Ejecutivo</h4>
          <p>Se registra un incremento del <strong>+41% en estafas vía WhatsApp</strong> donde suplantan ejecutivos de cuenta para solicitar códigos OTP y transferencias.</p>
        </div>
        <span class="status-badge alert">Prioridad Alta</span>
      </div>

      <!-- 4 KPIs DE FRAUDE Y VÍCTIMAS -->
      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Monto Total Defraudado</div>
          <div class="kpi-value danger">$<?= number_format($total_amount_affected, 2) ?></div>
          <div class="kpi-footer warning">Promedio: $2,795 / caso</div>
          <span class="kpi-sub">Mayor caso: $145,000 MXN</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Monto Recuperado / Bloqueado</div>
          <div class="kpi-value positive" style="color:var(--success);">$<?= number_format($total_amount_recovered, 2) ?></div>
          <div class="kpi-footer positive">31.8% tasa de recuperación</div>
          <span class="kpi-sub">Bloqueo en los primeros 15 min</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Grupo Etario Más Vulnerable</div>
          <div class="kpi-value gold" style="font-size:1.45rem;">30 - 49 Años</div>
          <div class="kpi-footer">46% del total de víctimas</div>
          <span class="kpi-sub">Seguido por 50-64 años (27%)</span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Canal de Ataque Principal</div>
          <div class="kpi-value" style="font-size:1.35rem; color:var(--blue);">Llamada Celular</div>
          <div class="kpi-footer warning">52% de los casos iniciados</div>
          <span class="kpi-sub">WhatsApp / SMS: 31%</span>
        </div>
      </section>

      <!-- GRÁFICAS DE FRAUDE FILA 1 -->
      <section class="charts-grid-equal-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Monto Defraudado por Tipo de Fraude (MXN)</h3>
            <span class="badge-tag">Pérdida Monetaria</span>
          </div>
          <div class="chart-container">
            <canvas id="fraudAmountByTypeChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Canales de Ataque Utilizados</h3>
            <span class="badge-tag">Vías de Contacto</span>
          </div>
          <div class="chart-container">
            <canvas id="attackChannelsChart"></canvas>
          </div>
        </div>
      </section>

      <!-- GRÁFICAS DE FRAUDE FILA 2 -->
      <section class="charts-grid-equal-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Pérdidas Económicas por Estado (Top 5)</h3>
            <span class="badge-tag">Geografía de Fraude</span>
          </div>
          <div class="chart-container">
            <canvas id="fraudLossesByStateChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Evolución Mensual de Pérdidas vs. Recuperación</h3>
            <span class="badge-tag">Tendencia en $</span>
          </div>
          <div class="chart-container">
            <canvas id="fraudMonthlyLossTrendChart"></canvas>
          </div>
        </div>
      </section>

    </div>

  </main>

  <!-- ========================================================
       MODAL: FORMULARIO PARA REGISTRAR NUEVO CASO DE FRAUDE
       ======================================================== -->
  <div id="fraudModal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
      <div class="modal-header">
        <h2>🛡️ Registrar Nuevo Caso de Fraude</h2>
        <button class="btn-close-modal" onclick="closeModal()">✕</button>
      </div>

      <form method="post">
        <input type="hidden" name="action" value="new_fraud">

        <div class="form-grid-2">
          <div class="form-group">
            <label>Tipo de Fraude *</label>
            <select name="fraud_type" class="form-select" required>
              <option value="Phishing Bancario">Phishing Bancario (Falso Ejecutivo)</option>
              <option value="Clonación de Tarjeta">Clonación de Tarjeta (Cajero/Terminal)</option>
              <option value="Suplantación de Identidad">Suplantación de Identidad (Crédito)</option>
              <option value="Transferencia No Reconocida">Transferencia No Reconocida (SPEI)</option>
              <option value="Extorsión Telefónica">Extorsión Telefónica (Falso Premio)</option>
              <option value="Otro">Otro Tipo</option>
            </select>
          </div>

          <div class="form-group">
            <label>Canal de Ataque *</label>
            <select name="attack_channel" class="form-select" required>
              <option value="Llamada Celular">Llamada Celular</option>
              <option value="WhatsApp / SMS">WhatsApp / SMS</option>
              <option value="Correo Falso">Correo Falso / Phishing</option>
              <option value="Sitio Web Falso">Sitio Web Falso</option>
              <option value="Cajero Automático">Cajero Automático</option>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label>Monto Afectado ($ MXN) *</label>
            <input type="number" step="0.01" name="amount_affected" class="form-input" placeholder="Ej. 25000" required>
          </div>

          <div class="form-group">
            <label>Monto Recuperado / Bloqueado ($ MXN)</label>
            <input type="number" step="0.01" name="amount_recovered" class="form-input" placeholder="Ej. 5000" value="0">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label>Estado / Región *</label>
            <select name="state_name" class="form-select" required>
              <option value="Ciudad de México">Ciudad de México</option>
              <option value="Estado de México">Estado de México</option>
              <option value="Jalisco">Jalisco</option>
              <option value="Nuevo León">Nuevo León</option>
              <option value="Puebla">Puebla</option>
              <option value="Guanajuato">Guanajuato</option>
              <option value="Veracruz">Veracruz</option>
              <option value="Querétaro">Querétaro</option>
            </select>
          </div>

          <div class="form-group">
            <label>Estatus del Caso *</label>
            <select name="status" class="form-select" required>
              <option value="En Investigación">En Investigación</option>
              <option value="Crítico">Crítico</option>
              <option value="Resuelto">Resuelto</option>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label>Edad de la Víctima</label>
            <input type="number" name="victim_age" class="form-input" placeholder="Ej. 45" value="35">
          </div>

          <div class="form-group">
            <label>Género de la Víctima</label>
            <select name="victim_gender" class="form-select">
              <option value="Masculino">Masculino</option>
              <option value="Femenino">Femenino</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Descripción / Modus Operandi</label>
          <textarea name="description" class="form-textarea" rows="3" placeholder="Detalle de cómo contactaron a la víctima..."></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn-primary">Guardar Reporte en MySQL</button>
        </div>
      </form>
    </div>
  </div>

  <!-- SCRIPTS PARA INTERACTIVIDAD Y GRÁFICOS -->
  <script>
    Chart.defaults.color = '#8b98a8';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';
    Chart.defaults.font.family = "'Inter', sans-serif";

    // FUNCIONES DEL MODAL
    function openModal() {
      document.getElementById('fraudModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('fraudModal').style.display = 'none';
    }

    // FUNCIÓN PARA CAMBIAR ENTRE LAS 3 PESTAÑAS
    function switchTab(tabName) {
      document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

      if (tabName === 'general') {
        document.getElementById('btn-tab-general').classList.add('active');
        document.getElementById('tab-general').classList.add('active');
      } else if (tabName === 'callcenter') {
        document.getElementById('btn-tab-callcenter').classList.add('active');
        document.getElementById('tab-callcenter').classList.add('active');
      } else if (tabName === 'fraud') {
        document.getElementById('btn-tab-fraud').classList.add('active');
        document.getElementById('tab-fraud').classList.add('active');
      }
    }

    // ==========================================
    // GRÁFICOS PESTAÑA 1 (RESUMEN GENERAL)
    // ==========================================
    new Chart(document.getElementById('evolutionChart'), {
      type: 'line',
      data: {
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5', 'Sem 6', 'Sem 7', 'Sem 8', 'Sem 9', 'Sem 10', 'Sem 11', 'Sem 12'],
        datasets: [
          {
            label: 'Llamadas Totales',
            data: [980, 1120, 1250, 1190, 1340, 1420, 1380, 1500, 1480, 1560, 1610, 1690],
            borderColor: '#4fa3a0',
            backgroundColor: 'rgba(79, 163, 160, 0.1)',
            fill: true,
            tension: 0.35,
            yAxisID: 'y'
          },
          {
            label: 'Reportes Fraude',
            data: [75, 88, 95, 90, 110, 125, 115, 130, 128, 140, 145, 152],
            borderColor: '#c9a24d',
            backgroundColor: 'rgba(201, 162, 77, 0.15)',
            fill: true,
            tension: 0.35,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
          y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Llamadas' } },
          y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Fraudes' } }
        }
      }
    });

    new Chart(document.getElementById('fraudTypesChart'), {
      type: 'doughnut',
      data: {
        labels: ['Phishing (38%)', 'Clonación (26%)', 'Suplantación (18%)', 'Transf. no rec. (12%)', 'Extorsión (6%)'],
        datasets: [{
          data: [38, 26, 18, 12, 6],
          backgroundColor: ['#c9a24d', '#dfba69', '#4fa3a0', '#e06c75', '#61afef'],
          borderColor: '#0f151d',
          borderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } }
      }
    });

    new Chart(document.getElementById('hourlyDemandChart'), {
      type: 'bar',
      data: {
        labels: ['8am', '10am', '12pm', '2pm', '4pm', '6pm', '8pm', '10pm'],
        datasets: [{
          label: 'Llamadas',
          data: [320, 780, 1240, 910, 850, 1120, 640, 210],
          backgroundColor: 'rgba(201, 162, 77, 0.75)',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
      }
    });

    new Chart(document.getElementById('geoChart'), {
      type: 'bar',
      data: {
        labels: ['CDMX', 'Edo. Mex', 'Jalisco', 'Nuevo León', 'Puebla'],
        datasets: [{
          label: 'Casos',
          data: [420, 310, 245, 180, 90],
          backgroundColor: ['#dfba69', '#c9a24d', '#b08a38', '#8a6c2a', '#69511d'],
          borderRadius: 6,
          indexAxis: 'y'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
      }
    });

    new Chart(document.getElementById('victimProfileChart'), {
      type: 'pie',
      data: {
        labels: ['18-29 años', '30-49 años', '50-64 años', '65+ años'],
        datasets: [{
          data: [15, 46, 27, 12],
          backgroundColor: ['#4fa3a0', '#c9a24d', '#e06c75', '#98c379'],
          borderColor: '#0f151d',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } }
      }
    });

    // ==========================================
    // GRÁFICOS PESTAÑA 2 (ANÁLISIS CALL CENTER)
    // ==========================================
    new Chart(document.getElementById('cc24HoursChart'), {
      type: 'line',
      data: {
        labels: ['00h', '02h', '04h', '06h', '08h', '10h', '11h (Pico)', '12h', '14h', '16h', '18h', '19h (Pico)', '20h', '22h'],
        datasets: [{
          label: 'Volumen por Hora',
          data: [42, 25, 18, 95, 320, 890, 1240, 1180, 780, 840, 960, 1120, 710, 280],
          borderColor: '#c9a24d',
          backgroundColor: 'rgba(201, 162, 77, 0.18)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: ['#c9a24d','#c9a24d','#c9a24d','#c9a24d','#c9a24d','#c9a24d','#e06c75','#c9a24d','#c9a24d','#c9a24d','#c9a24d','#e06c75','#c9a24d','#c9a24d'],
          pointRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
      }
    });

    new Chart(document.getElementById('ccDaySlotsChart'), {
      type: 'doughnut',
      data: {
        labels: ['🌅 Mañana (6am-12pm): 42%', '☀️ Tarde (12pm-6pm): 38%', '🌙 Noche (6pm-12am): 16%', '🌌 Madrugada (12am-6am): 4%'],
        datasets: [{
          data: [42, 38, 16, 4],
          backgroundColor: ['#c9a24d', '#dfba69', '#4fa3a0', '#61afef'],
          borderColor: '#0f151d',
          borderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } }
      }
    });

    new Chart(document.getElementById('ccWeekDaysChart'), {
      type: 'bar',
      data: {
        labels: ['Lunes', 'Martes (Pico)', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'],
        datasets: [{
          label: 'Llamadas Promedio',
          data: [610, 742, 590, 560, 520, 290, 124],
          backgroundColor: [
            'rgba(79, 163, 160, 0.8)',
            'rgba(201, 162, 77, 0.95)',
            'rgba(79, 163, 160, 0.8)',
            'rgba(79, 163, 160, 0.8)',
            'rgba(79, 163, 160, 0.8)',
            'rgba(139, 152, 168, 0.5)',
            'rgba(139, 152, 168, 0.4)'
          ],
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
      }
    });

    new Chart(document.getElementById('ccGeoPercentChart'), {
      type: 'bar',
      data: {
        labels: ['CDMX (32.5%)', 'Edo. Mex (24.1%)', 'Jalisco (18.2%)', 'Nuevo León (14.0%)', 'Puebla (6.8%)', 'Otros (4.4%)'],
        datasets: [{
          label: '% de Participación',
          data: [32.5, 24.1, 18.2, 14.0, 6.8, 4.4],
          backgroundColor: '#dfba69',
          borderRadius: 6,
          indexAxis: 'y'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { ticks: { callback: value => value + '%' } } }
      }
    });

    // ==========================================
    // GRÁFICOS PESTAÑA 3 (ANÁLISIS DE FRAUDE)
    // ==========================================
    new Chart(document.getElementById('fraudAmountByTypeChart'), {
      type: 'bar',
      data: {
        labels: ['Phishing', 'Suplantación', 'Clonación', 'Extorsión', 'Transferencias'],
        datasets: [{
          label: 'Monto ($ MXN)',
          data: [1420000, 980000, 560000, 320000, 200950],
          backgroundColor: ['#e06c75', '#c9a24d', '#dfba69', '#4fa3a0', '#61afef'],
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: val => '$' + (val/1000) + 'k' } } }
      }
    });

    new Chart(document.getElementById('attackChannelsChart'), {
      type: 'doughnut',
      data: {
        labels: ['📱 Llamada Telefónica (52%)', '💬 WhatsApp / SMS (31%)', '✉️ Correo Falso (17%)'],
        datasets: [{
          data: [52, 31, 17],
          backgroundColor: ['#61afef', '#98c379', '#c9a24d'],
          borderColor: '#0f151d',
          borderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } }
      }
    });

    new Chart(document.getElementById('fraudLossesByStateChart'), {
      type: 'bar',
      data: {
        labels: ['CDMX', 'Edo. Mex', 'Jalisco', 'Nuevo León', 'Puebla'],
        datasets: [{
          label: 'Pérdida Económica ($ MXN)',
          data: [1250000, 890000, 610000, 480000, 250950],
          backgroundColor: '#e06c75',
          borderRadius: 6,
          indexAxis: 'y'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { ticks: { callback: val => '$' + (val/1000) + 'k' } } }
      }
    });

    new Chart(document.getElementById('fraudMonthlyLossTrendChart'), {
      type: 'line',
      data: {
        labels: ['Mes 1 (Junio)', 'Mes 2 (Julio)', 'Mes 3 (Agosto)'],
        datasets: [
          {
            label: 'Monto Afectado',
            data: [980000, 1180000, 1320950],
            borderColor: '#e06c75',
            backgroundColor: 'rgba(224, 108, 117, 0.15)',
            fill: true,
            tension: 0.3
          },
          {
            label: 'Monto Recuperado / Bloqueado',
            data: [280000, 390000, 436942],
            borderColor: '#98c379',
            backgroundColor: 'rgba(152, 195, 121, 0.15)',
            fill: true,
            tension: 0.3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { ticks: { callback: val => '$' + (val/1000) + 'k' } } }
      }
    });
  </script>
</body>
</html>