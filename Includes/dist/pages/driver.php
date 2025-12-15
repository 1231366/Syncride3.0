<?php
session_start(); 
require __DIR__ . '/../../../auth/dbconfig.php'; 

$viagens = [];
$serviceTypeFilter = isset($_GET['serviceType']) ? $_GET['serviceType'] : null; 

// --- 1. VERIFICAÇÃO DE SESSÃO ---
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 2) {
    $userId = $_SESSION['user_id']; 
    $userName = $_SESSION['name'];

    // --- 2. FOTO DE PERFIL ---
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

    // --- 3. FETCH DAS VIAGENS ---
    try {
        $query = "
        SELECT 
            s.ID AS ServiceID, 
            s.serviceDate, 
            s.serviceStartTime, 
            s.serviceStartPoint, 
            s.serviceTargetPoint,
            s.paxADT, 
            s.paxCHD,
            s.FlightNumber, 
            s.NomeCliente, 
            s.ClientNumber,
            s.serviceType,
            s.total_price,
            COALESCE(s.status_id, 0) as status_id
        FROM Services_Rides sr
        INNER JOIN Services s ON sr.RideID = s.ID
        WHERE sr.UserID = ?";

        if ($serviceTypeFilter !== null) {
            $query .= " AND s.serviceType = ?";
        }

        $query .= " ORDER BY s.serviceDate ASC, s.serviceStartTime ASC";

        $stmt = $pdo->prepare($query);

        if ($serviceTypeFilter !== null) {
            $stmt->execute([$userId, $serviceTypeFilter]);
        } else {
            $stmt->execute([$userId]);
        }

        $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Erro: " . $e->getMessage();
    }
} else {
    header("refresh: 1; url=../../../index.php");
    exit();
}

// --- 4. ESTATÍSTICAS ---
$viagensHoje = 0; $viagensSemana = 0;
if (isset($userId)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE serviceDate = CURDATE() AND ID IN (SELECT RideID FROM Services_Rides WHERE UserID = ?)");
        $stmt->execute([$userId]);
        $viagensHoje = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE YEARWEEK(serviceDate, 1) = YEARWEEK(CURDATE(), 1) AND ID IN (SELECT RideID FROM Services_Rides WHERE UserID = ?)");
        $stmt->execute([$userId]);
        $viagensSemana = $stmt->fetchColumn();
    } catch (PDOException $e) {}
}

// --- 5. JS VARS ---
echo "<script> 
    var viagens = " . json_encode($viagens) . ";
    var currentDriverId = " . $_SESSION['user_id'] . ";
</script>";
?>

