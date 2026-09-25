<?php
require "db.php";
session_start();

$userName = $_SESSION["user_name"] ?? "Isai";
$msg = "";
$msgType = "";

// Si venimos de una redirección post-guardado, recuperar el mensaje guardado en sesión
if (isset($_SESSION["flash_msg"])) {
    $msg = $_SESSION["flash_msg"];
    $msgType = $_SESSION["flash_type"] ?? "success";
    unset($_SESSION["flash_msg"], $_SESSION["flash_type"]);
}

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
            
            // Guardar mensaje en sesión y redirigir (Post-Redirect-Get):
            // esto evita que un F5 o "volver atrás" reenvíe el formulario y duplique el caso.
            $_SESSION["flash_msg"] = "Caso $report_code registrado con éxito en MySQL.";
            $_SESSION["flash_type"] = "success";
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
        } catch (PDOException $e) {
            $msg = "Error al guardar en MySQL: " . $e->getMessage();
            $msgType = "danger";
        }
    } else {
        $msg = "Por favor completa los campos obligatorios.";
        $msgType = "danger";
    }
}

// PROCESAR FORMULARIO PARA REGISTRAR NUEVA LLAMADA EN MYSQL
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "new_call") {
    $call_date = trim($_POST["call_date"] ?? "");
    $call_time = trim($_POST["call_time"] ?? "");
    $duration_seconds = intval($_POST["duration_seconds"] ?? 180);
    $state_name_call = trim($_POST["state_name_call"] ?? "");
    $contact_reason = trim($_POST["contact_reason"] ?? "");
    $day_slot = trim($_POST["day_slot"] ?? "");
    $is_fraud_report = isset($_POST["is_fraud_report"]) ? 1 : 0;

    if ($call_date && $call_time && $state_name_call && $contact_reason && $day_slot) {
        try {
            $stmt = $pdo->prepare("INSERT INTO call_records 
                (call_date, call_time, duration_seconds, state_name, contact_reason, day_slot, is_fraud_report) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([$call_date, $call_time, $duration_seconds, $state_name_call, $contact_reason, $day_slot, $is_fraud_report]);

            $_SESSION["flash_msg"] = "Llamada registrada con éxito en MySQL.";
            $_SESSION["flash_type"] = "success";
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit;
        } catch (PDOException $e) {
            $msg = "Error al guardar la llamada en MySQL: " . $e->getMessage();
            $msgType = "danger";
        }
    } else {
        $msg = "Por favor completa los campos obligatorios de la llamada.";
        $msgType = "danger";
    }
}

/* =========================================================================
   MAPEO DE CÓDIGO DE ÁREA (EE.UU./Canadá) A ESTADO
   Cobertura amplia de códigos comunes; lo que no se reconozca cae en
   "Estado No Identificado" para no perder el registro.
   ========================================================================= */
function areaCodeToState($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '1') $digits = substr($digits, 1);
    if (strlen($digits) < 10) return null;
    $area = substr($digits, 0, 3);

    $map = [
        // California
        '209'=>'California (CA)','213'=>'California (CA)','310'=>'California (CA)','323'=>'California (CA)',
        '408'=>'California (CA)','415'=>'California (CA)','510'=>'California (CA)','530'=>'California (CA)',
        '559'=>'California (CA)','562'=>'California (CA)','619'=>'California (CA)','626'=>'California (CA)',
        '650'=>'California (CA)','661'=>'California (CA)','707'=>'California (CA)','714'=>'California (CA)',
        '760'=>'California (CA)','805'=>'California (CA)','818'=>'California (CA)','831'=>'California (CA)',
        '858'=>'California (CA)','909'=>'California (CA)','916'=>'California (CA)','925'=>'California (CA)',
        '949'=>'California (CA)','951'=>'California (CA)',
        // Texas
        '210'=>'Texas (TX)','214'=>'Texas (TX)','254'=>'Texas (TX)','281'=>'Texas (TX)','325'=>'Texas (TX)',
        '346'=>'Texas (TX)','361'=>'Texas (TX)','409'=>'Texas (TX)','430'=>'Texas (TX)','432'=>'Texas (TX)',
        '469'=>'Texas (TX)','512'=>'Texas (TX)','682'=>'Texas (TX)','713'=>'Texas (TX)','726'=>'Texas (TX)',
        '806'=>'Texas (TX)','817'=>'Texas (TX)','830'=>'Texas (TX)','832'=>'Texas (TX)','903'=>'Texas (TX)',
        '915'=>'Texas (TX)','936'=>'Texas (TX)','940'=>'Texas (TX)','956'=>'Texas (TX)','972'=>'Texas (TX)',
        '979'=>'Texas (TX)',
        // Florida
        '239'=>'Florida (FL)','305'=>'Florida (FL)','321'=>'Florida (FL)','352'=>'Florida (FL)',
        '386'=>'Florida (FL)','407'=>'Florida (FL)','561'=>'Florida (FL)','727'=>'Florida (FL)',
        '754'=>'Florida (FL)','772'=>'Florida (FL)','786'=>'Florida (FL)','813'=>'Florida (FL)',
        '850'=>'Florida (FL)','863'=>'Florida (FL)','904'=>'Florida (FL)','941'=>'Florida (FL)',
        '954'=>'Florida (FL)',
        // New York
        '212'=>'New York (NY)','315'=>'New York (NY)','347'=>'New York (NY)','516'=>'New York (NY)',
        '518'=>'New York (NY)','585'=>'New York (NY)','607'=>'New York (NY)','631'=>'New York (NY)',
        '646'=>'New York (NY)','680'=>'New York (NY)','716'=>'New York (NY)','718'=>'New York (NY)',
        '845'=>'New York (NY)','914'=>'New York (NY)','917'=>'New York (NY)','929'=>'New York (NY)',
        // Illinois
        '217'=>'Illinois (IL)','224'=>'Illinois (IL)','309'=>'Illinois (IL)','312'=>'Illinois (IL)',
        '331'=>'Illinois (IL)','618'=>'Illinois (IL)','630'=>'Illinois (IL)','708'=>'Illinois (IL)',
        '773'=>'Illinois (IL)','779'=>'Illinois (IL)','815'=>'Illinois (IL)','847'=>'Illinois (IL)',
        '872'=>'Illinois (IL)',
        // Pennsylvania
        '215'=>'Pennsylvania (PA)','223'=>'Pennsylvania (PA)','267'=>'Pennsylvania (PA)','272'=>'Pennsylvania (PA)',
        '412'=>'Pennsylvania (PA)','445'=>'Pennsylvania (PA)','484'=>'Pennsylvania (PA)','570'=>'Pennsylvania (PA)',
        '610'=>'Pennsylvania (PA)','717'=>'Pennsylvania (PA)','724'=>'Pennsylvania (PA)','814'=>'Pennsylvania (PA)',
        '878'=>'Pennsylvania (PA)',
        // Georgia
        '229'=>'Georgia (GA)','404'=>'Georgia (GA)','470'=>'Georgia (GA)','478'=>'Georgia (GA)',
        '678'=>'Georgia (GA)','706'=>'Georgia (GA)','762'=>'Georgia (GA)','770'=>'Georgia (GA)',
        '912'=>'Georgia (GA)',
        // Ohio
        '216'=>'Ohio (OH)','220'=>'Ohio (OH)','234'=>'Ohio (OH)','330'=>'Ohio (OH)','380'=>'Ohio (OH)',
        '419'=>'Ohio (OH)','440'=>'Ohio (OH)','513'=>'Ohio (OH)','567'=>'Ohio (OH)','614'=>'Ohio (OH)',
        '740'=>'Ohio (OH)','937'=>'Ohio (OH)',
        // Illinois/Minnesota
        '218'=>'Minnesota (MN)','320'=>'Minnesota (MN)','507'=>'Minnesota (MN)','612'=>'Minnesota (MN)',
        '651'=>'Minnesota (MN)','763'=>'Minnesota (MN)','952'=>'Minnesota (MN)',
        // Tennessee
        '423'=>'Tennessee (TN)','615'=>'Tennessee (TN)','629'=>'Tennessee (TN)','731'=>'Tennessee (TN)',
        '865'=>'Tennessee (TN)','901'=>'Tennessee (TN)','931'=>'Tennessee (TN)',
        // Arizona
        '480'=>'Arizona (AZ)','520'=>'Arizona (AZ)','602'=>'Arizona (AZ)','623'=>'Arizona (AZ)','928'=>'Arizona (AZ)',
        // North Carolina
        '252'=>'North Carolina (NC)','336'=>'North Carolina (NC)','704'=>'North Carolina (NC)',
        '743'=>'North Carolina (NC)','828'=>'North Carolina (NC)','910'=>'North Carolina (NC)',
        '919'=>'North Carolina (NC)','980'=>'North Carolina (NC)','984'=>'North Carolina (NC)',
        // Michigan
        '231'=>'Michigan (MI)','248'=>'Michigan (MI)','269'=>'Michigan (MI)','313'=>'Michigan (MI)',
        '517'=>'Michigan (MI)','586'=>'Michigan (MI)','616'=>'Michigan (MI)','734'=>'Michigan (MI)',
        '810'=>'Michigan (MI)','906'=>'Michigan (MI)','947'=>'Michigan (MI)','989'=>'Michigan (MI)',
        // Arizona/Nevada/Washington/others (muestra representativa)
        '702'=>'Nevada (NV)','725'=>'Nevada (NV)','775'=>'Nevada (NV)',
        '206'=>'Washington (WA)','253'=>'Washington (WA)','360'=>'Washington (WA)','425'=>'Washington (WA)',
        '509'=>'Washington (WA)',
        '303'=>'Colorado (CO)','719'=>'Colorado (CO)','720'=>'Colorado (CO)','970'=>'Colorado (CO)',
        '602'=>'Arizona (AZ)',
        '617'=>'Massachusetts (MA)','339'=>'Massachusetts (MA)','508'=>'Massachusetts (MA)',
        '774'=>'Massachusetts (MA)','781'=>'Massachusetts (MA)','857'=>'Massachusetts (MA)','978'=>'Massachusetts (MA)',
        '804'=>'Virginia (VA)','703'=>'Virginia (VA)','757'=>'Virginia (VA)','540'=>'Virginia (VA)','571'=>'Virginia (VA)',
        '202'=>'Washington DC',
        '602'=>'Arizona (AZ)',
    ];

    return $map[$area] ?? 'Estado No Identificado';
}

function parseDurationToSeconds($dur) {
    $parts = array_map('intval', explode(':', trim($dur)));
    if (count($parts) === 3) return $parts[0]*3600 + $parts[1]*60 + $parts[2];
    if (count($parts) === 2) return $parts[0]*60 + $parts[1];
    return (int)$dur;
}

function dayPartFromHour($hour) {
    $hour = (int)$hour;
    if ($hour >= 0 && $hour < 6) return 'Madrugada (0-6h)';
    if ($hour >= 6 && $hour < 12) return 'Mañana (6-12h)';
    if ($hour >= 12 && $hour < 18) return 'Tarde (12-18h)';
    return 'Noche (18-24h)';
}

// Calcula cuántos meses de historia real hay entre la fecha más antigua y la más reciente,
// con un tope máximo (por defecto 6), para no rellenar la gráfica con meses vacíos.
function monthsOfHistory($earliestDateStr, $referenceTs, $maxMonths = 6) {
    if (!$earliestDateStr) return 1;
    $earliestTs = strtotime($earliestDateStr);
    $diff = ((int)date('Y', $referenceTs) - (int)date('Y', $earliestTs)) * 12
        + ((int)date('n', $referenceTs) - (int)date('n', $earliestTs)) + 1;
    return max(1, min($maxMonths, $diff));
}

// PROCESAR IMPORTACIÓN MASIVA DE LLAMADAS DESDE CSV
$import_summary = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "import_calls_csv") {
    $csv_ext_ok = isset($_FILES['csv_file']) && strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) === 'csv';

    if (!$csv_ext_ok) {
        $_SESSION["flash_msg"] = "Solo se permiten archivos con extensión .csv";
        $_SESSION["flash_type"] = "danger";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $inserted = 0;
        $skipped = 0;
        $unidentified_states = [];

        if ($handle) {
            while (($row = fgetcsv($handle)) !== false) {
                // Columnas esperadas: [0]Tipo [1]Teléfono [2]Nombre [3]Fecha [4]Hora [5]TipoLlamada [6]Estatus [7]Descripción [8]Duración
                if (count($row) < 9) { $skipped++; continue; }

                $phone = trim($row[1]);
                $date_raw = trim($row[3]);
                $time_raw = trim($row[4]);
                $status_raw = trim($row[6]);
                $duration_raw = trim($row[8]);

                // Saltar filas con número inválido (#ERROR!, vacío, etc.)
                $digits_check = preg_replace('/\D/', '', $phone);
                if (strlen($digits_check) < 10) { $skipped++; continue; }

                // Fecha: quitar prefijo de día de la semana ("Sat 01/31/2026" -> "01/31/2026")
                $date_clean = preg_replace('/^[A-Za-z]{3}\s+/', '', $date_raw);
                $date_ts = strtotime($date_clean);
                if (!$date_ts) { $skipped++; continue; }
                $call_date_val = date('Y-m-d', $date_ts);

                // Hora: "10:23 PM" -> "22:23:00"
                $time_ts = strtotime($time_raw);
                if (!$time_ts) { $skipped++; continue; }
                $call_time_val = date('H:i:s', $time_ts);
                $hour_val = (int)date('H', $time_ts);

                $duration_val = parseDurationToSeconds($duration_raw);
                $state_val = areaCodeToState($phone);
                if ($state_val === 'Estado No Identificado') {
                    $unidentified_states[$phone] = true;
                }

                $status_map = [
                    'Accepted' => 'Llamada Contestada',
                    'Call connected' => 'Llamada Contestada',
                    'Missed' => 'Llamada Perdida',
                    'Hang Up' => 'Llamada Colgada por el Cliente',
                ];
                $reason_val = $status_map[$status_raw] ?? 'Otros';

                $day_slot_val = dayPartFromHour($hour_val);

                try {
                    $stmt = $pdo->prepare("INSERT INTO call_records 
                        (call_date, call_time, duration_seconds, state_name, contact_reason, day_slot, is_fraud_report) 
                        VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$call_date_val, $call_time_val, $duration_val, $state_val, $reason_val, $day_slot_val]);
                    $inserted++;
                } catch (PDOException $e) {
                    $skipped++;
                }
            }
            fclose($handle);
        }

        $extra = count($unidentified_states) > 0 ? " ($" . count($unidentified_states) . " números con código de área no reconocido, marcados como 'Estado No Identificado')" : "";
        $_SESSION["flash_msg"] = "Importación completada: $inserted llamadas agregadas, $skipped filas omitidas.$extra";
        $_SESSION["flash_type"] = "success";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    } else {
        $msg = "No se pudo leer el archivo CSV. Verifica que el archivo se haya subido correctamente.";
        $msgType = "danger";
    }
}

