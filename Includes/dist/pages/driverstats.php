<?php
// =================================================================
// 1. LÓGICA PHP (PRESERVADA)
// =================================================================
session_start();
require __DIR__ . '/../../../auth/dbconfig.php'; 

// Segurança
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 2) {
    header("refresh: 1; url=../../../index.php");
    exit();
}
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'];

// --- A. Lógica da Foto de Perfil (Para o Header Novo) ---
$userPhotoPath = '../../dist/assets/img/user2-160x160.jpg'; 
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhotoPath = '../../../' . $_SESSION['profile_photo_path'];
} else {
    try {
        $stmt = $pdo->prepare("SELECT profile_photo_path FROM Users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['profile_photo_path'])) {
            $userPhotoPath = '../../../' . $result['profile_photo_path'];
            $_SESSION['profile_photo_path'] = $result['profile_photo_path'];
        }
    } catch (PDOException $e) {}
}

// --- B. Estatísticas ---
$viagensTotal = 0;
$viagensUltimoMes = 0;
$totalViagensAno = 0;
$mesMaisAtivo = '-';
$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

try {
    // 1. Total de viagens (Sempre)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE sr.UserID = ? AND s.serviceDate <= CURDATE()");
    $stmt->execute([$userId]);
    $viagensTotal = $stmt->fetchColumn();

    // 2. Viagens no último mês
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE sr.UserID = ? AND s.serviceDate BETWEEN CURDATE() - INTERVAL 1 MONTH AND CURDATE()");
    $stmt->execute([$userId]);
    $viagensUltimoMes = $stmt->fetchColumn();

    // 3. Dados do Gráfico
    $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $monthly_data = array_fill(1, 12, 0); 

    $sql = "SELECT MONTH(s.serviceDate) AS mes, COUNT(sr.RideID) AS total
            FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID
            WHERE sr.UserID = :userId AND YEAR(s.serviceDate) = :year AND s.serviceDate <= CURDATE()
            GROUP BY mes";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['userId' => $userId, 'year' => $selectedYear]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $monthly_data[(int)$row['mes']] = (int)$row['total'];
    }

    // Anos disponíveis
    $stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(s.serviceDate) as ano FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID WHERE sr.UserID = :userId ORDER BY ano DESC");
    $stmt_years->execute(['userId' => $userId]);
    $available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    if (empty($available_years)) { $available_years[] = date('Y'); }

    // Stats adicionais baseadas no gráfico
    $totalViagensAno = array_sum($monthly_data);
    $maxViagensMes = max($monthly_data);
    if ($maxViagensMes > 0) {
        $mesIndex = array_search($maxViagensMes, $monthly_data);
        $mesMaisAtivo = $meses_nomes[$mesIndex - 1]; 
    }

    $dashboard_data_for_js = [
        'labels' => $meses_nomes,
        'data' => array_values($monthly_data),
        'available_years' => $available_years,
        'selected_year' => $selectedYear
    ];

} catch (PDOException $e) { $viagensTotal = 0; }
?>

