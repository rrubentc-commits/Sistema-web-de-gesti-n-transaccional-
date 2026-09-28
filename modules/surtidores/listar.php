<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireAdmin();

$db = Database::getInstance()->getConnection();
$surtidores = $db->query("
    SELECT s.*, p.nombre_producto, p.precio_actual 
    FROM surtidores s 
    INNER JOIN productos p ON s.id_producto = p.id_producto 
    ORDER BY s.codigo_surtidor
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-fuel-pump"></i> Surtidores</h2>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th>Última Calibración</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($surtidores as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['codigo_surtidor']) ?></strong></td>
                            <td><?= htmlspecialchars($s['descripcion']) ?></td>
                            <td><?= htmlspecialchars($s['nombre_producto']) ?></td>
                            <td>Bs <?= number_format($s['precio_actual'], 2) ?></td>
                            <td>
                                <?php
                                $badge = ['operativo' => 'success', 'mantenimiento' => 'warning', 'fuera_servicio' => 'danger'][$s['estado']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= $s['estado'] ?></span>
                            </td>
                            <td><?= $s['ultima_calibracion'] ? date('d/m/Y', strtotime($s['ultima_calibracion'])) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>