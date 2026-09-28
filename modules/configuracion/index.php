<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireSuperAdmin();

$db = Database::getInstance()->getConnection();
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['config'] as $clave => $valor) {
        $stmt = $db->prepare("UPDATE configuracion_sistema SET valor = :v WHERE clave = :c");
        $stmt->execute([':v' => $valor, ':c' => $clave]);
    }
    $auth->registrarAuditoria($_SESSION['user_id'], 'ACTUALIZAR_CONFIG', 'configuracion_sistema', null, null, $_POST);
    $mensaje = 'Configuración actualizada correctamente';
}

$configs = $db->query("SELECT * FROM configuracion_sistema ORDER BY solo_super_admin, clave")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-gear-fill text-danger"></i> Configuración Global</h2>
        <span class="badge bg-danger fs-6"><i class="bi bi-lock-fill"></i> Solo Super Admin</span>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= $mensaje ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <?php foreach ($configs as $c): ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                <?= htmlspecialchars($c['clave']) ?>
                                <?php if ($c['solo_super_admin']): ?>
                                    <span class="badge bg-danger">Super Admin</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="config[<?= $c['clave'] ?>]" 
                                   class="form-control" value="<?= htmlspecialchars($c['valor']) ?>">
                            <small class="text-muted"><?= htmlspecialchars($c['descripcion']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <hr>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>