/* =========================================================================
   CONSULTAS A MYSQL — TODOS LOS DATOS DEL DASHBOARD SE CALCULAN AQUÍ
   ========================================================================= */

// --- Reportes recientes (tabla) ---
try {
    $stmt = $pdo->query("SELECT * FROM fraud_reports ORDER BY incident_date DESC LIMIT 15");
    $recent_reports = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_reports = [];
}

// --- Totales generales ---
try {
    $total_frauds = (int)($pdo->query("SELECT COUNT(*) as c FROM fraud_reports")->fetch()['c'] ?? 0);
} catch (Exception $e) { $total_frauds = 0; }

try {
    $amt = $pdo->query("SELECT SUM(amount_affected) as total, SUM(amount_recovered) as rec FROM fraud_reports")->fetch();
    $total_amount_affected = (float)($amt['total'] ?? 0);
    $total_amount_recovered = (float)($amt['rec'] ?? 0);
} catch (Exception $e) {
    $total_amount_affected = 0; $total_amount_recovered = 0;
}

try {
    $total_calls = (int)($pdo->query("SELECT COUNT(*) as c FROM call_records")->fetch()['c'] ?? 0);
} catch (Exception $e) { $total_calls = 0; }

// --- Promedio diario / mensual de llamadas ---
try {
    $distinct_days = (int)($pdo->query("SELECT COUNT(DISTINCT call_date) as c FROM call_records")->fetch()['c'] ?? 0);
} catch (Exception $e) { $distinct_days = 0; }
$avg_daily_calls = $distinct_days > 0 ? round($total_calls / $distinct_days) : 0;

try {
    $distinct_months = (int)($pdo->query("SELECT COUNT(DISTINCT DATE_FORMAT(call_date, '%Y-%m')) as c FROM call_records")->fetch()['c'] ?? 0);
} catch (Exception $e) { $distinct_months = 0; }
$avg_monthly_calls = $distinct_months > 0 ? round($total_calls / $distinct_months) : 0;

// --- Crecimiento: últimos 90 días vs los 90 anteriores (con datos reales) ---
try {
    $latest_call_for_growth = $pdo->query("SELECT MAX(call_date) as m FROM call_records")->fetch()['m'];
    $growth_reference_date = $latest_call_for_growth ? strtotime($latest_call_for_growth) : time();
    $growth_ref_str = date('Y-m-d', $growth_reference_date);
    $growth_90_start = date('Y-m-d', strtotime("-90 days", $growth_reference_date));
    $growth_180_start = date('Y-m-d', strtotime("-180 days", $growth_reference_date));
    $stmt1 = $pdo->prepare("SELECT COUNT(*) as c FROM call_records WHERE call_date >= ? AND call_date <= ?");
    $stmt1->execute([$growth_90_start, $growth_ref_str]);
    $recent90 = (int)($stmt1->fetch()['c'] ?? 0);
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as c FROM call_records WHERE call_date >= ? AND call_date < ?");
    $stmt2->execute([$growth_180_start, $growth_90_start]);
    $prev90 = (int)($stmt2->fetch()['c'] ?? 0);
    $growth_pct = $prev90 > 0 ? round((($recent90 - $prev90) / $prev90) * 100, 1) : null;
} catch (Exception $e) { $growth_pct = null; }

// --- Hora pico / hora valle ---
try {
    $hours = $pdo->query("SELECT HOUR(call_time) as hr, COUNT(*) as c FROM call_records GROUP BY HOUR(call_time) ORDER BY c DESC")->fetchAll();
} catch (Exception $e) { $hours = []; }
$peak_hour = $hours[0] ?? null;
$valley_hour = !empty($hours) ? end($hours) : null;
$hour_ratio = ($peak_hour && $valley_hour && $valley_hour['c'] > 0) ? round($peak_hour['c'] / $valley_hour['c'], 1) : null;

function formatHour12($h) {
    $h = (int)$h;
    $suffix = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 == 0) $h12 = 12;
    return "$h12:00 $suffix";
}

// --- Día pico y día de menor volumen (día de la semana) ---
$dow_map = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'];
try {
    $peak_day_row = $pdo->query("SELECT DAYNAME(call_date) as dow, COUNT(*) as c FROM call_records GROUP BY DAYNAME(call_date) ORDER BY c DESC LIMIT 1")->fetch();
    $peak_day = $peak_day_row ? ($dow_map[$peak_day_row['dow']] ?? $peak_day_row['dow']) : 'N/D';
} catch (Exception $e) { $peak_day = 'N/D'; }

try {
    $lowest_day_row = $pdo->query("SELECT DAYNAME(call_date) as dow, COUNT(*) as c FROM call_records GROUP BY DAYNAME(call_date) ORDER BY c ASC LIMIT 1")->fetch();
    $lowest_day = $lowest_day_row ? ($dow_map[$lowest_day_row['dow']] ?? $lowest_day_row['dow']) : 'N/D';
} catch (Exception $e) { $lowest_day = 'N/D'; }

// --- Llamadas por día de la semana (Lunes a Domingo, para la gráfica semanal) ---
$weekday_order_en = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
try {
    $weekday_rows = $pdo->query("SELECT DAYNAME(call_date) as dow, COUNT(*) as c FROM call_records GROUP BY DAYNAME(call_date)")->fetchAll();
} catch (Exception $e) { $weekday_rows = []; }
$weekday_lookup = [];
foreach ($weekday_rows as $row) { $weekday_lookup[$row['dow']] = (int)$row['c']; }
$weekday_labels = array_map(fn($d) => $dow_map[$d], $weekday_order_en);
$weekday_values = array_map(fn($d) => $weekday_lookup[$d] ?? 0, $weekday_order_en);
$weekday_max = !empty($weekday_values) ? max($weekday_values) : 0;
$weekday_min_nonzero = !empty(array_filter($weekday_values, fn($v) => $v > 0)) ? min(array_filter($weekday_values, fn($v) => $v > 0)) : 0;

// --- Llamadas por estado de distribución ---
try {
    $calls_by_state = $pdo->query("SELECT state_name, COUNT(*) as c FROM call_records GROUP BY state_name ORDER BY c DESC LIMIT 15")->fetchAll();
} catch (Exception $e) { $calls_by_state = []; }
$calls_by_state_total = array_sum(array_map(fn($r) => (int)$r['c'], $calls_by_state));

// --- Evolución mensual de llamadas (hasta 6 meses con datos reales, sin relleno vacío) ---
try {
    $call_date_range = $pdo->query("SELECT MIN(call_date) as mn, MAX(call_date) as mx FROM call_records")->fetch();
    $earliest_call_date = $call_date_range['mn'] ?? null;
    $latest_call_date = $call_date_range['mx'] ?? null;
} catch (Exception $e) { $earliest_call_date = null; $latest_call_date = null; }
$evo_reference_date = $latest_call_date ? strtotime($latest_call_date) : time();
$months_of_history = monthsOfHistory($earliest_call_date, $evo_reference_date);

$calls_evo_labels = [];
$calls_evo_keys = [];
for ($i = $months_of_history - 1; $i >= 0; $i--) {
    $calls_evo_labels[] = ucfirst(date('M Y', strtotime("-$i months", $evo_reference_date)));
    $calls_evo_keys[] = date('Y-m', strtotime("-$i months", $evo_reference_date));
}
try {
    $evo_window_start = date('Y-m-01', strtotime("-" . ($months_of_history - 1) . " months", $evo_reference_date));
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(call_date, '%Y-%m') as ym, COUNT(*) as c FROM call_records WHERE call_date >= ? GROUP BY ym");
    $stmt->execute([$evo_window_start]);
    $calls_monthly_rows = $stmt->fetchAll();
} catch (Exception $e) { $calls_monthly_rows = []; }
$calls_monthly_lookup = [];
foreach ($calls_monthly_rows as $r) { $calls_monthly_lookup[$r['ym']] = (int)$r['c']; }
$calls_evo_values = [];
foreach ($calls_evo_keys as $ym) { $calls_evo_values[] = $calls_monthly_lookup[$ym] ?? 0; }

// --- Evolución semanal (últimas 12 semanas con datos reales): llamadas vs reportes de fraude ---
try {
    $latest_call_for_week = $pdo->query("SELECT MAX(call_date) as m FROM call_records")->fetch()['m'];
} catch (Exception $e) { $latest_call_for_week = null; }
try {
    $latest_fraud_for_week = $pdo->query("SELECT MAX(incident_date) as m FROM fraud_reports")->fetch()['m'];
} catch (Exception $e) { $latest_fraud_for_week = null; }
$week_reference_candidates = array_filter([$latest_call_for_week, $latest_fraud_for_week]);
$week_reference_date = !empty($week_reference_candidates) ? strtotime(max($week_reference_candidates)) : time();

$evolution_labels = [];
$evolution_calls = [];
$evolution_frauds = [];
for ($i = 11; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-$i weeks", strtotime('monday this week', $week_reference_date)));
    $week_end = date('Y-m-d', strtotime("$week_start +6 days"));
    $evolution_labels[] = date('d/m', strtotime($week_start));
    try {
        $c = $pdo->prepare("SELECT COUNT(*) as c FROM call_records WHERE call_date BETWEEN ? AND ?");
        $c->execute([$week_start, $week_end]);
        $evolution_calls[] = (int)($c->fetch()['c'] ?? 0);
    } catch (Exception $e) { $evolution_calls[] = 0; }
    try {
        $f = $pdo->prepare("SELECT COUNT(*) as c FROM fraud_reports WHERE incident_date BETWEEN ? AND ?");
        $f->execute([$week_start . ' 00:00:00', $week_end . ' 23:59:59']);
        $evolution_frauds[] = (int)($f->fetch()['c'] ?? 0);
    } catch (Exception $e) { $evolution_frauds[] = 0; }
}

// --- Distribución por tipo de fraude ---
try {
    $fraud_types = $pdo->query("SELECT fraud_type, COUNT(*) as c FROM fraud_reports GROUP BY fraud_type ORDER BY c DESC")->fetchAll();
} catch (Exception $e) { $fraud_types = []; }
$fraud_type_labels = array_map(fn($r) => $r['fraud_type'], $fraud_types);
$fraud_type_values = array_map(fn($r) => (int)$r['c'], $fraud_types);

/* ============================================================
   ANÁLISIS DE MODALIDADES DE FRAUDE (pestaña dedicada)
   ============================================================ */
try {
    $type_stats = $pdo->query("SELECT fraud_type, COUNT(*) as c, SUM(amount_affected) as total_amt, AVG(amount_affected) as avg_amt, AVG(victim_age) as avg_age FROM fraud_reports GROUP BY fraud_type")->fetchAll();
} catch (Exception $e) { $type_stats = []; }

$types_by_count = $type_stats;
usort($types_by_count, fn($a, $b) => $b['c'] <=> $a['c']);
$types_by_loss = $type_stats;
usort($types_by_loss, fn($a, $b) => $b['total_amt'] <=> $a['total_amt']);

$most_frequent_type = $types_by_count[0] ?? null;
$highest_loss_type = $types_by_loss[0] ?? null;
$modalidades_total = array_sum(array_map(fn($t) => (int)$t['c'], $type_stats));

// Género predominante por tipo
try {
    $type_gender_rows = $pdo->query("SELECT fraud_type, victim_gender, COUNT(*) as c FROM fraud_reports GROUP BY fraud_type, victim_gender")->fetchAll();
} catch (Exception $e) { $type_gender_rows = []; }
$gender_by_type = [];
foreach ($type_gender_rows as $row) {
    $t = $row['fraud_type'];
    if (!isset($gender_by_type[$t]) || $row['c'] > $gender_by_type[$t]['c']) {
        $gender_by_type[$t] = ['gender' => $row['victim_gender'], 'c' => (int)$row['c']];
    }
}

// Estado predominante por tipo
try {
    $type_state_rows = $pdo->query("SELECT fraud_type, state_name, COUNT(*) as c FROM fraud_reports GROUP BY fraud_type, state_name")->fetchAll();
} catch (Exception $e) { $type_state_rows = []; }
$state_by_type = [];
foreach ($type_state_rows as $row) {
    $t = $row['fraud_type'];
    if (!isset($state_by_type[$t]) || $row['c'] > $state_by_type[$t]['c']) {
        $state_by_type[$t] = ['state' => $row['state_name'], 'c' => (int)$row['c']];
    }
}

// Evolución mensual por tipo (hasta 6 meses con datos reales, sin relleno vacío; top 5 tipos por volumen)
$top5_types = array_slice(array_map(fn($t) => $t['fraud_type'], $types_by_count), 0, 5);
try {
    $fraud_date_range_type = $pdo->query("SELECT MIN(incident_date) as mn, MAX(incident_date) as mx FROM fraud_reports")->fetch();
    $earliest_fraud_for_type = $fraud_date_range_type['mn'] ?? null;
    $latest_fraud_for_type = $fraud_date_range_type['mx'] ?? null;
} catch (Exception $e) { $earliest_fraud_for_type = null; $latest_fraud_for_type = null; }
$type_evo_reference_date = $latest_fraud_for_type ? strtotime($latest_fraud_for_type) : time();
$type_months_of_history = monthsOfHistory($earliest_fraud_for_type, $type_evo_reference_date);

