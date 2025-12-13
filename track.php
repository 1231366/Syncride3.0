<?php
// track.php (Client Tracking Page)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require 'auth/dbconfig.php'; 

$rideId = $_GET['id'] ?? null;
if (!$rideId) die("Error: Ride not specified.");

// 1. BUSCAR INFORMAÇÕES DA VIAGEM, CONDUTOR E VEÍCULO
$stmt = $pdo->prepare("
    SELECT 
        s.serviceStartPoint, 
        s.serviceTargetPoint, 
        s.NomeCliente, 
        s.ClientNumber, 
        u.name AS driver_name, 
        u.phone AS driver_phone, 
        u.profile_photo_path, 
        v.brand, 
        v.model, 
        v.license_plate, 
        v.photo_path
    FROM Services s
    
    LEFT JOIN Services_Rides sr ON s.ID = sr.RideID
    LEFT JOIN Users u ON sr.UserID = u.id
    
    LEFT JOIN Vehicles v ON u.assigned_vehicle_id = v.id
    
    WHERE s.ID = ?
");
$stmt->execute([$rideId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) die("Error: Trip not found.");

// 2. FETCH LIVE LOCATION
$stmt = $pdo->prepare("
    SELECT latitude, longitude, speed, heading 
    FROM RideTracking 
    WHERE ride_id = ? 
    ORDER BY last_update DESC LIMIT 1
");
$stmt->execute([$rideId]);
$liveLocation = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback coordinates
$lat = $liveLocation['latitude'] ?? 41.15; 
$lng = $liveLocation['longitude'] ?? -8.62;

$pickUpPoint = $trip['serviceStartPoint'] ?? 'Pick-up Point';
$driverName = $trip['driver_name'] ?? 'SyncRide Driver';

$driverPhone       = $trip['driver_phone'] ?? '';
$driverPhoneFormatted = preg_replace('/[^0-9]/', '', $driverPhone);
$whatsappLink = "https://wa.me/{$driverPhoneFormatted}?text=Hello%2C%20I%20am%20the%20customer%20for%20ride%20#{$rideId}.";

// IMAGENS
$defaultDriverPhoto = '/Includes/dist/assets/img/user2-160x160.jpg';
$driverPhotoPath = $trip['profile_photo_path'] ?? $defaultDriverPhoto;
$driverPhotoURL = (empty($driverPhotoPath) || is_null($driverPhotoPath)) ? $defaultDriverPhoto : htmlspecialchars($driverPhotoPath);

$vehicleName = trim(($trip['brand'] ?? '') . ' ' . ($trip['model'] ?? ''));
$vehiclePlate = $trip['license_plate'] ?? 'N/A';

$defaultVehiclePhoto = '/Includes/dist/pages/uploads/vehicles/default_car.png'; 
$vehiclePhotoPath = $trip['photo_path'] ?? $defaultVehiclePhoto;
$vehiclePhotoURL = (empty($vehiclePhotoPath) || is_null($vehiclePhotoPath)) ? $defaultVehiclePhoto : htmlspecialchars($vehiclePhotoPath);

$useVehiclePhotoOnMap = !empty($trip['photo_path']) && !is_null($trip['photo_path']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Ride | SyncRide</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

<style>
html, body { margin:0; padding:0; height:100%; font-family: 'system-ui', sans-serif; }
#map { height:100%; width:100%; }

/* ETA CARD */
.eta-card {
    position:absolute; top:20px; right:15px;
    background:#000; color:#fff;
    padding:10px 15px; border-radius:12px;
    font-size:20px; font-weight:700; z-index:9999;
    box-shadow:0 4px 12px rgba(0,0,0,0.2);
    display:flex; flex-direction:column; align-items:center;
}
.eta-value { font-size:32px; font-weight:900; }
.eta-label { font-size:12px; opacity:0.8; }

/* DRIVER SHEET */
.driver-sheet {
    position:fixed; bottom:0; left:0; width:100%;
    background:#fff;
    border-radius:20px 20px 0 0;
    padding:20px 18px 30px;
    box-shadow:0 -4px 20px rgba(0,0,0,0.15);
    z-index:9999;
}

.pickup-info { 
    margin-bottom:18px; padding-bottom:14px;
    border-bottom:1px solid #eee;
}
.pickup-info h3 { margin:0; font-size:1.3rem; font-weight:700; }
.pickup-info p { margin:4px 0 0; font-size:0.9rem; color:#666; display:flex; align-items:center;}
.dot { width:8px; height:8px; border-radius:50%; background:#28a745; margin-right:8px; }

/* DRIVER + CAR */
.details-section {
    display:flex; align-items:center; gap:14px;
}

.driver-photo {
    width:60px; height:60px;
    border-radius:50%; object-fit:cover;
    border:2px solid #ddd;
}

.vehicle-rating { flex:1; }
.vehicle-rating h4 {
    margin:0; font-size:1.15rem; font-weight:700; display:flex; align-items:center; gap:5px;
}
.rating-box { display:flex; align-items:center; gap:4px; color:#f90; }
.rating-box span { color:#111; font-weight:600; }

.vehicle-rating p {
    margin:3px 0 0;
    font-size:0.85rem; color:#666;
    display:flex; align-items:center; gap:6px;
}

.car-avatar-in-details {
    width:68px; height:45px; object-fit:cover;
    border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);
}

/* CONTACT LABEL */
.contact-label {
    font-size:14px;
    font-weight:600;
    color:#555;
    margin-top:18px;
    margin-bottom:8px;
    padding-left:4px;
}

/* ACTION BUTTONS (PADRÃO MELHORADO) */
.action-section {
    display:flex;
    justify-content:flex-start;
    gap:14px;
    padding-right:env(safe-area-inset-right); /* evita cortar no iPhone */
}

/* BUTTONS */
.action-btn {
    width:52px; height:52px;
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:22px;
    color:white;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
}
.call-btn { background:#0d6efd; }
.whatsapp-btn { background:#25D366; }

/* CAR MARKER */
.car-photo {
    width:45px; height:45px;
    border-radius:50%;
    border:3px solid #0d6efd;
    object-fit:cover;
    box-shadow:0 3px 6px rgba(0,0,0,0.4);
}
</style>
</head>

<body>

<div id="map"></div>

<div class="eta-card">
    <div id="eta" class="eta-value">--</div>
    <div class="eta-label">min</div>
</div>

<div class="driver-sheet">

    <div class="pickup-info">
        <h3>Meet at the pickup point.</h3>
        <p><span class="dot"></span><?php echo htmlspecialchars($pickUpPoint); ?></p>
    </div>

    <div class="details-section">
        <img src="<?php echo $driverPhotoURL; ?>" class="driver-photo">
        <div class="vehicle-rating">
            <h4>
                <?php echo htmlspecialchars($driverName); ?>
                <span class="rating-box">
                    <span>4.89</span>
                    <i class="bi bi-star-fill" style="color:#FFC107;"></i>
                </span>
            </h4>
            <p>
                <img src="<?php echo $vehiclePhotoURL; ?>" class="car-avatar-in-details">
                <?php echo htmlspecialchars($vehicleName); ?> (<?php echo htmlspecialchars($vehiclePlate); ?>)
            </p>
        </div>
    </div>

    <!-- NEW LABEL -->
    <div class="contact-label">Contact your driver</div>

    <!-- FIXED, ALIGNED BUTTONS -->
    <div class="action-section">
        <a href="tel:<?php echo $driverPhoneFormatted; ?>" class="action-btn call-btn">
            <i class="bi bi-telephone-fill"></i>
        </a>
        <a href="<?php echo $whatsappLink; ?>" target="_blank" class="action-btn whatsapp-btn">
            <i class="bi bi-whatsapp"></i>
        </a>
    </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
/* JS ORIGINAL — sem alterações */
const RIDE_ID = <?php echo $rideId; ?>;
const INITIAL_LAT = <?php echo $lat; ?>;
const INITIAL_LNG = <?php echo $lng; ?>;
const USE_PHOTO = <?php echo $useVehiclePhotoOnMap ? 'true' : 'false'; ?>;
const VEHICLE_PHOTO_URL = '<?php echo $vehiclePhotoURL; ?>';

const carIconHtml = USE_PHOTO ?
    `<img src="${VEHICLE_PHOTO_URL}" class="car-photo">` :
    `<i class="bi bi-car-front-fill" style="font-size:32px;color:#0d6efd;"></i>`;

const iconSize = USE_PHOTO ? [45,45] : [32,32];
const iconAnchor = USE_PHOTO ? [22,45] : [16,32];

var map = L.map('map', { zoomControl:false, attributionControl:false })
.setView([INITIAL_LAT, INITIAL_LNG], 15);

L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', 
    { maxZoom:20 }
).addTo(map);

var carMarker = L.marker([INITIAL_LAT, INITIAL_LNG], {
    icon: L.divIcon({ html:carIconHtml, className:'', iconSize, iconAnchor })
}).addTo(map);

var routingControl = null;
var clientLat = undefined;
var clientLng = undefined;

function updateCarLocation(lat, lng, speed, heading) {
    if (!isNaN(lat) && !isNaN(lng)) {
        carMarker.setLatLng([lat,lng]);
    }
}

if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {

        clientLat = pos.coords.latitude;
        clientLng = pos.coords.longitude;

        L.marker([clientLat, clientLng], {
            icon: L.divIcon({
                html:'<i class="bi bi-geo-alt-fill" style="font-size:30px;color:#0d6efd;"></i>',
                iconSize:[30,30], iconAnchor:[15,30]
            })
        }).addTo(map);

        routingControl = L.Routing.control({
            waypoints:[
                L.latLng(INITIAL_LAT, INITIAL_LNG),
                L.latLng(clientLat, clientLng)
            ],
            routeWhileDragging:false,
            show:false,
            addWaypoints:false,
            lineOptions:{ styles:[{color:'#0d6efd', opacity:0.8, weight:6}] },
            createMarker: i => i===0 ? carMarker : L.marker([clientLat,clientLng], {
                icon: L.divIcon({
                    html:'<i class="bi bi-geo-alt-fill" style="font-size:30px;color:#0d6efd;"></i>',
                    iconSize:[30,30], iconAnchor:[15,30]
                })
            })
        }).addTo(map);

        routingControl.on('routesfound', e=>{
            var summary = e.routes[0].summary;
            document.getElementById('eta').innerText = Math.round(summary.totalTime/60);
        });

        setInterval(()=>{
            fetch(`Includes/dist/pages/api_get_tracking.php?ride_id=${RIDE_ID}`)
            .then(r=>r.json())
            .then(res=>{
                if(res.success && res.data){
                    let d = res.data;
                    updateCarLocation(d.latitude, d.longitude, d.speed, d.heading);
                }
            });
        }, 5000);

    });
}
</script>

</body>
</html>
