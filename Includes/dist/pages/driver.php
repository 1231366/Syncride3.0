<?php
session_start(); 
require __DIR__ . '/../../../auth/dbconfig.php'; 

$viagens = [];
$serviceTypeFilter = isset($_GET['serviceType']) ? $_GET['serviceType'] : null; 

// --- 0. MODO API (AUTO-REFRESH) ---
if (isset($_GET['api']) && $_GET['api'] === 'refresh') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit(); }

    $userId = $_SESSION['user_id'];
    $filterType = isset($_GET['serviceType']) ? $_GET['serviceType'] : null;

    try {
        $query = "SELECT s.ID AS ServiceID, s.serviceDate, s.serviceStartTime, s.serviceStartPoint, s.serviceTargetPoint, s.paxADT, s.paxCHD, s.FlightNumber, s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price, COALESCE(s.status_id, 0) as status_id FROM Services_Rides sr INNER JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ?";
        if ($filterType !== null) { $query .= " AND s.serviceType = ?"; }
        $query .= " ORDER BY s.serviceDate ASC, s.serviceStartTime ASC";
        $stmt = $pdo->prepare($query);
        if ($filterType !== null) { $stmt->execute([$userId, $filterType]); } else { $stmt->execute([$userId]); }
        $viagensData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($viagensData); exit();
    } catch (PDOException $e) { echo json_encode([]); exit(); }
}

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

    // --- 3. FETCH INICIAL ---
    try {
        $query = "SELECT s.ID AS ServiceID, s.serviceDate, s.serviceStartTime, s.serviceStartPoint, s.serviceTargetPoint, s.paxADT, s.paxCHD, s.FlightNumber, s.NomeCliente, s.ClientNumber, s.serviceType, s.total_price, COALESCE(s.status_id, 0) as status_id FROM Services_Rides sr INNER JOIN Services s ON sr.RideID = s.ID WHERE sr.UserID = ?";
        if ($serviceTypeFilter !== null) { $query .= " AND s.serviceType = ?"; }
        $query .= " ORDER BY s.serviceDate ASC, s.serviceStartTime ASC";
        $stmt = $pdo->prepare($query);
        if ($serviceTypeFilter !== null) { $stmt->execute([$userId, $serviceTypeFilter]); } else { $stmt->execute([$userId]); }
        $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { echo "Erro: " . $e->getMessage(); }
} else {
    header("refresh: 1; url=../../../index.php"); exit();
}

// --- 4. ESTATÍSTICAS ---
$viagensHoje = 0; $viagensSemana = 0;
if (isset($userId)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE serviceDate = CURDATE() AND ID IN (SELECT RideID FROM Services_Rides WHERE UserID = ?)");
        $stmt->execute([$userId]); $viagensHoje = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE YEARWEEK(serviceDate, 1) = YEARWEEK(CURDATE(), 1) AND ID IN (SELECT RideID FROM Services_Rides WHERE UserID = ?)");
        $stmt->execute([$userId]); $viagensSemana = $stmt->fetchColumn();
    } catch (PDOException $e) {}
}

echo "<script> var viagens = " . json_encode($viagens) . "; var currentDriverId = " . $_SESSION['user_id'] . "; </script>";
?>

