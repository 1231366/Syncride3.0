<?php
session_start();

// Verifica se o usuário está logado e tem a role de admin
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 1) {
    // Admin ok
} else {
    header("refresh: 1; url=../../../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Painel de Administrador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css" crossorigin="anonymous" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 65px; }

        /* --- 1. CABEÇALHO HÍBRIDO --- */
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

        /* --- 2. BARRA INFERIOR (MOBILE) --- */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            background: #ffffff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: none;
            justify-content: space-around;
            align-items: flex-start;
            padding-top: 10px;
            padding-bottom: env(safe-area-inset-bottom);
            z-index: 1040;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .nav-item-bottom {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500;
            transition: all 0.3s ease; width: 20%;
        }
        .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; }
        .nav-item-bottom.active { color: #0d6efd; }
        .nav-item-bottom.active i { transform: translateY(-3px); }

        /* --- 3. CORREÇÃO DOS ÍCONES DAS CAIXAS (Small Boxes) --- */
        .small-box { position: relative; overflow: hidden; }
        .small-box .icon {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 0;
            font-size: 60px; /* Tamanho grande recuperado */
            color: rgba(0, 0, 0, 0.15);
            transition: all 0.3s linear;
        }
        .small-box:hover .icon { transform: scale(1.1); }
        .small-box .inner { position: relative; z-index: 1; padding: 10px; }

        /* --- 4. UPLOAD AREA (Maior e mais bonita) --- */
        #drop-area {
            background-color: #007bff;
            border-radius: 15px;
            cursor: pointer;
            transition: 0.3s;
            border: 2px dashed rgba(255,255,255,0.3);
        }
        #drop-area:hover { background-color: #0056b3; border-color: rgba(255,255,255,0.6); }
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
                <span class="fw-bold fs-5">Dashboard</span>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User Image" />
                <span class="d-none d-md-inline"><?php  echo $_SESSION['name']; ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User Image" />
                  <p><?php require_once __DIR__ . '/../../../auth/dbconfig.php'; echo $_SESSION['name']; ?> - Admin</p>
                </li>
                <li class="user-footer">
                  <a href="#" class="btn btn-default btn-flat">Perfil</a>
                  <a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a>
                </li>
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
              <li class="nav-item"><a href="admin.php" class="nav-link active"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
              <li class="nav-item"><a href="ManageRides.php" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i><p>Viagens</p></a></li>
              <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Funcionários</p></a></li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-camera-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-archive-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar">
        <a href="admin.php" class="nav-item-bottom active"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
        <a href="ManageRides.php" class="nav-item-bottom"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="admin_driver_stats.php" class="nav-item-bottom"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
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
              <div class="col-sm-6"><h3 class="mb-0">Dashboard Geral</h3></div>
            </div>
          </div>
        </div>
        
        <?php
        require __DIR__ . '/../../../auth/dbconfig.php';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services"); $stmt->execute(); $totalTodasAsViagens = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE WEEK(serviceDate, 1) = WEEK(CURDATE(), 1) AND YEAR(serviceDate) = YEAR(CURDATE())"); $stmt->execute(); $totalViagensSemana = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE serviceDate = CURDATE()"); $stmt->execute(); $totalViagensHoje = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS totalConcluidas FROM Services s INNER JOIN Services_Rides sr ON s.ID = sr.RideID WHERE WEEK(s.serviceDate, 1) = WEEK(CURDATE(), 1) AND YEAR(s.serviceDate) = YEAR(CURDATE())"); $stmt->execute(); $res = $stmt->fetch(); $totalSemanaConcluidas = $res['totalConcluidas'] ?? 0;
        $percentagemSemanaConcluida = $totalViagensSemana > 0 ? round(($totalSemanaConcluidas / $totalViagensSemana) * 100, 2) : 0;

        $chartLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $monthlyDataCounts = array_fill(1, 12, 0);
        $sqlMensal = "SELECT MONTH(serviceDate) AS mes, COUNT(*) AS totalViagens FROM Services WHERE YEAR(serviceDate) = YEAR(CURDATE()) GROUP BY mes ORDER BY mes ASC";
        $stmtMensal = $pdo->prepare($sqlMensal);
        $stmtMensal->execute();
        $resultsMensal = $stmtMensal->fetchAll(PDO::FETCH_ASSOC);
        foreach ($resultsMensal as $row) { $monthlyDataCounts[(int)$row['mes']] = (int)$row['totalViagens']; }
        $chartData = array_values($monthlyDataCounts);
        ?>

        <div class="app-content">
          <div class="container-fluid">
            <div class="row">
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-primary">
                  <div class="inner"><h3><?= $totalTodasAsViagens ?></h3><p>Viagens Totais</p></div>
                  <div class="icon"><i class="bi bi-check-circle-fill"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-success">
                  <div class="inner"><h3><?= $totalViagensSemana ?></h3><p>Esta Semana</p></div>
                  <div class="icon"><i class="bi bi-calendar-week"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-warning">
                  <div class="inner"><h3><?= $percentagemSemanaConcluida ?>%</h3><p>Taxa Atribuição</p></div>
                  <div class="icon"><i class="bi bi-pie-chart-fill"></i></div>
                </div>
              </div>
              <div class="col-lg-3 col-6">
                <div class="small-box text-bg-danger">
                  <div class="inner"><h3><?= $totalViagensHoje ?></h3><p>Hoje</p></div>
                  <div class="icon"><i class="bi bi-calendar-day"></i></div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-7 connectedSortable d-none d-lg-block">
                <div class="card mb-4">
                  <div class="card-header"><h3 class="card-title">Volume Anual</h3></div>
                  <div class="card-body"><div id="revenue-chart"></div></div>
                </div>
              </div>
              
              <div class="col-lg-5 connectedSortable">
                <div class="card text-white bg-primary bg-gradient border-primary mb-4">
                  <div class="card-header border-0"><h3 class="card-title">Importar XML</h3></div>
                  <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                      <div class="text-center mb-4">
                        <div id="drop-area" class="drop-area p-5 text-center rounded-lg shadow-lg">
                          <i class="bi bi-cloud-upload fa-4x mb-3 text-white"></i>
                          <p class="text-white h5">Arraste ficheiro XML ou toque aqui</p>
                          <input type="file" name="xmlFile" id="xmlFile" accept=".xml" class="form-control d-none" required>
                          <div id="file-name" class="text-white mt-3 small">Nenhum selecionado</div>
                        </div>
                      </div>
                      <div class="d-grid gap-2"><button type="submit" class="btn btn-light btn-lg">Carregar Viagens</button></div>
                    </form>
                    <?php 
                        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['xmlFile']) && $_FILES['xmlFile']['error'] == 0) {
                            $xmlContent = file_get_contents($_FILES['xmlFile']['tmp_name']);
                            $xml = simplexml_load_string($xmlContent);
                            if($xml){
                                require_once __DIR__ . '/../../../auth/dbconfig.php';
                                $sql = "INSERT INTO Services (ID, serviceDate, serviceStartTime, paxADT, paxCHD, serviceStartPoint, serviceTargetPoint, FlightNumber, NomeCliente, ClientNumber, serviceType) VALUES (:ID, :serviceDate, :serviceStartTime, :paxADT, :paxCHD, :serviceStartPoint, :serviceTargetPoint, :FlightNumber, :NomeCliente, :ClientNumber, :serviceType) ON DUPLICATE KEY UPDATE serviceDate = VALUES(serviceDate), serviceStartTime = VALUES(serviceStartTime), paxADT = VALUES(paxADT), paxCHD = VALUES(paxCHD), serviceStartPoint = VALUES(serviceStartPoint), serviceTargetPoint = VALUES(serviceTargetPoint), FlightNumber = VALUES(FlightNumber), NomeCliente = VALUES(NomeCliente), ClientNumber = VALUES(ClientNumber), serviceType = VALUES(serviceType)";
                                $stmt = $pdo->prepare($sql);
                                $success = false;
                                if (isset($xml->Groupings->Grouping)) {
                                    foreach ($xml->Groupings->Grouping as $service) {
                                        $vehicleType = (string) $service->serviceUnitVehicleName;
                                        $serviceType = ($vehicleType == 'Taxi') ? 0 : 1;
                                        $serviceData = [
                                            'ID' => (string) $service->serviceId,
                                            'serviceDate' => (string) $service->serviceDate,
                                            'serviceStartTime' => (string) $service->serviceStartTime,
                                            'paxADT' => (int) $service->bookings->bookingItem->paxADT,
                                            'paxCHD' => (int) $service->bookings->bookingItem->paxCHD,
                                            'serviceStartPoint' => (string) $service->serviceStartPoint,
                                            'serviceTargetPoint' => (string) $service->serviceTargetPoint,
                                            'NomeCliente' => (string) $service->bookings->bookingItem->paxLeadName,
                                            'ClientNumber' => isset($service->bookings->bookingItem->remarks) ? (preg_match('/Phone number: (\+?\d+)/', (string)$service->bookings->bookingItem->remarks, $matches) ? $matches[1] : 'N/A') : 'N/A',
                                            'serviceType' => $serviceType
                                        ];
                                        $flightCodeNumber = 'N/A';
                                        if (isset($service->bookings->bookingItem->pickup->pickupPoint->flightNumber) && (string)$service->bookings->bookingItem->pickup->pickupPoint->flightNumber !== "") {
                                            $flightCodeNumber = (string)$service->bookings->bookingItem->pickup->pickupPoint->flightCompanyCode . ' ' . (string)$service->bookings->bookingItem->pickup->pickupPoint->flightNumber;
                                        } elseif (isset($service->bookings->bookingItem->dropoff->pickupPoint->flightNumber) && (string)$service->bookings->bookingItem->dropoff->pickupPoint->flightNumber !== "") {
                                            $flightCodeNumber = (string)$service->bookings->bookingItem->dropoff->pickupPoint->flightCompanyCode . ' ' . (string)$service->bookings->bookingItem->dropoff->pickupPoint->flightNumber;
                                        }
                                        $serviceData['FlightNumber'] = $flightCodeNumber;
                                        try { $stmt->execute($serviceData); $success = true; } catch (PDOException $e) {}
                                    }
                                }
                                if ($success) echo "<script>$(document).ready(function(){toastr.success('Sucesso!');});</script>";
                            }
                        }
                    ?>
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
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/js/jsvectormap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/maps/world.js" crossorigin="anonymous"></script>

    <script>
      const dropArea = document.getElementById('drop-area');
      const fileInput = document.getElementById('xmlFile');
      const fileNameDisplay = document.getElementById('file-name');
      dropArea.addEventListener('click', () => fileInput.click());
      dropArea.addEventListener('dragover', (e) => { e.preventDefault(); dropArea.style.backgroundColor = '#0056b3'; });
      dropArea.addEventListener('dragleave', () => { dropArea.style.backgroundColor = '#007bff'; });
      dropArea.addEventListener('drop', (e) => { e.preventDefault(); const file = e.dataTransfer.files[0]; fileInput.files = e.dataTransfer.files; fileNameDisplay.textContent = file.name; dropArea.style.backgroundColor = '#007bff'; });
      fileInput.addEventListener('change', (e) => { if (e.target.files[0]) fileNameDisplay.textContent = e.target.files[0].name; });

      const sales_chart_options = {
        series: [{ name: 'Viagens', data: <?php echo json_encode($chartData); ?> }],
        chart: { height: 300, type: 'area', toolbar: { show: false } },
        legend: { show: false },
        colors: ['#0d6efd'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' },
        xaxis: { type: 'category', categories: <?php echo json_encode($chartLabels); ?> },
        tooltip: { x: { format: 'dd MMMM' }, y: { formatter: function (val) { return val + " viagens"; } } }
      };
      const sales_chart = new ApexCharts(document.querySelector('#revenue-chart'), sales_chart_options);
      sales_chart.render();
    </script>
  </body>
</html>