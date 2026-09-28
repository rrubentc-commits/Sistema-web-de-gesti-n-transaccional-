<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireAdmin();

$db = Database::getInstance()->getConnection();
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $ci = trim($_POST['ci'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $id_rol = (int)$_POST['id_rol'];
        $password = $_POST['password'];

        // Validaciones
        if (strlen($password) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres');
        if ($auth->isSuperAdmin() === false && $id_rol == ROL_SUPER_ADMIN) {
            throw new Exception('Solo un Super Admin puede crear otro Super Admin');
        }

        $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = :u OR email = :e");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) throw new Exception('Usuario o email ya existe');

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre_completo, ci, email, username, password_hash, id_rol, telefono)
            VALUES (:n, :ci, :e, :u, :p, :r, :t)
        ");
        $stmt->execute([
            ':n' => $nombre, ':ci' => $ci ?: null, ':e' => $email, ':u' => $username,
            ':p' => $hash, ':r' => $id_rol, ':t' => $telefono ?: null
        ]);
        $newId = $db->lastInsertId();
        $auth->registrarAuditoria($_SESSION['user_id'], 'CREAR_USUARIO', 'usuarios', $newId);
        $exito = 'Usuario creado correctamente';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Roles disponibles (Super Admin solo puede crear Admin/Operador; Super Admin puede crear todos)
$roles = $db->query("SELECT * FROM roles ORDER BY nivel_jerarquia DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-person-plus"></i> Nuevo Usuario</h2>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" name="nombre_completo" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CI</label>
                        <input type="text" name="ci" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre de Usuario *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contraseña * (mín. 8 caracteres)</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rol *</label>
                        <select name="id_rol" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <?php if ($r['id_rol'] == ROL_SUPER_ADMIN && !$auth->isSuperAdmin()) continue; ?>
                                <option value="<?= $r['id_rol'] ?>"><?= htmlspecialchars($r['nombre_rol']) ?> - <?= htmlspecialchars($r['descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <hr>
                <a href="listar.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Crear Usuario</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>