$evo_type_labels = [];
$evo_type_keys = [];
for ($i = $type_months_of_history - 1; $i >= 0; $i--) {
    $evo_type_labels[] = ucfirst(date('M Y', strtotime("-$i months", $type_evo_reference_date)));
    $evo_type_keys[] = date('Y-m', strtotime("-$i months", $type_evo_reference_date));
}
try {
    $type_window_start = date('Y-m-01', strtotime("-" . ($type_months_of_history - 1) . " months", $type_evo_reference_date)) . ' 00:00:00';
    $stmt = $pdo->prepare("SELECT fraud_type, DATE_FORMAT(incident_date, '%Y-%m') as ym, COUNT(*) as c FROM fraud_reports WHERE incident_date >= ? GROUP BY fraud_type, ym");
    $stmt->execute([$type_window_start]);
    $monthly_type_rows = $stmt->fetchAll();
} catch (Exception $e) { $monthly_type_rows = []; }
$monthly_type_lookup = [];
foreach ($monthly_type_rows as $row) {
    $monthly_type_lookup[$row['fraud_type']][$row['ym']] = (int)$row['c'];
}
$evo_type_datasets = [];
foreach ($top5_types as $t) {
    $data = [];
    foreach ($evo_type_keys as $ym) {
        $data[] = $monthly_type_lookup[$t][$ym] ?? 0;
    }
    $evo_type_datasets[] = ['label' => $t, 'data' => $data];
}

// --- Call Center: motivos de contacto ---
try {
    $reasons = $pdo->query("SELECT contact_reason, COUNT(*) as c, AVG(duration_seconds) as avg_dur FROM call_records GROUP BY contact_reason ORDER BY c DESC")->fetchAll();
} catch (Exception $e) { $reasons = []; }
$reason_labels = array_map(fn($r) => $r['contact_reason'], $reasons);
$reason_counts = array_map(fn($r) => (int)$r['c'], $reasons);

$reasons_by_duration = $reasons;
usort($reasons_by_duration, fn($a, $b) => $b['avg_dur'] <=> $a['avg_dur']);
$duration_labels = array_map(fn($r) => $r['contact_reason'], $reasons_by_duration);
$duration_values = array_map(fn($r) => round($r['avg_dur'] / 60, 1), $reasons_by_duration);

// Orden fijo solicitado para las gráficas de barras "Distribución por Motivo" y "TMO"
// (el orden del donut de arriba y sus KPIs no se toca, siguen por volumen/duración)
function reorderRowsByLabel($rows, $labelKey, $customOrder) {
    $ordered = [];
    foreach ($customOrder as $wanted) {
        foreach ($rows as $row) {
            if ($row[$labelKey] === $wanted) { $ordered[] = $row; break; }
        }
    }
    foreach ($rows as $row) {
        if (!in_array($row[$labelKey], $customOrder)) { $ordered[] = $row; }
    }
    return $ordered;
}
$custom_reason_order = ['Llamada Contestada', 'Llamada Perdida', 'Llamada Colgada por el Cliente', 'Otros'];
$reasons_bar_ordered = reorderRowsByLabel($reasons, 'contact_reason', $custom_reason_order);
$reason_labels_bar = array_map(fn($r) => $r['contact_reason'], $reasons_bar_ordered);
$reason_counts_bar = array_map(fn($r) => (int)$r['c'], $reasons_bar_ordered);

$duration_rows_ordered = reorderRowsByLabel($reasons_by_duration, 'contact_reason', $custom_reason_order);
$duration_labels_ordered = array_map(fn($r) => $r['contact_reason'], $duration_rows_ordered);
$duration_values_ordered = array_map(fn($r) => round($r['avg_dur'] / 60, 1), $duration_rows_ordered);

$top_reason = $reasons[0] ?? null;
$top_reason_pct = ($top_reason && $total_calls > 0) ? round(($top_reason['c'] / $total_calls) * 100, 1) : 0;
$longest_reason = $reasons_by_duration[0] ?? null;
$reason_pcts = array_map(fn($r) => $total_calls > 0 ? round(($r['c'] / $total_calls) * 100, 1) : 0, $reasons);

// Misma paleta que donutPalette en el JS, para pintar la leyenda HTML de "Distribución Porcentual por Motivo"
$donut_palette_php = ['#c9a24d', '#4fa3a0', '#e06c75', '#61afef', '#98c379', '#c678dd', '#f39c12', '#9b59b6', '#95a5a6', '#dfba69'];


// Texto dinámico del banner de motivos (top 3)
$top3_text = "Sin datos suficientes de llamadas todavía.";
if (count($reasons) >= 1) {
    $parts = [];
    $sum_pct = 0;
    foreach (array_slice($reasons, 0, 3) as $r) {
        $pct = $total_calls > 0 ? round(($r['c'] / $total_calls) * 100, 1) : 0;
        $sum_pct += $pct;
        $parts[] = "<strong>" . htmlspecialchars($r['contact_reason']) . " ($pct%)</strong>";
    }
    $top3_text = "El <strong>" . round($sum_pct, 1) . "% del volumen total</strong> se concentra en " . count($parts) . " motivos: " . implode(", ", $parts) . ".";
    if ($longest_reason) {
        $top3_text .= " <strong>" . htmlspecialchars($longest_reason['contact_reason']) . "</strong> representa el mayor tiempo de operación.";
    }
}

// --- Forense / Demografía: estadísticas descriptivas ---
try {
    $amounts_raw = $pdo->query("SELECT amount_affected FROM fraud_reports ORDER BY amount_affected ASC")->fetchAll();
    $amounts = array_map(fn($r) => (float)$r['amount_affected'], $amounts_raw);
} catch (Exception $e) { $amounts = []; }

$stat_count = count($amounts);
$stat_total = array_sum($amounts);
$stat_avg = $stat_count > 0 ? $stat_total / $stat_count : 0;
$stat_min = $stat_count > 0 ? min($amounts) : 0;
$stat_max = $stat_count > 0 ? max($amounts) : 0;
if ($stat_count > 0) {
    $mid = intdiv($stat_count, 2);
    $stat_median = ($stat_count % 2 === 0) ? ($amounts[$mid - 1] + $amounts[$mid]) / 2 : $amounts[$mid];
} else {
    $stat_median = 0;
}

// --- 7 rangos de pérdida ---
$range_defs = [
    ['label' => '$0 - $500', 'min' => 0, 'max' => 500],
    ['label' => '$501 - $1,000', 'min' => 501, 'max' => 1000],
    ['label' => '$1,001 - $5,000', 'min' => 1001, 'max' => 5000],
    ['label' => '$5,001 - $10,000', 'min' => 5001, 'max' => 10000],
    ['label' => '$10,001 - $25,000', 'min' => 10001, 'max' => 25000],
    ['label' => '$25,001 - $50,000', 'min' => 25001, 'max' => 50000],
    ['label' => 'Más de $50,000', 'min' => 50001, 'max' => PHP_INT_MAX],
];
$range_counts = array_fill(0, 7, 0);
foreach ($amounts as $a) {
    foreach ($range_defs as $i => $rd) {
        if ($a >= $rd['min'] && $a <= $rd['max']) { $range_counts[$i]++; break; }
    }
}
$range_labels = array_map(fn($r) => $r['label'], $range_defs);
$range_pcts = array_map(fn($c) => $stat_count > 0 ? round(($c / $stat_count) * 100, 1) : 0, $range_counts);

// --- Demografía de víctimas ---
try {
    $victims = $pdo->query("SELECT victim_age, victim_gender, amount_affected, fraud_type FROM fraud_reports")->fetchAll();
} catch (Exception $e) { $victims = []; }

$age_range_defs = [
    'Menores de 25' => [0, 24],
    '25-34' => [25, 34],
    '35-44' => [35, 44],
    '45-54' => [45, 54],
    '55-64' => [55, 64],
    '65-74' => [65, 74],
    '75+' => [75, PHP_INT_MAX],
];
$age_buckets = [];
foreach ($age_range_defs as $label => $range) {
    $age_buckets[$label] = ['count' => 0, 'sum' => 0, 'types' => [], 'genders' => []];
}
$gender_counts = [];
$gender_stats = [];
foreach ($victims as $v) {
    $age = (int)$v['victim_age'];
    $amt = (float)$v['amount_affected'];
    $ftype = trim($v['fraud_type']) ?: 'Sin especificar';

    $bucket = '75+';
    foreach ($age_range_defs as $label => $range) {
        if ($age >= $range[0] && $age <= $range[1]) { $bucket = $label; break; }
    }
    $age_buckets[$bucket]['count']++;
    $age_buckets[$bucket]['sum'] += $amt;
    $age_buckets[$bucket]['types'][$ftype] = ($age_buckets[$bucket]['types'][$ftype] ?? 0) + 1;

    $g = trim($v['victim_gender']) ?: 'No especificado';
    $gender_counts[$g] = ($gender_counts[$g] ?? 0) + 1;
    $age_buckets[$bucket]['genders'][$g] = ($age_buckets[$bucket]['genders'][$g] ?? 0) + 1;

    if (!isset($gender_stats[$g])) {
        $gender_stats[$g] = ['count' => 0, 'sum' => 0, 'types' => []];
    }
    $gender_stats[$g]['count']++;
    $gender_stats[$g]['sum'] += $amt;
    $gender_stats[$g]['types'][$ftype] = ($gender_stats[$g]['types'][$ftype] ?? 0) + 1;
}
$total_victims = count($victims);
$age_labels = array_keys($age_buckets);
$age_pcts = array_map(fn($b) => $total_victims > 0 ? round(($b['count'] / $total_victims) * 100, 1) : 0, $age_buckets);
$age_avg_loss = array_map(fn($b) => $b['count'] > 0 ? round($b['sum'] / $b['count']) : 0, $age_buckets);
$age_predominant_type = array_map(function($b) {
    if (empty($b['types'])) return 'N/D';
    arsort($b['types']);
    return array_key_first($b['types']);
}, $age_buckets);

$gender_labels = array_keys($gender_counts);
$gender_values = array_values($gender_counts);
$gender_pcts = array_map(fn($v) => $total_victims > 0 ? round(($v / $total_victims) * 100, 1) : 0, $gender_values);

// Matriz Edad × Género (para el resumen de Fraude)
$matrix_genders = array_keys($gender_stats);

$gender_predominant_type = [];
foreach ($gender_stats as $g => $s) {
    if (empty($s['types'])) { $gender_predominant_type[$g] = 'N/D'; continue; }
    $types_copy = $s['types'];
    arsort($types_copy);
    $gender_predominant_type[$g] = array_key_first($types_copy);
}

// --- Evolución mensual por género (hasta 6 meses con datos reales, sin relleno vacío) ---
try {
    $fraud_date_range_gender = $pdo->query("SELECT MIN(incident_date) as mn, MAX(incident_date) as mx FROM fraud_reports")->fetch();
    $earliest_fraud_for_gender = $fraud_date_range_gender['mn'] ?? null;
    $latest_fraud_for_gender = $fraud_date_range_gender['mx'] ?? null;
} catch (Exception $e) { $earliest_fraud_for_gender = null; $latest_fraud_for_gender = null; }
$gender_evo_reference_date = $latest_fraud_for_gender ? strtotime($latest_fraud_for_gender) : time();
$gender_months_of_history = monthsOfHistory($earliest_fraud_for_gender, $gender_evo_reference_date);

$gender_evo_labels = [];
$gender_evo_keys = [];
for ($i = $gender_months_of_history - 1; $i >= 0; $i--) {
    $gender_evo_labels[] = ucfirst(date('M Y', strtotime("-$i months", $gender_evo_reference_date)));
    $gender_evo_keys[] = date('Y-m', strtotime("-$i months", $gender_evo_reference_date));
}
try {
    $gender_window_start = date('Y-m-01', strtotime("-" . ($gender_months_of_history - 1) . " months", $gender_evo_reference_date)) . ' 00:00:00';
    $stmt = $pdo->prepare("SELECT victim_gender, DATE_FORMAT(incident_date, '%Y-%m') as ym, COUNT(*) as c FROM fraud_reports WHERE incident_date >= ? GROUP BY victim_gender, ym");
    $stmt->execute([$gender_window_start]);
    $gender_monthly_rows = $stmt->fetchAll();
} catch (Exception $e) { $gender_monthly_rows = []; }
$gender_monthly_lookup = [];
foreach ($gender_monthly_rows as $row) {
    $g = trim($row['victim_gender']) ?: 'No especificado';
    $gender_monthly_lookup[$g][$row['ym']] = (int)$row['c'];
}
$gender_evo_datasets = [];
foreach ($matrix_genders as $g) {
    $data = [];
    foreach ($gender_evo_keys as $ym) {
        $data[] = $gender_monthly_lookup[$g][$ym] ?? 0;
    }
    $gender_evo_datasets[] = ['label' => $g, 'data' => $data];
}



// --- Análisis geográfico (genérico: usa lo que exista realmente en state_name) ---
try {
    $geo = $pdo->query("SELECT state_name, COUNT(*) as c, SUM(amount_affected) as total_amt, AVG(amount_affected) as avg_amt FROM fraud_reports GROUP BY state_name ORDER BY c DESC")->fetchAll();
} catch (Exception $e) { $geo = []; }

$geo_total_cases = array_sum(array_map(fn($r) => (int)$r['c'], $geo));
$geo_total_amount = array_sum(array_map(fn($r) => (float)$r['total_amt'], $geo));

$geo_by_amount = $geo;
usort($geo_by_amount, fn($a, $b) => $b['total_amt'] <=> $a['total_amt']);
$geo_by_avg = $geo;
usort($geo_by_avg, fn($a, $b) => $b['avg_amt'] <=> $a['avg_amt']);

$top_state_cases = $geo[0] ?? null;
$top_state_amount = $geo_by_amount[0] ?? null;
$top_state_avg = $geo_by_avg[0] ?? null;
$distinct_states_count = count($geo);

/* ============================================================
   MATRIZ DE FRAUDE: cruces estadísticos consolidados
   ============================================================ */
$present_types = array_map(fn($t) => $t['fraud_type'], $types_by_count);

