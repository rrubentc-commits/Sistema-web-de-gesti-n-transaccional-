<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireAdmin();

$db = Database::getInstance()->getConnection();

// Actualizar estado de una alerta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_alerta'])) {
    $stmt = $db->prepare("
        UPDATE alertas_anomalias 
        SET estado_alerta = :estado, revisada_por = :uid, fecha_revision = NOW(), comentario_revision = :com
        WHERE id_alerta = :id
    ");
    $stmt->execute([
        ':estado' => $_POST['estado'],
        ':uid' => $_SESSION['user_id'],
        ':com' => $_POST['comentario'] ?? '',
        ':id' => $_POST['id_alerta']
    ]);
    $auth->registrarAuditoria($_SESSION['user_id'], 'REVISAR_ALERTA', 'alertas_anomalias', $_POST['id_alerta']);
    header('Location: listar.php?ok=1');
    exit();
}

$filtroEstado = $_GET['estado'] ?? '';

$sql = "SELECT a.*, t.volumen_litros, t.monto_total, t.fecha_hora AS fecha_transaccion,
        s.codigo_surtidor, r.nombre_regla, r.tipo_anomalia,
        u.nombre_completo AS operador,
        rev.nombre_completo AS revisor
        FROM alertas_anomalias a
        INNER JOIN transacciones t ON a.id_transaccion = t.id_transaccion
        INNER JOIN surtidores s ON t.id_surtidor = s.id_surtidor
        INNER JOIN reglas_anomalias r ON a.id_regla = r.id_regla
        INNER JOIN usuarios u ON t.id_operador = u.id_usuario
        LEFT JOIN usuarios rev ON a.revisada_por = rev.id_usuario";

if ($filtroEstado) {
    $sql .= " WHERE a.estado_alerta = :estado";
}
$sql .= " ORDER BY a.fecha_alerta DESC LIMIT 200";

$stmt = $db->prepare($sql);
if ($filtroEstado) $stmt->bindValue(':estado', $filtroEstado);
$stmt->execute();
$alertas = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-exclamation-triangle text-warning"></i> Alertas de Anomalías</h2>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Alerta actualizada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <a href="listar.php" class="btn btn-sm <?= !$filtroEstado ? 'btn-primary' : 'btn-outline-primary' ?>">Todas</a>
        <a href="?estado=nueva" class="btn btn-sm <?= $filtroEstado == 'nueva' ? 'btn-danger' : 'btn-outline-danger' ?>">Nuevas</a>
        <a href="?estado=en_revision" class="btn btn-sm <?= $filtroEstado == 'en_revision' ? 'btn-warning' : 'btn-outline-warning' ?>">En Revisión</a>
        <a href="?estado=revisada" class="btn btn-sm <?= $filtroEstado == 'revisada' ? 'btn-info' : 'btn-outline-info' ?>">Revisadas</a>
        <a href="?estado=confirmada" class="btn btn-sm <?= $filtroEstado == 'confirmada' ? 'btn-success' : 'btn-outline-success' ?>">Confirmadas</a>
        <a href="?estado=descartada" class="btn btn-sm <?= $filtroEstado == 'descartada' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Descartadas</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha Alerta</th>
                            <th>Regla</th>
                            <th>Tipo</th>
                            <th>Surtidor</th>
                            <th>Volumen</th>
                            <th>Severidad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alertas)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No hay alertas</td></tr>
                        <?php else: ?>
                            <?php foreach ($alertas as $a): ?>
                                <tr>
                                    <td><?= $a['id_alerta'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($a['fecha_alerta'])) ?></td>
                                    <td><?= htmlspecialchars($a['nombre_regla']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $a['tipo_anomalia'] ?></span></td>
                                    <td><?= htmlspecialchars($a['codigo_surtidor']) ?></td>
                                    <td><?= number_format($a['volumen_litros'], 2) ?> L</td>
                                    <td>
                                        <?php
                                        $sevBadge = ['baja' => 'info', 'media' => 'warning', 'alta' => 'danger', 'critica' => 'dark'][$a['nivel_severidad']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $sevBadge ?>"><?= $a['nivel_severidad'] ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $estBadge = ['nueva' => 'danger', 'en_revision' => 'warning', 'revisada' => 'info', 'descartada' => 'secondary', 'confirmada' => 'success'][$a['estado_alerta']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $estBadge ?>"><?= $a['estado_alerta'] ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAlerta<?= $a['id_alerta'] ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal de revisión -->
                                <div class="modal fade" id="modalAlerta<?= $a['id_alerta'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header bg-warning">
                                                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Revisar Alerta #<?= $a['id_alerta'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_alerta" value="<?= $a['id_alerta'] ?>">
                                                    <p><strong>Regla:</strong> <?= htmlspecialchars($a['nombre_regla']) ?></p>
                                                    <p><strong>Descripción:</strong> <?= htmlspecialchars($a['descripcion']) ?></p>
                                                    <p><strong>Surtidor:</strong> <?= htmlspecialchars($a['codigo_surtidor']) ?> | 
                                                       <strong>Volumen:</strong> <?= number_format($a['volumen_litros'], 2) ?> L</p>
                                                    <p><strong>Operador:</strong> <?= htmlspecialchars($a['operador']) ?></p>
                                                    <hr>
                                                    <div class="mb-3">
                                                        <label class="form-label">Cambiar Estado</label>
                                                        <select name="estado" class="form-select" required>
                                                            <option value="en_revision" <?= $a['estado_alerta'] == 'en_revision' ? 'selected' : '' ?>>En Revisión</option>
                                                            <option value="revisada" <?= $a['estado_alerta'] == 'revisada' ? 'selected' : '' ?>>Revisada</option>
                                                            <option value="confirmada" <?= $a['estado_alerta'] == 'confirmada' ? 'selected' : '' ?>>Confirmada (anomalía real)</option>
                                                            <option value="descartada" <?= $a['estado_alerta'] == 'descartada' ? 'selected' : '' ?>>Descartada (falso positivo)</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Comentario</label>
                                                        <textarea name="comentario" class="form-control" rows="3"><?= htmlspecialchars($a['comentario_revision'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>