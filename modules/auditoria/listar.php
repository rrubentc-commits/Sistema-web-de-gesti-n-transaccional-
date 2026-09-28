<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireSuperAdmin(); // SOLO SUPER ADMIN

$db = Database::getInstance()->getConnection();

$sql = "SELECT a.*, u.nombre_completo AS usuario, u.username
        FROM auditoria a
        LEFT JOIN usuarios u ON a.id_usuario = u.id_usuario
        ORDER BY a.fecha_hora DESC LIMIT 500";
$registros = $db->query($sql)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-shield-check text-danger"></i> Auditoría del Sistema</h2>
        <span class="badge bg-danger fs-6"><i class="bi bi-lock-fill"></i> Solo Super Admin</span>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Esta sección registra todas las acciones críticas del sistema. Solo visible para el Super Administrador.
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha/Hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>Registro</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td><?= $r['id_auditoria'] ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($r['fecha_hora'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($r['usuario'] ?? 'N/A') ?>
                                    <small class="text-muted">(<?= htmlspecialchars($r['username'] ?? '') ?>)</small>
                                </td>
                                <td><span class="badge bg-primary"><?= htmlspecialchars($r['accion']) ?></span></td>
                                <td><?= htmlspecialchars($r['tabla_afectada'] ?? '-') ?></td>
                                <td><?= $r['id_registro_afectado'] ?? '-' ?></td>
                                <td><code><?= htmlspecialchars($r['ip_address'] ?? '-') ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>