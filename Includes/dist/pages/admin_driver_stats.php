<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require __DIR__ . '/../../../auth/dbconfig.php';

// =================================================================
// 2. LÓGICA DE DADOS
// =================================================================

$driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : null;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$page_title = "Estatísticas Gerais";
$driver_name = "Visão Geral";
$meses_nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

try {
    $available_drivers = $pdo->query("SELECT ID, name FROM Users WHERE role = 2 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $available_years = $pdo->query("SELECT DISTINCT YEAR(serviceDate) as ano FROM Services ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($available_years)) {
        $available_years[] = date('Y');
    }
} catch (PDOException $e) {
    die("Erro BD: " . $e->getMessage());
}

// Valores padrão (placeholder)
$box1_val = 0; $box1_lbl = "-"; $box1_icon = "bi-person-check-fill"; $box1_color = "primary";
$box2_val = 0; $box2_lbl = "-"; $box2_icon = "bi-calendar-day"; $box2_color = "info";
$box3_val = 0; $box3_lbl = "-"; $box3_icon = "bi-calendar-month"; $box3_color = "warning";
$box4_val = 0; $box4_lbl = "-"; $box4_icon = "bi-bar-chart-fill"; $box4_color = "success";

$chart_labels = $meses_nomes;
$chart_data = array_fill(0, 12, 0);
$table_data = [];
$table_title = "";
$table_is_leaderboard = false;


