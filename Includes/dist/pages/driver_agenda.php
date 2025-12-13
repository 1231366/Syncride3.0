<?php
session_start(); 
require __DIR__ . '/../../../auth/dbconfig.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 2) { header("Location: ../../../index.php"); exit(); }

$userId = $_SESSION['user_id']; 
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Buscar viagens da data selecionada
try {
    $stmt = $pdo->prepare("
        SELECT s.* FROM Services_Rides sr
        INNER JOIN Services s ON sr.RideID = s.ID
        WHERE sr.UserID = ? AND s.serviceDate = ?
        ORDER BY s.serviceStartTime ASC
    ");
    $stmt->execute([$userId, $selectedDate]);
    $viagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $viagens = []; }
?>
<!doctype html>
<html lang="pt">
  <head>
    <meta charset="utf-8" />
    <title>Minha Agenda | SyncRide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../dist/css/adminlte.css" />
    
    <style>
        /* Estilo Igual ao driver.php */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 65px; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-around; align-items: center; z-index: 1000; border-top: 1px solid #eee; }
        .nav-item-mobile { display: flex; flex-direction: column; align-items: center; justify-content: center; color: #adb5bd; text-decoration: none; font-size: 0.75rem; width: 100%; height: 100%; }
        .nav-item-mobile i { font-size: 1.4rem; margin-bottom: 2px; }
        .nav-item-mobile.active { color: #00b0ff; font-weight: 600; }
        .app-content { padding-bottom: 80px; }
        
        /* Estilo Específico da Agenda */
        .date-selector { background: #fff; padding: 15px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; margin-bottom: 20px; }
        .date-input { border: 2px solid #e9ecef; border-radius: 8px; padding: 8px 15px; font-size: 1.1rem; font-weight: bold; color: #495057; outline: none; width: 100%; text-align: center; }
        .date-input:focus { border-color: #00b0ff; }
    </style>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
      
      <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <span class="navbar-brand fw-bold">Minha Agenda</span>
        </div>
      </nav>

      <main class="app-main">
        <div class="app-content pt-3">
          <div class="container-fluid">
            
            <!-- SELECIONADOR DE DATA -->
            <div class="date-selector">
                <label class="d-block text-muted small mb-2 fw-bold text-uppercase">Selecione o Dia</label>
                <form method="GET">
                    <input type="date" name="date" class="date-input" value="<?php echo $selectedDate; ?>" onchange="this.form.submit()">
                </form>
            </div>

            <!-- LISTA (Reaproveitando o design do driver.php) -->
            <?php if(count($viagens) > 0): ?>
                <?php foreach($viagens as $v): 
                    $isPriv = ($v['serviceType'] == 1);
                    $color = $isPriv ? "#0d6efd" : "#ffc107";
                    $badge = $isPriv ? "Privado" : "Partilhado";
                ?>
                <div class="card mb-3 border-0 shadow-sm" style="border-left: 5px solid <?php echo $color; ?>; border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="fw-bold m-0 text-dark"><?php echo substr($v['serviceStartTime'], 0, 5); ?></h4>
                            <span class="badge bg-light text-dark border"><?php echo $badge; ?></span>
                        </div>
                        <div class="text-truncate mb-1 text-secondary"><i class="bi bi-geo-alt-fill text-success me-2"></i> <?php echo htmlspecialchars($v['serviceStartPoint']); ?></div>
                        <div class="text-truncate text-dark fw-medium"><i class="bi bi-flag-fill text-danger me-2"></i> <?php echo htmlspecialchars($v['serviceTargetPoint']); ?></div>
                        <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                            <small class="text-muted"><?php echo $v['paxADT']; ?> Adultos, <?php echo $v['paxCHD']; ?> Crianças</small>
                            <small class="fw-bold text-primary"><?php echo $v['NomeCliente']; ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 text-muted opacity-50">
                    <i class="bi bi-calendar-x fs-1"></i>
                    <p class="mt-3">Livre! Sem serviço para este dia.</p>
                </div>
            <?php endif; ?>

          </div>
        </div>
      </main>

      <!-- MENU INFERIOR -->
      <nav class="bottom-nav d-flex d-md-none">
          <a href="driver.php" class="nav-item-mobile">
              <i class="bi bi-car-front-fill"></i>
              <span>Viagens</span>
          </a>
          <a href="driver_agenda.php" class="nav-item-mobile active">
              <i class="bi bi-calendar3"></i>
              <span>Agenda</span>
          </a>
          <a href="driverstats.php" class="nav-item-mobile">
              <i class="bi bi-bar-chart-fill"></i>
              <span>Stats</span>
          </a>
          <a href="logout.php" class="nav-item-mobile">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sair</span>
          </a>
      </nav>

    </div>
    <script src="../../dist/js/adminlte.js"></script>
  </body>
</html>