// Matriz Estado × Tipo de Fraude (reutiliza $type_state_rows ya calculado arriba)
$state_type_matrix = [];
foreach ($type_state_rows as $row) {
    $state_type_matrix[$row['state_name']][$row['fraud_type']] = (int)$row['c'];
}
$matrix_states = array_keys($state_type_matrix);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="darkreader-lock">
  <title>León SA de CV — Sistema de Inteligencia & Fraudes</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
  
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

  <style>
    :root {
      --ink: #080c10; --surface: #0f151d; --card: #151d27; --card-hover: #1b2533;
      --border: #232f3e; --text: #f0f4f8; --text-muted: #8b98a8;
      --gold: #c9a24d; --gold-hover: #dfba69; --gold-soft: rgba(201, 162, 77, 0.14); --gold-border: rgba(201, 162, 77, 0.35);
      --teal: #4fa3a0; --teal-soft: rgba(79, 163, 160, 0.15);
      --danger: #e06c75; --danger-soft: rgba(224, 108, 117, 0.12);
      --success: #98c379; --success-soft: rgba(152, 195, 121, 0.12);
      --blue: #61afef; --purple: #c678dd;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--ink); color: var(--text); font-family: 'Inter', system-ui, -apple-system, sans-serif; min-height: 100vh; }
    .navbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 12px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
    .nav-brand { display: flex; align-items: center; gap: 14px; }
    .nav-logo { width: 55px; height: 55px; border-radius: 0; object-fit: contain; border: 1.5px solid var(--gold); flex-shrink: 0; }
    .nav-brand h2 { margin: 0; font-size: 1.1rem; color: #ffffff; }
    .nav-brand span { font-size: 0.78rem; color: var(--text-muted); }
    .nav-actions { display: flex; align-items: center; gap: 16px; }
    .user-badge { display: flex; align-items: center; gap: 8px; background: var(--card); border: 1px solid var(--border); padding: 6px 14px; border-radius: 20px; font-size: 0.82rem; color: var(--text); }
    .status-dot { width: 8px; height: 8px; background: #98c379; border-radius: 50%; box-shadow: 0 0 6px #98c379; }
    .btn-logout { background: transparent; border: 1px solid var(--border); color: var(--danger); padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; text-decoration: none; font-weight: 500; transition: all 0.2s ease; }
    .btn-logout:hover { background: var(--danger-soft); border-color: var(--danger); }
    .dashboard-container { max-width: 1360px; margin: 0 auto; padding: 24px 24px 60px; }
    .alert-banner { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; }
    .alert-banner.success { background: var(--success-soft); border: 1px solid var(--success); color: #a8d488; }
    .alert-banner.danger { background: var(--danger-soft); border: 1px solid var(--danger); color: #ff8582; }
    .tabs-nav { display: flex; gap: 10px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: thin; }
    .tabs-nav::-webkit-scrollbar { height: 6px; }
    .tabs-nav::-webkit-scrollbar-thumb { background: var(--border); border-radius: 6px; }
    .tabs-nav::-webkit-scrollbar-thumb:hover { background: var(--gold); }
    .tab-btn { background: var(--surface); border: 1px solid var(--border); color: var(--text-muted); padding: 10px 18px; border-radius: 10px; font-family: inherit; font-weight: 600; font-size: 0.88rem; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s ease; flex-shrink: 0; white-space: nowrap; }
    .tab-btn:hover { background: var(--card-hover); color: var(--text); }
    .tab-btn.active { background: linear-gradient(135deg, rgba(201, 162, 77, 0.2), rgba(223, 186, 105, 0.1)); border-color: var(--gold); color: var(--gold-hover); box-shadow: 0 0 12px rgba(201, 162, 77, 0.2); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .dash-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    .dash-header h1 { margin: 0 0 4px; font-size: 1.45rem; color: #ffffff; }
    .dash-header p { margin: 0; font-size: 0.86rem; color: var(--text-muted); }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 26px; }
    .kpi-grid-5 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .kpi-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; box-shadow: 0 6px 20px rgba(0,0,0,0.35); position: relative; overflow: hidden; }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--border); }
    .kpi-card:hover::before { background: var(--gold); }
    .kpi-title { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
    .kpi-value { font-size: 1.75rem; font-weight: 700; color: #ffffff; margin-bottom: 6px; }
    .kpi-value.gold { color: var(--gold-hover); }
    .kpi-value.danger { color: var(--danger); }
    .kpi-value.teal { color: var(--teal); }
    .kpi-value.purple { color: var(--purple); }
    .kpi-value.small { font-size: 1.4rem; }
    .kpi-footer { font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; }
    .kpi-footer.positive { color: var(--success); }
    .kpi-footer.warning { color: var(--gold); }
    .kpi-footer.danger { color: var(--danger); }
    .kpi-footer.neutral { color: var(--text-muted); }
    .kpi-sub { font-size: 0.75rem; color: var(--text-muted); display: block; }
    .charts-grid-2 { display: grid; grid-template-columns: 2fr 1.2fr; gap: 20px; margin-bottom: 24px; }
    .charts-grid-equal-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    .charts-grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 26px; }
    .chart-card { background: linear-gradient(180deg, #111a28 0%, var(--surface) 100%); border: 1px solid #26364b; border-top: 2px solid var(--gold); border-radius: 14px; padding: 18px 20px 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.34), 0 0 18px rgba(201,162,77,0.045); position: relative; overflow: hidden; }
    .chart-card::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 44px; background: linear-gradient(180deg, rgba(201,162,77,0.055), transparent); pointer-events: none; }
    .chart-card:hover { border-color: rgba(201,162,77,0.48); box-shadow: 0 10px 28px rgba(0,0,0,0.38), 0 0 22px rgba(201,162,77,0.08); }
    .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; position: relative; z-index: 1; gap: 12px; }
    .chart-header h3 { margin: 0; font-size: 0.98rem; font-weight: 600; color: #ffffff; display: flex; align-items: center; gap: 8px; }
    .chart-header h3::before { content: '▦'; color: var(--gold-hover); font-size: 0.92rem; text-shadow: 0 0 8px rgba(201,162,77,0.45); }
    .badge-tag { background: var(--card); border: 1px solid var(--border); padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; color: var(--gold-hover); }
    .badge-tag.critical { border-color: var(--danger); color: var(--danger); background: var(--danger-soft); }
    .chart-container { position: relative; height: 260px; width: 100%; border-top: 1px solid rgba(255,255,255,0.055); padding-top: 8px; }
    .chart-container canvas { transition: filter 0.25s ease, transform 0.25s ease; }
    .chart-container canvas:hover { filter: drop-shadow(0 0 7px rgba(201,162,77,0.24)); }
    .chart-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); }
    .chart-summary-item { min-width: 0; min-height: 58px; background: rgba(15,21,29,0.42); border: 1px solid #25344a; border-radius: 6px; padding: 9px 8px 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
    .chart-summary-item:hover { border-color: rgba(201,162,77,0.38); background: rgba(201,162,77,0.045); box-shadow: 0 0 12px rgba(201,162,77,0.06); }
    .chart-summary-label { color: #aeb9c8; font-size: 0.66rem; text-transform: uppercase; letter-spacing: .02em; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .chart-summary-value { color: var(--text); font-size: 0.84rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .chart-summary-value.accent { color: var(--gold-hover); text-shadow: 0 0 8px rgba(201,162,77,0.2); }
    .chart-summary-value.teal { color: #72c6c2; }
    @media (max-width: 600px) { .chart-summary { grid-template-columns: 1fr; } }
    .empty-state { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 20px; }
    .alert-box-extra { background: linear-gradient(135deg, rgba(201, 162, 77, 0.12), rgba(15, 21, 29, 0.8)); border: 1px solid var(--gold-border); border-radius: 14px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
    .alert-box-extra h4 { margin: 0 0 4px; font-size: 1rem; color: var(--gold-hover); }
    .alert-box-extra p { margin: 0; font-size: 0.84rem; color: var(--text); }
    .table-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.35); margin-bottom: 24px; }
    .table-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 14px; }
    .table-header h3 { margin: 0 0 4px; font-size: 1.1rem; color: #ffffff; }
    .table-header p { margin: 0; font-size: 0.8rem; color: var(--text-muted); }
    .btn-primary { background: linear-gradient(135deg, #c9a24d, #dfba69); color: #0f0d06; font-weight: 700; border: none; padding: 10px 18px; border-radius: 8px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; }
    .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; text-align: left; }
    .data-table th { background: var(--card); color: var(--text-muted); font-weight: 600; padding: 12px 14px; border-bottom: 1px solid var(--border); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em; }
    .matriz-table td:not(:first-child), .matriz-table th:not(:first-child) { text-align: center; }
    .data-table td { padding: 14px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); color: var(--text); }
    .data-table tbody tr:hover { background: var(--card-hover); }
    .mono-gold { font-family: 'IBM Plex Mono', monospace; color: var(--gold-hover); font-weight: 600; }
    .amount { font-weight: 600; color: #ffffff; }
    .status-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .status-badge.alert { background: var(--danger-soft); border: 1px solid var(--danger); color: #ff8582; }
    .status-badge.process { background: var(--gold-soft); border: 1px solid var(--gold); color: var(--gold-hover); }
    .status-badge.success { background: var(--success-soft); border: 1px solid var(--success); color: #a8d488; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 16px; }
    .modal-card { background: var(--surface); border: 1px solid var(--gold-border); border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 26px 28px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8); }
    .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }
    .modal-header h2 { margin: 0; font-size: 1.25rem; color: #ffffff; }
    .btn-close-modal { background: transparent; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; }
    .btn-close-modal:hover { color: var(--danger); }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 0.78rem; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; }
    .form-input, .form-select, .form-textarea { width: 100%; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; color: #0f172a; font-family: inherit; font-size: 0.88rem; outline: none; transition: all 0.2s ease; }
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
  <!-- VERSION-CHECK: dashboard-unified-chart-cards-2026-09-24 -->

  <header class="navbar">
    <div class="nav-brand">
      <img src="athena_logo.jpg" alt="Logo Athena Bitcoin" class="nav-logo">
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

  <main class="dashboard-container">

    <?php if ($msg): ?>
      <div class="alert-banner <?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
        <button onclick="this.parentElement.style.display='none'" style="background:none; border:none; color:inherit; font-weight:bold; cursor:pointer;">✕</button>
      </div>
    <?php endif; ?>

    <nav class="tabs-nav">
      <button class="tab-btn active" id="btn-tab-general" onclick="switchTab('general')">Resumen General</button>
      <button class="tab-btn" id="btn-tab-callcenter" onclick="switchTab('callcenter')">Análisis Call Center & Motivos</button>
      <button class="tab-btn" id="btn-tab-fraud" onclick="switchTab('fraud')">Forense, Demografía & Rangos</button>
      <button class="tab-btn" id="btn-tab-geo" onclick="switchTab('geo')">Análisis Geográfico</button>
      <button class="tab-btn" id="btn-tab-modalidades" onclick="switchTab('modalidades')">Modalidades de Fraude</button>
      <button class="tab-btn" id="btn-tab-matriz" onclick="switchTab('matriz')">Matriz de Fraude</button>
      <button class="tab-btn" id="btn-tab-resumenfraude" onclick="switchTab('resumenfraude')">Fraude</button>
    </nav>

    <!-- ================= TAB 1: RESUMEN GENERAL ================= -->
    <div id="tab-general" class="tab-content active">
      <div class="dash-header">
        <div>
          <h1>Resumen Ejecutivo de Operaciones</h1>
          <p>Monitoreo general de volumen de llamadas, detección de patrones y reportes de fraude.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Registrar Nuevo Caso</button>
      </div>

      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Total Llamadas Atendidas</div>
          <div class="kpi-value"><?= number_format($total_calls) ?></div>
          <?php if ($growth_pct !== null): ?>
            <div class="kpi-footer <?= $growth_pct >= 0 ? 'positive' : 'danger' ?>"><?= $growth_pct >= 0 ? '↑' : '↓' ?> <?= abs($growth_pct) ?>% vs 90 días anteriores</div>
          <?php else: ?>
            <div class="kpi-footer neutral">Datos insuficientes para comparar</div>
          <?php endif; ?>
          <span class="kpi-sub">Promedio diario: <?= number_format($avg_daily_calls) ?> llamadas</span>
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
          <div class="kpi-footer">Promedio: $<?= $total_frauds > 0 ? number_format($total_amount_affected / $total_frauds, 2) : '0.00' ?> / caso</div>
          <span class="kpi-sub">Recuperado: $<?= number_format($total_amount_recovered, 2) ?></span>
        </div>

        <div class="kpi-card">
          <div class="kpi-title">Horario & Día Mayor Demanda</div>
          <div class="kpi-value" style="font-size:1.35rem; color:var(--gold-hover);"><?= $peak_hour ? formatHour12($peak_hour['hr']) : 'N/D' ?></div>
          <div class="kpi-footer positive">Día pico: <?= htmlspecialchars($peak_day) ?></div>
          <span class="kpi-sub"><?= $valley_hour ? 'Menor demanda: ' . formatHour12($valley_hour['hr']) : '' ?></span>
        </div>
      </section>

      <section class="charts-grid-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Evolución Temporal: Llamadas vs. Reportes de Fraude</h3>
            <span class="badge-tag">Últimas 12 semanas</span>
          </div>
          <div class="chart-container"><canvas id="evolutionChart"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-header">
            <h3>Distribución por Tipo de Fraude</h3>
            <span class="badge-tag">Categorías</span>
          </div>
          <div class="chart-container">
            <?php if (empty($fraud_type_labels)): ?>
              <div class="empty-state">Sin reportes de fraude registrados todavía.</div>
            <?php else: ?>
              <canvas id="fraudTypesChart"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </section>

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
              <tr><th>ID Reporte</th><th>Fecha y Hora</th><th>Tipo de Fraude</th><th>Canal</th><th>Estado / Región</th><th>Víctima</th><th>Monto</th><th>Estatus</th></tr>
            </thead>
            <tbody>
              <?php if (count($recent_reports) === 0): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:24px;">No hay incidentes registrados. La base de datos está vacía.</td></tr>
              <?php endif; ?>
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

    <!-- ================= TAB 2: CALL CENTER & MOTIVOS ================= -->
    <div id="tab-callcenter" class="tab-content">
      <div class="dash-header">
        <div>
          <h1>Análisis de Operaciones & Motivos de Contacto</h1>
          <p>Determinación de volumen, horarios críticos y clasificación exacta de por qué llaman los clientes.</p>
        </div>
        <div style="display:flex; flex-direction:row; flex-wrap:nowrap; gap:10px;">
          <button class="btn-primary" onclick="openCallModal()" style="white-space:nowrap;">+ Registrar Llamada</button>
          <button class="btn-primary" onclick="openImportModal()" style="white-space:nowrap; background:linear-gradient(135deg, #4fa3a0, #6cc2bf);">+ Importar CSV</button>
        </div>
      </div>

      <div class="alert-box-extra">
        <div>
          <h4>Inteligencia Operativa: Motivos Principales de Llamada</h4>
          <p><?= $top3_text ?></p>
        </div>
        <span class="badge-tag"><?= count($reasons) ?> Motivos Clasificados</span>
      </div>

      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Volumen Total Llamadas</div>
          <div class="kpi-value teal"><?= number_format($total_calls) ?></div>
          <div class="kpi-footer positive">Promedio mensual: <?= number_format($avg_monthly_calls) ?></div>
          <span class="kpi-sub">Promedio diario: <?= number_format($avg_daily_calls) ?> llamadas</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Motivo Principal (#1)</div>
          <div class="kpi-value gold" style="font-size:1.4rem;"><?= $top_reason ? htmlspecialchars($top_reason['contact_reason']) : 'N/D' ?></div>
          <div class="kpi-footer warning"><?= $top_reason ? number_format($top_reason['c']) . ' llamadas (' . $top_reason_pct . '%)' : '—' ?></div>
          <span class="kpi-sub">Motivo con mayor volumen</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Mayor Tiempo en Llamada (TMO)</div>
          <div class="kpi-value" style="font-size:1.35rem; color:var(--danger)"><?= $longest_reason ? htmlspecialchars($longest_reason['contact_reason']) : 'N/D' ?></div>
          <div class="kpi-footer danger"><?= $longest_reason ? round($longest_reason['avg_dur'] / 60, 1) . ' min promedio / llamada' : '—' ?></div>
          <span class="kpi-sub">Motivo más complejo de atender</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Hora Pico vs. Hora Valle</div>
          <div class="kpi-value" style="font-size:1.28rem; color:var(--gold-hover);"><?= $peak_hour ? formatHour12($peak_hour['hr']) : 'N/D' ?> / <?= $valley_hour ? formatHour12($valley_hour['hr']) : 'N/D' ?></div>
          <div class="kpi-footer positive"><?= $peak_hour ? 'Mayor: ' . number_format($peak_hour['c']) . ' ll' : '' ?> <?= $valley_hour ? ' / Menor: ' . number_format($valley_hour['c']) . ' ll' : '' ?></div>
          <span class="kpi-sub"><?= $hour_ratio !== null ? 'Ratio de demanda: ' . $hour_ratio . ' a 1' : '' ?></span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Día de Mayor y Menor Volumen</div>
          <div class="kpi-value" style="font-size:1.28rem; color:var(--gold-hover);"><?= htmlspecialchars($peak_day) ?> / <?= htmlspecialchars($lowest_day) ?></div>
          <div class="kpi-footer positive">Día pico / Día de menor demanda</div>
          <span class="kpi-sub">Basado en call_records</span>
        </div>
      </section>

      <section class="charts-grid-equal-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Distribución de Llamadas por Motivo de Contacto</h3>
            <span class="badge-tag">Volumen Absoluto</span>
          </div>
          <div class="chart-container" style="height:320px;">
            <?php if (empty($reason_labels)): ?>
              <div class="empty-state">Sin llamadas registradas todavía.</div>
            <?php else: ?>
              <canvas id="reasonsBarChart"></canvas>
            <?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header">
            <h3>Duración Promedio de Atención (TMO en Minutos)</h3>
            <span class="badge-tag">Complejidad Operativa</span>
          </div>
          <div class="chart-container" style="height:320px;">
            <?php if (empty($duration_labels)): ?>
              <div class="empty-state">Sin llamadas registradas todavía.</div>
            <?php else: ?>
              <canvas id="reasonsDurationChart"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="charts-grid-equal-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Llamadas por Estado de Distribución</h3>
            <span class="badge-tag">Volumen Geográfico</span>
          </div>
          <div class="chart-container" style="height:420px;">
            <?php if (empty($calls_by_state)): ?>
              <div class="empty-state">Sin llamadas registradas todavía.</div>
            <?php else: ?>
              <canvas id="callsByStateChart"></canvas>
            <?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header">
            <h3>Distribución Porcentual por Motivo</h3>
            <span class="badge-tag">% del Total</span>
          </div>
          <div class="chart-container" style="height:230px;">
            <?php if (empty($reason_labels)): ?>
              <div class="empty-state">Sin llamadas registradas todavía.</div>
            <?php else: ?>
              <canvas id="reasonsPctChart"></canvas>
            <?php endif; ?>
          </div>
          <?php if (!empty($reason_labels)): ?>
          <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:14px; margin-top:14px; padding-top:10px; border-top:1px solid var(--border);">
            <?php foreach ($reason_labels as $i => $label): ?>
              <div style="display:flex; align-items:center; gap:6px; font-size:0.85rem; color:var(--text); font-weight:600;">
                <span style="width:12px; height:12px; border-radius:3px; background:<?= htmlspecialchars($donut_palette_php[$i % count($donut_palette_php)]) ?>; flex-shrink:0;"></span>
                <span><?= htmlspecialchars($label) ?> (<?= $reason_pcts[$i] ?>%)</span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="chart-card">
        <div class="chart-header">
          <h3>Evolución Mensual de Llamadas</h3>
          <span class="badge-tag"><?= $months_of_history == 1 ? 'Último mes' : "Últimos $months_of_history meses" ?></span>
        </div>
        <div class="chart-container">
          <canvas id="callsMonthlyEvoChart"></canvas>
        </div>
      </section>

      <section class="chart-card">
        <div class="chart-header">
          <h3>Llamadas por Día de la Semana</h3>
          <span class="badge-tag">Lunes a Domingo</span>
        </div>
        <div class="chart-container">
          <?php if (empty($weekday_values) || $weekday_max === 0): ?>
            <div class="empty-state">Sin llamadas registradas todavía.</div>
          <?php else: ?>
            <canvas id="weekdayCallsChart"></canvas>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <!-- ================= TAB 3: FORENSE, DEMOGRAFÍA & RANGOS ================= -->
    <div id="tab-fraud" class="tab-content">
      <div class="dash-header">
        <div>
          <h1>Análisis Forense, Demografía & Medidas Económicas</h1>
          <p>Consolidación estadística de víctimas, rangos de pérdida monetaria y medidas de tendencia central.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Registrar Caso</button>
      </div>

      <section class="kpi-grid-5">
        <div class="kpi-card">
          <div class="kpi-title">Total Defraudado</div>
          <div class="kpi-value danger small">$<?= number_format($stat_total, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Total consolidado (<?= $stat_count ?> casos)</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Promedio (Media)</div>
          <div class="kpi-value gold small">$<?= number_format($stat_avg, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Valor medio por caso</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Mediana Estadística</div>
          <div class="kpi-value teal small">$<?= number_format($stat_median, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Valor central sin sesgo</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Pérdida Mínima</div>
          <div class="kpi-value small" style="color:#94a3b8;">$<?= number_format($stat_min, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Caso de menor impacto</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Pérdida Máxima</div>
          <div class="kpi-value purple small">$<?= number_format($stat_max, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Caso de mayor impacto</span>
        </div>
      </section>

      <section class="charts-grid-equal-2">
        <div class="chart-card">
          <div class="chart-header"><h3>Distribución por Rangos de Pérdida ($ MXN)</h3><span class="badge-tag">7 Rangos</span></div>
          <div class="chart-container" style="height:320px;">
            <?php if ($stat_count === 0): ?><div class="empty-state">Sin reportes de fraude todavía.</div><?php else: ?><canvas id="lossRangesBarChart"></canvas><?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header"><h3>Participación % por Rango Económico</h3><span class="badge-tag">Volumen de Casos</span></div>
          <div class="chart-container" style="height:320px;">
            <?php if ($stat_count === 0): ?><div class="empty-state">Sin reportes de fraude todavía.</div><?php else: ?><canvas id="lossRangesDonutChart"></canvas><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="charts-grid-3">
        <div class="chart-card">
          <div class="chart-header"><h3>Distribución por Rangos de Edad</h3></div>
          <div class="chart-container">
            <?php if ($total_victims === 0): ?><div class="empty-state">Sin víctimas registradas.</div><?php else: ?><canvas id="victimAgeChart"></canvas><?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header"><h3>Distribución por Género</h3></div>
          <div class="chart-container">
            <?php if (empty($gender_labels)): ?><div class="empty-state">Sin víctimas registradas.</div><?php else: ?><canvas id="victimGenderChart"></canvas><?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header"><h3>Pérdida Promedio según Edad ($ MXN)</h3></div>
          <div class="chart-container">
            <?php if ($total_victims === 0): ?><div class="empty-state">Sin víctimas registradas.</div><?php else: ?><canvas id="victimLossByAgeChart"></canvas><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="table-card">
        <div class="table-header">
          <div>
            <h3>Análisis Detallado por Edad</h3>
            <p>Número y porcentaje de víctimas, monto total perdido, pérdida promedio y modalidad de fraude predominante por rango de edad.</p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Rango de Edad</th>
                <th>Núm. Víctimas</th>
                <th>% del Total</th>
                <th>Cantidad Total Perdida</th>
                <th>Pérdida Promedio</th>
                <th>Tipo de Fraude Predominante</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($total_victims === 0): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:24px;">Sin víctimas registradas todavía.</td></tr>
              <?php endif; ?>
              <?php foreach ($age_buckets as $label => $b): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($label) ?></strong></td>
                  <td><?= number_format($b['count']) ?></td>
                  <td class="mono-gold"><?= $total_victims > 0 ? round(($b['count'] / $total_victims) * 100, 1) : 0 ?>%</td>
                  <td class="amount">$<?= number_format($b['sum'], 2) ?></td>
                  <td>$<?= $b['count'] > 0 ? number_format($b['sum'] / $b['count'], 2) : '0.00' ?></td>
                  <td><?= htmlspecialchars($age_predominant_type[$label] ?? 'N/D') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="table-card">
        <div class="table-header">
          <div>
            <h3>Análisis Detallado por Género</h3>
            <p>Número y porcentaje de víctimas, monto total perdido, pérdida promedio y modalidad de fraude predominante por género.</p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Género</th>
                <th>Núm. Víctimas</th>
                <th>% del Total</th>
                <th>Cantidad Total Perdida</th>
                <th>Pérdida Promedio</th>
                <th>Tipo de Fraude Predominante</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($total_victims === 0): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:24px;">Sin víctimas registradas todavía.</td></tr>
              <?php endif; ?>
              <?php foreach ($gender_stats as $g => $s): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($g) ?></strong></td>
                  <td><?= number_format($s['count']) ?></td>
                  <td class="mono-gold"><?= $total_victims > 0 ? round(($s['count'] / $total_victims) * 100, 1) : 0 ?>%</td>
                  <td class="amount">$<?= number_format($s['sum'], 2) ?></td>
                  <td>$<?= $s['count'] > 0 ? number_format($s['sum'] / $s['count'], 2) : '0.00' ?></td>
                  <td><?= htmlspecialchars($gender_predominant_type[$g] ?? 'N/D') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($total_victims > 0): ?>
        <p style="margin-top:14px; font-size:0.78rem; color:var(--text-muted); line-height:1.5;">
          <strong>Nota metodológica:</strong> esta tabla muestra diferencias observadas en los datos registrados hasta ahora. Con un número de casos aún reducido, estas cifras no deben interpretarse como que un género sea "más vulnerable" o "más propenso" a cierto tipo de fraude — eso requeriría una muestra más grande y un análisis estadístico que descarte otros factores (edad, canal de contacto, tipo de fraude, etc.). Úsala como punto de partida descriptivo, no como conclusión causal.
        </p>
        <?php endif; ?>
      </section>
    </div>

    <!-- ================= TAB 4: ANÁLISIS GEOGRÁFICO ================= -->
    <div id="tab-geo" class="tab-content">
      <div class="dash-header">
        <div>
          <h1>Análisis Geográfico del Fraude</h1>
          <p>Distribución territorial por estado/región registrada: comparación entre volumen de víctimas e impacto económico.</p>
        </div>
      </div>

      <div class="alert-box-extra">
        <div>
          <h4>Diferenciación Clave: Volumen de Casos vs. Impacto Económico</h4>
          <p>
            <?php if ($top_state_cases && $top_state_amount && $top_state_avg): ?>
              <strong><?= htmlspecialchars($top_state_cases['state_name']) ?></strong> lidera en volumen de víctimas (<strong><?= $top_state_cases['c'] ?> casos</strong>) y <strong><?= htmlspecialchars($top_state_amount['state_name']) ?></strong> concentra el mayor monto total (<strong>$<?= number_format($top_state_amount['total_amt'], 2) ?></strong>), mientras que <strong><?= htmlspecialchars($top_state_avg['state_name']) ?></strong> registra la <strong>mayor pérdida promedio por víctima ($<?= number_format($top_state_avg['avg_amt'], 2) ?> / caso)</strong>.
            <?php else: ?>
              Sin datos geográficos suficientes todavía.
            <?php endif; ?>
          </p>
        </div>
        <span class="badge-tag"><?= $distinct_states_count ?> Estados/Regiones con Casos</span>
      </div>

      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Mayor Número de Víctimas</div>
          <div class="kpi-value gold"><?= $top_state_cases ? htmlspecialchars($top_state_cases['state_name']) : 'N/D' ?></div>
          <div class="kpi-footer warning"><?= $top_state_cases ? $top_state_cases['c'] . ' víctimas (' . ($geo_total_cases > 0 ? round(($top_state_cases['c']/$geo_total_cases)*100,1) : 0) . '% del total)' : '—' ?></div>
          <span class="kpi-sub">Estado/región con más casos</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Mayor Dinero Perdido</div>
          <div class="kpi-value danger">$<?= $top_state_amount ? number_format($top_state_amount['total_amt'], 2) : '0.00' ?></div>
          <div class="kpi-footer danger"><?= $top_state_amount ? htmlspecialchars($top_state_amount['state_name']) : 'N/D' ?></div>
          <span class="kpi-sub">Mayor concentración monetaria</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Mayor Pérdida Promedio / Caso</div>
          <div class="kpi-value teal" style="font-size:1.45rem;"><?= $top_state_avg ? htmlspecialchars($top_state_avg['state_name']) . ' ($' . number_format($top_state_avg['avg_amt'], 0) . ')' : 'N/D' ?></div>
          <div class="kpi-footer positive">vs. promedio nacional: $<?= number_format($stat_avg, 0) ?></div>
          <span class="kpi-sub">Ticket medio más alto</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Cobertura Geográfica</div>
          <div class="kpi-value purple" style="font-size:1.45rem;"><?= $distinct_states_count ?> estados</div>
          <div class="kpi-footer warning">Con al menos un caso registrado</div>
          <span class="kpi-sub">Basado en tabla fraud_reports</span>
        </div>
      </section>

      <section class="charts-grid-equal-2">
        <div class="chart-card">
          <div class="chart-header"><h3>A) Ranking por Mayor Cantidad de Víctimas / Casos</h3><span class="badge-tag">Volumen</span></div>
          <div class="chart-container" style="height:320px;">
            <?php if (empty($geo)): ?><div class="empty-state">Sin datos geográficos todavía.</div><?php else: ?><canvas id="geoCasesChart"></canvas><?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header"><h3>B) Ranking por Mayor Impacto Económico ($ MXN)</h3><span class="badge-tag">Pérdida Monetaria</span></div>
          <div class="chart-container" style="height:320px;">
            <?php if (empty($geo)): ?><div class="empty-state">Sin datos geográficos todavía.</div><?php else: ?><canvas id="geoAmountChart"></canvas><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="table-card">
        <div class="table-header">
          <div><h3>Tabla Maestra de Distribución de Fraude por Estado/Región</h3><p>Análisis consolidado de volumen, dinero total perdido y promedio.</p></div>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>Estado/Región</th><th>Núm. Víctimas</th><th>% en Casos</th><th>Total Defraudado ($ MXN)</th><th>% en Pérdidas</th><th>Pérdida Promedio</th></tr></thead>
            <tbody>
              <?php if (empty($geo)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:24px;">Sin datos geográficos registrados.</td></tr>
              <?php endif; ?>
              <?php foreach ($geo as $g): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($g['state_name']) ?></strong></td>
                  <td><?= number_format($g['c']) ?></td>
                  <td class="mono-gold"><?= $geo_total_cases > 0 ? round(($g['c']/$geo_total_cases)*100, 1) : 0 ?>%</td>
                  <td class="amount">$<?= number_format($g['total_amt'], 2) ?></td>
                  <td class="mono-gold"><?= $geo_total_amount > 0 ? round(($g['total_amt']/$geo_total_amount)*100, 1) : 0 ?>%</td>
                  <td>$<?= number_format($g['avg_amt'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- ================= TAB 5: MODALIDADES DE FRAUDE ================= -->
    <div id="tab-modalidades" class="tab-content">
      <div class="dash-header">
        <div>
          <h1>Modalidades de Fraude (Scam Types)</h1>
          <p>Clasificación por modalidad: romance scam, impersonation, inversión, soporte técnico, empleo, premios, militar, familiar, cripto, marketplace, extorsión y otros.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Registrar Caso</button>
      </div>

      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Modalidad Más Frecuente</div>
          <div class="kpi-value gold" style="font-size:1.4rem;"><?= $most_frequent_type ? htmlspecialchars($most_frequent_type['fraud_type']) : 'N/D' ?></div>
          <div class="kpi-footer warning"><?= $most_frequent_type ? number_format($most_frequent_type['c']) . ' casos (' . ($modalidades_total > 0 ? round(($most_frequent_type['c']/$modalidades_total)*100,1) : 0) . '%)' : '—' ?></div>
          <span class="kpi-sub">Basado en volumen de reportes</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Modalidad de Mayor Pérdida</div>
          <div class="kpi-value danger" style="font-size:1.3rem;"><?= $highest_loss_type ? htmlspecialchars($highest_loss_type['fraud_type']) : 'N/D' ?></div>
          <div class="kpi-footer danger"><?= $highest_loss_type ? '$' . number_format($highest_loss_type['total_amt'], 2) . ' MXN' : '—' ?></div>
          <span class="kpi-sub"><?= $highest_loss_type ? 'Promedio: $' . number_format($highest_loss_type['avg_amt'], 2) . ' / caso' : '' ?></span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Estado Predominante (Top Modalidad)</div>
          <div class="kpi-value teal" style="font-size:1.3rem;"><?= ($most_frequent_type && isset($state_by_type[$most_frequent_type['fraud_type']])) ? htmlspecialchars($state_by_type[$most_frequent_type['fraud_type']]['state']) : 'N/D' ?></div>
          <div class="kpi-footer positive">Donde más ocurre la modalidad #1</div>
          <span class="kpi-sub">Basado en registros de fraud_reports</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Modalidades Distintas Registradas</div>
          <div class="kpi-value purple" style="font-size:1.4rem;"><?= count($type_stats) ?></div>
          <div class="kpi-footer warning">Con al menos un caso</div>
          <span class="kpi-sub">De las categorías clasificadas</span>
        </div>
      </section>

      <section class="charts-grid-2">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Evolución Mensual por Modalidad (Top 5)</h3>
            <span class="badge-tag"><?= $type_months_of_history == 1 ? 'Último mes' : "Últimos $type_months_of_history meses" ?></span>
          </div>
          <div class="chart-container">
            <?php if (empty($evo_type_datasets) || $modalidades_total === 0): ?>
              <div class="empty-state">Sin datos suficientes para mostrar evolución por modalidad.</div>
            <?php else: ?>
              <canvas id="modalidadesEvoChart"></canvas>
            <?php endif; ?>
          </div>
        </div>
        <div class="chart-card">
          <div class="chart-header">
            <h3>Pérdida Total por Modalidad ($ MXN)</h3>
            <span class="badge-tag">Impacto Económico</span>
          </div>
          <div class="chart-container">
            <?php if (empty($types_by_loss)): ?>
              <div class="empty-state">Sin reportes de fraude registrados todavía.</div>
            <?php else: ?>
              <canvas id="modalidadesLossChart"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="table-card">
        <div class="table-header">
          <div>
            <h3>Perfil Estadístico por Modalidad de Fraude</h3>
            <p>Frecuencia, pérdida total y promedio, estado donde predomina y perfil de víctimas por cada modalidad.</p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Modalidad</th>
                <th>Casos</th>
                <th>% del Total</th>
                <th>Pérdida Total</th>
                <th>Pérdida Promedio</th>
                <th>Estado Predominante</th>
                <th>Edad Promedio Víctima</th>
                <th>Género Predominante</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($types_by_count)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:24px;">Sin modalidades de fraude registradas todavía.</td></tr>
              <?php endif; ?>
              <?php foreach ($types_by_count as $t): ?>
                <?php
                  $tname = $t['fraud_type'];
                  $pct = $modalidades_total > 0 ? round(($t['c'] / $modalidades_total) * 100, 1) : 0;
                  $pred_state = $state_by_type[$tname]['state'] ?? 'N/D';
                  $pred_gender = $gender_by_type[$tname]['gender'] ?? 'N/D';
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($tname) ?></strong></td>
                  <td><?= number_format($t['c']) ?></td>
                  <td class="mono-gold"><?= $pct ?>%</td>
                  <td class="amount">$<?= number_format($t['total_amt'], 2) ?></td>
                  <td>$<?= number_format($t['avg_amt'], 2) ?></td>
                  <td><?= htmlspecialchars($pred_state) ?></td>
                  <td><?= round($t['avg_age'], 1) ?> años</td>
                  <td><?= htmlspecialchars($pred_gender) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- ================= TAB 6: MATRIZ DE FRAUDE ================= -->
    <div id="tab-matriz" class="tab-content">
      <div class="dash-header">
        <div>
          <h1>Matriz de Fraude</h1>
          <p>Cruces estadísticos consolidados: edad, género, estado, tipo de fraude y monto perdido.</p>
        </div>
      </div>

      <?php if (empty($present_types)): ?>
        <section class="table-card">
          <div class="empty-state" style="height:120px;">Sin reportes de fraude registrados todavía. Esta matriz se llenará conforme se capturen casos.</div>
        </section>
      <?php else: ?>

      <!-- 1. Edad × Tipo de Fraude -->
      <section class="table-card">
        <div class="table-header">
          <div><h3>1. Edad × Tipo de Fraude</h3><p>Número de casos por rango de edad y modalidad de fraude.</p></div>
        </div>
        <div class="table-responsive">
          <table class="data-table matriz-table">
            <thead>
              <tr>
                <th>Rango de Edad</th>
                <?php foreach ($present_types as $pt): ?><th><?= htmlspecialchars($pt) ?></th><?php endforeach; ?>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($age_buckets as $label => $b): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($label) ?></strong></td>
                  <?php foreach ($present_types as $pt): ?>
                    <td><?= $b['types'][$pt] ?? 0 ?></td>
                  <?php endforeach; ?>
                  <td class="mono-gold"><?= $b['count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 2. Género × Tipo de Fraude -->
      <section class="table-card">
        <div class="table-header">
          <div><h3>2. Género × Tipo de Fraude</h3><p>Número de casos por género y modalidad de fraude.</p></div>
        </div>
        <div class="table-responsive">
          <table class="data-table matriz-table">
            <thead>
              <tr>
                <th>Género</th>
                <?php foreach ($present_types as $pt): ?><th><?= htmlspecialchars($pt) ?></th><?php endforeach; ?>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gender_stats as $g => $s): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($g) ?></strong></td>
                  <?php foreach ($present_types as $pt): ?>
                    <td><?= $s['types'][$pt] ?? 0 ?></td>
                  <?php endforeach; ?>
                  <td class="mono-gold"><?= $s['count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 3. Estado × Tipo de Fraude -->
      <section class="table-card">
        <div class="table-header">
          <div><h3>3. Estado × Tipo de Fraude</h3><p>Número de casos por estado/región y modalidad de fraude.</p></div>
        </div>
        <div class="table-responsive">
          <table class="data-table matriz-table">
            <thead>
              <tr>
                <th>Estado/Región</th>
                <?php foreach ($present_types as $pt): ?><th><?= htmlspecialchars($pt) ?></th><?php endforeach; ?>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($matrix_states as $st): ?>
                <?php $rowTotal = array_sum($state_type_matrix[$st]); ?>
                <tr>
                  <td><strong><?= htmlspecialchars($st) ?></strong></td>
                  <?php foreach ($present_types as $pt): ?>
                    <td><?= $state_type_matrix[$st][$pt] ?? 0 ?></td>
                  <?php endforeach; ?>
                  <td class="mono-gold"><?= $rowTotal ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <div class="charts-grid-equal-2" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <!-- 4. Estado × Monto Perdido -->
        <section class="table-card">
          <div class="table-header"><div><h3>4. Estado × Monto Perdido</h3><p>Total y promedio de pérdida por estado/región.</p></div></div>
          <div class="table-responsive">
            <table class="data-table matriz-table">
              <thead><tr><th>Estado</th><th>Total Perdido</th><th>Promedio</th></tr></thead>
              <tbody>
                <?php foreach ($geo_by_amount as $g): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($g['state_name']) ?></strong></td>
                    <td class="amount">$<?= number_format($g['total_amt'], 2) ?></td>
                    <td>$<?= number_format($g['avg_amt'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 8. Estado × Número de Víctimas -->
        <section class="table-card">
          <div class="table-header"><div><h3>8. Estado × Número de Víctimas</h3><p>Cantidad de víctimas por estado/región.</p></div></div>
          <div class="table-responsive">
            <table class="data-table matriz-table">
              <thead><tr><th>Estado</th><th>Núm. Víctimas</th><th>% del Total</th></tr></thead>
              <tbody>
                <?php foreach ($geo as $g): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($g['state_name']) ?></strong></td>
                    <td><?= number_format($g['c']) ?></td>
                    <td class="mono-gold"><?= $geo_total_cases > 0 ? round(($g['c']/$geo_total_cases)*100, 1) : 0 ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 5. Edad × Monto Perdido -->
        <section class="table-card">
          <div class="table-header"><div><h3>5. Edad × Monto Perdido</h3><p>Total y promedio de pérdida por rango de edad.</p></div></div>
          <div class="table-responsive">
            <table class="data-table matriz-table">
              <thead><tr><th>Rango de Edad</th><th>Total Perdido</th><th>Promedio</th></tr></thead>
              <tbody>
                <?php foreach ($age_buckets as $label => $b): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($label) ?></strong></td>
                    <td class="amount">$<?= number_format($b['sum'], 2) ?></td>
                    <td>$<?= $b['count'] > 0 ? number_format($b['sum'] / $b['count'], 2) : '0.00' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 6. Género × Monto Perdido -->
        <section class="table-card">
          <div class="table-header"><div><h3>6. Género × Monto Perdido</h3><p>Total y promedio de pérdida por género.</p></div></div>
          <div class="table-responsive">
            <table class="data-table matriz-table">
              <thead><tr><th>Género</th><th>Total Perdido</th><th>Promedio</th></tr></thead>
              <tbody>
                <?php foreach ($gender_stats as $g => $s): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($g) ?></strong></td>
                    <td class="amount">$<?= number_format($s['sum'], 2) ?></td>
                    <td>$<?= $s['count'] > 0 ? number_format($s['sum'] / $s['count'], 2) : '0.00' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 7. Tipo de Fraude × Monto Perdido -->
        <section class="table-card">
          <div class="table-header"><div><h3>7. Tipo de Fraude × Monto Perdido</h3><p>Total y promedio de pérdida por modalidad.</p></div></div>
          <div class="table-responsive">
            <table class="data-table matriz-table">
              <thead><tr><th>Modalidad</th><th>Total Perdido</th><th>Promedio</th></tr></thead>
              <tbody>
                <?php foreach ($types_by_loss as $t): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($t['fraud_type']) ?></strong></td>
                    <td class="amount">$<?= number_format($t['total_amt'], 2) ?></td>
                    <td>$<?= number_format($t['avg_amt'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <p style="margin-top:4px; font-size:0.78rem; color:var(--text-muted); line-height:1.5;">
        <strong>Nota metodológica:</strong> estas matrices muestran cruces descriptivos de los datos capturados hasta ahora. Con pocos casos, evita interpretar patrones como relaciones causales — úsalos como punto de partida para investigar, no como conclusiones definitivas.
      </p>
      <?php endif; ?>
    </div>

    <!-- ================= TAB 7: FRAUDE (RESUMEN CONSOLIDADO) ================= -->
    <div id="tab-resumenfraude" class="tab-content">
      <div class="dash-header">
        <div>
          <h1>Fraude</h1>
          <p>Panorama consolidado: volumen, pérdidas, distribución por estado, tipo, edad y género.</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Registrar Caso</button>
      </div>

      <section class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Total Cases</div>
          <div class="kpi-value gold"><?= number_format($total_frauds) ?></div>
          <span class="kpi-sub">Casos registrados en fraud_reports</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Total Victims</div>
          <div class="kpi-value teal"><?= number_format($total_victims) ?></div>
          <span class="kpi-sub">Víctimas con datos demográficos</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Total Loss</div>
          <div class="kpi-value danger">$<?= number_format($stat_total, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Suma de todos los montos afectados</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Average Loss</div>
          <div class="kpi-value gold">$<?= number_format($stat_avg, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Promedio por caso</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Mediana</div>
          <div class="kpi-value teal">$<?= number_format($stat_median, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Valor central sin sesgo</span>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Pérdida Máxima</div>
          <div class="kpi-value purple">$<?= number_format($stat_max, 2) ?> <small style="font-size:0.5em; color:var(--text-muted)">MXN</small></div>
          <span class="kpi-sub">Caso de mayor impacto</span>
        </div>
      </section>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <!-- Casos y pérdida por estado -->
        <section class="table-card">
          <div class="table-header"><div><h3>Casos y Pérdida por Estado</h3><p>Volumen y monto total por estado/región.</p></div></div>
          <div class="table-responsive">
            <table class="data-table">
              <thead><tr><th>Estado</th><th>Casos</th><th>Pérdida Total</th></tr></thead>
              <tbody>
                <?php if (empty($geo)): ?>
                  <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:20px;">Sin datos todavía.</td></tr>
                <?php endif; ?>
                <?php foreach ($geo as $g): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($g['state_name']) ?></strong></td>
                    <td><?= number_format($g['c']) ?></td>
                    <td class="amount">$<?= number_format($g['total_amt'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Casos y pérdida por tipo de fraude -->
        <section class="table-card">
          <div class="table-header"><div><h3>Casos y Pérdida por Tipo de Fraude</h3><p>Volumen y monto total por modalidad.</p></div></div>
          <div class="table-responsive">
            <table class="data-table">
              <thead><tr><th>Modalidad</th><th>Casos</th><th>Pérdida Total</th></tr></thead>
              <tbody>
                <?php if (empty($types_by_count)): ?>
                  <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:20px;">Sin datos todavía.</td></tr>
                <?php endif; ?>
                <?php foreach ($types_by_count as $t): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($t['fraud_type']) ?></strong></td>
                    <td><?= number_format($t['c']) ?></td>
                    <td class="amount">$<?= number_format($t['total_amt'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Víctimas por edad y género -->
      <section class="table-card">
        <div class="table-header"><div><h3>Víctimas por Edad y Género</h3><p>Número de víctimas cruzando rango de edad con género.</p></div></div>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Rango de Edad</th>
                <?php foreach ($matrix_genders as $g): ?><th><?= htmlspecialchars($g) ?></th><?php endforeach; ?>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($total_victims === 0): ?>
                <tr><td colspan="<?= count($matrix_genders) + 2 ?>" style="text-align:center; color:var(--text-muted); padding:20px;">Sin víctimas registradas todavía.</td></tr>
              <?php endif; ?>
              <?php foreach ($age_buckets as $label => $b): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($label) ?></strong></td>
                  <?php foreach ($matrix_genders as $g): ?>
                    <td><?= $b['genders'][$g] ?? 0 ?></td>
                  <?php endforeach; ?>
                  <td class="mono-gold"><?= $b['count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Género y evolución -->
      <section class="chart-card">
        <div class="chart-header">
          <h3>Género y Evolución</h3>
          <span class="badge-tag"><?= $gender_months_of_history == 1 ? 'Último mes' : "Últimos $gender_months_of_history meses" ?></span>
        </div>
        <div class="chart-container">
          <?php if (empty($gender_evo_datasets) || $total_victims === 0): ?>
            <div class="empty-state">Sin datos suficientes para mostrar evolución por género.</div>
          <?php else: ?>
            <canvas id="genderEvoChart"></canvas>
          <?php endif; ?>
        </div>
      </section>
    </div>

  </main>

  <!-- MODAL -->
  <div id="fraudModal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
      <div class="modal-header"><h2>Registrar Nuevo Caso de Fraude</h2><button class="btn-close-modal" onclick="closeModal()">✕</button></div>
      <form method="post">
        <input type="hidden" name="action" value="new_fraud">
        <div class="form-grid-2">
          <div class="form-group">
            <label>Tipo de Fraude *</label>
            <select name="fraud_type" class="form-select" required>
              <optgroup label="Categorías Originales">
                <option value="Phishing Bancario">Phishing Bancario (Falso Ejecutivo)</option>
                <option value="Clonación de Tarjeta">Clonación de Tarjeta (Cajero/Terminal)</option>
                <option value="Suplantación de Identidad">Suplantación de Identidad (Crédito)</option>
                <option value="Transferencia No Reconocida">Transferencia No Reconocida (SPEI/Wire)</option>
                <option value="Extorsión Telefónica">Extorsión Telefónica (Falso Premio)</option>
                <option value="Crypto Scam">Información Bitcoin / Crypto Scam</option>
              </optgroup>
              <optgroup label="Modalidades de Scam">
                <option value="Romance Scam">Romance Scam</option>
                <option value="Impersonation Scam">Impersonation Scam</option>
                <option value="Government Impersonation">Government Impersonation</option>
                <option value="Investment Scam">Investment Scam</option>
                <option value="Tech Support Scam">Tech Support Scam</option>
                <option value="Employment Scam">Employment Scam</option>
                <option value="Giveaway/Prize Scam">Giveaway/Prize Scam</option>
                <option value="Military Scam">Military Scam</option>
                <option value="Family/Relative Scam">Family/Relative Scam</option>
                <option value="Cryptocurrency Scam">Cryptocurrency Scam</option>
                <option value="Online Marketplace Scam">Online Marketplace Scam</option>
                <option value="Extorsión">Extorsión</option>
                <option value="Otros">Otros</option>
              </optgroup>
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
          <div class="form-group"><label>Monto Afectado ($) *</label><input type="number" step="0.01" name="amount_affected" class="form-input" placeholder="Ej. 25000" required></div>
          <div class="form-group"><label>Monto Recuperado / Bloqueado ($)</label><input type="number" step="0.01" name="amount_recovered" class="form-input" placeholder="Ej. 5000" value="0"></div>
        </div>
        <div class="form-grid-2">
          <div class="form-group">
            <label>Estado de Residencia *</label>
            <select name="state_name" class="form-select" required>
              <option value="California (CA)">California (CA)</option>
              <option value="Texas (TX)">Texas (TX)</option>
              <option value="Florida (FL)">Florida (FL)</option>
              <option value="New York (NY)">New York (NY)</option>
              <option value="Illinois (IL)">Illinois (IL)</option>
              <option value="Pennsylvania (PA)">Pennsylvania (PA)</option>
              <option value="Georgia (GA)">Georgia (GA)</option>
              <option value="Ohio (OH)">Ohio (OH)</option>
              <option value="Minnesota (MN)">Minnesota (MN)</option>
              <option value="Tennessee (TN)">Tennessee (TN)</option>
              <option value="Arizona (AZ)">Arizona (AZ)</option>
              <option value="North Carolina (NC)">North Carolina (NC)</option>
              <option value="Michigan (MI)">Michigan (MI)</option>
              <option value="Nevada (NV)">Nevada (NV)</option>
              <option value="Washington (WA)">Washington (WA)</option>
              <option value="Colorado (CO)">Colorado (CO)</option>
              <option value="Massachusetts (MA)">Massachusetts (MA)</option>
              <option value="Virginia (VA)">Virginia (VA)</option>
              <option value="Washington DC">Washington DC</option>
              <option value="Ciudad de México">Ciudad de México</option>
              <option value="Estado de México">Estado de México</option>
              <option value="Jalisco">Jalisco</option>
              <option value="Nuevo León">Nuevo León</option>
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
          <div class="form-group"><label>Edad de la Víctima</label><input type="number" name="victim_age" class="form-input" placeholder="Ej. 45" value="35"></div>
          <div class="form-group">
            <label>Género de la Víctima</label>
            <select name="victim_gender" class="form-select">
              <option value="Masculino">Masculino</option>
              <option value="Femenino">Femenino</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Descripción / Modus Operandi</label><textarea name="description" class="form-textarea" rows="3" placeholder="Detalle del incidente..."></textarea></div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn-primary">Guardar Reporte en MySQL</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: FORMULARIO PARA REGISTRAR NUEVA LLAMADA -->
  <div id="callModal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
      <div class="modal-header">
        <h2>Registrar Nueva Llamada</h2>
        <button class="btn-close-modal" onclick="closeCallModal()">✕</button>
      </div>
      <form method="post">
        <input type="hidden" name="action" value="new_call">

        <div class="form-grid-2">
          <div class="form-group">
            <label>Fecha de la Llamada *</label>
            <input type="date" name="call_date" class="form-input" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>Hora de la Llamada *</label>
            <input type="time" name="call_time" class="form-input" required value="<?= date('H:i') ?>">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label>Duración (segundos) *</label>
            <input type="number" name="duration_seconds" class="form-input" placeholder="Ej. 240" value="180" min="1" required>
          </div>
          <div class="form-group">
            <label>Turno del Día *</label>
            <select name="day_slot" class="form-select" required>
              <option value="Madrugada (0-6h)">Madrugada (0-6h)</option>
              <option value="Mañana (6-12h)" selected>Mañana (6-12h)</option>
              <option value="Tarde (12-18h)">Tarde (12-18h)</option>
              <option value="Noche (18-24h)">Noche (18-24h)</option>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label>Estado de Residencia *</label>
            <input type="text" name="state_name_call" class="form-input" list="states_datalist_call" placeholder="Escribe el estado (ej. Texas (TX))" autocomplete="off" required>
            <datalist id="states_datalist_call">
              <option value="California (CA)">
              <option value="Texas (TX)">
              <option value="Florida (FL)">
              <option value="New York (NY)">
              <option value="Illinois (IL)">
              <option value="Pennsylvania (PA)">
              <option value="Georgia (GA)">
              <option value="Ohio (OH)">
              <option value="Minnesota (MN)">
              <option value="Tennessee (TN)">
              <option value="Arizona (AZ)">
              <option value="North Carolina (NC)">
              <option value="Michigan (MI)">
              <option value="Nevada (NV)">
              <option value="Washington (WA)">
              <option value="Colorado (CO)">
              <option value="Massachusetts (MA)">
              <option value="Virginia (VA)">
              <option value="Washington DC">
              <option value="Ciudad de México">
              <option value="Estado de México">
              <option value="Jalisco">
              <option value="Nuevo León">
              <option value="Estado No Identificado">
            </datalist>
          </div>
          <div class="form-group">
            <label>Motivo de Contacto *</label>
            <select name="contact_reason" class="form-select" required>
              <option value="Transacciones">Transacciones</option>
              <option value="Preguntas generales">Preguntas generales</option>
              <option value="Problemas con ATM">Problemas con ATM</option>
              <option value="Problemas técnicos">Problemas técnicos</option>
              <option value="Reembolsos">Reembolsos</option>
              <option value="Fraude / Scam">Fraude / Scam</option>
              <option value="Info Bitcoin">Info Bitcoin</option>
              <option value="Compliance">Compliance</option>
              <option value="Law Enforcement">Law Enforcement</option>
              <option value="Otros">Otros</option>
            </select>
          </div>
        </div>

        <div class="form-group" style="display:flex; align-items:center; gap:10px;">
          <input type="checkbox" name="is_fraud_report" id="is_fraud_report" style="width:auto;">
          <label for="is_fraud_report" style="margin:0; color:var(--text);">Esta llamada derivó en un reporte de fraude</label>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="closeCallModal()">Cancelar</button>
          <button type="submit" class="btn-primary">Guardar Llamada en MySQL</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: IMPORTAR LLAMADAS DESDE CSV -->
  <div id="importModal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
      <div class="modal-header">
        <h2>Importar Llamadas desde CSV</h2>
        <button class="btn-close-modal" onclick="closeImportModal()">✕</button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_calls_csv">

        <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.6; margin-bottom:16px;">
          Sube un archivo CSV exportado desde tu hoja de registro de llamadas (formato: Tipo, Teléfono, Nombre, Fecha, Hora, Tipo de Llamada, Estatus, Descripción, Duración).<br><br>
          El sistema detectará automáticamente el <strong>estado</strong> según el código de área del teléfono, traducirá el <strong>estatus</strong> a motivo de contacto, calculará el <strong>turno del día</strong>, y omitirá filas con números inválidos.
        </p>

        <div class="form-group">
          <label>Archivo CSV *</label>
          <input type="file" name="csv_file" class="form-input" accept=".csv" required>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="closeImportModal()">Cancelar</button>
          <button type="submit" class="btn-primary">Importar Llamadas</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    Chart.defaults.color = '#f0f4f8';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.devicePixelRatio = Math.max(window.devicePixelRatio || 1, 2);

    function openModal() { document.getElementById('fraudModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('fraudModal').style.display = 'none'; }
    function openCallModal() { document.getElementById('callModal').style.display = 'flex'; }
    function closeCallModal() { document.getElementById('callModal').style.display = 'none'; }
    function openImportModal() { document.getElementById('importModal').style.display = 'flex'; }
    function closeImportModal() { document.getElementById('importModal').style.display = 'none'; }

    function switchTab(tabName) {
      document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
      document.getElementById('btn-tab-' + tabName).classList.add('active');
      document.getElementById('tab-' + tabName).classList.add('active');
      initChartsForTab(tabName);
    }

    const palette = ['#c9a24d', '#dfba69', '#4fa3a0', '#e06c75', '#61afef', '#98c379', '#c678dd', '#f39c12', '#9b59b6', '#95a5a6'];
    const donutPalette = ['#c9a24d', '#4fa3a0', '#e06c75', '#61afef', '#98c379', '#c678dd', '#f39c12', '#9b59b6', '#95a5a6', '#dfba69'];
    const altPalette = ['#61afef', '#e06c75', '#98c379', '#c678dd', '#f39c12', '#4fa3a0', '#c9a24d', '#9b59b6', '#dfba69', '#95a5a6'];
    if (window.ChartDataLabels) {
      Chart.register(ChartDataLabels);
      Chart.defaults.set('plugins.datalabels', { display: false });
    }

    // Efecto de brillo sutil para las gráficas.
    // No modifica datos, tooltips ni la configuración funcional de Chart.js.
    const athenaGlowPlugin = {
      id: 'athenaGlow',
      beforeDatasetDraw(chart, args) {
        const dataset = chart.data.datasets[args.index];
        if (!dataset) return;

        const active = chart.getActiveElements();
        const hovered = active.some(el => el.datasetIndex === args.index);
        const background = dataset.backgroundColor;
        const border = dataset.borderColor;
        let glowColor = Array.isArray(background) ? background[0] : background;
        if (typeof glowColor !== 'string') {
          glowColor = Array.isArray(border) ? border[0] : border;
        }
        if (typeof glowColor !== 'string') glowColor = '#c9a24d';

        const ctx = chart.ctx;
        ctx.save();
        ctx.shadowColor = glowColor;
        ctx.shadowBlur = hovered ? 24 : 10;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
      },
      afterDatasetDraw(chart) {
        chart.ctx.restore();
      }
    };
    Chart.register(athenaGlowPlugin);

    // Control de inicialización perezosa: cada pestaña dibuja sus gráficas
    // solo la primera vez que se abre (cuando el canvas ya es visible y
    // tiene tamaño real). Esto evita un error interno de Chart.js que
    // ocurre al crear gráficas de barras dentro de contenedores ocultos.
    const chartsInitialized = { general: false, callcenter: false, fraud: false, geo: false, modalidades: false, resumenfraude: false };

    function formatChartMetric(value) {
      if (typeof value !== 'number' || !Number.isFinite(value)) return '—';
      return Number.isInteger(value) ? value.toLocaleString('es-SV') : value.toLocaleString('es-SV', { maximumFractionDigits: 1 });
    }

    function refreshChartCardMetrics(tabName) {
      const tab = document.getElementById('tab-' + tabName);
      if (!tab) return;
      tab.querySelectorAll('.chart-card').forEach(card => {
        const canvas = card.querySelector('canvas');
        if (!canvas) return;
        const chart = Chart.getChart(canvas);
        if (!chart || !chart.data || !chart.data.datasets || !chart.data.datasets.length) return;

        let values = [];
        chart.data.datasets.forEach(ds => {
          (ds.data || []).forEach(v => {
            const n = typeof v === 'number' ? v : Number(v);
            if (Number.isFinite(n)) values.push(n);
          });
        });
        if (!values.length) return;

        const total = values.reduce((a,b) => a + b, 0);
        const max = Math.max(...values);
        const min = Math.min(...values);
        const maxIndex = values.indexOf(max);
        const labels = chart.data.labels || [];
        const peak = labels[maxIndex] != null ? String(labels[maxIndex]) : 'Mayor valor';
        const isDonut = chart.config.type === 'doughnut' || chart.config.type === 'pie';

        let items;
        if (isDonut) {
          items = [
            ['TOTAL', formatChartMetric(total)],
            ['MAYOR VALOR', formatChartMetric(max)],
            ['CATEGORÍA PRINCIPAL', peak]
          ];
        } else {
          items = [
            ['TOTAL', formatChartMetric(total)],
            ['VALOR MÁXIMO', formatChartMetric(max)],
            ['PUNTO DESTACADO', peak]
          ];
        }

        let footer = card.querySelector('.chart-summary');
        if (!footer) {
          footer = document.createElement('div');
          footer.className = 'chart-summary';
          card.appendChild(footer);
        }
        footer.innerHTML = items.map((item, i) => `
          <div class="chart-summary-item">
            <span class="chart-summary-label">${item[0]}</span>
            <span class="chart-summary-value ${i === 0 ? 'accent' : ''}">${item[1]}</span>
          </div>`).join('');
      });
    }

    function initChartsForTab(tabName) {
      if (chartsInitialized[tabName]) return;
      chartsInitialized[tabName] = true;
      if (tabName === 'general') initGeneralCharts();
      else if (tabName === 'callcenter') initCallcenterCharts();
      else if (tabName === 'fraud') initForenseCharts();
      else if (tabName === 'geo') initGeoCharts();
      else if (tabName === 'modalidades') initModalidadesCharts();
      else if (tabName === 'resumenfraude') initResumenFraudeCharts();
      requestAnimationFrame(() => refreshChartCardMetrics(tabName));
    }

    // ===== TAB 1: RESUMEN GENERAL =====
    function initGeneralCharts() {
      new Chart(document.getElementById('evolutionChart'), {
        type: 'line',
        data: {
          labels: <?= json_encode($evolution_labels) ?>,
          datasets: [
            { label: 'Llamadas Totales', data: <?= json_encode($evolution_calls) ?>, borderColor: '#4fa3a0', backgroundColor: 'rgba(79, 163, 160, 0.1)', fill: true, tension: 0.35, yAxisID: 'y' },
            { label: 'Reportes Fraude', data: <?= json_encode($evolution_frauds) ?>, borderColor: '#c9a24d', backgroundColor: 'rgba(201, 162, 77, 0.15)', fill: true, tension: 0.35, yAxisID: 'y1' }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { position: 'top' } },
          scales: {
            y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Llamadas' } },
            y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Fraudes' } }
          }
        }
      });

      <?php if (!empty($fraud_type_labels)): ?>
      new Chart(document.getElementById('fraudTypesChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($fraud_type_labels) ?>, datasets: [{ data: <?= json_encode($fraud_type_values) ?>, backgroundColor: palette, borderColor: '#0f151d', borderWidth: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
      });
      <?php endif; ?>
    }

    // ===== TAB 2: CALL CENTER =====
    function initCallcenterCharts() {
      <?php if (!empty($reason_labels)): ?>
      new Chart(document.getElementById('reasonsBarChart'), {
        type: 'bar',
        data: { labels: <?= json_encode($reason_labels_bar) ?>, datasets: [{ label: 'Llamadas', data: <?= json_encode($reason_counts_bar) ?>, backgroundColor: altPalette, borderRadius: 6, barPercentage: 0.6, categoryPercentage: 0.7 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 11 } } }, y: { ticks: { precision: 0 } } } }
      });
      <?php endif; ?>

      <?php if (!empty($duration_labels)): ?>
      new Chart(document.getElementById('reasonsDurationChart'), {
        type: 'bar',
        data: { labels: <?= json_encode($duration_labels_ordered) ?>, datasets: [{ label: 'Duración Promedio (Minutos)', data: <?= json_encode($duration_values_ordered) ?>, backgroundColor: altPalette, borderRadius: 6, barPercentage: 0.6, categoryPercentage: 0.7 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: val => val + ' min' } }, x: { ticks: { maxRotation: 0, minRotation: 0, autoSkip: false, font: { size: 11 } } } } }
      });
      <?php endif; ?>

      <?php if (!empty($calls_by_state)): ?>
      new Chart(document.getElementById('callsByStateChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_map(fn($r) => $r['state_name'], $calls_by_state)) ?>, datasets: [{ label: 'Llamadas', data: <?= json_encode(array_map(fn($r) => (int)$r['c'], $calls_by_state)) ?>, backgroundColor: palette.concat(palette), borderRadius: 6, barPercentage: 0.7, categoryPercentage: 0.7 }] },
        options: {
          indexAxis: 'y',
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { y: { ticks: { autoSkip: false, font: { size: 11 } } }, x: { ticks: { precision: 0 } } }
        }
      });
      <?php endif; ?>

      <?php if (!empty($reason_labels)): ?>
      new Chart(document.getElementById('reasonsPctChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($reason_labels) ?>, datasets: [{ data: <?= json_encode($reason_pcts) ?>, backgroundColor: donutPalette, borderColor: '#0f151d', borderWidth: 3 }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.raw + '%' } },
            datalabels: { display: false }
          }
        }
      });
      <?php endif; ?>

      new Chart(document.getElementById('callsMonthlyEvoChart'), {
        type: 'line',
        data: { labels: <?= json_encode($calls_evo_labels) ?>, datasets: [{ label: 'Llamadas', data: <?= json_encode($calls_evo_values) ?>, borderColor: '#4fa3a0', backgroundColor: 'rgba(79, 163, 160, 0.12)', fill: true, tension: 0.3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
      });

      <?php if (!empty($weekday_values) && $weekday_max > 0): ?>
      new Chart(document.getElementById('weekdayCallsChart'), {
        type: 'bar',
        data: {
          labels: <?= json_encode($weekday_labels) ?>,
          datasets: [{
            label: 'Llamadas',
            data: <?= json_encode($weekday_values) ?>,
            backgroundColor: <?= json_encode(array_map(fn($v) => $v == $weekday_max ? '#c9a24d' : ($v == $weekday_min_nonzero ? '#e06c75' : '#4fa3a0'), $weekday_values)) ?>,
            borderRadius: 6, barPercentage: 0.4, categoryPercentage: 0.5
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ctx.raw + ' llamadas' } }
          },
          scales: { x: { ticks: { maxRotation: 0, minRotation: 0 } }, y: { ticks: { precision: 0 } } }
        }
      });
      <?php endif; ?>
    }

    // ===== TAB 3: FORENSE, DEMOGRAFÍA & RANGOS =====
    function initForenseCharts() {
      <?php if ($stat_count > 0): ?>
      new Chart(document.getElementById('lossRangesBarChart'), {
        type: 'line',
        data: { labels: <?= json_encode($range_labels) ?>, datasets: [{ label: 'Número de Casos', data: <?= json_encode($range_counts) ?>, borderColor: '#c9a24d', backgroundColor: 'rgba(201, 162, 77, 0.15)', fill: true, tension: 0.3, pointRadius: 4, pointBackgroundColor: '#c9a24d' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { title: { display: true, text: 'Cantidad de Casos' } } } }
      });
      new Chart(document.getElementById('lossRangesDonutChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($range_labels) ?>, datasets: [{ data: <?= json_encode($range_pcts) ?>, backgroundColor: palette, borderColor: '#0f151d', borderWidth: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
      });
      <?php endif; ?>

      <?php if ($total_victims > 0): ?>
      new Chart(document.getElementById('victimAgeChart'), {
        type: 'pie',
        data: { labels: <?= json_encode($age_labels) ?>, datasets: [{ data: <?= json_encode($age_pcts) ?>, backgroundColor: ['#4fa3a0', '#c9a24d', '#e06c75', '#98c379', '#61afef', '#c678dd', '#f39c12'], borderColor: '#0f151d', borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
      });
      new Chart(document.getElementById('victimGenderChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($gender_labels) ?>, datasets: [{ data: <?= json_encode($gender_values) ?>, backgroundColor: ['#61afef', '#dfba69', '#c678dd'], borderColor: '#0f151d', borderWidth: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
      });
      new Chart(document.getElementById('victimLossByAgeChart'), {
        type: 'line',
        data: { labels: <?= json_encode($age_labels) ?>, datasets: [{ label: 'Pérdida Promedio ($ MXN)', data: <?= json_encode($age_avg_loss) ?>, borderColor: '#c678dd', backgroundColor: 'rgba(198, 120, 221, 0.15)', fill: true, tension: 0.3, pointRadius: 4, pointBackgroundColor: '#c678dd' }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: val => '$' + (val/1000) + 'k' } } } }
      });
      <?php endif; ?>
    }

    // ===== TAB 4: ANÁLISIS GEOGRÁFICO =====
    function initGeoCharts() {
      <?php if (!empty($geo)): ?>
      new Chart(document.getElementById('geoCasesChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_map(fn($g) => $g['state_name'], $geo)) ?>, datasets: [{ label: 'Número de Víctimas', data: <?= json_encode(array_map(fn($g) => (int)$g['c'], $geo)) ?>, backgroundColor: '#c9a24d', borderRadius: 6, barPercentage: 0.5, categoryPercentage: 0.6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
      });
      new Chart(document.getElementById('geoAmountChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_map(fn($g) => $g['state_name'], $geo_by_amount)) ?>, datasets: [{ label: 'Monto Total ($ MXN)', data: <?= json_encode(array_map(fn($g) => round($g['total_amt'], 2), $geo_by_amount)) ?>, backgroundColor: '#e06c75', borderRadius: 6, barPercentage: 0.5, categoryPercentage: 0.6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
      });
      <?php endif; ?>
    }

    // ===== TAB 5: MODALIDADES DE FRAUDE =====
    function initModalidadesCharts() {
      <?php if (!empty($evo_type_datasets) && $modalidades_total > 0): ?>
      new Chart(document.getElementById('modalidadesEvoChart'), {
        type: 'line',
        data: {
          labels: <?= json_encode($evo_type_labels) ?>,
          datasets: [
            <?php foreach ($evo_type_datasets as $i => $ds): ?>
            { label: <?= json_encode($ds['label']) ?>, data: <?= json_encode($ds['data']) ?>, borderColor: palette[<?= $i ?> % palette.length], backgroundColor: 'transparent', tension: 0.3 },
            <?php endforeach; ?>
          ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
      });
      <?php endif; ?>

      <?php if (!empty($types_by_loss)): ?>
      new Chart(document.getElementById('modalidadesLossChart'), {
        type: 'bar',
        data: {
          labels: <?= json_encode(array_map(fn($t) => $t['fraud_type'], $types_by_loss)) ?>,
          datasets: [{ label: 'Pérdida Total ($ MXN)', data: <?= json_encode(array_map(fn($t) => round($t['total_amt'], 2), $types_by_loss)) ?>, backgroundColor: '#e06c75', borderRadius: 6, barPercentage: 0.5, categoryPercentage: 0.6 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
      });
      <?php endif; ?>
    }

    // ===== TAB 7: FRAUDE (RESUMEN CONSOLIDADO) =====
    function initResumenFraudeCharts() {
      <?php if (!empty($gender_evo_datasets) && $total_victims > 0): ?>
      new Chart(document.getElementById('genderEvoChart'), {
        type: 'line',
        data: {
          labels: <?= json_encode($gender_evo_labels) ?>,
          datasets: [
            <?php foreach ($gender_evo_datasets as $i => $ds): ?>
            { label: <?= json_encode($ds['label']) ?>, data: <?= json_encode($ds['data']) ?>, borderColor: palette[<?= $i ?> % palette.length], backgroundColor: 'transparent', tension: 0.3 },
            <?php endforeach; ?>
          ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8 } } } }
      });
      <?php endif; ?>
    }

    // La pestaña "Resumen General" está visible desde que carga la página,
    // así que sus gráficas se inicializan de inmediato.
    initChartsForTab('general');
      window.addEventListener('resize', () => {
      document.querySelectorAll('.tab-content.active').forEach(tab => refreshChartCardMetrics(tab.id.replace('tab-', '')));
    });

</script>
</body>
</html>
