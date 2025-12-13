<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php'; 

// Verifica se o Admin está logado
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 1) {
    header("Location: ../../../index.php");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>SyncRide - Rastreio de Condutores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="../../dist/css/adminlte.min.css" />

    <!-- Leaflet CSS (Biblioteca de Mapas) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
        xintegrity="sha512-dxS4xOQo44X2O8w5vXj1R4/p3q8kG4j5k6f8p4z4A6c/c5Y4+J4z4w3/g3Q2w5J0g3" crossorigin="" />
    
    <style>
        #map { 
            height: 80vh; 
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <!-- HEADER (Copiar do admin.php) -->
        <!-- SIDEBAR (Copiar do admin.php) -->
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <h3 class="mb-0">Rastreio em Tempo Real</h3>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="card p-3">
                        <div id="map"></div>
                        <div id="info" class="mt-3 p-3 bg-light rounded">
                            <p class="mb-0">Clique num condutor para ver os detalhes da viagem ativa.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
        xintegrity="sha512-Lz5Z6/7K1g9oG4z8L1B0y8Jv8F1/k4lG+8p4v4P4x4/q2J3j4w4x4/g3Q2w5J0g3" crossorigin=""></script>
    
    <!-- jQuery, Bootstrap, AdminLTE JS (Copiar do admin.php) -->

    <script>
        // Mapeia o estado da BD para cor do ícone
        const iconColors = {
            'driving': 'blue',
            'idle': 'gray',
            'offline': 'red'
        };

        // Função para criar o ícone (pode ser substituído por um SVG de carro mais tarde)
        function createIcon(heading, status) {
            const color = iconColors[status] || 'gray';
            return L.divIcon({
                className: 'custom-div-icon',
                html: `<div style="transform: rotate(${heading}deg); color: ${color}; font-size: 24px;">&#x1F697;</div>`, // Ícone de carro Unicode
                iconSize: [30, 42],
                iconAnchor: [15, 42]
            });
        }
        
        // 1. Inicializar o Mapa
        const map = L.map('map').setView([40.6397, -8.6475], 8); // Coordenadas de Aveiro, zoom 8

        // Adiciona Google Maps como base
        L.tileLayer('http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',{
            maxZoom: 20,
            subdomains:['mt0','mt1','mt2','mt3']
        }).addTo(map);

        const markers = {}; // Objeto para armazenar os marcadores ativos

        // 2. Função para Buscar Dados do Servidor
        async function fetchDriverLocations() {
            try {
                // Nova API para Admin: Devolve todos os condutores com a sua última localização
                const response = await fetch('api_get_live_locations.php'); 
                const data = await response.json();

                if (data.success) {
                    const activeDrivers = data.drivers;
                    const now = Date.now();
                    
                    activeDrivers.forEach(driver => {
                        const lat = parseFloat(driver.latitude);
                        const lng = parseFloat(driver.longitude);
                        const heading = driver.heading || 0;
                        const tripId = driver.trip_id;
                        
                        // Estado do condutor
                        let status = 'idle';
                        if (tripId) {
                            status = 'driving';
                        }
                        
                        // Se não atualizar há mais de 2 minutos
                        const lastUpdate = new Date(driver.last_update).getTime();
                        if (now - lastUpdate > 120000) { 
                            status = 'offline'; 
                        }

                        // Conteúdo do Pop-up (Detalhes da Viagem)
                        let popupContent = `
                            <strong>Condutor:</strong> ${driver.name} (${driver.driver_id})<br>
                            <strong>Status:</strong> ${status.toUpperCase()}<br>
                            ${tripId ? `<strong>Viagem ID:</strong> <a href='updateRide.php?id=${tripId}'>${tripId}</a><br>` : 'Livre / Offline'}
                            <strong>Última Posição:</strong> ${driver.last_update}<br>
                            <strong>Velocidade:</strong> ${driver.speed} km/h
                        `;
                        
                        if (markers[driver.driver_id]) {
                            // Atualiza marcador existente
                            markers[driver.driver_id].setLatLng([lat, lng]);
                            markers[driver.driver_id].setIcon(createIcon(heading, status));
                            markers[driver.driver_id].getPopup().setContent(popupContent);
                        } else {
                            // Cria novo marcador
                            const marker = L.marker([lat, lng], {
                                icon: createIcon(heading, status)
                            }).addTo(map)
                            .bindPopup(popupContent);
                            
                            markers[driver.driver_id] = marker;
                        }
                    });
                }
            } catch (error) {
                console.error("Erro ao buscar localizações:", error);
                document.getElementById('info').innerHTML = `<p class="text-danger">ERRO: Falha ao comunicar com a API de rastreio.</p>`;
            }
        }

        // 3. Inicia o Polling (Atualiza a cada 5 segundos)
        fetchDriverLocations();
        setInterval(fetchDriverLocations, 5000); 

    </script>
</body>
</html>
```

### 5. Criar API de Consulta para o Admin (`api_get_live_locations.php`)

Este ficheiro é a API que a página do Admin vai chamar a cada 5 segundos.

Cria um ficheiro novo em `Includes/dist/pages/api_get_live_locations.php`:

```php
<?php
// api_get_live_locations.php
// Devolve a localização de TODOS os condutores para o mapa do Admin

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

require __DIR__ . '/../../../auth/dbconfig.php';

// Verifica se o Admin está logado (opcional, mas recomendado)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 1) {
    // Apenas Admin pode ver
    // echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    // exit();
}

try {
    // Junta a tabela de localização com a tabela de utilizadores para obter o nome
    $sql = "SELECT 
                l.driver_id, 
                l.trip_id, 
                l.latitude, 
                l.longitude, 
                l.speed, 
                l.heading, 
                l.last_update,
                u.name
            FROM DriverLiveLocation l
            JOIN Users u ON l.driver_id = u.id
            WHERE u.role = 2"; // Filtra apenas condutores (role=2)

    $stmt = $pdo->query($sql);
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'drivers' => $drivers]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar dados da BD: ' . $e->getMessage()]);
}
?>