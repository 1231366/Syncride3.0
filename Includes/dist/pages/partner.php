<?php
session_start();
require __DIR__ . '/../../../auth/dbconfig.php';

// Verificação de Segurança
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 3) {
    header("Location: ../../../index.php");
    exit();
}

$partner_id = $_SESSION['user_id'];

// Lógica da Foto
$defaultPhoto = "../assets/img/user2-160x160.jpg"; 
$userPhoto = $defaultPhoto;
if (isset($_SESSION['profile_photo_path']) && !empty($_SESSION['profile_photo_path'])) {
    $userPhoto = "../../../" . $_SESSION['profile_photo_path'];
}

// Estatísticas
$stats = ['total' => 0, 'pendentes' => 0, 'confirmadas' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_pedido = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status_pedido = 'aprovado' THEN 1 ELSE 0 END) as confirmadas
        FROM Services WHERE partner_id = ?
    ");
    $stmt->execute([$partner_id]);
    $res = $stmt->fetch();
    if($res) $stats = $res;
} catch (PDOException $e) { }
?>

<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>Portal Parceiro | SyncRide</title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta1/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

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
            --radius-md: 16px;
            --radius-sm: 10px;
            --card-shadow: 0 2px 12px rgba(0,0,0,0.04);
            
            /* Safe Areas */
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
            --card-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        body {
            font-family: var(--font-primary);
            background-color: var(--bg-body);
            color: var(--text-main);
            /* Padding extra em baixo para o botão saliente */
            padding-bottom: calc(90px + var(--safe-bottom)); 
            padding-top: 0;
            margin: 0;
            min-height: 100vh;
        }
        
        @media (min-width: 992px) { body { padding-bottom: 0; } }

        /* --- HEADER --- */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding-top: var(--safe-top); 
            height: calc(70px + var(--safe-top));
            display: flex; align-items: center;
            position: fixed; top: 0; width: 100%; z-index: 1040;
        }
        [data-bs-theme="dark"] .app-header { background: rgba(30, 41, 59, 0.95); }
        .navbar-brand img { height: 35px; margin-right: 10px; }
        .navbar-brand span { font-family: var(--font-display); font-weight: 700; }
        
        /* MAIN CONTENT */
        .app-main { padding-top: calc(80px + var(--safe-top)) !important; }

        /* --- STATS --- */
        .stat-card {
            background: var(--bg-card); border: none; border-radius: var(--radius-md); padding: 1rem;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; box-shadow: var(--card-shadow);
        }
        .stat-value { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; margin-bottom: 0; line-height: 1.2; }
        .stat-label { font-size: 0.75rem; opacity: 0.7; font-weight: 500; }
        .stat-blue { color: #3b82f6; } .stat-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; margin-bottom: 8px; font-size: 1rem; }
        .stat-blue .stat-icon { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .stat-orange { color: #f97316; } .stat-orange .stat-icon { color: #f97316; background: rgba(249, 115, 22, 0.1); }
        .stat-green { color: #10b981; } .stat-green .stat-icon { color: #10b981; background: rgba(16, 185, 129, 0.1); }

        /* --- SEARCH & TABS --- */
        .modern-search { position: relative; width: 100%; max-width: 400px; }
        .modern-search input {
            width: 100%; padding: 10px 15px 10px 45px; border-radius: 50px;
            border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main);
            box-shadow: var(--card-shadow);
        }
        .modern-search i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

        .nav-pills { gap: 8px; flex-wrap: nowrap; padding: 0; margin: 0; border: none; }
        .nav-pills .nav-link {
            color: var(--text-muted); background: var(--bg-card); border-radius: 50px;
            padding: 8px 20px; font-size: 0.85rem; font-weight: 600; white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: all 0.2s;
        }
        .nav-pills .nav-link.active { background-color: var(--primary-accent); color: #fff; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }

        @media (max-width: 767.98px) {
            .nav-pills { display: flex; width: 100%; justify-content: space-between; gap: 5px; }
            .nav-pills .nav-item { flex: 1; min-width: 0; }
            .nav-pills .nav-link { width: 100%; text-align: center; padding: 10px 0; font-size: 0.75rem; border-radius: 12px; text-overflow: ellipsis; overflow: hidden; }
        }

        /* --- MOBILE CARDS --- */
        .card-table { background: transparent; border-radius: var(--radius-md); overflow: hidden; }
        
        @media (min-width: 768px) {
            .card-table { background: var(--bg-card); border: 1px solid var(--border-color); }
            .table td { padding: 1rem; vertical-align: middle; }
        }

        @media (max-width: 767.98px) {
            #tabelaPartner thead { display: none; }
            #tabelaPartner tbody tr {
                display: flex; flex-direction: column; background-color: var(--bg-card);
                border: none; border-radius: 14px; margin-bottom: 12px; padding: 12px; 
                position: relative; box-shadow: var(--card-shadow);
            }
            #tabelaPartner tbody td { 
                display: block; width: 100%; padding: 0 !important; margin-bottom: 3px;
                background: transparent !important; border: none !important; box-shadow: none !important;
            }
            #tabelaPartner tbody td:nth-child(1) { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
            #tabelaPartner tbody td:nth-child(2) { font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px; line-height: 1.2; }
            #tabelaPartner tbody td:nth-child(3), #tabelaPartner tbody td:nth-child(4), #tabelaPartner tbody td:nth-child(5) {
                font-size: 0.85rem; color: var(--text-main); display: flex; align-items: center; min-height: 22px;
            }
            #tabelaPartner tbody td:nth-child(6) { position: absolute; top: 12px; right: 12px; width: auto !important; margin: 0; }
            .dataTables_empty { text-align: center; padding: 20px !important; color: var(--text-muted); }
        }

        /* --- MENU INFERIOR COOL (O Teu Design) --- */
        .bottom-navbar {
            background-color: var(--bg-card); 
            border-top: 1px solid var(--border-color);
            z-index: 1040; 
            
            position: fixed; bottom: 0; left: 0; width: 100%;
            /* Altura base + safe area */
            height: calc(70px + var(--safe-bottom)); 
            padding-bottom: var(--safe-bottom);
            
            display: flex; justify-content: space-around; align-items: center;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
        }
        
        .nav-item-bottom { 
            color: var(--text-muted); flex: 1; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-decoration: none; border: none; background: transparent; font-size: 0.75rem; 
            height: 100%; transition: color 0.2s; cursor: pointer;
        }
        .nav-item-bottom i { font-size: 1.5rem; margin-bottom: 2px; } 
        .nav-item-bottom.active { color: var(--primary-accent); }

        /* O Botão "+" Central Saliente */
        .btn-center-add {
            position: absolute;
            top: -25px; /* Sobe para fora da barra */
            left: 50%;
            transform: translateX(-50%);
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--primary-accent), var(--primary-hover));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 2rem;
            border: 5px solid var(--bg-body); /* Cria o efeito de "recorte" visual */
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.4);
            cursor: pointer;
            z-index: 1050;
            transition: transform 0.2s;
        }
        .btn-center-add:active { transform: translateX(-50%) scale(0.95); }
        
    </style>