<!doctype html>
<html lang="pt">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Painel de Condutor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    
    <style>
        /* ESTILOS GERAIS */
        .card-header-custom { background: linear-gradient(90deg, #00b0ff, #7f00ff); color: white; border-radius: 12px 12px 0 0; text-align: center; padding: 15px; }
        .filter-btn { transition: all 0.3s ease; background-color: transparent; border: 2px solid #ddd; color: #666; }
        .filter-btn:hover { transform: scale(1.05); border-color: #00b0ff; color: #00b0ff; }
        .filter-btn.active { background: linear-gradient(90deg, #00b0ff, #7f00ff); color: white; border: none; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); }
        .profile-photo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        /* TIMELINE */
        .route-timeline { position: relative; padding-left: 20px; border-left: 2px dashed #dee2e6; margin: 15px 0 25px 5px; }
        .rt-item { position: relative; margin-bottom: 20px; }
        .rt-item:last-child { margin-bottom: 0; }
        .rt-dot { position: absolute; left: -26px; top: 2px; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #fff; }
        .dot-green { background: #198754; box-shadow: 0 0 0 3px #d1e7dd; }
        .dot-red { background: #dc3545; box-shadow: 0 0 0 3px #f8d7da; }

        /* BOTÃO WHATSAPP */
        .btn-whatsapp {
            background-color: #25D366; color: white; border: none; 
            border-radius: 10px; padding: 10px 15px; font-weight: bold;
            box-shadow: 0 2px 4px rgba(37, 211, 102, 0.2); transition: transform 0.2s;
            text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%;
        }
        .btn-whatsapp:hover { background-color: #128C7E; color: white; transform: scale(1.02); }

        /* BOTÃO DE AÇÃO DINÂMICO */
        .btn-dynamic-action {
            width: 100%; padding: 15px; font-size: 1.2rem; font-weight: 800; border-radius: 12px;
            text-transform: uppercase; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease;
            border: none; color: white;
        }
        .btn-dynamic-action:active { transform: scale(0.98); }
        .status-btn-0 { background: linear-gradient(45deg, #0d6efd, #0a58ca); } 
        .status-btn-1 { background: linear-gradient(45deg, #ffc107, #ffca2c); color: #000; } 
        .status-btn-2 { background: linear-gradient(45deg, #198754, #157347); } 
        .status-btn-3 { background: linear-gradient(45deg, #dc3545, #b02a37); animation: pulse-red 2s infinite; }
        .status-btn-4 { background-color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

        /* MODO AEROPORTO (FUNDO PRETO) */
        #airportOverlay { 
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; 
            background: #000; /* Preto */
            z-index: 99999; display: none; flex-direction: column; align-items: center; justify-content: center; 
            text-align: center; padding: 10px; 
        }
        #airportClientName { 
            color: #fff; /* Branco */
            font-weight: 900; line-height: 1.1; text-transform: uppercase; margin: 0; font-size: 15vw; 
            word-break: break-word; font-family: 'Arial Black', sans-serif;
        }
        .airport-controls { position: absolute; top: 20px; right: 20px; display: flex; gap: 20px; z-index: 100001; }
        .airport-icon { color: #fff; font-size: 2.5rem; cursor: pointer; opacity: 0.5; }
        .airport-icon:hover { opacity: 1; }
        .rotate-mode .content-wrapper { transform: rotate(90deg); width: 100vh; height: 100vw; display:flex; justify-content:center; align-items:center; }

        /* MENUS */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 65px; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-around; align-items: center; z-index: 1000; border-top: 1px solid #eee; }
        .nav-item-mobile { display: flex; flex-direction: column; align-items: center; justify-content: center; color: #adb5bd; text-decoration: none; font-size: 0.75rem; width: 100%; height: 100%; }
        .nav-item-mobile i { font-size: 1.4rem; margin-bottom: 2px; }
        .nav-item-mobile.active { color: #00b0ff; font-weight: 600; }
        .app-content { padding-bottom: 80px; }
        
        /* User Menu Override para AdminLTE */
        .user-menu .dropdown-menu { width: 280px; padding: 0; border: 0; box-shadow: 0 0 10px rgba(0,0,0,0.2); }
        .user-menu .user-header { height: 175px; padding: 10px; text-align: center; }
        .user-menu .user-header img { z-index: 5; height: 90px; width: 90px; border: 3px solid; border-color: transparent; border-color: rgba(255,255,255,.2); }
        .user-menu .user-header p { z-index: 5; color: #fff; color: rgba(255,255,255,.8); font-size: 17px; margin-top: 10px; }
        .user-menu .user-footer { background-color: #f8f9fa; padding: 10px; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    
    <div id="airportOverlay">
        <div class="airport-controls">
            <i class="bi bi-arrow-repeat airport-icon" id="rotateScreenBtn" title="Rodar Ecrã"></i>
            <i class="bi bi-x-circle-fill airport-icon" id="closeAirportMode" title="Fechar"></i>
        </div>
        <div class="content-wrapper w-100 h-100 d-flex flex-column align-items-center justify-content-center">
            <h1 id="airportClientName">NOME</h1>
            <h2 id="airportFlight" class="mt-4 text-white fs-1"></h2>
        </div>
    </div>

    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list"></i></a></li></ul>
          <ul class="navbar-nav ms-auto">
            
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="<?php echo $userPhotoPath; ?>" class="user-image rounded-circle shadow" alt="User Image">
                <span class="d-none d-md-inline"><?php echo $userName; ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="<?php echo $userPhotoPath; ?>" class="rounded-circle shadow" alt="User Image">
                  <p>
                    <?php echo $userName; ?>
                    <small>Condutor SyncRide</small>
                  </p>
                </li>
                <li class="user-footer">
                  <a href="#" class="btn btn-default btn-flat" data-bs-toggle="modal" data-bs-target="#photoModal">Perfil</a>
                  <a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a>
                </li>
              </ul>
            </li>

          </ul>
        </div>
      </nav>

      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0">Painel de Condutor</h3></div>
            </div>
          </div>
        </div>
        
        <div class="app-content">
          <div class="container-fluid">
            
            <div class="row mb-3">
              <div class="col-6">
                  <div class="small-box text-bg-primary shadow-sm mb-0">
                      <div class="inner"><h3><?php echo $viagensHoje; ?></h3><p>Hoje</p></div>
                      <div class="small-box-icon"><i class="bi bi-calendar-day"></i></div>
                  </div>
              </div>
              <div class="col-6">
                  <div class="small-box text-bg-success shadow-sm mb-0">
                      <div class="inner"><h3><?php echo $viagensSemana; ?></h3><p>Semana</p></div>
                      <div class="small-box-icon"><i class="bi bi-calendar-week"></i></div>
                  </div>
              </div>
            </div>

            <div class="card mb-4 shadow-lg border-0 rounded-4">
                <div class="card-header-custom">
                    <h3 class="card-title fw-bold mb-0">Viagens</h3>
                </div>

                <div class="d-flex justify-content-center gap-3 p-3 bg-light border-bottom">
                    <button class="btn btn-sm rounded-pill px-4 filter-btn" data-filter="yesterday">Ontem</button>
                    <button class="btn btn-sm rounded-pill px-4 filter-btn active" data-filter="today">Hoje</button>
                    <button class="btn btn-sm rounded-pill px-4 filter-btn" data-filter="tomorrow">Amanhã</button>
                </div>

                <div class="card-body p-3 bg-light">
                    <div class="list-group">
                        <p class="text-center text-muted py-5">A carregar viagens...</p>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="detailsModal" tabindex="-1" data-bs-backdrop="static">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content shadow-lg border-0" style="border-radius: 20px;">
                  
                  <div class="modal-header bg-white border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Detalhes do Serviço</h5>
                        <small class="text-muted">ID: #<span id="modalIdDisplay"></span></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  
                  <div class="modal-body p-4 pt-2">
                    
                    <div class="bg-light p-3 rounded-3 border mb-3 mt-2">
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-white p-2 rounded-circle me-3 shadow-sm">
                                <i class="bi bi-person-fill fs-3 text-primary"></i>
                            </div>
                            <div class="w-100">
                                <div class="text-muted small text-uppercase fw-bold">Cliente</div>
                                <div id="modalClient" class="fw-bold text-dark fs-5"></div>
                                <div id="modalClientNumber" class="text-muted small"></div>
                                
                                <div id="whatsappContainer" class="mt-2" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="priceAlertContainer" style="display:none;">
                        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-3" role="alert" style="border-radius: 12px; background: linear-gradient(45deg, #198754, #20c997); color: white;">
                            <div class="d-flex align-items-center">
                                <div class="bg-white text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                    <i class="bi bi-cash-coin fs-3"></i>
                                </div>
                                <div>
                                    <small class="d-block text-uppercase opacity-75 fw-bold" style="font-size: 0.7rem;">Cobrar ao Cliente</small>
                                    <span class="fs-2 fw-bold" id="modalPriceDisplay">0.00€</span>
                                </div>
                            </div>
                            <i class="bi bi-exclamation-circle-fill fs-4 opacity-50"></i>
                        </div>
                    </div>

                    <button class="btn btn-dark w-100 mb-3 fw-bold shadow-sm" id="btnAirportMode" style="border-radius: 10px;">
                        <i class="bi bi-signpost-2-fill me-2"></i> MODO AEROPORTO
                    </button>

                    <div class="route-timeline">
                        <div class="rt-item">
                            <div class="rt-dot dot-green"></div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Recolha</small>
                            <div id="modalPickup" class="fw-bold text-dark lh-sm"></div>
                        </div>
                        <div class="rt-item mt-3">
                            <div class="rt-dot dot-red"></div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Entrega</small>
                            <div id="modalDropoff" class="fw-bold text-dark lh-sm"></div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3 gap-2">
                        <div class="bg-white border rounded p-2 w-50 text-center shadow-sm">
                            <span class="d-block text-muted small fw-bold text-uppercase">Adultos</span>
                            <span class="fs-5 fw-bold text-dark" id="modalADT">0</span>
                        </div>
                        <div class="bg-white border rounded p-2 w-50 text-center shadow-sm">
                            <span class="d-block text-muted small fw-bold text-uppercase">Crianças</span>
                            <span class="fs-5 fw-bold text-dark" id="modalCHD">0</span>
                        </div>
                    </div>
                    
                    <div id="flightSection" class="alert alert-info border-0 d-flex align-items-center justify-content-between py-2 mb-3" style="display: none;">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-airplane-engines-fill me-2 fs-5"></i>
                            <div>
                                <small class="d-block lh-1 opacity-75">Voo</small>
                                <strong id="modalFlight" class="fs-6"></strong>
                            </div>
                        </div>
                        <a href="#" id="trackFlightLink" target="_blank" class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3 ms-auto">
                            Rastrear
                        </a>
                    </div>

                    <hr>
                    <div class="d-grid gap-2 mb-3">
                        <button id="btnDynamicAction" class="btn-dynamic-action status-btn-0">
                            <i class="bi bi-car-front-fill me-2"></i> INICIAR RECOLHA
                        </button>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-outline-secondary w-100 py-2" id="uploadVoucher">
                                <i class="bi bi-ticket-perforated"></i> Voucher
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-danger w-100 py-2" id="uploadNoShow">
                                <i class="bi bi-camera"></i> No-Show
                            </button>
                        </div>
                    </div>
                    
                    <div id="cameraContainer" class="mt-3 bg-white p-3 rounded border shadow-sm" style="display: none;">
                      <p class="text-center mb-2 fw-bold" id="cameraInstruction">Tirar Foto</p>
                      <div style="position: relative; padding-top: 100%; overflow: hidden; border-radius: 8px; background: #000;">
                          <video id="cameraStream" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;" autoplay playsinline></video>
                          <canvas id="photoCanvas" style="display: none; width: 100%; height: 100%;"></canvas>
                      </div>
                      <div class="d-flex gap-2 mt-3">
                          <button class="btn btn-light flex-grow-1" onclick="stopCameraStream(); document.getElementById('cameraContainer').style.display='none';">Cancelar</button>
                          <button class="btn btn-primary flex-grow-1" id="capturePhoto">📸 Capturar</button>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
            </div>

            <div class="modal fade" id="photoModal" tabindex="-1">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg">
                  <form action="../../../save_profile_photo.php" method="POST" enctype="multipart/form-data">
                      <div class="modal-header py-3 border-bottom-0">
                        <h5 class="modal-title fw-bold">Gerir Foto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body px-4 pb-4 pt-0 text-center">
                        <div class="mb-4"><img id="currentProfilePhoto" src="<?php echo $userPhotoPath; ?>" class="profile-photo"></div>
                        <input type="file" name="profile_photo" id="profilePhotoInput" class="form-control" accept="image/*" required>
                      </div>
                      <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">Guardar</button>
                      </div>
                  </form>
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>
      
      <footer class="app-footer">SyncRide All rights reserved.</footer>

      <nav class="bottom-nav d-flex d-md-none">
          <a href="driver.php" class="nav-item-mobile active"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
          <a href="driver_agenda.php" class="nav-item-mobile"><i class="bi bi-calendar3"></i><span>Agenda</span></a>
          <a href="#" class="nav-item-mobile" data-bs-toggle="modal" data-bs-target="#photoModal"><i class="bi bi-person-circle"></i><span>Perfil</span></a>
          <a href="logout.php" class="nav-item-mobile text-danger"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
      </nav>

    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../dist/js/adminlte.js"></script>
    
    <script>
        // --- VARIÁVEIS GLOBAIS ---
        let backgroundWatcherId = null;
        let trackingInterval = null;
        let currentRideId = null;
        let currentRideData = null;
        let localTripStatus = {};

        // Inicializar estados
        viagens.forEach(v => {
            localTripStatus[v.ServiceID] = parseInt(v.status_id) || 0;
        });

        // --- GPS ---
        function sendPosition(position) {
            if(!currentRideId) return;
            const lat = position.latitude || position.coords?.latitude;
            const lng = position.longitude || position.coords?.longitude;
            const speed = position.speed || position.coords?.speed;
            const heading = position.bearing || position.coords?.heading;
            
            if (lat === undefined || lng === undefined) {
                if (window.Capacitor?.Plugins?.BackgroundGeolocation) Capacitor.Plugins.BackgroundGeolocation.finish(); 
                return;
            }

            const payload = {
                ride_id: currentRideId,
                driver_id: <?php echo $_SESSION['user_id']; ?>,
                lat: lat, lng: lng, speed: speed, heading: heading
            };
            
            fetch('../../../Includes/dist/pages/api_update_location.php', {
                method: 'POST', body: JSON.stringify(payload), headers: {'Content-Type': 'application/json'}
            }).catch(e => console.log("Erro GPS: ", e));

            if (window.Capacitor?.Plugins?.BackgroundGeolocation) Capacitor.Plugins.BackgroundGeolocation.finish(); 
        }
        
        function startLiveTracking(rideId) {
            currentRideId = rideId;
            
            if (window.Capacitor?.Plugins?.BackgroundGeolocation) {
                if (backgroundWatcherId) return;
                Capacitor.Plugins.BackgroundGeolocation.addWatcher({
                    interval: 5, fastestInterval: 2, distanceFilter: 10, desiredAccuracy: 10,
                    stopOnTerminate: false, notificationTitle: 'SyncRide', notificationText: 'Viagem em curso.'
                }, sendPosition).then(id => { backgroundWatcherId = id; });
            } else if ("geolocation" in navigator) {
                if(trackingInterval) clearInterval(trackingInterval); 
                navigator.geolocation.getCurrentPosition(pos => sendPosition(pos));
                trackingInterval = setInterval(() => { navigator.geolocation.getCurrentPosition(pos => sendPosition(pos)); }, 5000);
            }
        }
        
        function stopLiveTracking() {
            if(!currentRideId) return;
            fetch('api_stop_tracking.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ ride_id: currentRideId })
            });
            currentRideId = null;
            if (window.Capacitor?.Plugins?.BackgroundGeolocation && backgroundWatcherId) {
                Capacitor.Plugins.BackgroundGeolocation.removeWatcher({ id: backgroundWatcherId });
                backgroundWatcherId = null;
            } 
            if(trackingInterval) { clearInterval(trackingInterval); trackingInterval = null; }
        }

        function requestAppPermissions() {
            if (window.Capacitor?.isNativePlatform()) {
                const { Geolocation, Camera, BackgroundGeolocation } = Capacitor.Plugins;
                Geolocation.requestPermissions(); 
                Camera.requestPermissions(); 
                BackgroundGeolocation.requestPermissions();
            }
        }
        document.addEventListener('DOMContentLoaded', requestAppPermissions);

        // --- FUNÇÕES DE INTERFACE ---

        function openWaze(address) {
            window.open("https://waze.com/ul?q=" + encodeURIComponent(address) + "&navigate=yes", '_blank');
        }

        function updateButtonUI(status) {
            const btn = document.getElementById('btnDynamicAction');
            btn.className = 'btn-dynamic-action'; 
            
            switch(parseInt(status)) {
                case 0:
                    btn.classList.add('status-btn-0');
                    btn.innerHTML = '<i class="bi bi-car-front-fill me-2"></i> INICIAR RECOLHA';
                    btn.disabled = false;
                    break;
                case 1:
                    btn.classList.add('status-btn-1');
                    btn.innerHTML = '<i class="bi bi-geo-alt-fill me-2"></i> CHEGUEI AO PONTO';
                    btn.disabled = false;
                    break;
                case 2:
                    btn.classList.add('status-btn-2');
                    btn.innerHTML = '<i class="bi bi-play-circle-fill me-2"></i> INICIAR VIAGEM';
                    btn.disabled = false;
                    break;
                case 3:
                    btn.classList.add('status-btn-3');
                    btn.innerHTML = '<i class="bi bi-stop-circle-fill me-2"></i> TERMINAR VIAGEM';
                    btn.disabled = false;
                    break;
                default:
                    btn.classList.add('status-btn-4');
                    btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> VIAGEM CONCLUÍDA';
                    btn.disabled = true;
            }
        }

        function updateStatusBackend(rideId, nextStatus) {
            let formData = new FormData();
            formData.append('ride_id', rideId);
            formData.append('status', nextStatus);

            fetch('api_update_status.php', {
                method: 'POST', body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    localTripStatus[rideId] = nextStatus;
                    updateButtonUI(nextStatus);
                    if(nextStatus === 4) {
                        setTimeout(() => {
                            bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();
                            location.reload(); 
                        }, 1000);
                    }
                } else { alert('Erro ao atualizar estado.'); }
            })
            .catch(error => console.error('Erro:', error));
        }

        // --- BOTÃO DINÂMICO ---
        document.getElementById('btnDynamicAction').addEventListener('click', function() {
            if(!currentRideData) return;
            const rideId = currentRideData.id;
            const currentStatus = localTripStatus[rideId];
            const nextStatus = currentStatus + 1;

            if(nextStatus > 4) return;

            if(currentStatus === 0) {
                if(!confirm("Iniciar recolha e abrir GPS?")) return;
                startLiveTracking(rideId);
                openWaze(currentRideData.start);
            }
            else if(currentStatus === 1) {
                if(!confirm("Confirma que chegou ao ponto de recolha?")) return;
            }
            else if(currentStatus === 2) {
                if(!confirm("Cliente a bordo? Iniciar viagem para destino.")) return;
                openWaze(currentRideData.end);
            }
            else if(currentStatus === 3) {
                if(!confirm("Terminar viagem e fechar serviço?")) return;
                stopLiveTracking();
            }

            updateStatusBackend(rideId, nextStatus);
        });

        // --- FILTROS DE LISTA ---
        function formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, "0");
            const d = String(date.getDate()).padStart(2, "0");
            return `${y}-${m}-${d}`;
        }

        function filterTrips(filter) {
            const t = new Date();
            const y = new Date(t); y.setDate(t.getDate() - 1);
            const tm = new Date(t); tm.setDate(t.getDate() + 1);
            const map = { "yesterday": formatDate(y), "today": formatDate(t), "tomorrow": formatDate(tm) };
            const res = viagens.filter(v => v.serviceDate === map[filter]);
            renderList(res);
        }

        function renderList(data) {
            const el = document.querySelector(".list-group");
            el.innerHTML = ""; 
            if (data.length === 0) {
                el.innerHTML = "<div class='text-center py-5 text-muted opacity-50'><i class='bi bi-calendar-x fs-1'></i><p>Sem viagens.</p></div>";
                return;
            }
            data.forEach(v => {
                const isPriv = (v.serviceType == 1);
                const borderColor = isPriv ? "#0d6efd" : "#ffc107";
                const badge = isPriv ? '<span class="badge bg-primary rounded-pill">Privado</span>' : '<span class="badge bg-warning text-dark rounded-pill">Partilhado</span>';
                const status = localTripStatus[v.ServiceID] !== undefined ? localTripStatus[v.ServiceID] : v.status_id;
                const opacity = status >= 4 ? '0.6' : '1';

                const html = `
                  <div class="card mb-3 border-0 shadow-sm open-modal"
                       style="border-left: 5px solid ${borderColor}; cursor: pointer; border-radius: 12px; opacity: ${opacity}"
                       data-id="${v.ServiceID}"
                       data-start="${v.serviceStartPoint}"
                       data-end="${v.serviceTargetPoint}"
                       data-time="${v.serviceStartTime.substr(0, 5)}"
                       data-date="${v.serviceDate}"
                       data-paxadt="${v.paxADT||0}"
                       data-paxchd="${v.paxCHD||0}"
                       data-flight="${v.FlightNumber || ''}"
                       data-client="${v.NomeCliente || ''}"
                       data-clientnumber="${v.ClientNumber || ''}"
                       data-price="${v.total_price || ''}">
                    <div class="card-body p-3">
                       <div class="d-flex justify-content-between align-items-center mb-2">
                          <h4 class="fw-bold m-0 text-dark">${v.serviceStartTime.substr(0, 5)}</h4>
                          ${badge}
                       </div>
                       <div class="text-truncate mb-1 text-secondary"><i class="bi bi-geo-alt-fill text-success me-2"></i> ${v.serviceStartPoint}</div>
                       <div class="text-truncate text-dark fw-medium"><i class="bi bi-flag-fill text-danger me-2"></i> ${v.serviceTargetPoint}</div>
                       
                       ${v.total_price > 0 ? '<div class="mt-2 text-end"><span class="badge bg-success"><i class="bi bi-cash"></i> Cobrar: ' + parseFloat(v.total_price).toFixed(2) + '€</span></div>' : ''}
                       
                    </div>
                  </div>`;
                el.innerHTML += html;
            });
        }

        filterTrips("today");

        document.querySelectorAll(".filter-btn").forEach(b => {
            b.addEventListener("click", function() {
                document.querySelectorAll(".filter-btn").forEach(x => { x.classList.remove("active"); });
                this.classList.add("active");
                filterTrips(this.dataset.filter);
            });
        });

        // --- ABRIR MODAL ---
        const modal = document.getElementById('detailsModal');
        document.querySelector(".list-group").addEventListener("click", e => {
            const card = e.target.closest(".open-modal");
            if(!card) return;

            stopCameraStream();
            document.getElementById('cameraContainer').style.display = 'none';

            const d = card.dataset;
            currentRideData = d;
            
            modal.querySelector("#modalIdDisplay").textContent = d.id;
            modal.querySelector("#modalPickup").textContent = d.start;
            modal.querySelector("#modalDropoff").textContent = d.end;
            modal.querySelector("#modalADT").textContent = d.paxadt;
            modal.querySelector("#modalCHD").textContent = d.paxchd;
            modal.querySelector("#modalClient").textContent = d.client || 'Cliente';
            modal.querySelector("#modalClientNumber").textContent = d.clientnumber;
            
            // WHATSAPP LÓGICA INTELIGENTE
            const waContainer = document.getElementById('whatsappContainer');
            waContainer.innerHTML = ''; 
            waContainer.style.display = 'none';
            
            if (d.clientnumber) {
                // Remove tudo que não é número
                let cleanNum = d.clientnumber.replace(/[^0-9]/g, '');
                // Assume que um numero valido tem pelo menos 7 digitos
                if (cleanNum.length > 7) {
                    waContainer.style.display = 'block';
                    waContainer.innerHTML = `
                        <a href="https://wa.me/${cleanNum}" target="_blank" class="btn-whatsapp">
                            <i class="bi bi-whatsapp me-2"></i> Enviar Mensagem
                        </a>`;
                }
            }
            
            // PREÇO NO MODAL
            const priceContainer = document.getElementById('priceAlertContainer');
            const priceDisplay = document.getElementById('modalPriceDisplay');
            let priceVal = parseFloat(d.price);

            if (d.price && priceVal > 0) {
                priceDisplay.textContent = priceVal.toFixed(2) + " €";
                priceContainer.style.display = "block";
            } else {
                priceContainer.style.display = "none";
            }

            // PLACA
            document.getElementById('airportClientName').textContent = d.client || "CLIENTE";
            document.getElementById('airportFlight').textContent = d.flight || "";

            // VOO
            const fSection = modal.querySelector("#flightSection");
            if(d.flight && d.flight !== 'N/A' && d.flight.trim() !== '') {
                fSection.style.display = "flex";
                modal.querySelector("#modalFlight").textContent = d.flight;
                modal.querySelector("#trackFlightLink").href = "https://www.flightradar24.com/data/flights/" + d.flight.replace(/\s/g, '');
            } else {
                fSection.style.display = "none";
            }

            modal.querySelector("#uploadNoShow").dataset.tripId = d.id;
            modal.querySelector("#uploadVoucher").dataset.tripId = d.id;

            // ESTADO
            const status = localTripStatus[d.id];
            updateButtonUI(status);

            new bootstrap.Modal(modal).show();
        });

        // --- MODO PLACA ---
        document.getElementById('btnAirportMode').onclick = () => {
            const overlay = document.getElementById('airportOverlay');
            overlay.style.display = "flex";
            overlay.classList.remove("rotate-mode");
            if(document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(()=>{});
        };
        document.getElementById('closeAirportMode').onclick = () => {
            document.getElementById('airportOverlay').style.display = "none";
            if(document.exitFullscreen) document.exitFullscreen().catch(()=>{});
        };
        document.getElementById('rotateScreenBtn').onclick = () => {
            document.getElementById('airportOverlay').classList.toggle("rotate-mode");
        };

        // --- CÂMERA ---
        const video = document.getElementById('cameraStream');
        const canvas = document.getElementById('photoCanvas');
        const captureButton = document.getElementById('capturePhoto');
        let stream = null; let currentMode = 'noshow';

        function stopCameraStream() { if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; } }

        async function startCamera(mode) {
            currentMode = mode;
            document.getElementById('cameraInstruction').textContent = (mode === 'voucher') ? "Fotografar Voucher" : "Prova de No-Show";
            if (stream) { stopCameraStream(); document.getElementById('cameraContainer').style.display = 'none'; return; }
            canvas.style.display = 'none'; video.style.display = 'block'; 
            document.getElementById('cameraContainer').style.display = 'block'; 
            try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); video.srcObject = stream; } 
            catch (err) { try { stream = await navigator.mediaDevices.getUserMedia({ video: true }); video.srcObject = stream; } catch (e) {} }
        }

        document.getElementById('uploadNoShow').addEventListener('click', () => startCamera('noshow'));
        document.getElementById('uploadVoucher').addEventListener('click', () => startCamera('voucher'));

        captureButton.addEventListener('click', () => {
            if (!stream) return; 
            captureButton.disabled = true; captureButton.innerHTML = 'A enviar...';
            const ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);
            const imgData = canvas.toDataURL('image/jpeg');
            stopCameraStream(); video.style.display='none'; 

            const tripId = currentRideData.id; 
            const endpoint = currentMode === 'voucher' ? 'upload_voucher.php' : 'upload_no_show.php';

            fetch(endpoint, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trip_id: tripId, image_data: imgData })
            })
            .then(r => r.json())
            .then(d => {
                alert(d.message); 
                document.getElementById('cameraContainer').style.display='none';
                
                // NO-SHOW LOGIC: Finaliza Viagem Automaticamente
                if (currentMode === 'noshow' && d.success) {
                    stopLiveTracking(); 
                    updateStatusBackend(tripId, 4); // Força status 4 (Concluído)
                }
            })
            .catch(e => alert('Erro envio.'))
            .finally(() => { captureButton.disabled = false; captureButton.textContent = '📸 Capturar'; });
        });

        // Modal Foto
        const photoModal = document.getElementById('photoModal');
        photoModal.addEventListener('show.bs.modal', function () {
            const currentImg = document.querySelector('img.user-image').src;
            document.getElementById('currentProfilePhoto').src = currentImg;
            document.getElementById('profilePhotoInput').value = '';
        });
        
        document.getElementById('profilePhotoInput').addEventListener('change', function(e) {
            const [file] = e.target.files;
            if (file) { document.getElementById('currentProfilePhoto').src = URL.createObjectURL(file); }
        });

    </script>
  </body>
</html>