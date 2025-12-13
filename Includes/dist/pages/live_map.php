<?php
session_start();

// 1. VERIFICAÇÃO DE ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 1) {
    header("refresh: 1; url=../../../index.php");
    exit();
}

require_once __DIR__ . '/../../../auth/dbconfig.php';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Live Map | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

    <style>
        :root { --header-height-base: 56px; --bottom-nav-height: 70px; --sheet-width: 380px; }

        /* --- HEADER & LAYOUT --- */
        .app-header { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; height: var(--header-height-base); }
        .app-main { padding: 0 !important; margin: 0 !important; height: 100vh; position: relative; overflow: hidden; }
        
        @media (max-width: 991.98px) {
            .app-header {
                padding-top: env(safe-area-inset-top);
                height: calc(var(--header-height-base) + env(safe-area-inset-top));
                background-color: #ffffff !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .app-sidebar, .navbar-toggler, .bi-list { display: none !important; }
            .app-main {
                margin-top: calc(var(--header-height-base) + env(safe-area-inset-top)) !important;
                padding-bottom: calc(var(--bottom-nav-height) + 20px + env(safe-area-inset-bottom)) !important;
                height: calc(100vh - (var(--bottom-nav-height) + env(safe-area-inset-bottom)));
            }
            .bottom-navbar { display: flex !important; }
        }

        @media (min-width: 992px) {
            .bottom-navbar { display: none !important; }
            .app-main { 
                margin-top: var(--header-height-base) !important; 
                height: calc(100vh - var(--header-height-base));
            }
        }

        /* --- MAPA --- */
        #adminMap { width: 100%; height: 100%; z-index: 1; background: #0f1724; }

        /* MOVIMENTO SUAVE (CSS) */
        .leaflet-marker-icon, .leaflet-marker-shadow {
            transition: transform 3s linear; 
        }

        /* --- WIDGET RADAR (TOPO) --- */
        .status-widget {
            position: absolute; top: 20px; left: 50%; transform: translateX(-50%);
            z-index: 1035;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            padding: 8px 18px;
            border-radius: 50px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex; align-items: center; gap: 12px;
            font-size: 0.9rem; font-weight: 600; color: #1f2937;
            border: 1px solid rgba(255,255,255,0.4);
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

        /* --- DRIVER SHEET (COMPACTA E AJUSTADA) --- */
        .driver-sheet {
            position: absolute; left: 50%; 
            /* Correção para Mobile: Fica acima da barra de navegação */
            bottom: calc(var(--bottom-nav-height) + 20px); 
            transform: translateX(-50%) translateY(120%); /* Escondido por defeito */
            z-index: 1035;
            width: 90%; max-width: var(--sheet-width);
            background: #fff; border-radius: 20px;
            padding: 16px; 
            padding-bottom: 20px; /* Espaço extra em baixo */
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        /* Ajuste para Desktop (encosta mais ao fundo pois não tem nav bar) */
        @media (min-width: 992px) {
            .driver-sheet { bottom: 25px; }
        }

        .driver-sheet.active { transform: translateX(-50%) translateY(0); }

        .sheet-top { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
        .sheet-avatar { 
            width: 46px; height: 46px; border-radius: 50%; background: #f3f4f6; 
            display: flex; align-items: center; justify-content: center; font-size: 22px; color: #6b7280; flex-shrink: 0;
        }
        .sheet-info h4 { margin: 0; font-size: 1rem; font-weight: 700; color: #111; }
        .sheet-info p { margin: 0; font-size: 0.8rem; color: #666; }
        
        .sheet-stats { display: flex; gap: 10px; margin-bottom: 14px; }
        .stat-box { 
            flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; 
            padding: 10px; text-align: center; 
        }
        .stat-label { display: block; font-size: 0.65rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 2px;}
        .stat-value { font-size: 0.95rem; font-weight: 800; color: #0f1724; }

        .dest-bar { 
            background: #eff6ff; color: #1e40af; padding: 12px; border-radius: 12px; 
            display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 600; margin-bottom: 12px;
        }
        .dest-bar i { font-size: 1.1rem; }
        .dest-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .btn-close-sheet {
            width: 100%; background: #0f1724; color: white; border: none; padding: 12px;
            border-radius: 12px; font-size: 0.9rem; font-weight: 600; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-close-sheet:hover { background: #1f2937; }

        .last-update-text {
            display: block; width: 100%; text-align: center;
            font-size: 0.7rem; color: #9ca3af; margin-top: 10px; font-weight: 500;
        }

        /* ÍCONES DO MAPA */
        .car-marker-container { pointer-events: auto; }
        .car-body svg { width: 34px; height: auto; display: block; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4)); transition: transform 0.5s linear; }

        /* Esconder painel de texto de rota, manter a linha no mapa */
        .leaflet-routing-container { display: none !important; }
        
        /* MOBILE BOTTOM NAV */
        .bottom-navbar {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            background: #ffffff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: none; justify-content: space-around; align-items: flex-start;
            padding-top: 10px; padding-bottom: env(safe-area-inset-bottom); z-index: 1040;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
        }
        .nav-item-bottom { display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: #adb5bd; font-size: 10px; font-weight: 500; transition: all 0.3s ease; width: 20%; }
        .nav-item-bottom i { font-size: 22px; margin-bottom: 4px; transition: transform 0.2s; }
        .nav-item-bottom.active { color: #0d6efd; }
        .nav-item-bottom.active i { transform: translateY(-3px); }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
          <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="bi bi-list" style="font-size: 1.5rem;"></i></a></li>
            <li class="nav-item d-lg-none ms-2"><span class="fw-bold fs-5">Live Map</span></li>
            <li class="nav-item d-none d-lg-block"><a href="#" class="nav-link">Home</a></li>
          </ul>
          <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="#" data-lte-toggle="fullscreen"><i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i><i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i></a></li>
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="User" />
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <li class="user-header text-bg-primary">
                  <img src="https://syncride.webminds.pt/Includes/dist/assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="User" />
                  <p><?php echo $_SESSION['name'] ?? 'Admin'; ?> - Admin</p>
                </li>
                <li class="user-footer"><a href="logout.php" class="btn btn-default btn-flat float-end">Sair</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="./admin.php" class="brand-link"><span class="brand-text fw-light">SyncRide</span></a></div>
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
              <li class="nav-item"><a href="admin.php" class="nav-link"><i class="nav-icon bi bi-speedometer"></i><p>Dashboard</p></a></li>
              <li class="nav-item"><a href="live_map.php" class="nav-link active"><i class="nav-icon bi bi-map-fill"></i><p>Live Map</p></a></li>
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
        <a href="admin.php" class="nav-item-bottom"><i class="bi bi-house-door-fill"></i><span>Home</span></a>
        <a href="live_map.php" class="nav-item-bottom active"><i class="bi bi-map-fill"></i><span>Map</span></a>
        <a href="ManageRides.php" class="nav-item-bottom"><i class="bi bi-car-front-fill"></i><span>Viagens</span></a>
        <a href="manageUsers.php" class="nav-item-bottom"><i class="bi bi-people-fill"></i><span>Staff</span></a>
        <a href="#" class="nav-item-bottom" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-grid-fill"></i><span>Mais</span></a>
      </div>

      <div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileMenu" style="height: 50vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header"><h5 class="offcanvas-title fw-bold">Menu</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
        <div class="offcanvas-body">
            <div class="row g-3 text-center">
                <div class="col-4"><a href="ManageNoShows.php" class="d-block p-3 rounded bg-light text-dark text-decoration-none"><i class="bi bi-camera-fill fs-1 text-danger"></i><div class="small mt-2">No Shows</div></a></div>
                <div class="col-4"><a href="manageStorage.php" class="d-block p-3 rounded bg-light text-dark text-decoration-none"><i class="bi bi-hdd-fill fs-1 text-warning"></i><div class="small mt-2">Storage</div></a></div>
                <div class="col-4"><a href="logout.php" class="d-block p-3 rounded bg-light text-dark text-decoration-none"><i class="bi bi-box-arrow-right fs-1 text-secondary"></i><div class="small mt-2">Sair</div></a></div>
            </div>
        </div>
      </div>

      <main class="app-main">
        
        <div class="status-widget">
            <div class="radar-dot"></div>
            <div><span id="activeCount">0</span> <span style="font-weight:400; color:#555;">viagens ativas</span></div>
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
            
            <div class="sheet-stats">
                <div class="stat-box">
                    <span class="stat-label">Velocidade</span>
                    <span class="stat-value" id="sSpeed">0 km/h</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Cliente</span>
                    <span class="stat-value" id="sClient">--</span>
                </div>
            </div>

            <div class="dest-bar">
                <i class="bi bi-geo-alt-fill"></i>
                <div class="dest-text" id="sDest">Sem destino</div>
            </div>

            <button class="btn-close-sheet" onclick="closeSheet()">Fechar</button>
            <span class="last-update-text" id="sUpdate">Atualizado: --:--:--</span>
        </div>

      </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/js/adminlte.min.js"></script>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script>
    // Config inicial do Mapa
    var map = L.map('adminMap', { zoomControl: false, attributionControl: false }).setView([41.15, -8.62], 12);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 20 }).addTo(map);
    L.control.zoom({position: 'bottomright'}).addTo(map);

    // SVG Carro
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
            // NOVO MOTORISTA
            var icon = L.divIcon({ className: 'car-marker-container', html: `<div class="car-body">${carSvg}</div>`, iconSize: [30,60], iconAnchor: [15,30] });
            var m = L.marker([lat, lng], { icon: icon }).addTo(map);
            rotateMarker(m, d.heading);
            m.on('click', function() { openSheetForDriver(id); });

            drivers[id] = { marker: m, routeLayer: null, destMarker: null, data: d };
            
            if (d.serviceTargetPoint && d.serviceTargetPoint !== 'N/A') ensureRoute(id, d);

          } else {
            // ATUALIZAR
            var entry = drivers[id];
            entry.data = d;
            entry.marker.setLatLng([lat, lng]);
            rotateMarker(entry.marker, d.heading);

            if(isSheetOpenFor(id)) updateSheet(d);
          }
        });

        cleanupDrivers(activeIds);

        // AUTO ZOOM (só se o painel estiver fechado, para não incomodar)
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

    // --- SHEET LOGIC ATUALIZADA ---
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
        // Converte string SQL (YYYY-MM-DD HH:MM:SS) para JS Date
        // O replace é para garantir compatibilidade com Safari (substitui - por /)
        var date = new Date(dateStr.replace(/-/g, "/"));
        var time = date.toLocaleTimeString('pt-PT'); // Formato HH:MM:SS
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