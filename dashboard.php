 <?php
require_once 'config/config.php';
require_once 'includes/auth.php';
$auth->requireLogin();

$user = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Estadísticas según el rol
$stats = [];

// Total de transacciones hoy
$stmt = $db->query("SELECT COUNT(*) as total FROM transacciones WHERE DATE(fecha_hora) = CURDATE()");
$stats['transacciones_hoy'] = $stmt->fetch()['total'];

// Alertas nuevas
$stmt = $db->query("SELECT COUNT(*) as total FROM alertas_anomalias WHERE estado_alerta = 'nueva'");
$stats['alertas_nuevas'] = $stmt->fetch()['total'];

// Total litros hoy
$stmt = $db->query("SELECT COALESCE(SUM(volumen_litros),0) as total FROM transacciones WHERE DATE(fecha_hora) = CURDATE()");
$stats['litros_hoy'] = $stmt->fetch()['total'];

// Monto total hoy
$stmt = $db->query("SELECT COALESCE(SUM(monto_total),0) as total FROM transacciones WHERE DATE(fecha_hora) = CURDATE()");
$stats['monto_hoy'] = $stmt->fetch()['total'];

// Solo para admins
if ($auth->isAdmin()) {
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
    $stats['usuarios_activos'] = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM surtidores WHERE activo = 1");
    $stats['surtidores_activos'] = $stmt->fetch()['total'];
}

// Últimas 5 transacciones
$stmt = $db->query("SELECT * FROM vista_transacciones_completas ORDER BY fecha_hora DESC LIMIT 5");
$ultimas_transacciones = $stmt->fetchAll();

// Últimas 5 alertas (solo admin+)
$ultimas_alertas = [];
if ($auth->isAdmin()) {
    $stmt = $db->query("
        SELECT a.*, t.volumen_litros, s.codigo_surtidor, r.nombre_regla 
        FROM alertas_anomalias a
        INNER JOIN transacciones t ON a.id_transaccion = t.id_transaccion
        INNER JOIN surtidores s ON t.id_surtidor = s.id_surtidor
        INNER JOIN reglas_anomalias r ON a.id_regla = r.id_regla
        ORDER BY a.fecha_alerta DESC LIMIT 5
    ");
    $ultimas_alertas = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="container-fluid dashboard-page">
    <section class="dashboard-hero mb-4">
        <div>
            <p class="eyebrow mb-2"><i class="bi bi-speedometer2"></i> Panel de control</p>
            <h1 class="mb-2">Buenos días, <?= htmlspecialchars($user['nombre_completo']) ?></h1>
            <p class="hero-subtitle mb-0">Resumen operativo de tu estación de combustible.</p>
        </div>
        <div class="hero-user">
            <span class="hero-user-icon"><i class="bi bi-person"></i></span>
            <div>
                <strong><?= htmlspecialchars($user['nombre_rol']) ?></strong>
                <small>Sesión activa</small>
            </div>
        </div>
    </section>

    <!-- Tarjetas de estadísticas -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card stat-card-blue">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="stat-label mb-2">Transacciones hoy</p>
                            <h2 class="mb-0"><?= number_format($stats['transacciones_hoy']) ?></h2>
                        </div>
                        <span class="stat-icon"><i class="bi bi-receipt"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card stat-card-coral">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="stat-label mb-2">Alertas nuevas</p>
                            <h2 class="mb-0"><?= number_format($stats['alertas_nuevas']) ?></h2>
                        </div>
                        <i class="bi bi-exclamation-triangle" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card stat-card-teal">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="stat-label mb-2">Litros hoy</p>
                            <h2 class="mb-0"><?= number_format($stats['litros_hoy'], 2) ?></h2>
                        </div>
                        <i class="bi bi-droplet" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card stat-card-green">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="stat-label mb-2">Monto hoy (Bs)</p>
                            <h2 class="mb-0"><?= number_format($stats['monto_hoy'], 2) ?></h2>
                        </div>
                        <i class="bi bi-cash-coin" style="font-size: 3rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Últimas transacciones -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Últimas Transacciones</h6>
                    <a href="<?= BASE_URL ?>modules/transacciones/listar.php" class="btn btn-sm btn-outline-primary">Ver todas <i class="bi bi-arrow-up-right"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Surtidor</th>
                                    <th>Producto</th>
                                    <th>Litros</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimas_transacciones)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">Sin transacciones</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ultimas_transacciones as $t): ?>
                                        <tr>
                                            <td><?= date('d/m H:i', strtotime($t['fecha_hora'])) ?></td>
                                            <td><?= htmlspecialchars($t['codigo_surtidor']) ?></td>
                                            <td><?= htmlspecialchars($t['nombre_producto']) ?></td>
                                            <td><?= number_format($t['volumen_litros'], 2) ?> L</td>
                                            <td>Bs <?= number_format($t['monto_total'], 2) ?></td>
                                            <td>
                                                <?php
                                                $badge = 'secondary';
                                                if ($t['estado'] === 'registrada') $badge = 'success';
                                                elseif ($t['estado'] === 'sospechosa') $badge = 'warning';
                                                elseif ($t['estado'] === 'anulada') $badge = 'danger';
                                                elseif ($t['estado'] === 'verificada') $badge = 'info';
                                                ?>
                                                <span class="badge bg-<?= $badge ?>"><?= $t['estado'] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas alertas -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-bell"></i> Alertas Recientes</h6>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="<?= BASE_URL ?>modules/anomalias/listar.php" class="btn btn-sm btn-outline-warning">Ver todas <i class="bi bi-arrow-up-right"></i></a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($ultimas_alertas)): ?>
                        <p class="text-center text-muted py-3 mb-0">Sin alertas recientes</p>
                    <?php else: ?>
                        <?php foreach ($ultimas_alertas as $a): ?>
                            <div class="alert alert-warning py-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <strong><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($a['nombre_regla']) ?></strong>
                                    <small><?= date('d/m H:i', strtotime($a['fecha_alerta'])) ?></small>
                                </div>
                                <small>
                                    Surtidor <?= htmlspecialchars($a['codigo_surtidor']) ?> - 
                                    <?= number_format($a['volumen_litros'], 2) ?> L
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>