</head>

<body class="layout-top-nav">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand fixed-top">
            <div class="container-fluid">
                <a href="#" class="navbar-brand d-flex align-items-center">
                    <img id="app-logo" src="../../../assets/images/icons/SyncRide.png" alt="Logo">
                </a>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-2">
                        <button class="btn btn-link text-muted p-0 border-0" id="theme-toggle"><i class="bi bi-moon-stars-fill fs-5" id="theme-icon"></i></button>
                    </li>
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img src="<?php echo $userPhoto; ?>" class="rounded-circle shadow-sm border" style="width: 36px; height: 36px; object-fit: cover;">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 overflow-hidden mt-2">
                            <li class="user-header bg-primary text-white p-4 text-center">
                                <p class="mb-0 fw-bold"><?php echo $_SESSION['name']; ?></p>
                                <a href="logout.php" class="btn btn-danger btn-sm rounded-pill mt-3 fw-bold">Sair</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="app-main"> 
            <div class="app-content">
                <div class="container-xl">
                    <div class="d-flex justify-content-between align-items-end mb-4 px-1">
                        <div>
                            <h3 class="fw-bold mb-0 text-main">Olá, <?php echo explode(' ', $_SESSION['name'])[0]; ?></h3>
                            <p class="text-muted mb-0 small">Bem-vindo ao portal.</p>
                        </div>
                        <button class="btn btn-primary rounded-pill d-none d-md-block shadow-sm px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoPedido">
                            <i class="bi bi-plus-lg me-1"></i> Nova Reserva
                        </button>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-4"><div class="stat-card stat-blue"><div class="stat-icon"><i class="bi bi-airplane-fill"></i></div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div></div>
                        <div class="col-4"><div class="stat-card stat-orange"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="stat-value"><?= $stats['pendentes'] ?></div><div class="stat-label">Pendentes</div></div></div>
                        <div class="col-4"><div class="stat-card stat-green"><div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div><div class="stat-value"><?= $stats['confirmadas'] ?></div><div class="stat-label">Aceites</div></div></div>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
                        <div class="modern-search order-2 order-md-1">
                            <i class="bi bi-search"></i>
                            <input type="text" id="customSearch" placeholder="Pesquisar...">
                        </div>
                        <ul class="nav nav-pills order-1 order-md-2" id="partner-tabs">
                            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pendente" onclick="reloadTable('pendente')">Pendentes</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#aprovado" onclick="reloadTable('aprovado')">Confirmadas</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#rejeitado" onclick="reloadTable('rejeitado')">Rejeitadas</a></li>
                        </ul>
                    </div>

                    <div class="card-table">
                        <table id="tabelaPartner" class="table mb-0 w-100">
                            <thead>
                                <tr>
                                    <th class="ps-4">Data</th>
                                    <th>Passageiro</th>
                                    <th>Voo</th>
                                    <th>Rota</th>
                                    <th>Pax</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="bottom-navbar d-md-none">
        <a href="#" class="nav-item-bottom active">
            <i class="bi bi-house-door-fill"></i>
            <span>Home</span>
        </a>
        
        <div style="width: 60px; height: 60px;"></div> 
        <div class="btn-center-add" data-bs-toggle="modal" data-bs-target="#modalNovoPedido">
            <i class="bi bi-plus-lg"></i>
        </div>

        <button onclick="confirmLogout()" class="nav-item-bottom text-danger">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sair</span>
        </button>
    </div>

    <div class="modal fade" id="modalConfirmLogout" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-body text-center p-4">
                    <h5 class="fw-bold mb-2">Sair da Conta?</h5>
                    <p class="text-muted small mb-4">Terá de fazer login novamente.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Não</button>
                        <a href="logout.php" class="btn btn-danger rounded-pill px-4 fw-bold">Sim</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalNovoPedido" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0"><h5 class="modal-title fw-bold">Nova Reserva</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form id="formNovoPedido">
                        <div class="row g-3">
                            <div class="col-6"><label class="small text-muted fw-bold">Data</label><input type="date" name="date" class="form-control" required></div>
                            <div class="col-6"><label class="small text-muted fw-bold">Hora</label><input type="time" name="time" class="form-control" required></div>
                            <div class="col-12"><label class="small text-muted fw-bold">Nome</label><input type="text" name="client_name" class="form-control" required></div>
                            <div class="col-6"><label class="small text-muted fw-bold">Telefone</label><input type="text" name="client_phone" class="form-control"></div>
                            <div class="col-6"><label class="small text-muted fw-bold">Preço</label><input type="number" step="0.01" name="price" class="form-control"></div>
                            <div class="col-6"><label class="small text-muted fw-bold">Adultos</label><input type="number" name="pax_adt" value="1" class="form-control" required></div>
                            <div class="col-6"><label class="small text-muted fw-bold">Crianças</label><input type="number" name="pax_chd" value="0" class="form-control"></div>
                            <div class="col-12"><label class="small text-muted fw-bold">Recolha</label><input type="text" name="pickup" class="form-control" required></div>
                            <div class="col-12"><label class="small text-muted fw-bold">Destino</label><input type="text" name="dropoff" class="form-control" required></div>
                            <div class="col-12"><label class="small text-muted fw-bold">Voo</label><input type="text" name="flight" class="form-control"></div>
                            <div class="col-12 mt-4"><button type="submit" class="btn btn-primary w-100 rounded-pill">Enviar</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Dark Mode e Logo (Mantido Igual) ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const appLogo = document.getElementById('app-logo');
        const htmlElement = document.documentElement;
        
        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeUI(savedTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = htmlElement.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeUI(newTheme);
        });

        function updateThemeUI(theme) {
            themeIcon.className = theme === 'light' ? 'bi bi-moon-stars-fill fs-5' : 'bi bi-sun-fill fs-5';
            appLogo.src = theme === 'dark' ? "../../../assets/images/icons/Syncridewhite.png" : "../../../assets/images/icons/Syncride.png";
        }

        // --- Logout Modal ---
        function confirmLogout() { new bootstrap.Modal(document.getElementById('modalConfirmLogout')).show(); }

        // --- DataTables Config ---
        let table;
        let currentStatus = 'pendente';

        $(document).ready(function() {
            table = $('#tabelaPartner').DataTable({
                "language": {
                    "emptyTable": "Sem reservas neste estado",
                    "info": "A mostrar _START_ a _END_ de _TOTAL_ registos",
                    "infoEmpty": "A mostrar 0 a 0 de 0 registos",
                    "infoFiltered": "(filtrado de _MAX_ registos)",
                    "lengthMenu": "Mostrar _MENU_ registos",
                    "loadingRecords": "A carregar...",
                    "processing": "A processar...",
                    "search": "Procurar:",
                    "zeroRecords": "Não foram encontrados resultados",
                    "paginate": {
                        "first": "Primeiro",
                        "last": "Último",
                        "next": "Seguinte",
                        "previous": "Anterior"
                    }
                },
                "ajax": {
                    "url": "api_fetch_partner_rides.php",
                    "data": function(d) { d.status = currentStatus; }
                },
                "columns": [
                    { data: 'data_hora', className: "ps-4" },
                    { data: 'cliente', className: "fw-bold" },
                    { 
                        data: 'voo',
                        render: function(data, type, row) {
                            if (window.innerWidth < 768) {
                                return '<i class="bi bi-airplane-fill text-primary me-2"></i> ' + (data ? data : '--');
                            }
                            return data;
                        }
                    },
                    { 
                        data: 'rota',
                        render: function(data, type, row) {
                            if (window.innerWidth < 768) {
                                return '<i class="bi bi-geo-alt-fill text-muted me-2"></i> ' + data;
                            }
                            return data;
                        }
                    },
                    { 
                        data: 'pax',
                        render: function(data, type, row) {
                            if(window.innerWidth < 768) return '<i class="bi bi-people-fill text-muted me-2"></i> ' + data;
                            return data;
                        }
                    },
                    { 
                        data: 'status', 
                        className: "text-center",
                        render: function(data, type, row) {
                            let badgeClass = 'bg-secondary text-secondary';
                            let statusLower = (data || '').toLowerCase();
                            let statusText = data;

                            if (statusLower === 'pendente' || statusLower === 'aguardando') {
                                badgeClass = 'bg-warning text-warning';
                                statusText = 'Pendente'; 
                            } else if (statusLower === 'aprovado') {
                                badgeClass = 'bg-success text-success';
                                statusText = 'Confirmada';
                            } else if (statusLower === 'rejeitado') {
                                badgeClass = 'bg-danger text-danger';
                                statusText = 'Rejeitada';
                            }
                            
                            return `<span class="badge rounded-pill ${badgeClass} bg-opacity-10 px-3 py-2 border border-opacity-10">${statusText}</span>`;
                        }
                    }
                ],
                "order": [[ 0, "desc" ]],
                "dom": 'rt<"d-flex justify-content-center mt-3"p>',
                "pageLength": 10,
                "autoWidth": false
            });

            $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });

            $('#formNovoPedido').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const originalText = btn.html();
                btn.prop('disabled', true).html('A enviar...');
                
                fetch('api_create_ride_partner.php', { method: 'POST', body: new FormData(this) })
                .then(r => r.json())
                .then(res => {
                    if(res.success) {
                        toastr.success('Sucesso!'); $('#modalNovoPedido').modal('hide'); $('#formNovoPedido')[0].reset(); table.ajax.reload();
                    } else toastr.error(res.message);
                })
                .catch(() => toastr.error('Erro.'))
                .finally(() => btn.prop('disabled', false).html(originalText));
            });
        });

        function reloadTable(status) {
            currentStatus = status;
            table.ajax.reload();
        }
    </script>
</body>
</html>