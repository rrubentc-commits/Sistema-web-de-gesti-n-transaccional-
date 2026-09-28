<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireSuperAdmin();

$db = Database::getInstance()->getConnection();
$backupDir = BASE_PATH . '/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

$mensaje = '';
$tipo = 'info';

// Crear respaldo
if (isset($_POST['crear_backup'])) {
    $nombreArchivo = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $rutaCompleta = $backupDir . $nombreArchivo;

    $tablas = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sqlDump = "-- Respaldo generado el " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Base de datos: " . DB_NAME . "\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tablas as $tabla) {
        $create = $db->query("SHOW CREATE TABLE `$tabla`")->fetch();
        $sqlDump .= "\n-- Tabla: $tabla\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$tabla`;\n";
        $sqlDump .= $create['Create Table'] . ";\n\n";

        $filas = $db->query("SELECT * FROM `$tabla`")->fetchAll();
        foreach ($filas as $fila) {
            $cols = array_map(fn($c) => "`$c`", array_keys($fila));
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($fila));
            $sqlDump .= "INSERT INTO `$tabla` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        $sqlDump .= "\n";
    }
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($rutaCompleta, $sqlDump);
    $auth->registrarAuditoria($_SESSION['user_id'], 'CREAR_BACKUP', null, null, null, ['archivo' => $nombreArchivo]);
    $mensaje = "Respaldo creado: $nombreArchivo (" . round(filesize($rutaCompleta)/1024, 2) . " KB)";
    $tipo = 'success';
}

// Eliminar respaldo
if (isset($_GET['delete'])) {
    $archivo = basename($_GET['delete']);
    $ruta = $backupDir . $archivo;
    if (file_exists($ruta) && str_starts_with($archivo, 'backup_')) {
        unlink($ruta);
        $auth->registrarAuditoria($_SESSION['user_id'], 'ELIMINAR_BACKUP', null, null, null, ['archivo' => $archivo]);
        $mensaje = "Respaldo eliminado: $archivo";
        $tipo = 'warning';
    }
}

$backups = glob($backupDir . 'backup_*.sql');
rsort($backups);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-database-fill-gear text-danger"></i> Copias de Seguridad</h2>
        <span class="badge bg-danger fs-6"><i class="bi bi-lock-fill"></i> Solo Super Admin</span>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo ?> alert-dismissible fade show">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="POST">
                <button type="submit" name="crear_backup" class="btn btn-primary">
                    <i class="bi bi-download"></i> Crear Respaldo Ahora
                </button>
                <small class="text-muted ms-2">Genera un archivo .sql con toda la base de datos</small>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h6 class="mb-0">Respaldos disponibles</h6></div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Archivo</th>
                        <th>Tamaño</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No hay respaldos</td></tr>
                    <?php else: ?>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><i class="bi bi-file-earmark-code"></i> <?= basename($b) ?></td>
                                <td><?= round(filesize($b)/1024, 2) ?> KB</td>
                                <td><?= date('d/m/Y H:i', filemtime($b)) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>backups/<?= basename($b) ?>" class="btn btn-sm btn-success" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <a href="?delete=<?= urlencode(basename($b)) ?>" class="btn btn-sm btn-danger confirm-delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>