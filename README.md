# 🦁 León SA de CV — Sistema de Inteligencia Estadística & Control de Fraudes

> **Sistema estructurado de recopilación, análisis estadístico y visualización de datos de operaciones de Call Center y detección de patrones de fraude.**

---

## 📌 Descripción del Proyecto

El presente sistema tiene como finalidad recopilar, organizar, analizar y presentar información estadística estratégica relacionada con las operaciones del Call Center y la prevención de fraudes financieros.

El software transforma datos operativos sin procesar en **inteligencia de negocio accionable**, permitiendo identificar:
* **Volumen y Demanda:** Períodos de mayor y menor volumen de llamadas, promedios diarios y mensuales.
* **Comportamiento Temporal:** Horarios pico, franjas críticas de saturación y distribución entre mañana, tarde, noche y madrugada.
* **Distribución Geográfica:** Estados de procedencia y participación porcentual de llamadas y casos.
* **Análisis de Fraude:** Tipologías de estafa, comportamiento de incidentes, montos económicos afectados y tasas de recuperación.
* **Perfil de Víctimas:** Segmentación sociodemográfica (rangos de edad, género) y canales de ataque (Llamada, WhatsApp/SMS, Phishing).

---

## 🚀 Módulos y Características

### 1. 🔐 Autenticación Corporativa
* Pantallas de **Registro (`register.php`)** e **Inicio de Sesión (`login.php`)** con cifrado seguro de contraseñas (`BCRYPT`).
* Interfaz con tema **Luxury Dark & Gold** y escudo oficial de León SA de CV.

### 2. 📊 Dashboard de Inteligencia (3 Vistas Interactivas)
* **📊 Resumen General:** KPIs ejecutivos, gráficos combinados de volumen vs. fraude, categorización y tabla de incidentes recientes.
* **📞 Análisis del Call Center:**
  - Curva de demanda de 24 horas y detección de franjas críticas (11:00 - 13:30 hrs).
  - Distribución por franjas: Mañana (42%), Tarde (38%), Noche (16%), Madrugada (4%).
  - Patrón de días de la semana (Lunes a Domingo) y detección de picos en quincenas (+28.5%).
  - Procedencia geográfica con porcentaje de participación.
* **🛡️ Análisis Forense de Fraudes & Víctimas:**
  - Alerta en tiempo real de vectores emergentes (Phishing vía WhatsApp).
  - Impacto económico en \$ MXN por tipología de estafa.
  - Análisis de canales de ataque y pérdidas monetarias por estado.
  - Perfil de grupos etarios más vulnerables (30-49 años con 46% de afectación).

---

## 🛠️ Tecnologías Utilizadas

* **Backend:** PHP 8.x
* **Base de Datos:** MySQL / MariaDB
* **Frontend:** HTML5 semántico, CSS3 moderno (Variables CSS, Flexbox, Grid)
* **Visualización de Datos:** [Chart.js](https://www.chartjs.org/) (Gráficos interactivos de líneas, barras, donas y áreas)
* **Tipografía:** Google Fonts (*Inter* & *IBM Plex Mono*)
* **Entorno de Servidor:** Laragon / Apache

---

## 💻 Instalación y Uso Local

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/hdzisai91-sudo/leon-callcenter-analytics.git