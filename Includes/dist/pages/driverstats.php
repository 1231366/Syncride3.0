<?php
// =================================================================
// 1. INÍCIO DO BLOCO PHP - BUSCAR TODOS OS DADOS
// =================================================================
session_start();
require __DIR__ . '/../../../auth/dbconfig.php'; // Inclui a configuração do banco de dados

// Proteger a página e obter o ID do utilizador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 2) {
    header("refresh: 1; url=../../../index.php");
    exit();
}
$userId = $_SESSION['user_id'];

// --- DADOS PARA AS "SMALL BOXES" ---
$viagensTotal = 0;
$viagensUltimoMes = 0;
$totalViagensAno = 0;
$mesMaisAtivo = '-';
$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

try {
    // 1. Total de viagens feitas (desde sempre)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE sr.UserID = ? AND s.serviceDate <= CURDATE()");
    $stmt->execute([$userId]);
    $viagensTotal = $stmt->fetchColumn();

    // 2. Viagens no último mês
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE sr.UserID = ? AND s.serviceDate BETWEEN CURDATE() - INTERVAL 1 MONTH AND CURDATE()");
    $stmt->execute([$userId]);
    $viagensUltimoMes = $stmt->fetchColumn();

    // --- DADOS PARA O GRÁFICO E CARDS DO ANO ---
    
    // Obter o ano a partir do URL (?year=...). Se não existir, usa o ano atual.
    $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

    // Inicializar os dados de todos os meses a zero
    $monthly_data = array_fill(1, 12, 0); // Chaves de 1 a 12

    // Buscar dados mensais para o ano selecionado
    $sql = "SELECT MONTH(s.serviceDate) AS mes, COUNT(sr.associationID) AS total
            FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID
            WHERE sr.UserID = :userId AND YEAR(s.serviceDate) = :year AND s.serviceDate <= CURDATE()
            GROUP BY mes";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['userId' => $userId, 'year' => $selectedYear]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $monthly_data[(int)$row['mes']] = (int)$row['total'];
    }

    // Buscar todos os anos disponíveis para o seletor
    $stmt_years = $pdo->prepare("SELECT DISTINCT YEAR(s.serviceDate) as ano FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID WHERE sr.UserID = :userId ORDER BY ano DESC");
    $stmt_years->execute(['userId' => $userId]);
    $available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    
    // Garantir que o ano atual está na lista se não houver viagens
    if (empty($available_years)) {
        $available_years[] = date('Y');
    }

    // Calcular stats para as caixas a partir dos dados do gráfico
    $totalViagensAno = array_sum($monthly_data);
    $maxViagensMes = max($monthly_data);
    if ($maxViagensMes > 0) {
        $mesIndex = array_search($maxViagensMes, $monthly_data); // A chave é 1-12
        $mesMaisAtivo = $meses_nomes[$mesIndex - 1]; // Ajustar para array 0-11
    }

    // Preparar os dados para passar ao JavaScript (Chart.js precisa de um array 0-indexed)
    $dashboard_data_for_js = [
        'labels' => $meses_nomes,
        'data' => array_values($monthly_data), // Converte [1=>0, 2=>5,...] para [0, 5,...]
        'available_years' => $available_years,
        'selected_year' => $selectedYear
    ];

} catch (PDOException $e) {
    die("Erro ao buscar dados do dashboard: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Estatísticas - Painel de Condutor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    
    <style>
      .card-header.bg-gradient-driver {
        background: linear-gradient(90deg, #00b0ff, #7f00ff);
        color: white;
      }
      .year-selector {
        padding: 4px 8px;
        font-size: 0.9rem;
        border-radius: 8px;
        border: 1px solid #ddd;
        background-color: white;
        color: #333;
      }

      /* --- BOTTOM NAVIGATION BAR (Igual ao driver.php) --- */
      .bottom-nav {
          position: fixed; bottom: 0; left: 0; width: 100%; height: 65px;
          background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
          display: flex; justify-content: space-around; align-items: center;
          z-index: 1000; border-top: 1px solid #eee;
      }
      .nav-item-mobile {
          display: flex; flex-direction: column; align-items: center; justify-content: center;
          color: #adb5bd; text-decoration: none; font-size: 0.75rem; width: 100%; height: 100%;
      }
      .nav-item-mobile i { font-size: 1.4rem; margin-bottom: 2px; transition: transform 0.2s; }
      .nav-item-mobile.active { color: #00b0ff; font-weight: 600; }
      .nav-item-mobile.active i { transform: translateY(-2px); }
      
      /* Ajuste para o conteúdo não ficar escondido atrás do menu */
      .app-content { padding-bottom: 80px; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li></ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="../../dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User Image" />
                <span class="d-none d-md-inline"><?php echo $_SESSION['name']; ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="../../dist/assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User Image" />
                  <p><?php echo $_SESSION['name']; ?> - Condutor</p>
                </li>
                <li class="user-footer">
                  <a href="driver.php" class="btn btn-default btn-flat">Painel</a>
                  <a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="#" class="brand-link"><span class="brand-text fw-light">SyncRide</span></a></div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                <li class="nav-item">
                    <a href="driver.php" class="nav-link">
                        <i class="nav-icon bi bi-car-front"></i>
                        <p>Minhas Viagens</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="driver_agenda.php" class="nav-link">
                        <i class="nav-icon bi bi-calendar3"></i>
                        <p>Minha Agenda</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="driverstats.php" class="nav-link active">
                        <i class="nav-icon bi bi-graph-up"></i>
                        <p>Estatísticas</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link text-danger">
                        <i class="nav-icon bi bi-box-arrow-right"></i>
                        <p>Sair</p>
                    </a>
                </li>
            </ul>
          </nav>
        </div>
      </aside>

      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0">Minhas Estatísticas</h3></div>
              <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item"><a href="driver.php">Painel</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Estatísticas</li>
                </ol>
              </div>
            </div>
          </div>
        </div>
        
        <div class="app-content">
          <div class="container-fluid">
            
            <div class="row">
              <div class="col-lg-3 col-6">
                  <div class="small-box text-bg-info">
                      <div class="inner">
                          <h3><?php echo $viagensTotal; ?></h3>
                          <p>Viagens Totais<br>(Desde Sempre)</p>
                      </div>
                      <div class="small-box-icon"><i class="bi bi-archive"></i></div>
                  </div>
              </div>
              <div class="col-lg-3 col-6">
                  <div class="small-box text-bg-success">
                      <div class="inner">
                          <h3><?php echo $viagensUltimoMes; ?></h3>
                          <p>Viagens<br>(Últimos 30 dias)</p>
                      </div>
                      <div class="small-box-icon"><i class="bi bi-calendar-month"></i></div>
                  </div>
              </div>
              <div class="col-lg-3 col-6">
                  <div class="small-box text-bg-primary">
                      <div class="inner">
                          <h3><?php echo $totalViagensAno; ?></h3>
                          <p>Viagens Totais<br>(Ano: <?php echo $selectedYear; ?>)</p>
                      </div>
                      <div class="small-box-icon"><i class="bi bi-calendar-check"></i></div>
                  </div>
              </div>
              <div class="col-lg-3 col-6">
                  <div class="small-box text-bg-warning">
                      <div class="inner">
                          <h3><?php echo $mesMaisAtivo; ?></h3>
                          <p>Mês de Topo<br>(Ano: <?php echo $selectedYear; ?>)</p>
                      </div>
                      <div class="small-box-icon"><i class="bi bi-graph-up-arrow"></i></div>
                  </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-12">
                <div class="card mb-4 shadow-lg border-0 rounded-4">
                    <div class="card-header text-center bg-gradient-driver rounded-top-4 d-flex justify-content-between align-items-center p-3">
                        <h3 class="card-title fw-bold mb-0" style="font-size: 1.25rem;">
                            📊 Volume de Viagens
                        </h3>
                        <select id="year-selector" class="year-selector"></select>
                    </div>
                    
                    <div class="card-body p-4" style="background: #f1f5f9;">
                        <div class="chart-container" style="background: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);">
                            <canvas id="monthlyTripsChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
              </div>
            </div>
            
          </div>
        </div>
      </main>
      
      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline"></div>
        SyncRide All rights reserved.
      </footer>

      <nav class="bottom-nav d-flex d-md-none">
          <a href="driver.php" class="nav-item-mobile">
              <i class="bi bi-car-front-fill"></i>
              <span>Viagens</span>
          </a>
          <a href="driver_agenda.php" class="nav-item-mobile">
              <i class="bi bi-calendar3"></i>
              <span>Agenda</span>
          </a>
          <a href="driverstats.php" class="nav-item-mobile active">
              <i class="bi bi-bar-chart-fill"></i>
              <span>Stats</span>
          </a>
          <a href="logout.php" class="nav-item-mobile text-danger">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sair</span>
          </a>
      </nav>

    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <script src="../../dist/js/adminlte.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const dashboardData = <?php echo json_encode($dashboard_data_for_js); ?>;
        const yearSelector = document.getElementById('year-selector');
        let myChart = null;

        function renderChart(labels, data, year) {
            const ctx = document.getElementById('monthlyTripsChart').getContext('2d');
            if (myChart) myChart.destroy();
            
            myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nº de Viagens',
                        data: data,
                        backgroundColor: 'rgba(0, 123, 255, 0.7)',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    plugins: { legend: { display: false }, title: { display: false } }
                }
            });
        }

        dashboardData.available_years.forEach(y => {
            const option = new Option(y, y);
            if (y == dashboardData.selected_year) option.selected = true;
            yearSelector.add(option);
        });
        
        yearSelector.addEventListener('change', function() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('year', this.value);
            window.location.href = currentUrl.toString();
        });

        renderChart(dashboardData.labels, dashboardData.data, dashboardData.selected_year);
    });
    </script>
  </body>
</html>