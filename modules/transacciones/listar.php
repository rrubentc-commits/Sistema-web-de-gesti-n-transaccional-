<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireLogin();

$db = Database::getInstance()->getConnection();

// Filtros
$where = [];
$params = [];

if (!empty($_GET['surtidor'])) {
    $where[] = "t.id_surtidor = :surtidor";
    $params[':surtidor'] = $_GET['surtidor'];
}
if (!empty($_GET['estado'])) {
    $where[] = "t.estado = :estado";
    $params[':estado'] = $_GET['estado'];
}
if (!empty($_GET['desde'])) {
    $where[] = "DATE(t.fecha_hora) >= :desde";
    $params[':desde'] = $_GET['desde'];
}
if (!empty($_GET['hasta'])) {
    $where[] = "DATE(t.fecha_hora) <= :hasta";
    $params[':hasta'] = $_GET['hasta'];
}

// Operadores solo ven sus propias transacciones
if (!$auth->isAdmin()) {
    $where[] = "t.id_operador = :uid";
    $params[':uid'] = $_SESSION['user_id'];
}

$sql = "SELECT t.*, s.codigo_surtidor, p.nombre_producto, u.nombre_completo AS operador,
        (SELECT COUNT(*) FROM alertas_anomalias a WHERE a.id_transaccion = t.id_transaccion) AS total_alertas
        FROM transacciones t
        INNER JOIN surtidores s ON t.id_surtidor = s.id_surtidor
        INNER JOIN productos p ON t.id_producto = p.id_producto
        INNER JOIN usuarios u ON t.id_operador = u.id_usuario";

if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY t.fecha_hora DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transacciones = $stmt->fetchAll();

$surtidores = $db->query("SELECT id_surtidor, codigo_surtidor FROM surtidores WHERE activo = 1")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-receipt"></i> Transacciones</h2>
        <a href="registrar.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Transacción
        </a>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-2">
                    <select name="surtidor" class="form-select form-select-sm">
                        <option value="">Todos los surtidores</option>
                        <?php foreach ($surtidores as $s): ?>
                            <option value="<?= $s['id_surtidor'] ?>" <?= ($_GET['surtidor'] ?? '') == $s['id_surtidor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['codigo_surtidor']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="registrada" <?= ($_GET['estado'] ?? '') == 'registrada' ? 'selected' : '' ?>>Registrada</option>
                        <option value="sospechosa" <?= ($_GET['estado'] ?? '') == 'sospechosa' ? 'selected' : '' ?>>Sospechosa</option>
                        <option value="verificada" <?= ($_GET['estado'] ?? '') == 'verificada' ? 'selected' : '' ?>>Verificada</option>
                        <option value="anulada" <?= ($_GET['estado'] ?? '') == 'anulada' ? 'selected' : '' ?>>Anulada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= $_GET['desde'] ?? '' ?>" placeholder="Desde">
                </div>
                <div class="col-md-2">
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $_GET['hasta'] ?? '' ?>" placeholder="Hasta">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                </div>
                <div class="col-md-2">
                    <a href="listar.php" class="btn btn-sm btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Surtidor</th>
                            <th>Producto</th>
                            <th>Volumen</th>
                            <th>Monto</th>
                            <th>Placa</th>
                            <th>Operador</th>
                            <th>Estado</th>
                            <th>Alertas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transacciones)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">No hay transacciones</td></tr>
                        <?php else: ?>
                            <?php foreach ($transacciones as $t): ?>
                                <tr>
                                    <td><?= $t['id_transaccion'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($t['fecha_hora'])) ?></td>
                                    <td><?= htmlspecialchars($t['codigo_surtidor']) ?></td>
                                    <td><?= htmlspecialchars($t['nombre_producto']) ?></td>
                                    <td><?= number_format($t['volumen_litros'], 2) ?> L</td>
                                    <td>Bs <?= number_format($t['monto_total'], 2) ?></td>
                                    <td><?= htmlspecialchars($t['placa_vehiculo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($t['operador']) ?></td>
                                    <td>
                                        <?php
                                        $badge = ['registrada' => 'success', 'sospechosa' => 'warning', 'verificada' => 'info', 'anulada' => 'danger'][$t['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= $t['estado'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($t['total_alertas'] > 0): ?>
                                            <span class="badge bg-danger"><?= $t['total_alertas'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>