<!doctype html>
<html lang="pt" data-bs-theme="light">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Condutor | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    
    <style>
        /* --- DESIGN SYSTEM --- */
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
            /* SAFE AREA VARIABLES */
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
            padding-bottom: calc(80px + var(--safe-bottom));
            padding-top: 0;
            margin: 0;
            min-height: 100vh;
        }

        /* --- HEADER (Z-INDEX 1080 - SUPREMO) --- */
        /* Colocámos 1080 para ficar ACIMA do overlay (1070) e do Modal (1055) */
        .app-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: calc(15px + var(--safe-top)) 20px 15px 20px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; 
            z-index: 1080; 
        }
        .brand-logo { height: 30px; width: auto; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); }

        /* --- STAT CARDS --- */
        .stat-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 15px;
            display: flex; align-items: center; gap: 15px;
            box-shadow: var(--shadow-sm);
        }
        .stat-icon {
            width: 45px; height: 45px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        .bg-indigo-soft { background: rgba(79, 70, 229, 0.1); color: var(--primary-accent); }
        .bg-emerald-soft { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-info h3 { font-family: var(--font-display); font-weight: 700; font-size: 1.5rem; margin: 0; line-height: 1; }
        .stat-info p { margin: 0; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

        /* --- FILTER PILLS --- */
        .filter-container {
            background-color: var(--bg-card);
            padding: 5px; border-radius: 50px;
            display: flex; justify-content: space-between;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .filter-btn {
            flex: 1; text-align: center; padding: 8px 0;
            border-radius: 50px; border: none; background: transparent;
            color: var(--text-muted); font-size: 0.9rem; font-weight: 500;
            transition: all 0.2s;
        }
        .filter-btn.active {
            background-color: var(--primary-accent); color: white;
            box-shadow: 0 2px 5px rgba(79, 70, 229, 0.3);
        }

        /* --- RIDE CARD --- */
        .ride-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 15px;
            padding: 16px;
            position: relative;
            transition: transform 0.1s;
            box-shadow: var(--shadow-sm);
        }
        .ride-card:active { transform: scale(0.98); }
        
        .ride-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .ride-time { font-family: var(--font-display); font-size: 1.25rem; font-weight: 700; color: var(--text-main); }
        .ride-badge { font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 50px; text-transform: uppercase; }
        .badge-private { background: rgba(79, 70, 229, 0.1); color: var(--primary-accent); border: 1px solid rgba(79, 70, 229, 0.2); }
        .badge-shared { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }

        .card-timeline { position: relative; padding-left: 20px; border-left: 2px dashed var(--border-color); margin-left: 6px; }
        .ct-point { position: relative; margin-bottom: 15px; }
        .ct-point:last-child { margin-bottom: 0; }
        .ct-dot {
            width: 12px; height: 12px; border-radius: 50%;
            position: absolute; left: -27px; top: 4px;
            border: 2px solid var(--bg-card);
        }
        .dot-pickup { background-color: #10b981; }
        .dot-dropoff { background-color: #ef4444; }
        .ct-text { font-size: 0.95rem; color: var(--text-main); line-height: 1.3; }

        .price-tag {
            position: absolute; bottom: 16px; right: 16px;
            background-color: #10b981; color: white;
            font-weight: 700; font-size: 0.85rem;
            padding: 4px 10px; border-radius: 8px;
            display: flex; align-items: center; gap: 4px;
        }

        /* --- MODAL STYLES (COMPACTED) --- */
        .modal-content { background-color: var(--bg-card); border-radius: 24px; border: none; }
        .modal-header { border-bottom: 1px solid var(--border-color); padding: 1rem 1.2rem; }
        .modal-title { font-family: var(--font-display); font-weight: 700; color: var(--text-main); font-size: 1.1rem; }
        .modal-body { padding: 1rem; }
        
        .info-box { background-color: var(--bg-body); border-radius: 12px; padding: 10px; border: 1px solid var(--border-color); margin-bottom: 8px; }
        .info-label { font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; display: block; margin-bottom: 2px; }
        .info-value { font-size: 0.95rem; color: var(--text-main); font-weight: 600; line-height: 1.2; }

        .btn-dynamic-action {
            width: 100%; padding: 12px; font-size: 1rem; font-weight: 700; font-family: var(--font-display);
            border-radius: 12px; border: none; color: white; text-transform: uppercase; letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.2s; margin-bottom: 10px;
        }
        .btn-dynamic-action:active { transform: scale(0.97); }
        .status-btn-0 { background: var(--primary-accent); }
        .status-btn-1 { background: #f59e0b; color: #fff; }
        .status-btn-2 { background: #10b981; }
        .status-btn-3 { background: #ef4444; }
        .status-btn-4 { background: #6b7280; opacity: 0.7; }
        .btn-whatsapp { background-color: #25D366; color: white; border: none; border-radius: 8px; padding: 8px; width: 100%; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; margin-top: 8px; font-size: 0.9rem;}

        .modal-timeline .ct-point { margin-bottom: 10px; }
        .modal-timeline .ct-text { font-size: 0.9rem; }

        /* --- BOTTOM NAV (Z-INDEX 1080 - SUPREMO) --- */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; 
            height: calc(70px + var(--safe-bottom));
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            display: flex; justify-content: space-around; align-items: flex-start;
            z-index: 1080; 
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

        /* --- AIRPORT OVERLAY --- */
        /* Z-INDEX 1070: Acima do Modal (1055) mas abaixo do Header/Nav (1080) */
        #airportOverlay { 
            position: fixed; top: 0; left: 0; 
            width: 100%; height: 100%; 
            background: #000; 
            z-index: 1070; 
            display: none; 
            overflow: hidden; 
            
            /* PADDING AJUSTADO PARA O NOME NÃO FICAR FULLSCREEN */
            /* Compensa o Header (~76px) e Footer (~80px) */
            padding-top: calc(76px + var(--safe-top));
            padding-bottom: calc(85px + var(--safe-bottom));
            box-sizing: border-box; /* Garante que o padding não aumenta a width */
        }

        #airportContentWrapper {
            width: 100%; height: 100%;
            display: flex; flex-direction: column; 
            align-items: center; justify-content: center;
            transition: transform 0.3s ease;
        }

        #airportOverlay.landscape-mode {
             /* Em paisagem, removemos o padding vertical porque vai rodar */
             padding-top: 0; padding-bottom: 0;
        }

        #airportOverlay.landscape-mode #airportContentWrapper {
            position: absolute;
            top: 50%; left: 50%;
            width: 100vh; height: 100vw;
            transform: translate(-50%, -50%) rotate(90deg);
        }
        
        #airportClientName { 
            color: #fff; font-weight: 900; line-height: 1.1; 
            text-transform: uppercase; font-family: sans-serif; word-break: break-word; 
            text-align: center; width: 100%; padding: 0 20px;
            font-size: 10vw; 
        }
        
        /* --- CAMERA OVERLAY (Z-INDEX 99999 - TOTAL) --- */
        #cameraOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #000; z-index: 99999; display: none; flex-direction: column; }
        #cameraViewArea { flex: 1; position: relative; overflow: hidden; background: #000; }
        #cameraStream, #photoCanvas { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }
        .camera-ui-controls { position: absolute; bottom: 0; left: 0; width: 100%; padding: 40px 20px calc(40px + var(--safe-bottom)) 20px; background: linear-gradient(to top, #000, transparent); display: flex; justify-content: center; gap: 20px; align-items: center; }
        .camera-btn { width: 70px; height: 70px; border-radius: 50%; border: 4px solid white; background: transparent; display: flex; align-items: center; justify-content: center; padding: 0; }
        .camera-btn-inner { width: 56px; height: 56px; background: white; border-radius: 50%; transition: transform 0.1s; }
        .camera-btn:active .camera-btn-inner { transform: scale(0.9); }
        .btn-circle-action { width: 50px; height: 50px; border-radius: 50%; border: none; background: rgba(255,255,255,0.2); color: white; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(5px); }

    </style>
  </head>
  <body>
    
    <div id="airportOverlay">
        <div style="position: absolute; top: calc(90px + var(--safe-top)); right: 20px; z-index: 100002; display: flex; gap: 20px;">
            <i class="bi bi-arrow-repeat text-white fs-1" id="rotateScreenBtn" style="cursor: pointer;"></i>
            <i class="bi bi-x-lg text-white fs-1" id="closeAirportMode" style="cursor: pointer;"></i>
        </div>

        <div id="airportContentWrapper">
            <h1 id="airportClientName">NOME</h1>
            <h2 id="airportFlight" class="mt-4 text-white fs-2 text-center"></h2>
        </div>
    </div>

    <div id="cameraOverlay">
        <div style="position: absolute; top: max(20px, env(safe-area-inset-top)); left: 0; width: 100%; text-align: center; color: white; z-index: 10; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.8);" id="cameraInstruction">Fotografar</div>
        <div id="cameraViewArea">
            <div id="cameraLoading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); color: white; display: none;">
                <div class="spinner-border mb-2"></div><div>A iniciar...</div>
            </div>
            <video id="cameraStream" autoplay playsinline></video>
            <canvas id="photoCanvas" style="display: none;"></canvas>
        </div>
        <div class="camera-ui-controls">
            <div id="stepCaptureControls" class="d-flex align-items-center gap-4">
                <button class="btn-circle-action" onclick="closeCameraOverlay()"><i class="bi bi-x-lg"></i></button>
                <button class="camera-btn" id="btnCapture"><div class="camera-btn-inner"></div></button>
                <button class="btn-circle-action" id="btnRotateCamera"><i class="bi bi-arrow-repeat"></i></button>
            </div>
            <div id="stepConfirmControls" class="d-none d-flex gap-3 w-100 justify-content-center">
                <button class="btn btn-light rounded-pill px-4 py-2 fw-bold" id="btnRetake">Repetir</button>
                <button class="btn btn-primary rounded-pill px-4 py-2 fw-bold" id="btnConfirmSend">Enviar</button>
            </div>
        </div>
    </div>

    <header class="app-header">
        <img src="../../../assets/images/icons/Syncride.png" alt="SyncRide" class="brand-logo" id="header-logo">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-muted p-0" id="theme-toggle"><i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i></button>
            <img src="<?php echo $userPhotoPath; ?>" class="user-avatar shadow-sm" alt="User" data-bs-toggle="modal" data-bs-target="#photoModal">
        </div>
    </header>

    <div class="container-fluid px-3 pt-3">
        <div class="mb-4">
            <h4 class="fw-bold mb-3 text-main">Olá, <?php echo explode(' ', trim($userName))[0]; ?>! 👋</h4>
            <div class="row g-3">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-indigo-soft"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $viagensHoje; ?></h3>
                            <p>Hoje</p>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-emerald-soft"><i class="bi bi-calendar-week"></i></div>
                        <div class="stat-info">
                            <h3><?php echo $viagensSemana; ?></h3>
                            <p>Semana</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-container">
            <button class="filter-btn" data-filter="yesterday">Ontem</button>
            <button class="filter-btn active" data-filter="today">Hoje</button>
            <button class="filter-btn" data-filter="tomorrow">Amanhã</button>
        </div>

        <div id="rideList">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 small">A carregar viagens...</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Detalhes</h5>
                        <small class="text-muted">ID #<span id="modalIdDisplay"></span></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-box d-flex align-items-start gap-3">
                         <div class="bg-body-secondary rounded-circle p-2 mt-1"><i class="bi bi-person-fill fs-5 text-primary"></i></div>
                         <div class="flex-grow-1">
                             <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="info-label">Cliente</span>
                                    <div id="modalClient" class="info-value text-break"></div>
                                </div>
                                <div class="text-end">
                                     <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2" id="modalPaxBadge">
                                        <i class="bi bi-people-fill"></i> <span id="modalADT"></span>+<span id="modalCHD"></span>
                                     </span>
                                </div>
                             </div>
                             <div class="d-flex justify-content-between align-items-end mt-1">
                                 <div id="modalClientNumber" class="small text-muted"></div>
                                 <div id="priceBadgeContainer" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2" style="display:none;">
                                    <span id="modalPriceDisplay"></span>
                                 </div>
                             </div>
                             <div id="whatsappContainer" style="display:none;"></div>
                         </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6"><button class="btn btn-sm btn-dark w-100 py-2 rounded-3 fw-bold" id="btnAirportMode"><i class="bi bi-signpost-2-fill me-1"></i> Placa</button></div>
                        <div class="col-6"><a href="#" id="trackFlightLink" target="_blank" class="btn btn-sm btn-info w-100 py-2 rounded-3 fw-bold text-white" style="display:none;"><i class="bi bi-airplane-fill me-1"></i> <span id="modalFlight"></span></a></div>
                    </div>

                    <div class="card p-2 border border-color shadow-none mb-3">
                        <div class="card-timeline modal-timeline" style="margin-left: 0; padding-left: 20px;">
                            <div class="ct-point"><div class="ct-dot dot-pickup"></div><span class="ct-label info-label">Recolha</span><div id="modalPickup" class="ct-text fw-bold lh-sm"></div></div>
                            <div class="ct-point mb-0"><div class="ct-dot dot-dropoff"></div><span class="ct-label info-label">Entrega</span><div id="modalDropoff" class="ct-text fw-bold lh-sm"></div></div>
                        </div>
                    </div>

                    <button id="btnDynamicAction" class="btn-dynamic-action status-btn-0">INICIAR RECOLHA</button>
                    
                    <div class="row g-2">
                        <div class="col-6"><button class="btn btn-sm btn-outline-secondary w-100 py-2 rounded-pill fw-bold" id="uploadVoucher"><i class="bi bi-ticket-perforated"></i> Voucher</button></div>
                        <div class="col-6"><button class="btn btn-sm btn-outline-danger w-100 py-2 rounded-pill fw-bold" id="uploadNoShow"><i class="bi bi-camera"></i> No-Show</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0"><h5 class="modal-title">Foto de Perfil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-center">
                    <form action="../../../save_profile_photo.php" method="POST" enctype="multipart/form-data">
                        <img id="currentProfilePhoto" src="<?php echo $userPhotoPath; ?>" class="rounded-circle shadow mb-4" style="width: 120px; height: 120px; object-fit: cover;">
                        <input type="file" name="profile_photo" id="profilePhotoInput" class="form-control mb-3" accept="image/*" required>
                        <button type="submit" class="btn btn-primary rounded-pill w-100 py-2 fw-bold">Guardar Alteração</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="driver.php" class="nav-item-mobile active"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="driver_agenda.php" class="nav-item-mobile"><i class="bi bi-calendar3"></i><span>Agenda</span></a>
        <a href="driverstats.php" class="nav-item-mobile"><i class="bi bi-bar-chart-fill"></i><span>Stats</span></a>
        <a href="logout.php" class="nav-item-mobile text-danger"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
    </nav>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- 1. DARK MODE & LOGO ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const htmlElement = document.documentElement;
        const headerLogo = document.getElementById('header-logo');
        const logoDark = "../../../assets/images/icons/Syncride.png"; 
        const logoLight = "../../../assets/images/icons/Syncridewhite.png";

        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme); updateLogo(savedTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = htmlElement.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme); updateLogo(newTheme);
        });
        function updateThemeIcon(theme) { themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5'; }
        function updateLogo(theme) { if(headerLogo) headerLogo.src = theme === 'dark' ? logoLight : logoDark; }

        // --- 2. GLOBAL VARS ---
        let backgroundWatcherId = null; let trackingInterval = null; let currentRideId = null; let currentRideData = null;
        let localTripStatus = {}; let currentFilter = "today"; let stream = null; let currentMode = 'noshow';
        let currentFacingMode = 'environment'; let locationWatcher = null; let currentLat = null; let currentLng = null;

        if (typeof viagens !== 'undefined') { viagens.forEach(v => { localTripStatus[v.ServiceID] = parseInt(v.status_id) || 0; }); }

        // --- 3. AUTO REFRESH ---
        function fetchLatestRides() {
            fetch('driver.php?api=refresh').then(r => r.json()).then(data => {
                if (Array.isArray(data)) {
                    viagens = data;
                    viagens.forEach(v => { if (localTripStatus[v.ServiceID] === undefined) localTripStatus[v.ServiceID] = parseInt(v.status_id) || 0; });
                    filterTrips(currentFilter);
                }
            }).catch(err => console.log(err));
        }
        setInterval(fetchLatestRides, 15000);

        // --- 4. GPS & LOGIC ---
        function sendPosition(position) {
            if(!currentRideId) return;
            const lat = position.latitude || position.coords?.latitude; const lng = position.longitude || position.coords?.longitude;
            if (lat === undefined || lng === undefined) { if (window.Capacitor?.Plugins?.BackgroundGeolocation) Capacitor.Plugins.BackgroundGeolocation.finish(); return; }
            const payload = { ride_id: currentRideId, driver_id: <?php echo $_SESSION['user_id'] ?? 0; ?>, lat: lat, lng: lng };
            fetch('../../../Includes/dist/pages/api_update_location.php', { method: 'POST', body: JSON.stringify(payload), headers: {'Content-Type': 'application/json'} }).catch(e => console.log(e));
            if (window.Capacitor?.Plugins?.BackgroundGeolocation) Capacitor.Plugins.BackgroundGeolocation.finish(); 
        }
        function startLiveTracking(rideId) {
    currentRideId = rideId;
    
    if (window.Capacitor?.Plugins?.BackgroundGeolocation) {
        const BGeo = Capacitor.Plugins.BackgroundGeolocation;
        
        if (backgroundWatcherId) return;

        // Configuração robusta para iOS e Android
        BGeo.addWatcher({
            backgroundTitle: "SyncRide em Serviço",
            backgroundMessage: "A sua localização está a ser partilhada",
            requestAllowAlwaysLocation: true, // Crucial para iOS abrir o popup de "Sempre"
            distanceFilter: 10,               // Apenas envia se mover 10 metros (evita spam e poupa bateria)
            staleLocationThreshold: 30,
            radius: 20
        }, (location, error) => {
            if (error) {
                console.error("Erro GPS:", error);
                return;
            }
            if (location) {
                sendPosition(location);
            }
        }).then(id => { 
            backgroundWatcherId = id;
            console.log("Watcher iniciado:", id);
        });

    } else if ("geolocation" in navigator) {
        // ... manter a tua lógica de fallback para browser ...
        if(trackingInterval) clearInterval(trackingInterval); 
        navigator.geolocation.getCurrentPosition(pos => sendPosition(pos));
        trackingInterval = setInterval(() => { 
            navigator.geolocation.getCurrentPosition(pos => sendPosition(pos)); 
        }, 5000);
    }
}
        function stopLiveTracking() {
            if(!currentRideId) return;
            fetch('api_stop_tracking.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ ride_id: currentRideId }) });
            currentRideId = null;
            if (window.Capacitor?.Plugins?.BackgroundGeolocation && backgroundWatcherId) { Capacitor.Plugins.BackgroundGeolocation.removeWatcher({ id: backgroundWatcherId }); backgroundWatcherId = null; } 
            if(trackingInterval) { clearInterval(trackingInterval); trackingInterval = null; }
        }
        document.addEventListener('DOMContentLoaded', async () => {
    if (window.Capacitor?.isNativePlatform()) {
        const { Geolocation, Camera, BackgroundGeolocation } = Capacitor.Plugins;
        
        // 1. Pede primeiro permissões normais (Foreground)
        await Geolocation.requestPermissions();
        await Camera.requestPermissions();
        
        // 2. TENTA pedir a de Background isoladamente
        // Isto prepara o terreno, mas o popup de "Sempre" muitas vezes só 
        // aparece na SEGUNDA vez que a app corre ou quando o Watcher inicia.
        if (BackgroundGeolocation.requestPermissions) {
            await BackgroundGeolocation.requestPermissions();
        }
    }
});

        // --- 5. UI HELPERS ---
        function openWaze(address) { window.location.href = "waze://?q=" + encodeURIComponent(address) + "&navigate=yes"; }
        function updateButtonUI(status) {
            const btn = document.getElementById('btnDynamicAction'); if(!btn) return;
            btn.className = 'btn-dynamic-action'; 
            switch(parseInt(status)) {
                case 0: btn.classList.add('status-btn-0'); btn.innerHTML = '<i class="bi bi-car-front-fill me-2"></i> INICIAR RECOLHA'; btn.disabled = false; break;
                case 1: btn.classList.add('status-btn-1'); btn.innerHTML = '<i class="bi bi-geo-alt-fill me-2"></i> CHEGUEI'; btn.disabled = false; break;
                case 2: btn.classList.add('status-btn-2'); btn.innerHTML = '<i class="bi bi-play-circle-fill me-2"></i> INICIAR VIAGEM'; btn.disabled = false; break;
                case 3: btn.classList.add('status-btn-3'); btn.innerHTML = '<i class="bi bi-stop-circle-fill me-2"></i> TERMINAR'; btn.disabled = false; break;
                default: btn.classList.add('status-btn-4'); btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> CONCLUÍDA'; btn.disabled = true;
            }
        }
        function updateStatusBackend(rideId, nextStatus) {
            let formData = new FormData(); formData.append('ride_id', rideId); formData.append('status', nextStatus);
            fetch('api_update_status.php', { method: 'POST', body: formData }).then(r => r.json()).then(d => {
                if (d.success) {
                    localTripStatus[rideId] = nextStatus; updateButtonUI(nextStatus);
                    if(nextStatus === 4) { setTimeout(() => { bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide(); fetchLatestRides(); }, 1000); }
                } else { alert('Erro ao atualizar estado.'); }
            }).catch(e => console.error(e));
        }
        if(document.getElementById('btnDynamicAction')) {
            document.getElementById('btnDynamicAction').addEventListener('click', function() {
                if(!currentRideData) return;
                const rideId = currentRideData.id; const currentStatus = localTripStatus[rideId]; const nextStatus = currentStatus + 1;
                if(nextStatus > 4) return;
                
                if(currentStatus === 0) { 
                    if(!confirm("Iniciar recolha e abrir GPS?")) return; 
                    
                    // --- NOVA LÓGICA WHATSAPP ---
                    if (currentRideData.clientnumber) {
                        let cNum = currentRideData.clientnumber.replace(/[^0-9]/g, '');
                        if (cNum.length > 7) {
                            // Calcula URL base do sistema para o track.php (assume estrutura padrão)
                            // Se estiver em .../Includes/dist/pages/driver.php, queremos subir para a raiz
                            let baseUrl = window.location.href.split('/Includes/dist/pages/')[0];
                            let trackLink = baseUrl + '/track.php?id=' + rideId;
                            let msg = encodeURIComponent("Hello! Your driver is on the way. Track your driver localization here: " + trackLink);
                            
                            // Abre WhatsApp (numa nova janela/tab para nao interferir com o Waze)
                            window.open("https://wa.me/" + cNum + "?text=" + msg, '_blank');
                        }
                    }
                    // ----------------------------

                    startLiveTracking(rideId); 
                    openWaze(currentRideData.start); 
                }
                else if(currentStatus === 1) { if(!confirm("Confirma que chegou?")) return; }
                else if(currentStatus === 2) { if(!confirm("Iniciar viagem para destino?")) return; openWaze(currentRideData.end); }
                else if(currentStatus === 3) { if(!confirm("Terminar serviço?")) return; stopLiveTracking(); }
                
                updateStatusBackend(rideId, nextStatus);
            });
        }

        // --- 6. RENDER LIST ---
        function formatDate(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`; }
        function filterTrips(filter) {
            currentFilter = filter; if (typeof viagens === 'undefined') return;
            const t = new Date(); const y = new Date(t); y.setDate(t.getDate() - 1); const tm = new Date(t); tm.setDate(t.getDate() + 1);
            const map = { "yesterday": formatDate(y), "today": formatDate(t), "tomorrow": formatDate(tm) };
            renderList(viagens.filter(v => v.serviceDate === map[filter]));
        }
        function renderList(data) {
            const el = document.getElementById("rideList"); if(!el) return; el.innerHTML = "";
            if (data.length === 0) { el.innerHTML = "<div class='text-center py-5 text-muted'><i class='bi bi-calendar-x fs-1 opacity-50'></i><p class='mt-2'>Sem serviços.</p></div>"; return; }
            data.forEach(v => {
                const isPriv = (v.serviceType == 1); const badgeClass = isPriv ? 'badge-private' : 'badge-shared'; const badgeText = isPriv ? 'Privado' : 'Partilhado';
                el.innerHTML += `<div class="ride-card open-modal" data-id="${v.ServiceID}" data-start="${v.serviceStartPoint}" data-end="${v.serviceTargetPoint}" 
                        data-time="${v.serviceStartTime.substr(0, 5)}" data-date="${v.serviceDate}" data-paxadt="${v.paxADT||0}" data-paxchd="${v.paxCHD||0}"
                        data-flight="${v.FlightNumber || ''}" data-client="${v.NomeCliente || ''}" data-clientnumber="${v.ClientNumber || ''}" data-price="${v.total_price || ''}">
                    <div class="ride-header"><div class="ride-time">${v.serviceStartTime.substr(0, 5)}</div><span class="ride-badge ${badgeClass}">${badgeText}</span></div>
                    <div class="card-timeline"><div class="ct-point"><div class="ct-dot dot-pickup"></div><span class="ct-text">${v.serviceStartPoint}</span></div>
                    <div class="ct-point"><div class="ct-dot dot-dropoff"></div><span class="ct-text">${v.serviceTargetPoint}</span></div></div>
                    ${v.total_price > 0 ? `<div class="price-tag"><i class="bi bi-cash"></i> ${parseFloat(v.total_price).toFixed(2)}€</div>` : ''}</div>`;
            });
            document.querySelectorAll(".open-modal").forEach(card => {
                card.addEventListener("click", () => {
                    const d = card.dataset; currentRideData = d;
                    const m = document.getElementById('detailsModal');
                    m.querySelector("#modalIdDisplay").textContent = d.id;
                    m.querySelector("#modalPickup").textContent = d.start; m.querySelector("#modalDropoff").textContent = d.end;
                    
                    m.querySelector("#modalADT").textContent = d.paxadt; m.querySelector("#modalCHD").textContent = d.paxchd;
                    m.querySelector("#modalClient").textContent = d.client || 'Cliente'; m.querySelector("#modalClientNumber").textContent = d.clientnumber;
                    
                    const wa = document.getElementById('whatsappContainer'); wa.innerHTML = ''; wa.style.display = 'none';
                    if (d.clientnumber && d.clientnumber.replace(/[^0-9]/g, '').length > 7) { wa.style.display = 'block'; wa.innerHTML = `<a href="https://wa.me/${d.clientnumber.replace(/[^0-9]/g, '')}" target="_blank" class="btn-whatsapp"><i class="bi bi-whatsapp"></i> WhatsApp</a>`; }
                    
                    const pc = document.getElementById('priceBadgeContainer'); 
                    if (d.price && parseFloat(d.price) > 0) { document.getElementById('modalPriceDisplay').textContent = parseFloat(d.price).toFixed(2) + " €"; pc.style.display = "block"; } else { pc.style.display = "none"; }

                    // Atualiza Nome para a Placa
                    let rawName = d.client || "CLIENTE";
                    const nameEl = document.getElementById('airportClientName');
                    nameEl.innerHTML = rawName.replace(/\s+/g, '<br>');
                    
                    let words = rawName.trim().split(/\s+/).length;
                    nameEl.style.fontSize = words <= 2 ? "12vw" : "8vw";

                    document.getElementById('airportFlight').textContent = d.flight || "";
                    const trackLink = document.getElementById("trackFlightLink");
                    if(d.flight && d.flight.trim() !== '') { trackLink.style.display = "inline-flex"; document.getElementById("modalFlight").textContent = d.flight; trackLink.href = "https://www.flightradar24.com/data/flights/" + d.flight.replace(/\s/g, ''); } else { trackLink.style.display = "none"; }

                    updateButtonUI(localTripStatus[d.id]);
                    new bootstrap.Modal(m).show();
                });
            });
        }
        document.querySelectorAll(".filter-btn").forEach(b => { b.addEventListener("click", function() { document.querySelectorAll(".filter-btn").forEach(x => x.classList.remove("active")); this.classList.add("active"); filterTrips(this.dataset.filter); }); });
        filterTrips("today");

        // --- 7. CAMERA & AIRPORT LOGIC (FIXED) ---
        
        const airportOverlay = document.getElementById('airportOverlay');
        const airportContent = document.getElementById('airportContentWrapper');
        const nameElement = document.getElementById('airportClientName');

        document.getElementById('btnAirportMode').onclick = () => {
            airportOverlay.style.display = "block";
            airportOverlay.classList.remove('landscape-mode');
        };

        document.getElementById('closeAirportMode').onclick = () => {
            airportOverlay.style.display = "none";
            airportOverlay.classList.remove('landscape-mode');
        };

        document.getElementById('rotateScreenBtn').onclick = () => {
            const isLandscape = airportOverlay.classList.toggle('landscape-mode');
            if (isLandscape) {
                 let words = nameElement.innerText.split(/\s+/).length;
                 nameElement.style.fontSize = words <= 2 ? "15vh" : "10vh";
            } else {
                 let words = nameElement.innerText.split(/\s+/).length;
                 nameElement.style.fontSize = words <= 2 ? "15vw" : "10vw";
            }
        };

        // --- Camera Logic ---
        const video = document.getElementById('cameraStream'); const canvas = document.getElementById('photoCanvas');
        const camOverlay = document.getElementById('cameraOverlay'); const loading = document.getElementById('cameraLoading');
        const btnSend = document.getElementById('btnConfirmSend');
        
        function stopCameraStream() { if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; } if(locationWatcher) navigator.geolocation.clearWatch(locationWatcher); }
        function closeCameraOverlay() { stopCameraStream(); camOverlay.style.display = 'none'; }
        
        function updateCameraUI(state) {
             const cCap = document.getElementById('stepCaptureControls');
             const cConf = document.getElementById('stepConfirmControls');
             if (state === 'loading') { loading.style.display='block'; video.style.display='none'; canvas.style.display='none'; cCap.classList.add('d-none'); cConf.classList.add('d-none'); }
             else if (state === 'capture') { loading.style.display='none'; video.style.display='block'; canvas.style.display='none'; cCap.classList.remove('d-none'); cCap.classList.add('d-flex'); cConf.classList.remove('d-flex'); cConf.classList.add('d-none'); }
             else if (state === 'review') { loading.style.display='none'; video.style.display='none'; canvas.style.display='block'; cCap.classList.remove('d-flex'); cCap.classList.add('d-none'); cConf.classList.remove('d-none'); cConf.classList.add('d-flex'); btnSend.disabled=false; btnSend.innerHTML='Enviar'; }
             else if (state === 'sending') { btnSend.disabled=true; btnSend.innerHTML='A enviar...'; }
        }

        async function startCamera() {
            stopCameraStream(); camOverlay.style.display = 'flex'; updateCameraUI('loading');
            try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: currentFacingMode } }); video.srcObject = stream; video.onloadedmetadata = () => updateCameraUI('capture'); } 
            catch (e) { alert('Erro: '+e.message); closeCameraOverlay(); return; }
            if ("geolocation" in navigator) locationWatcher = navigator.geolocation.watchPosition(p => { currentLat=p.coords.latitude; currentLng=p.coords.longitude; }, e=>{}, {enableHighAccuracy:true});
        }
        
        function openCamera(mode) { currentMode = mode; document.getElementById('cameraInstruction').textContent = (mode==='voucher'?'Fotografar Voucher':'Fotografar No-Show'); startCamera(); }
        document.getElementById('uploadNoShow').onclick = () => openCamera('noshow');
        document.getElementById('uploadVoucher').onclick = () => openCamera('voucher');
        document.getElementById('btnRotateCamera').onclick = () => { currentFacingMode = (currentFacingMode==='environment'?'user':'environment'); startCamera(); };
        document.getElementById('btnCapture').onclick = () => { if(!stream) return; canvas.width = video.videoWidth; canvas.height = video.videoHeight; canvas.getContext('2d').drawImage(video, 0, 0); updateCameraUI('review'); };
        document.getElementById('btnRetake').onclick = () => updateCameraUI('capture');
        
        btnSend.onclick = () => {
            updateCameraUI('sending');
            const img = canvas.toDataURL('image/jpeg');
            fetch(currentMode === 'voucher' ? 'upload_voucher.php' : 'upload_no_show.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ trip_id: currentRideData.id, image_data: img, lat: currentLat, lng: currentLng }) })
            .then(r=>r.json()).then(d=>{ if(d.success) { alert(d.message); closeCameraOverlay(); if(currentMode==='noshow'){ stopLiveTracking(); updateStatusBackend(currentRideData.id, 4); } } else { alert('Erro: '+d.message); updateCameraUI('review'); } })
            .catch(e=>{ alert('Erro conexão'); updateCameraUI('review'); });
        };

        const pModal = document.getElementById('photoModal');
        if(pModal) {
            pModal.addEventListener('show.bs.modal', () => { document.getElementById('profilePhotoInput').value = ''; });
            document.getElementById('profilePhotoInput').onchange = (e) => { const [f] = e.target.files; if (f) document.getElementById('currentProfilePhoto').src = URL.createObjectURL(f); };
        }
    </script>
  </body>
</html>