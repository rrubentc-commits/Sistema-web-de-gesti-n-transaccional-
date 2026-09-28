<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireAdmin();

$db = Database::getInstance()->getConnection();

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Exportar a CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_anomalias_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['ID', 'Fecha', 'Regla', 'Tipo', 'Severidad', 'Surtidor', 'Volumen', 'Operador', 'Estado']);

    $stmt = $db->prepare("
        SELECT a.id_alerta, a.fecha_alerta, r.nombre_regla, r.tipo_anomalia, a.nivel_severidad,
               s.codigo_surtidor, t.volumen_litros, u.nombre_completo, a.estado_alerta
        FROM alertas_anomalias a
        INNER JOIN transacciones t ON a.id_transaccion = t.id_transaccion
        INNER JOIN surtidores s ON t.id_surtidor = s.id_surtidor
        INNER JOIN reglas_anomalias r ON a.id_regla = r.id_regla
        INNER JOIN usuarios u ON t.id_operador = u.id_usuario
        WHERE DATE(a.fecha_alerta) BETWEEN :d AND :h
        ORDER BY a.fecha_alerta DESC
    ");
    $stmt->execute([':d' => $desde, ':h' => $hasta]);
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($out, $row);
    fclose($out);
    exit();
}

// Resumen estadístico
$stmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_alertas,
        SUM(CASE WHEN estado_alerta='confirmada' THEN 1 ELSE 0 END) AS confirmadas,
        SUM(CASE WHEN estado_alerta='descartada' THEN 1 ELSE 0 END) AS descartadas
    FROM alertas_anomalias
    WHERE DATE(fecha_alerta) BETWEEN :d AND :h
");
$stmt->execute([':d' => $desde, ':h' => $hasta]);
$resumen = $stmt->fetch();

// Por tipo
$stmt = $db->prepare("
    SELECT r.tipo_anomalia, COUNT(*) AS total
    FROM alertas_anomalias a
    INNER JOIN reglas_anomalias r ON a.id_regla = r.id_regla
    WHERE DATE(a.fecha_alerta) BETWEEN :d AND :h
    GROUP BY r.tipo_anomalia
");
$stmt->execute([':d' => $desde, ':h' => $hasta]);
$porTipo = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-file-earmark-bar-graph"></i> Reportes</h2>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?= $desde ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?= $hasta ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Generar</button>
                </div>
                <div class="col-md-2">
                    <a href="?desde=<?= $desde ?>&hasta=<?= $hasta ?>&export=csv" class="btn btn-success w-100">
                        <i class="bi bi-file-earmark-excel"></i> Exportar CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <h6 class="text-muted">Total Alertas</h6>
                    <h2 class="text-danger mb-0"><?= $resumen['total_alertas'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h6 class="text-muted">Confirmadas</h6>
                    <h2 class="text-success mb-0"><?= $resumen['confirmadas'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-secondary">
                <div class="card-body">
                    <h6 class="text-muted">Descartadas</h6>
                    <h2 class="text-secondary mb-0"><?= $resumen['descartadas'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h6 class="mb-0">Alertas por Tipo de Anomalía</h6></div>
        <div class="card-body">
            <?php if (empty($porTipo)): ?>
                <p class="text-muted mb-0">Sin datos en el periodo seleccionado.</p>
            <?php else: ?>
                <?php foreach ($porTipo as $t): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><i class="bi bi-tag"></i> <?= htmlspecialchars($t['tipo_anomalia']) ?></span>
                        <span class="badge bg-primary"><?= $t['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>