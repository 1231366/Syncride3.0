<?php
// track.php (SyncRide v3.6 - Manual Rating & Submission Lock - Full Integrity)
session_start();
require 'auth/dbconfig.php';

$rideId = $_GET['id'] ?? null;
if (!$rideId) die("Error: Ride not specified.");

// --- API INTERNA (AJAX) ---
if (isset($_GET['check_status'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("
            SELECT 
                rt.latitude, rt.longitude, rt.speed, rt.heading,
                s.status_id
            FROM Services s
            LEFT JOIN RideTracking rt ON s.ID = rt.ride_id
            WHERE s.ID = ?
            ORDER BY rt.last_update DESC LIMIT 1
        ");
        $stmt->execute([$rideId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            $stmt = $pdo->prepare("SELECT status_id FROM Services WHERE ID = ?");
            $stmt->execute([$rideId]);
            $statusOnly = $stmt->fetch(PDO::FETCH_ASSOC);
            $data = ['status_id' => $statusOnly['status_id'] ?? 0, 'latitude' => null, 'longitude' => null];
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- NOVO: AJAX PARA SALVAR AVALIAÇÃO ---
if (isset($_GET['rate_driver'])) {
    header('Content-Type: application/json');
    $rating = $_POST['rating'] ?? null;
    if ($rating && $rideId) {
        // Verifica se já existe rating para não permitir duplicados
        $stmt = $pdo->prepare("UPDATE Services SET driver_rating = ? WHERE ID = ? AND driver_rating IS NULL");
        $stmt->execute([$rating, $rideId]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// 1. BUSCAR DADOS
$stmt = $pdo->prepare("
    SELECT 
        s.serviceStartPoint, s.serviceTargetPoint, s.NomeCliente, s.ClientNumber, s.status_id, s.driver_rating,
        u.id AS driver_id, u.name AS driver_name, u.phone AS driver_phone, u.profile_photo_path, 
        v.brand, v.model, v.license_plate, v.photo_path,
        (SELECT AVG(driver_rating) FROM Services sr 
         INNER JOIN Services_Rides srr ON sr.ID = srr.RideID 
         WHERE srr.UserID = u.id AND sr.driver_rating IS NOT NULL) as avg_rating
    FROM Services s
    LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
    LEFT JOIN Users u ON sr.UserID = u.id
    LEFT JOIN Vehicles v ON u.assigned_vehicle_id = v.id
    WHERE s.ID = ?
");
$stmt->execute([$rideId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) die("Error: Trip not found.");

$isFinished = ($trip['status_id'] >= 4);
$alreadyRated = ($trip['driver_rating'] !== null);
$driverRating = $trip['avg_rating'] ? number_format($trip['avg_rating'], 1) : "5.0";

// 2. LIVE LOCATION INICIAL
$stmt = $pdo->prepare("SELECT latitude, longitude FROM RideTracking WHERE ride_id = ? ORDER BY last_update DESC LIMIT 1");
$stmt->execute([$rideId]);
$liveLocation = $stmt->fetch(PDO::FETCH_ASSOC);

$lat = $liveLocation['latitude'] ?? 41.15; 
$lng = $liveLocation['longitude'] ?? -8.62;

$pickUpPoint = $trip['serviceStartPoint'] ?? 'Pick-up Point';
$dropOffPoint = $trip['serviceTargetPoint'] ?? 'Destination';
$driverName = $trip['driver_name'] ?? 'SyncRide Driver';
$driverPhone = preg_replace('/[^0-9]/', '', $trip['driver_phone'] ?? '');
$whatsappLink = "https://wa.me/{$driverPhone}?text=Hi%2C%20I'm%20waiting%20for%20ride%20%23{$rideId}.";

$driverPhotoURL = !empty($trip['profile_photo_path']) ? $trip['profile_photo_path'] : 'Includes/dist/assets/img/user2-160x160.jpg';
$vehiclePhotoURL = !empty($trip['photo_path']) ? $trip['photo_path'] : 'Includes/dist/pages/uploads/vehicles/defaultcar.png';
$vehicleName = trim(($trip['brand'] ?? '') . ' ' . ($trip['model'] ?? ''));
$vehiclePlate = $trip['license_plate'] ?? 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Track Ride | SyncRide</title>

<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

<style>
    :root {
        --primary: #3b82f6;
        --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
        --dark-bg: #0f172a;
        --glass-bg: rgba(15, 23, 42, 0.85);
        --glass-border: rgba(255, 255, 255, 0.1);
        --text-light: #f8fafc;
        --text-dim: #94a3b8;
    }

    * { box-sizing: border-box; }
    body, html { margin:0; padding:0; height:100%; width: 100%; font-family: 'Outfit', sans-serif; background: var(--dark-bg); overflow: hidden; }
    #map { height: 100%; width: 100%; z-index: 1; background: #1a1a1a; }

    .leaflet-routing-container { display: none !important; }
    .leaflet-control-attribution { display: none !important; }

    .eta-pill {
        position: absolute; top: 60px; left: 50%; transform: translateX(-50%);
        background: var(--glass-bg); backdrop-filter: blur(12px);
        padding: 10px 30px; border-radius: 50px;
        border: 1px solid var(--glass-border);
        box-shadow: 0 15px 40px rgba(0,0,0,0.4);
        display: flex; flex-direction: column; align-items: center;
        z-index: 900; transition: all 0.3s ease;
    }
    .eta-time { font-size: 2rem; font-weight: 800; background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1; }
    .eta-label { font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; font-weight: 600; }

    .driver-sheet {
        position: fixed; bottom: 0; left: 0; width: 100%;
        background: var(--glass-bg); backdrop-filter: blur(20px);
        border-radius: 35px 35px 0 0;
        border-top: 1px solid var(--glass-border);
        padding: 30px 25px 45px;
        box-shadow: 0 -10px 50px rgba(0,0,0,0.6);
        z-index: 999;
    }

    .status-header { margin-bottom: 25px; display: flex; align-items: center; gap: 15px; }
    .status-icon-box { 
        width: 50px; height: 50px; border-radius: 50%; 
        background: rgba(59, 130, 246, 0.15); color: var(--primary);
        display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
    }
    .status-text h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: var(--text-light); }
    .status-text p { margin: 4px 0 0; font-size: 0.95rem; color: var(--text-dim); max-width: 70vw; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .driver-card {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 18px;
        display: flex; align-items: center; justify-content: space-between;
    }

    .profile-group { display: flex; align-items: center; gap: 15px; }
    .driver-img { width: 55px; height: 55px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); }
    .driver-info h4 { margin: 0; font-size: 1.05rem; font-weight: 600; color: var(--text-light); }
    .driver-info span { font-size: 0.85rem; color: var(--text-dim); display: flex; align-items: center; gap: 8px; margin-top: 4px; }
    .plate-badge { background: rgba(255,255,255,0.15); padding: 2px 8px; border-radius: 6px; font-weight: 700; color: #fff; font-size: 0.75rem; }
    
    .rating-badge { color: #f59e0b; display: flex; align-items: center; gap: 4px; font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; }

    .action-group { display: flex; gap: 12px; }
    .btn-circle {
        width: 48px; height: 48px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        text-decoration: none; color: white; font-size: 1.2rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .btn-call { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .btn-wa { background: linear-gradient(135deg, #10b981, #059669); }

    #thankYouScreen {
        position: fixed; inset: 0;
        width: 100vw; height: 100vh;
        background: var(--dark-bg);
        display: <?php echo $isFinished ? 'flex' : 'none'; ?>;
        flex-direction: column; align-items: center; justify-content: center;
        z-index: 10000; text-align: center; padding: 30px;
        opacity: <?php echo $isFinished ? '1' : '0'; ?>;
        transition: opacity 0.5s ease;
    }
    .ty-icon-box {
        width: 110px; height: 110px; border-radius: 50%;
        background: var(--primary-gradient);
        display: flex; align-items: center; justify-content: center;
        font-size: 3.5rem; color: white; margin-bottom: 30px;
        box-shadow: 0 0 50px rgba(59, 130, 246, 0.4);
    }
    .ty-title { font-size: 2.8rem; font-weight: 800; color: white; margin-bottom: 15px; }
    .ty-text { color: var(--text-dim); font-size: 1.15rem; max-width: 320px; margin-bottom: 20px; }
    
    /* ESTRELAS DE AVALIAÇÃO */
    .star-rating { display: flex; gap: 10px; margin-bottom: 30px; }
    .star-rating i { font-size: 2.5rem; color: #334155; cursor: pointer; transition: 0.2s; }
    .star-rating i.active { color: #f59e0b; text-shadow: 0 0 15px rgba(245, 158, 11, 0.5); }
    
    .ty-btn {
        background: var(--primary-gradient); border: none;
        color: white; padding: 16px 50px; border-radius: 50px;
        font-weight: 600; cursor: pointer; display: inline-block;
        transition: all 0.3s ease;
    }
    .ty-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .custom-car-icon { filter: drop-shadow(0 10px 15px rgba(0,0,0,0.5)); transition: transform 0.5s linear; }
</style>
</head>
<body>

    <div id="thankYouScreen">
        <div class="ty-icon-box"><i class="bi bi-check-lg"></i></div>
        <div class="ty-title">Arrived!</div>
        <div class="ty-text" id="ratingStatusText"><?php echo $alreadyRated ? "Thank you for your rating!" : "Rate your experience with " . htmlspecialchars($driverName); ?></div>
        
        <div class="star-rating" id="starContainer" style="<?php echo $alreadyRated ? 'pointer-events:none;' : ''; ?>">
            <i class="bi bi-star-fill <?php echo ($trip['driver_rating'] >= 1) ? 'active' : ''; ?>" data-v="1"></i>
            <i class="bi bi-star-fill <?php echo ($trip['driver_rating'] >= 2) ? 'active' : ''; ?>" data-v="2"></i>
            <i class="bi bi-star-fill <?php echo ($trip['driver_rating'] >= 3) ? 'active' : ''; ?>" data-v="3"></i>
            <i class="bi bi-star-fill <?php echo ($trip['driver_rating'] >= 4) ? 'active' : ''; ?>" data-v="4"></i>
            <i class="bi bi-star-fill <?php echo ($trip['driver_rating'] >= 5) ? 'active' : ''; ?>" data-v="5"></i>
        </div>

        <?php if (!$alreadyRated): ?>
            <button class="ty-btn" id="btnSubmitRating" style="display:none; margin-bottom: 15px;">Submit Rating</button>
        <?php endif; ?>

        <button onclick="window.close()" class="ty-btn" id="btnCloseTY" style="<?php echo !$alreadyRated ? 'background:rgba(255,255,255,0.05);' : ''; ?>">Finish</button>
    </div>

    <div id="map"></div>

    <div class="eta-pill" id="etaContainer" style="display:none;">
        <div class="eta-time" id="etaValue">--</div>
        <div class="eta-label">MINUTES</div>
    </div>

    <div class="driver-sheet">
        <div class="status-header">
            <div class="status-icon-box" id="statusIconBg">
                <i class="bi bi-car-front-fill" id="statusIcon"></i>
            </div>
            <div class="status-text">
                <h2 id="statusTitle">Driver is on the way</h2>
                <p id="statusAddress"><?php echo htmlspecialchars($pickUpPoint); ?></p>
            </div>
        </div>

        <div class="driver-card">
            <div class="profile-group">
                <img src="<?php echo $driverPhotoURL; ?>" class="driver-img">
                <div class="driver-info">
                    <div class="rating-badge"><i class="bi bi-star-fill"></i> <?php echo $driverRating; ?></div>
                    <h4><?php echo htmlspecialchars($driverName); ?></h4>
                    <span>
                        <span class="plate-badge"><?php echo htmlspecialchars($vehiclePlate); ?></span>
                        <?php echo htmlspecialchars($vehicleName); ?>
                    </span>
                </div>
            </div>
            <div class="action-group">
                <a href="tel:<?php echo $driverPhone; ?>" class="btn-circle btn-call"><i class="bi bi-telephone-fill"></i></a>
                <a href="<?php echo $whatsappLink; ?>" target="_blank" class="btn-circle btn-wa"><i class="bi bi-whatsapp"></i></a>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script>
    const RIDE_ID = <?php echo $rideId; ?>;
    const PICKUP_TXT = "<?php echo htmlspecialchars($pickUpPoint); ?>";
    const DROPOFF_TXT = "<?php echo htmlspecialchars($dropOffPoint); ?>";
    const CAR_IMG = "<?php echo $vehiclePhotoURL; ?>";
    let selectedRating = 0;

    const map = L.map('map', { zoomControl: false, attributionControl: false })
        .setView([<?php echo $lat; ?>, <?php echo $lng; ?>], 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 20
    }).addTo(map);

    const carIcon = L.divIcon({
        html: `<img src="${CAR_IMG}" style="width:50px;height:50px;object-fit:contain" class="custom-car-icon">`,
        className: '',
        iconSize: [50, 50],
        iconAnchor: [25, 25]
    });

    const pinIcon = L.divIcon({
        html: `<div style="font-size:30px;color:#3b82f6;"><i class="bi bi-geo-alt-fill"></i></div>`,
        className: '',
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });

    const flagIcon = L.divIcon({
        html: `<div style="font-size:30px;color:#10b981;"><i class="bi bi-flag-fill"></i></div>`,
        className: '',
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });

    let driverMarker = L.marker([<?php echo $lat; ?>, <?php echo $lng; ?>], { icon: carIcon }).addTo(map);
    let targetMarker = null;
    let routingControl = null;
    let pickupCoords = null;
    let dropoffCoords = null;
    let lastStatus = -1;

    /* ===========================
        LOGICA DE AVALIAÇÃO
    ============================ */
    const stars = document.querySelectorAll('#starContainer i');
    const btnSubmit = document.getElementById('btnSubmitRating');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = this.getAttribute('data-v');
            stars.forEach(s => s.classList.remove('active'));
            for(let i=0; i<selectedRating; i++) stars[i].classList.add('active');
            
            if (btnSubmit) btnSubmit.style.display = 'inline-block';
        });
    });

    if (btnSubmit) {
        btnSubmit.addEventListener('click', function() {
            if (selectedRating === 0) return;
            
            this.disabled = true;
            this.innerText = "Saving...";

            let formData = new FormData();
            formData.append('rating', selectedRating);
            
            fetch(`track.php?rate_driver=1&id=${RIDE_ID}`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('ratingStatusText').innerText = "Thank you for your rating!";
                    document.getElementById('starContainer').style.pointerEvents = 'none';
                    btnSubmit.style.display = 'none';
                    document.getElementById('btnCloseTY').style.background = 'var(--primary-gradient)';
                }
            });
        });
    }

    /* ===========================
        CONTROLO DE INTERAÇÃO USER
    ============================ */
    let userInteracting = false;
    let interactionTimeout = null;

    map.on('movestart zoomstart touchstart', () => {
        userInteracting = true;
        if (interactionTimeout) clearTimeout(interactionTimeout);
    });

    map.on('moveend zoomend touchend', () => {
        interactionTimeout = setTimeout(() => {
            userInteracting = false;
        }, 8000);
    });

    /* ===========================
        NORMALIZAÇÃO DE STATUS
    ============================ */
    function normalizeStatus(rawStatus) {
        switch (rawStatus) {
            case 0:
            case 1:
                return 0; // A caminho da recolha
            case 2:
                return 1; // Chegou / à espera
            case 3:
                return 2; // Em viagem
            default:
                return rawStatus; // >=4 concluído
        }
    }

    /* ===========================
        GEOCODING
    ============================ */
    async function getCoords(address) {
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&countrycodes=pt&q=${encodeURIComponent(address + ', Portugal')}`
            );
            const data = await res.json();
            return data.length ? L.latLng(data[0].lat, data[0].lon) : null;
        } catch {
            return null;
        }
    }

    async function init() {
        pickupCoords = await getCoords(PICKUP_TXT);
        dropoffCoords = await getCoords(DROPOFF_TXT);

        if (pickupCoords) {
            targetMarker = L.marker(pickupCoords, { icon: pinIcon }).addTo(map);
            startRouting(pickupCoords);
        }
    }
    init();

    /* ===========================
        ROUTING
    ============================ */
    function startRouting(dest) {
        if (!dest) return;

        if (routingControl) map.removeControl(routingControl);

        routingControl = L.Routing.control({
            waypoints: [driverMarker.getLatLng(), dest],
            routeWhileDragging: false,
            addWaypoints: false,
            show: false,
            lineOptions: {
                styles: [{ color: '#3b82f6', opacity: 0.9, weight: 6 }]
            },
            createMarker: () => null,
            fitSelectedRoutes: !userInteracting
        }).addTo(map);

        routingControl.on('routesfound', e => {
            const mins = Math.round(e.routes[0].summary.totalTime / 60);
            document.getElementById('etaValue').innerText = mins;

            if ([0, 2].includes(lastStatus)) {
                document.getElementById('etaContainer').style.display = 'flex';
            } else {
                document.getElementById('etaContainer').style.display = 'none';
            }
        });
    }

    /* ===========================
        UI STATUS
    ============================ */
    function updateUI(status) {
        if (status === lastStatus) return;

        const title = document.getElementById('statusTitle');
        const addr = document.getElementById('statusAddress');
        const icon = document.getElementById('statusIcon');
        const bg = document.getElementById('statusIconBg');
        const eta = document.getElementById('etaContainer');

        if (status === 0) {
            title.innerText = "Driver is on the way";
            title.style.color = "#f8fafc";
            addr.innerText = PICKUP_TXT;
            eta.style.display = 'flex';
            icon.className = "bi bi-car-front-fill";
            bg.style.background = "rgba(59,130,246,0.15)";
            bg.style.color = "#3b82f6";
            if (pickupCoords) startRouting(pickupCoords);
        }

        else if (status === 1) {
            title.innerText = "Driver has arrived!";
            title.style.color = "#10b981";
            addr.innerText = "Waiting at pickup point";
            eta.style.display = 'none';
            icon.className = "bi bi-geo-alt-fill";
            bg.style.background = "rgba(16,185,129,0.15)";
            bg.style.color = "#10b981";
            if (!userInteracting) map.setView(driverMarker.getLatLng(), 18);
        }

        else if (status === 2) {
            title.innerText = "Heading to destination";
            title.style.color = "#f8fafc";
            addr.innerText = DROPOFF_TXT;
            eta.style.display = 'flex';
            icon.className = "bi bi-cursor-fill";
            bg.style.background = "rgba(245,158,11,0.15)";
            bg.style.color = "#f59e0b";
            if (dropoffCoords) {
                if (targetMarker) targetMarker.setLatLng(dropoffCoords).setIcon(flagIcon);
                startRouting(dropoffCoords);
            }
        }

        else if (status >= 4) {
            const ty = document.getElementById('thankYouScreen');
            ty.style.display = 'flex';
            setTimeout(() => {
                ty.style.opacity = '1';
            }, 100);
        }

        lastStatus = status;
    }

    /* ===========================
        POLLING
    ============================ */
    setInterval(() => {
        fetch(`track.php?check_status=1&id=${RIDE_ID}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.data) return;

                const rawStatus = parseInt(res.data.status_id);
                const status = normalizeStatus(rawStatus);

                updateUI(status);

                if (res.data.latitude && res.data.longitude) {
                    const newPos = L.latLng(res.data.latitude, res.data.longitude);
                    driverMarker.setLatLng(newPos);

                    if (routingControl && [0, 2].includes(status)) {
                        const dest = status === 2 ? dropoffCoords : pickupCoords;
                        if (dest) {
                            routingControl.setWaypoints([newPos, dest]);
                            routingControl.options.fitSelectedRoutes = !userInteracting;
                        }
                    }
                }
            });
    }, 4000);
</script>

</body>
</html>