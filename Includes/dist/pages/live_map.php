<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../auth/dbconfig.php';

// Lógica da Foto de Perfil
$defaultPhoto = "../assets/img/user2-160x160.jpg"; 
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Live Map | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

    <style>
        /* --- DESIGN SYSTEM MODERNO (SaaS) --- */
        :root {
            --font-primary: 'Inter', sans-serif;
            --font-display: 'Poppins', sans-serif;
            
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --primary-accent: #4f46e5;
            --border-color: #e5e7eb;
            
            --header-height: 70px;
            --bottom-nav-height: 70px;
            --sheet-width: 380px;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f9fafb;
            --text-muted: #94a3b8;
            --primary-accent: #6366f1;
            --border-color: #334155;
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow: hidden; /* Importante para o mapa full screen */
        }

        /* --- LAYOUT FIXES FOR MAP --- */
        .app-wrapper { height: 100vh; display: flex; flex-direction: column; }
        
        .app-header {
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color); height: var(--header-height);
            position: fixed; top: 0; width: 100%; z-index: 1040;
        }
        [data-bs-theme="dark"] .app-header { background: rgba(30, 41, 59, 0.85); }

        .app-sidebar {
            background-color: var(--bg-card); border-right: 1px solid var(--border-color);
            position: fixed; height: 100vh; z-index: 1039; top: 0; padding-top: var(--header-height);
        }
        
        .app-main {
            flex: 1; margin-top: var(--header-height); 
            height: calc(100vh - var(--header-height));
            position: relative; padding: 0 !important;
        }

        /* Mobile adjustments */
        @media (max-width: 991.98px) {
            .app-main {
                /* Desconta altura do header e do bottom nav */
                height: calc(100vh - var(--header-height) - var(--bottom-nav-height));
                padding-bottom: env(safe-area-inset-bottom);
            }
        }

        /* --- MAPA --- */
        #adminMap { width: 100%; height: 100%; z-index: 1; background: #0f1724; }
        
        /* Marker Animation */
        .leaflet-marker-icon, .leaflet-marker-shadow { transition: transform 3s linear; }
        .leaflet-routing-container { display: none !important; }

        /* --- WIDGET RADAR (Topo) --- */
        .status-widget {
            position: absolute; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1030;
            background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px);
            padding: 8px 20px; border-radius: 50px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 10px;
            font-size: 0.9rem; font-weight: 600; color: #111827;
            border: 1px solid rgba(255,255,255,0.5);
        }
        [data-bs-theme="dark"] .status-widget {
            background: rgba(30, 41, 59, 0.8); color: #f9fafb; border-color: rgba(255,255,255,0.1);
        }
        .radar-dot {
            width: 10px; height: 10px; background: #10b981; border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: radar-pulse 2s infinite;
        }
        @keyframes radar-pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* --- DRIVER SHEET (Bottom Overlay) --- */
        .driver-sheet {
            position: absolute; left: 50%; bottom: 30px;
            transform: translateX(-50%) translateY(150%); /* Hidden */
            z-index: 1035; width: 90%; max-width: var(--sheet-width);
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 24px; padding: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .driver-sheet.active { transform: translateX(-50%) translateY(0); }

        .sheet-top { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .sheet-avatar { 
            width: 50px; height: 50px; border-radius: 50%; background: var(--bg-body); 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.5rem; color: var(--text-muted); border: 1px solid var(--border-color);
        }
        .sheet-info h4 { margin: 0; font-family: var(--font-display); font-weight: 700; color: var(--text-main); font-size: 1.1rem; }
        .sheet-info p { margin: 0; font-size: 0.85rem; color: var(--text-muted); }
        
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .stat-box { 
            background: var(--bg-body); border-radius: 16px; padding: 12px; text-align: center; border: 1px solid var(--border-color);
        }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; display: block; margin-bottom: 4px; }
        .stat-value { font-size: 1.1rem; font-weight: 800; color: var(--text-main); font-family: var(--font-display); }

        .dest-bar { 
            background: rgba(79, 70, 229, 0.1); color: var(--primary-accent); 
            padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px; 
            font-size: 0.9rem; font-weight: 600; margin-bottom: 15px;
        }
        .dest-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .btn-close-sheet {
            width: 100%; background: var(--text-main); color: var(--bg-card); border: none; 
            padding: 14px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: opacity 0.2s;
        }
        .btn-close-sheet:hover { opacity: 0.9; }
        .last-update { font-size: 0.7rem; color: var(--text-muted); text-align: center; margin-top: 10px; display: block; }

        /* --- SIDEBAR & MENU STYLES (SHARED) --- */
        .sidebar-brand { height: 70px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--border-color); }
        .brand-link { text-decoration: none; }
        .brand-image { max-height: 45px; width: auto; transition: transform 0.3s; }
        .brand-image:hover { transform: scale(1.05); }
        
        .sidebar-menu .nav-link { color: var(--text-muted); border-radius: 10px; margin: 4px 12px; padding: 10px 16px; font-weight: 500; transition: all 0.2s; }
        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active { background-color: var(--primary-accent); color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .sidebar-menu .nav-icon { margin-right: 12px; font-size: 1.1rem; }

        /* --- BOTTOM NAV (CORRIGIDA) --- */
        .bottom-navbar {
            background-color: var(--bg-card); border-top: 1px solid var(--border-color);
            z-index: 1050; padding-bottom: env(safe-area-inset-bottom);
            position: fixed; bottom: 0; left: 0; width: 100%; 
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            display: none; justify-content: space-around; align-items: center;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
        }
        @media (max-width: 991.98px) { .bottom-navbar { display: flex; } }
        
        .nav-item-bottom {
            color: var(--text-muted); font-size: 0.7rem; font-weight: 600; text-decoration: none;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; width: 100%; transition: color 0.2s;
        }
        .nav-item-bottom.active { color: var(--primary-accent); }
        .nav-item-bottom i { font-size: 1.4rem; margin-bottom: 4px; }

        /* --- MOBILE MENU GRID --- */
        .quick-action-btn {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 15px; border-radius: 16px; background-color: var(--bg-body);
            color: var(--text-main); text-decoration: none; border: 1px solid var(--border-color);
            transition: transform 0.1s; height: 100%;
        }
        .quick-action-btn:active { transform: scale(0.96); }
        .quick-action-btn i { font-size: 1.8rem; margin-bottom: 8px; }
        .quick-action-btn span { font-size: 0.8rem; font-weight: 600; }
        
        /* Map Icons */
        .car-marker-container { pointer-events: auto; }
        .car-body svg { width: 34px; height: auto; display: block; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4)); transition: transform 0.5s linear; }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg">
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list text-muted fs-4"></i>
              </a>
            </li>
            <li class="nav-item d-lg-none ms-2 d-flex align-items-center">
                <img src="../../../assets/images/icons/SyncRide.png" id="header-logo" alt="SyncRide" style="height: 30px;">
            </li>
          </ul>
          <ul class="navbar-nav ms-auto align-items-center">
            
            <li class="nav-item me-3">
                <button class="btn btn-link text-muted p-0 border-0" id="theme-toggle">
                    <i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i>
                </button>
            </li>

            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <img src="<?php echo $userPhoto; ?>" class="user-image rounded-circle shadow-sm" alt="User Image" style="width: 38px; height: 38px; object-fit: cover;">
                <div class="d-none d-md-block text-start lh-1">
                    <span class="d-block fw-semibold text-main small"><?php echo $_SESSION['name']; ?></span>
                    <span class="text-muted" style="font-size: 0.7rem;">Administrador</span>
                </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end border-0 shadow-lg rounded-4 overflow-hidden mt-2">
                <li class="user-header bg-primary text-white p-4 text-center">
                  <img src="<?php echo $userPhoto; ?>" class="rounded-circle shadow mb-2 border border-2 border-white" alt="User Image" style="width: 80px; height: 80px; object-fit: cover;">
                  <p class="mb-0 fw-bold"><?php echo $_SESSION['name']; ?></p>
                  <small class="opacity-75">Gestor de Frota</small>
                </li>
                <li class="user-footer p-3 bg-card d-flex justify-content-between">
                  <a href="#" class="btn btn-light btn-sm rounded-pill px-4">Perfil</a>
                  <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-4">Sair</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar">
        <div class="sidebar-brand">
          <a href="./admin.php" class="brand-link">
            <img src="../../../assets/images/icons/SyncRide.png" id="sidebar-logo" alt="SyncRide Logo" class="brand-image" style="opacity: 1;">
          </a>
        </div>
        
        <div class="sidebar-wrapper mt-3">
          <nav>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <li class="nav-header text-muted fw-bold small text-uppercase px-3 mb-2">Visão Geral</li>
              
              <li class="nav-item">
                  <a href="admin.php" class="nav-link">
                      <i class="nav-icon bi bi-grid-fill"></i>
                      <p>Dashboard</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="live_map.php" class="nav-link active">
                      <i class="nav-icon bi bi-map-fill"></i>
                      <p>Live Map</p>
                  </a>
              </li>
              <li class="nav-item">
                  <a href="ManageRides.php" class="nav-link">
                      <i class="nav-icon bi bi-car-front-fill"></i>
                      <p>Viagens</p>
                  </a>
              </li>
              
              <li class="nav-header text-muted fw-bold small text-uppercase px-3 mb-2 mt-4">Gestão</li>
              
              <li class="nav-item"><a href="manageUsers.php" class="nav-link"><i class="nav-icon bi bi-people-fill"></i><p>Equipa</p></a></li>
              <li class="nav-item"><a href="manageFleet.php" class="nav-link"><i class="nav-icon bi bi-truck-front-fill"></i><p>Frota</p></a></li>
              <li class="nav-item"><a href="financial.php" class="nav-link"><i class="nav-icon bi bi-cash-coin"></i><p>Financeiro</p></a></li>
              <li class="nav-item"><a href="admin_driver_stats.php" class="nav-link"><i class="nav-icon bi bi-bar-chart-fill"></i><p>Estatísticas</p></a></li>
              <li class="nav-item"><a href="ManageNoShows.php" class="nav-link"><i class="nav-icon bi bi-exclamation-triangle-fill"></i><p>No Shows</p></a></li>
              <li class="nav-item"><a href="manageStorage.php" class="nav-link"><i class="nav-icon bi bi-hdd-network-fill"></i><p>Armazenamento</p></a></li>
            </ul>
          </nav>
        </div>
      </aside>

      <div class="bottom-navbar">
        <a href="admin.php" class="nav-item-bottom">
            <i class="bi bi-grid-fill"></i><span>Home</span>
        </a>
        <a href="ManageRides.php" class="nav-item-bottom">
            <i class="bi bi-car-front-fill"></i><span>Viagens</span>
        </a>
        <a href="live_map.php" class="nav-item-bottom active">
            <i class="bi bi-map-fill"></i><span>LiveMap</span>
        </a>
        <a href="#" class="nav-item-bottom" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
            <i class="bi bi-three-dots"></i><span>Menu</span>
        </a>
      </div>

      <div class="offcanvas offcanvas-bottom rounded-top-4" tabindex="-1" id="mobileMenu" style="height: auto; min-height: 40vh;">
        <div class="offcanvas-header pb-0">
          <h5 class="offcanvas-title fw-bold text-main">Menu Rápido</h5>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body pt-3">
            <div class="row g-3">
                <div class="col-4 text-center"><a href="financial.php" class="quick-action-btn shadow-sm"><i class="bi bi-cash-coin text-success"></i><span>Finanças</span></a></div>
                <div class="col-4 text-center"><a href="manageFleet.php" class="quick-action-btn shadow-sm"><i class="bi bi-truck-front-fill text-primary"></i><span>Frota</span></a></div>
                <div class="col-4 text-center"><a href="manageUsers.php" class="quick-action-btn shadow-sm"><i class="bi bi-people-fill text-info"></i><span>Equipa</span></a></div>
                <div class="col-4 text-center"><a href="admin_driver_stats.php" class="quick-action-btn shadow-sm"><i class="bi bi-bar-chart-fill text-warning"></i><span>Stats</span></a></div>
                <div class="col-4 text-center"><a href="ManageNoShows.php" class="quick-action-btn shadow-sm"><i class="bi bi-camera-fill text-danger"></i><span>NoShow</span></a></div>
                <div class="col-4 text-center"><a href="manageStorage.php" class="quick-action-btn shadow-sm"><i class="bi bi-hdd-fill text-secondary"></i><span>Storage</span></a></div>
                <div class="col-12 mt-3">
                    <a href="logout.php" class="d-block p-3 rounded-4 bg-light text-decoration-none shadow-sm text-center text-danger fw-bold">
                        <i class="bi bi-box-arrow-right me-2"></i> Sair
                    </a>
                </div>
            </div>
        </div>
      </div>

      <main class="app-main">
        
        <div class="status-widget">
            <div class="radar-dot"></div>
            <div><span id="activeCount">0</span> <span style="font-weight:400; opacity: 0.8;">viagens ativas</span></div>
        </div>

        <div id="adminMap"></div>

        <div class="driver-sheet" id="driverSheet" aria-hidden="true">
            <div class="sheet-top">
                <div class="sheet-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="sheet-info">
                    <h4 id="sDriver">Motorista</h4>
                    <p id="sVehicle">Viatura --</p>
                </div>
            </div>
            
            <div class="stat-grid">
                <div class="stat-box">
                    <span class="stat-label">Velocidade</span>
                    <span class="stat-value" id="sSpeed">0 km/h</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Cliente</span>
                    <span class="stat-value" id="sClient" style="font-size: 0.9rem; padding-top: 2px;">--</span>
                </div>
            </div>

            <div class="dest-bar">
                <i class="bi bi-geo-alt-fill"></i>
                <div class="dest-text" id="sDest">Sem destino definido</div>
            </div>

            <button class="btn-close-sheet" onclick="closeSheet()">Fechar</button>
            <span class="last-update" id="sUpdate">Atualizado: --:--:--</span>
        </div>

      </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script>
    // --- Dark Mode & Logo Logic ---
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const htmlElement = document.documentElement;
    const headerLogo = document.getElementById('header-logo');
    const sidebarLogo = document.getElementById('sidebar-logo');
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
        if(sidebarLogo) sidebarLogo.src = newSrc;
    }

    // --- MAP LOGIC ---
    var map = L.map('adminMap', { zoomControl: false, attributionControl: false }).setView([41.15, -8.62], 12);
    // Dark Tiles by default for better contrast with overlays
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 20 }).addTo(map);
    L.control.zoom({position: 'bottomright'}).addTo(map);

    // SVG Car Icon
    const carSvg = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 100">
      <path d="M5,15 Q25,-5 45,15 L48,85 Q48,98 25,98 Q2,98 2,85 Z" fill="#d1d5db" stroke="#999" stroke-width="1"/>
      <path d="M6,30 Q25,25 44,30 L42,45 Q25,40 8,45 Z" fill="#1f2937"/>
      <path d="M10,75 Q25,72 40,75 L38,82 Q25,85 12,82 Z" fill="#1f2937"/>
      <rect x="4" y="90" width="8" height="3" fill="#dc2626" rx="1"/><rect x="38" y="90" width="8" height="3" fill="#dc2626" rx="1"/>
    </svg>`;

    var destIcon = L.divIcon({
      className: 'dest-pin',
      html: '<svg width="24" height="24" viewBox="0 0 24 24"><path fill="#3b82f6" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 11 7 11s7-5.75 7-11c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5" fill="#fff"/></svg>',
      iconSize: [24, 24], iconAnchor: [12, 24]
    });

    var drivers = {}; 
    var isFetching = false;

    function refresh() {
      if (isFetching) return;
      isFetching = true;

      fetch('api_get_tracking.php').then(r=>r.json()).then(res=>{
        isFetching = false;
        if (!res.success || !res.data) return;

        var activeIds = [];
        var allLatLngs = []; 

        document.getElementById('activeCount').innerText = res.data.length;

        res.data.forEach(d => {
          var id = String(d.driver_id);
          activeIds.push(id);
          var lat = parseFloat(d.latitude);
          var lng = parseFloat(d.longitude);
          
          if(isNaN(lat) || isNaN(lng)) return; 

          allLatLngs.push([lat, lng]);

          if (!drivers[id]) {
            var icon = L.divIcon({ className: 'car-marker-container', html: `<div class="car-body">${carSvg}</div>`, iconSize: [30,60], iconAnchor: [15,30] });
            var m = L.marker([lat, lng], { icon: icon }).addTo(map);
            rotateMarker(m, d.heading);
            m.on('click', function() { openSheetForDriver(id); });

            drivers[id] = { marker: m, routeLayer: null, destMarker: null, data: d };
            
            if (d.serviceTargetPoint && d.serviceTargetPoint !== 'N/A') ensureRoute(id, d);

          } else {
            var entry = drivers[id];
            entry.data = d;
            entry.marker.setLatLng([lat, lng]);
            rotateMarker(entry.marker, d.heading);

            if(isSheetOpenFor(id)) updateSheet(d);
          }
        });

        cleanupDrivers(activeIds);

        // Auto zoom if sheet is closed
        if (allLatLngs.length > 0 && !isSheetOpen()) {
             var bounds = L.latLngBounds(allLatLngs);
             map.fitBounds(bounds, { padding: [80, 80], maxZoom: 16, animate: true, duration: 1.5 });
        }

      }).catch(err=>{ isFetching = false; console.error(err); });
    }

    function rotateMarker(marker, heading) {
        let el = marker.getElement();
        if(el) { 
            let svg = el.querySelector('svg'); 
            if(svg) svg.style.transform = `rotate(${heading || 0}deg)`; 
        }
    }

    function ensureRoute(id, d) {
        var entry = drivers[id];
        if(entry.routeLayer) return;

        var url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(d.serviceTargetPoint + ', Portugal')}`;
        
        fetch(url).then(r=>r.json()).then(res => {
            if(res && res.length > 0) {
                var destLatLng = [parseFloat(res[0].lat), parseFloat(res[0].lon)];
                var startLatLng = [d.latitude, d.longitude];

                if(!entry.destMarker) entry.destMarker = L.marker(destLatLng, {icon: destIcon}).addTo(map);

                try {
                    entry.routeLayer = L.Routing.control({
                        waypoints: [ L.latLng(startLatLng), L.latLng(destLatLng) ],
                        lineOptions: { styles: [{color: '#0ea5e9', opacity: 0.8, weight: 5}] },
                        createMarker: function() { return null; },
                        addWaypoints: false, draggableWaypoints: false, fitSelectedRoutes: false, show: false
                    }).addTo(map);
                } catch(e) {}
            }
        });
    }

    function cleanupDrivers(activeIds) {
        Object.keys(drivers).forEach(eid=> {
          if (!activeIds.includes(eid)) {
            var e = drivers[eid];
            map.removeLayer(e.marker);
            if (e.routeLayer) { try{ map.removeControl(e.routeLayer); }catch(x){} }
            if (e.destMarker) map.removeLayer(e.destMarker);
            delete drivers[eid];
          }
        });
    }

    function openSheetForDriver(id) {
        var entry = drivers[id];
        if(!entry) return;
        var d = entry.data;
        
        document.getElementById('sDriver').innerText = d.driver_name;
        document.getElementById('sDriver').dataset.did = id;
        document.getElementById('sClient').innerText = d.NomeCliente || '--';
        document.getElementById('sDest').innerText = d.serviceTargetPoint || 'Sem destino';
        document.getElementById('sSpeed').innerText = Math.round(d.speed||0) + " km/h";
        
        updateTimestamp(d.last_update);
        
        document.getElementById('driverSheet').classList.add('active');
        map.panTo(entry.marker.getLatLng());
    }

    function updateSheet(d) {
        document.getElementById('sSpeed').innerText = Math.round(d.speed||0) + " km/h";
        updateTimestamp(d.last_update);
    }

    function updateTimestamp(dateStr) {
        if(!dateStr) {
            document.getElementById('sUpdate').innerText = "A aguardar atualização...";
            return;
        }
        var date = new Date(dateStr.replace(/-/g, "/"));
        var time = date.toLocaleTimeString('pt-PT');
        document.getElementById('sUpdate').innerText = "Atualizado às: " + time;
    }

    function closeSheet() {
        document.getElementById('driverSheet').classList.remove('active');
        document.getElementById('sDriver').dataset.did = "";
    }

    function isSheetOpenFor(id) {
        return document.getElementById('driverSheet').classList.contains('active') && 
               document.getElementById('sDriver').dataset.did === id;
    }
    
    function isSheetOpen() {
        return document.getElementById('driverSheet').classList.contains('active');
    }

    setInterval(refresh, 3000);
    refresh();

    window.addEventListener('resize', function(){ map.invalidateSize(); });
    </script>
</body>
</html>