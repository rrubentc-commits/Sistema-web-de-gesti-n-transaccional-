<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireAdmin();

$db = Database::getInstance()->getConnection();

// Desactivar/activar
if (isset($_GET['toggle']) && $auth->isAdmin()) {
    $id = (int)$_GET['toggle'];
    // Admin normal no puede modificar super_admin
    if (!$auth->isSuperAdmin()) {
        $check = $db->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id");
        $check->execute([':id' => $id]);
        if ($check->fetch()['id_rol'] == ROL_SUPER_ADMIN) {
            header('Location: listar.php?error=no_permitido');
            exit();
        }
    }
    $db->prepare("UPDATE usuarios SET activo = NOT activo WHERE id_usuario = :id")->execute([':id' => $id]);
    $auth->registrarAuditoria($_SESSION['user_id'], 'TOGGLE_USUARIO', 'usuarios', $id);
    header('Location: listar.php?ok=1');
    exit();
}

$usuarios = $db->query("
    SELECT u.*, r.nombre_rol, r.nivel_jerarquia 
    FROM usuarios u 
    INNER JOIN roles r ON u.id_rol = r.id_rol 
    ORDER BY r.nivel_jerarquia DESC, u.nombre_completo
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> Gestión de Usuarios</h2>
        <a href="crear.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Nuevo Usuario</a>
    </div>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success alert-dismissible fade show">Operación realizada.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Último Acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= $u['id_usuario'] ?></td>
                            <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php
                                $rolBadge = $u['id_rol'] == 1 ? 'danger' : ($u['id_rol'] == 2 ? 'primary' : 'secondary');
                                ?>
                                <span class="badge bg-<?= $rolBadge ?>"><?= $u['nombre_rol'] ?></span>
                            </td>
                            <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
                            <td>
                                <?php if ($u['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $puedeEditar = $auth->isSuperAdmin() || $u['id_rol'] != ROL_SUPER_ADMIN;
                                ?>
                                <?php if ($puedeEditar): ?>
                                    <a href="editar.php?id=<?= $u['id_usuario'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="?toggle=<?= $u['id_usuario'] ?>" class="btn btn-sm btn-outline-<?= $u['activo'] ? 'danger' : 'success' ?>">
                                        <i class="bi bi-<?= $u['activo'] ? 'lock' : 'unlock' ?>"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Protegido</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>