<!doctype html>
<html lang="pt" data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <title>Estatísticas | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    
    <style>
        /* --- DESIGN SYSTEM (Unificado) --- */
        :root {
            --font-primary: 'Inter', sans-serif;
            --font-display: 'Poppins', sans-serif;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --primary-accent: #4f46e5;
            --primary-hover: #4338ca;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --radius-md: 16px;

            /* UPDATE: Safe Area Variables */
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f9fafb;
            --text-muted: #94a3b8;
            --primary-accent: #6366f1;
            --primary-hover: #818cf8;
            --border-color: #334155;
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            /* UPDATE: Padding ajustado para Safe Area */
            padding-bottom: calc(80px + var(--safe-bottom));
            padding-top: 0;
            margin: 0;
        }

        /* --- HEADER --- */
        .app-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            /* UPDATE: Padding superior com Safe Top */
            padding: calc(15px + var(--safe-top)) 20px 15px 20px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 1020;
        }
        .brand-logo { height: 30px; width: auto; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }

        /* --- STAT CARDS (Novo Estilo) --- */
        .stat-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 15px;
            display: flex; align-items: center; gap: 15px;
            box-shadow: var(--shadow-sm);
            height: 100%;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        
        /* Cores dos Ícones */
        .bg-indigo-soft { background: rgba(79, 70, 229, 0.1); color: var(--primary-accent); }
        .bg-emerald-soft { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-amber-soft { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .bg-pink-soft { background: rgba(236, 72, 153, 0.1); color: #ec4899; }

        .stat-info { display: flex; flex-direction: column; justify-content: center; }
        .stat-info h3 { font-family: var(--font-display); font-weight: 700; font-size: 1.4rem; margin: 0; line-height: 1; color: var(--text-main); }
        .stat-info p { margin: 3px 0 0 0; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2; }

        /* --- CHART CARD --- */
        .chart-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .chart-title { font-family: var(--font-display); font-weight: 700; font-size: 1.1rem; color: var(--text-main); margin: 0; }
        
        .year-select {
            border: 1px solid var(--border-color);
            background-color: var(--bg-body);
            color: var(--text-main);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 0.9rem;
            outline: none;
            font-weight: 600;
        }

        /* --- BOTTOM NAV --- */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; 
            /* UPDATE: Altura e Padding com Safe Area */
            height: calc(70px + var(--safe-bottom));
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            display: flex; justify-content: space-around; 
            align-items: flex-start;
            z-index: 1030; 
            padding-bottom: var(--safe-bottom);
            padding-top: 10px;
        }
        .nav-item-mobile {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 500;
            width: 100%; height: 50px; transition: color 0.2s;
        }
        .nav-item-mobile i { font-size: 1.5rem; margin-bottom: 4px; }
        .nav-item-mobile.active { color: var(--primary-accent); }

    </style>
  </head>
  <body>
    
    <header class="app-header">
        <img src="../../../assets/images/icons/SyncRide.png" alt="SyncRide" class="brand-logo" id="header-logo">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-muted p-0" id="theme-toggle"><i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i></button>
            <img src="<?php echo $userPhotoPath; ?>" class="user-avatar shadow-sm" alt="User">
        </div>
    </header>

    <div class="container-fluid px-3 pt-3">
        
        <h4 class="fw-bold mb-3 text-main">Performance 📊</h4>

        <div class="row g-3 mb-4">
            
            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon bg-indigo-soft"><i class="bi bi-archive"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $viagensTotal; ?></h3>
                        <p>Total Geral</p>
                    </div>
                </div>
            </div>

            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon bg-emerald-soft"><i class="bi bi-calendar-check"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $viagensUltimoMes; ?></h3>
                        <p>30 Dias</p>
                    </div>
                </div>
            </div>

            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon bg-amber-soft"><i class="bi bi-calendar3"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalViagensAno; ?></h3>
                        <p>Ano <?php echo $selectedYear; ?></p>
                    </div>
                </div>
            </div>

            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon bg-pink-soft"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $mesMaisAtivo; ?></h3>
                        <p>Melhor Mês</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h5 class="chart-title">Evolução Mensal</h5>
                <select id="year-selector" class="year-select"></select>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="monthlyTripsChart"></canvas>
            </div>
        </div>

    </div>

    <nav class="bottom-nav">
        <a href="driver.php" class="nav-item-mobile"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="driver_agenda.php" class="nav-item-mobile"><i class="bi bi-calendar3"></i><span>Agenda</span></a>
        <a href="driverstats.php" class="nav-item-mobile active"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="logout.php" class="nav-item-mobile text-danger"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // 1. Theme Logic
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        const headerLogo = document.getElementById('header-logo');
        const logoDark = "../../../assets/images/icons/SyncRide.png"; 
        const logoLight = "../../../assets/images/icons/Syncridewhite.png";

        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme);
        updateLogo(savedTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = htmlElement.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            updateLogo(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
        }
        function updateLogo(theme) {
            const newSrc = theme === 'dark' ? logoLight : logoDark;
            if(headerLogo) headerLogo.src = newSrc;
        }

        // 2. Chart Logic
        document.addEventListener('DOMContentLoaded', function () {
            const dashboardData = <?php echo json_encode($dashboard_data_for_js); ?>;
            const yearSelector = document.getElementById('year-selector');
            let myChart = null;
            
            // Populate Selector
            dashboardData.available_years.forEach(y => {
                const option = new Option(y, y);
                if (y == dashboardData.selected_year) option.selected = true;
                yearSelector.add(option);
            });
            
            // Selector Event
            yearSelector.addEventListener('change', function() {
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('year', this.value);
                window.location.href = currentUrl.toString();
            });

            // Render Chart
            const ctx = document.getElementById('monthlyTripsChart').getContext('2d');
            
            // Cores do gráfico ajustadas ao tema (Azul Indigo)
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(79, 70, 229, 0.5)'); // Indigo
            gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

            myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dashboardData.labels,
                    datasets: [{
                        label: 'Viagens',
                        data: dashboardData.data,
                        backgroundColor: gradient,
                        borderColor: '#4f46e5',
                        borderWidth: 2,
                        borderRadius: 6,
                        barThickness: 'flex',
                        maxBarThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(200, 200, 200, 0.1)' },
                            ticks: { font: { family: 'Inter' } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Inter' } }
                        }
                    },
                    plugins: { 
                        legend: { display: false }
                    }
                }
            });
        });
    </script>
  </body>
</html>