if ($driver_id) {
    // --- VISTA INDIVIDUAL (Um Condutor) ---
    try {
        $stmt = $pdo->prepare("SELECT name FROM Users WHERE ID = ? AND role = 2");
        $stmt->execute([$driver_id]);
        $driver_name = $stmt->fetchColumn();
        if (!$driver_name) { header("Location: admin_driver_stats.php"); exit(); }
        $page_title = "Atividade: " . htmlspecialchars($driver_name);

        // Caixas
        $stmt = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ? AND s.serviceDate = CURDATE()) AS trips_today,
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ? AND YEARWEEK(s.serviceDate, 1) = YEARWEEK(CURDATE(), 1)) AS trips_week,
            (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ? AND YEAR(s.serviceDate) = ? AND MONTH(s.serviceDate) = MONTH(CURDATE())) AS trips_month_current,
            (SELECT COUNT(*) FROM Services_Rides sr WHERE sr.UserID = ?) AS trips_total");
        $stmt->execute([$driver_id, $driver_id, $driver_id, $selectedYear, $driver_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $box1_val = $stats['trips_today']; $box1_lbl = "Viagens Hoje"; $box1_icon = "bi-calendar-day"; $box1_color = "info";
        $box2_val = $stats['trips_week'];  $box2_lbl = "Viagens Semana"; $box2_icon = "bi-calendar-week"; $box2_color = "primary";
        $box3_val = $stats['trips_month_current']; $box3_lbl = "Viagens Mês (Atual)"; $box3_icon = "bi-calendar-month"; $box3_color = "warning";
        $box4_val = $stats['trips_total']; $box4_lbl = "Total Geral"; $box4_icon = "bi-bar-chart-fill"; $box4_color = "success";

        // Gráfico
        $sqlChart = "SELECT MONTH(s.serviceDate) AS mes, COUNT(sr.associationID) AS total FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID WHERE sr.UserID = ? AND YEAR(s.serviceDate) = ? GROUP BY mes";
        $stmtChart = $pdo->prepare($sqlChart);
        $stmtChart->execute([$driver_id, $selectedYear]);
        $monthly_results = $stmtChart->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($monthly_results as $mes => $total) { $chart_data[$mes - 1] = (int)$total; }

        // Tabela (Histórico)
        $table_title = "Histórico Recente";
        $sqlTable = "SELECT s.ID, s.serviceDate, s.serviceStartTime, s.serviceStartPoint, s.serviceTargetPoint, s.serviceType FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE sr.UserID = ? ORDER BY s.serviceDate DESC, s.serviceStartTime DESC LIMIT 20";
        $stmtTable = $pdo->prepare($sqlTable);
        $stmtTable->execute([$driver_id]);
        $table_data = $stmtTable->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { die("Erro: " . $e->getMessage()); }

} else {
    // --- VISTA GERAL (Todos) ---
    $page_title = "Visão Geral";
    $table_title = "Leaderboard ($selectedYear)";
    $table_is_leaderboard = true;

    try {
        // Caixas
        $box1_val = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 2")->fetchColumn(); $box1_lbl = "Condutores Ativos"; $box1_icon = "bi-people-fill"; $box1_color = "primary";
        $box2_val = $pdo->query("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE s.serviceDate = CURDATE()")->fetchColumn(); $box2_lbl = "Total Hoje"; $box2_icon = "bi-calendar-day"; $box2_color = "info";
        $box3_val = $pdo->query("SELECT COUNT(*) FROM Services s JOIN Services_Rides sr ON s.ID = sr.RideID WHERE YEAR(s.serviceDate) = YEAR(CURDATE()) AND MONTH(s.serviceDate) = MONTH(CURDATE())")->fetchColumn(); $box3_lbl = "Total Mês"; $box3_icon = "bi-calendar-month"; $box3_color = "warning";
        $box4_val = $pdo->query("SELECT COUNT(*) FROM Services_Rides")->fetchColumn(); $box4_lbl = "Total Geral"; $box4_icon = "bi-bar-chart-fill"; $box4_color = "success";
        
        // Gráfico
        $sqlChart = "SELECT MONTH(s.serviceDate) AS mes, COUNT(sr.associationID) AS total FROM Services_Rides AS sr JOIN Services AS s ON sr.RideID = s.ID WHERE YEAR(s.serviceDate) = ? GROUP BY mes";
        $stmtChart = $pdo->prepare($sqlChart);
        $stmtChart->execute([$selectedYear]);
        $monthly_results = $stmtChart->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($monthly_results as $mes => $total) { $chart_data[$mes - 1] = (int)$total; }

        // Tabela (Leaderboard)
        $sqlTable = "SELECT u.ID, u.name, (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = u.ID AND s.serviceDate = CURDATE()) AS trips_today, (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = u.ID AND YEARWEEK(s.serviceDate, 1) = YEARWEEK(CURDATE(), 1)) AS trips_week, (SELECT COUNT(*) FROM Services_Rides sr JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = u.ID AND YEAR(s.serviceDate) = ?) AS trips_year, (SELECT COUNT(*) FROM Services_Rides sr WHERE sr.UserID = u.ID) AS trips_total FROM Users u WHERE u.role = 2 ORDER BY trips_year DESC, trips_total DESC, u.name ASC";
        $stmtTable = $pdo->prepare($sqlTable);
        $stmtTable->execute([$selectedYear]);
        $table_data = $stmtTable->fetchAll(PDO::FETCH_ASSOC);
        
        $top3 = array_slice($table_data, 0, 3);
        $rest_of_drivers = array_slice($table_data, 3);

    } catch (PDOException $e) { die("Erro: " . $e->getMessage()); }
}

$js_data = json_encode([
    'labels' => $chart_labels,
    'data' => $chart_data,
    'driver_name' => $driver_name,
    'year' => $selectedYear
]);
?>

<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?php echo $page_title; ?> - Admin SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    
    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 65px; }

        /* --- 1. CABEÇALHO --- */
        .app-header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1030;
            height: var(--header-height-base);
        }
        
        /* MOBILE APP (< 992px) */
        @media (max-width: 991.98px) {
            .app-header {
                padding-top: env(safe-area-inset-top);
                height: calc(var(--header-height-base) + env(safe-area-inset-top));
                background-color: #ffffff !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .app-sidebar, .navbar-toggler, .bi-list { display: none !important; }
            .app-main {
                margin-top: calc(var(--header-height-base) + env(safe-area-inset-top)) !important;
                padding-bottom: calc(var(--bottom-nav-height) + 20px + env(safe-area-inset-bottom)) !important;
            }
            .bottom-navbar { display: flex !important; }
        }

        /* WEB DESKTOP (>= 992px) */
        @media (min-width: 992px) {
            .bottom-navbar { display: none !important; }
            .app-main { margin-top: var(--header-height-base) !important; }
        }

        /* --- 2. BARRA INFERIOR --- */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            background: #ffffff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: none; justify-content: space-around; align-items: flex-start;
            padding-top: 10px; padding-bottom: env(safe-area-inset-bottom);
            z-index: 1040;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .nav-item-bottom {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500;
            transition: all 0.3s ease; width: 20%;
        }
        .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; transition: transform 0.2s; }
        .nav-item-bottom.active { color: #0d6efd; }
        .nav-item-bottom.active i { transform: translateY(-3px); }

        /* --- 3. ÍCONES DAS CAIXAS (CORREÇÃO) --- */
        .small-box { position: relative; overflow: hidden; border-radius: 0.5rem; box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2); margin-bottom: 20px; }
        .small-box .icon {
            position: absolute; top: 10px; right: 10px; z-index: 0;
            font-size: 60px; color: rgba(0, 0, 0, 0.15); transition: all 0.3s linear;
        }
        .small-box:hover .icon { transform: scale(1.1); }
        .small-box .inner { position: relative; z-index: 1; padding: 15px; color: white; }

        /* --- OUTROS --- */
        .list-group-item-action:hover { background-color: #f8f9fa; }
        .leaderboard-name { font-weight: 600; color: #0d6efd; text-decoration: none; }
        .card-header-custom { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; padding: 1rem; }
        .trip-card-private { border-left: 4px solid #007bff; }
        .trip-card-shared { border-left: 4px solid #ffc107; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list" style="font-size: 1.5rem;"></i>
              </a>
            </li>
            <li class="nav-item d-lg-none ms-2">
                <span class="fw-bold fs-5">Estatísticas</span>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User" />
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User" />
                  <p><?php echo $_SESSION['name']; ?> - Admin</p>
                </li>
                <li class="user-footer"><a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
          <a href="./admin.php" class="brand-link">
            <img src="https://syncride.webminds.pt/Includes/dist/assets/img/AdminLTELogo.png" alt="Logo" class="brand-image opacity-75 shadow" />
            <span class="brand-text fw-light">SyncRide</span>
          </a>
        </div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
              <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i><p>Viagens</p></a></li>
              <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Funcionários</p></a></li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link active"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-archive-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar">
        <a href="admin.php" class="nav-item-bottom"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
        <a href="ManageRides.php" class="nav-item-bottom"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="admin_driver_stats.php" class="nav-item-bottom active"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="manageUsers.php" class="nav-item-bottom"><i class="bi bi-people-fill"></i><span>Staff</span></a>
        <a href="#" class="nav-item-bottom" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-grid-fill"></i><span>Mais</span></a>
      </div>

      <div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileMenu" style="height: 50vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header">
          <h5 class="offcanvas-title fw-bold">Menu Completo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="row g-3 text-center">
                <div class="col-4">
                    <a href="ManageNoShows.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-camera-fill fs-1 text-danger"></i><div class="small mt-2">No Shows</div>
                    </a>
                </div>
                <div class="col-4">
                    <a href="manageStorage.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-hdd-fill fs-1 text-warning"></i><div class="small mt-2">Storage</div>
                    </a>
                </div>
                <div class="col-4">
                    <a href="logout.php" class="d-block p-3 rounded bg-light text-decoration-none text-dark">
                        <i class="bi bi-box-arrow-right fs-1 text-secondary"></i><div class="small mt-2">Sair</div>
                    </a>
                </div>
            </div>
        </div>
      </div>

      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0"><?php echo $page_title; ?></h3></div>
              <div class="col-sm-6 d-none d-sm-block">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item"><a href="admin.php">Home</a></li>
                  <li class="breadcrumb-item active">Stats</li>
                </ol>
              </div>
            </div>
          </div>
        </div>

        <div class="app-content">
          <div class="container-fluid">
            
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header-custom">
                    <form method="GET" action="admin_driver_stats.php" class="d-flex align-items-center w-100 justify-content-end" id="filter-form">
                        <select name="driver_id" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                            <option value="">-- Todos os Condutores --</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?php echo $driver['ID']; ?>" <?php echo ($driver['ID'] == $driver_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($driver['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm me-2" onchange="this.form.submit()" style="max-width: 100px;">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if($driver_id): ?>
                            <a href="admin_driver_stats.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="row">
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-<?php echo $box1_color; ?>">
                  <div class="inner"><h3><?php echo $box1_val; ?></h3><p><?php echo $box1_lbl; ?></p></div>
                  <div class="icon"><i class="bi <?php echo $box1_icon; ?>"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-<?php echo $box2_color; ?>">
                  <div class="inner"><h3><?php echo $box2_val; ?></h3><p><?php echo $box2_lbl; ?></p></div>
                  <div class="icon"><i class="bi <?php echo $box2_icon; ?>"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-<?php echo $box3_color; ?>">
                  <div class="inner"><h3><?php echo $box3_val; ?></h3><p><?php echo $box3_lbl; ?></p></div>
                  <div class="icon"><i class="bi <?php echo $box3_icon; ?>"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-<?php echo $box4_color; ?>">
                  <div class="inner"><h3><?php echo $box4_val; ?></h3><p><?php echo $box4_lbl; ?></p></div>
                  <div class="icon"><i class="bi <?php echo $box4_icon; ?>"></i></div>
                </div>
              </div>
            </div>

            <div class="row">
                <div class="col-lg-7 connectedSortable">
                    <div class="card card-primary card-outline shadow-sm mb-4">
                        <div class="card-header border-0"><h3 class="card-title">Evolução em <?php echo $selectedYear; ?></h3></div>
                        <div class="card-body">
                            <div class="chart-container" style="min-height: 300px;">
                                <canvas id="mainChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-5 connectedSortable">
                    <div class="card card-success card-outline shadow-sm mb-4">
                        <div class="card-header border-0"><h3 class="card-title"><?php echo $table_title; ?></h3></div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($table_data)): ?>
                                    <div class="list-group-item text-center p-4">Sem dados disponíveis.</div>
                                <?php else: ?>
                                    <?php if ($table_is_leaderboard): ?>
                                        <?php for($i=0; $i<3; $i++): if(isset($top3[$i])): $medal = ($i==0?'🥇':($i==1?'🥈':'🥉')); $bg = ($i==0?'#fffaf0':($i==1?'#f8f9fa':'#fdf8f5')); ?>
                                            <div class="list-group-item p-3" style="background-color: <?=$bg?>;">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <div class="fs-5"><span class="me-2"><?=$medal?></span> 
                                                        <a href="admin_driver_stats.php?driver_id=<?=$top3[$i]['ID']?>&year=<?=$selectedYear?>" class="leaderboard-name text-dark">
                                                            <?=htmlspecialchars($top3[$i]['name'])?>
                                                        </a>
                                                    </div>
                                                    <span class="badge bg-warning fs-6"><?=$top3[$i]['trips_year']?></span>
                                                </div>
                                            </div>
                                        <?php endif; endfor; ?>
                                        <?php foreach ($rest_of_drivers as $key => $d): ?>
                                             <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <div><span class="me-2 text-muted"><?=$key + 4?>.</span> 
                                                        <a href="admin_driver_stats.php?driver_id=<?=$d['ID']?>&year=<?=$selectedYear?>" class="leaderboard-name text-secondary">
                                                            <?=htmlspecialchars($d['name'])?>
                                                        </a>
                                                    </div>
                                                    <span class="badge bg-light text-dark border"><?=$d['trips_year']?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php foreach ($table_data as $row): ?>
                                            <?php $border = $row['serviceType'] == 1 ? 'trip-card-private' : 'trip-card-shared'; ?>
                                            <div class="list-group-item border-start border-4 <?=$border?> px-3 py-2">
                                                <div class="d-flex justify-content-between">
                                                    <span class="fw-bold"><?=htmlspecialchars($row['serviceDate'])?></span>
                                                    <span class="text-primary"><?=substr($row['serviceStartTime'],0,5)?></span>
                                                </div>
                                                <small class="text-muted">
                                                    <?=htmlspecialchars($row['serviceStartPoint'])?> <i class="bi bi-arrow-right"></i> <?=htmlspecialchars($row['serviceTargetPoint'])?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
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
        <strong>SyncRide All rights reserved.</strong>
      </footer>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarWrapper = document.querySelector('.sidebar-wrapper');
            if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
                OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, { scrollbars: { theme: 'os-theme-light', autoHide: 'leave', clickScroll: true } });
            }

            // GRÁFICO
            const chartConfig = <?php echo $js_data; ?>;
            const ctx = document.getElementById('mainChart').getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line', 
                    data: {
                        labels: chartConfig.labels,
                        datasets: [{
                            label: 'Viagens',
                            data: chartConfig.data,
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        });
    </script>
  </body>
</html>