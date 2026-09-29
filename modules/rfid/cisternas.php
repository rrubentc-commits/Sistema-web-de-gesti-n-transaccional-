<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireSuperAdmin();

$db = Database::getInstance()->getConnection();
if (empty($_SESSION['rfid_csrf'])) {
    $_SESSION['rfid_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['rfid_csrf'];
$mensaje = '';
$tipoMensaje = 'success';

function normalizarUidCisterna($uid): string
{
    $uid = strtoupper(preg_replace('/\s+/', '', trim((string)$uid)));
    if ($uid === '' || strlen($uid) > 64 || !preg_match('/^[A-Z0-9:-]+$/', $uid)) {
        throw new InvalidArgumentException('El UID RFID no tiene un formato válido.');
    }
    return $uid;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $mensaje = 'Token de seguridad inválido. Recarga la página.';
        $tipoMensaje = 'danger';
    } else {
        try {
            $accion = $_POST['action'] ?? '';
            if ($accion === 'crear_cisterna') {
                $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
                $nombre = trim($_POST['nombre'] ?? '');
                $placa = strtoupper(trim($_POST['placa'] ?? ''));
                $capacidad = trim($_POST['capacidad_litros'] ?? '');
                if ($codigo === '' || $nombre === '' || $placa === '') {
                    throw new InvalidArgumentException('Completa código, nombre y placa de la cisterna.');
                }
                if (strlen($codigo) > 30 || strlen($nombre) > 100 || strlen($placa) > 20) {
                    throw new InvalidArgumentException('Uno de los datos supera la longitud permitida.');
                }
                if ($capacidad !== '' && (!is_numeric($capacidad) || (float)$capacidad <= 0)) {
                    throw new InvalidArgumentException('La capacidad debe ser un número mayor que cero.');
                }
                $stmt = $db->prepare("INSERT INTO cisternas (codigo, nombre, placa, capacidad_litros)
                    VALUES (:codigo, :nombre, :placa, :capacidad)");
                $stmt->execute([
                    ':codigo' => $codigo,
                    ':nombre' => $nombre,
                    ':placa' => $placa,
                    ':capacidad' => $capacidad === '' ? null : $capacidad
                ]);
                $idCisterna = (int)$db->lastInsertId();
                $auth->registrarAuditoria($_SESSION['user_id'], 'CREAR_CISTERNA', 'cisternas', $idCisterna, null, $_POST);
                $mensaje = 'Cisterna registrada.';
            } elseif ($accion === 'asociar_tarjeta') {
                $uid = normalizarUidCisterna($_POST['uid'] ?? '');
                $idCisterna = (int)($_POST['id_cisterna'] ?? 0);
                $stmt = $db->prepare('SELECT id_cisterna FROM cisternas WHERE id_cisterna = :id AND activa = 1');
                $stmt->execute([':id' => $idCisterna]);
                if (!$stmt->fetch()) {
                    throw new InvalidArgumentException('Selecciona una cisterna activa.');
                }
                $stmt = $db->prepare("INSERT INTO rfid_tarjetas (uid, id_cisterna) VALUES (:uid, :cisterna)
                    ON DUPLICATE KEY UPDATE id_cisterna = VALUES(id_cisterna), activa = 1");
                $stmt->execute([':uid' => $uid, ':cisterna' => $idCisterna]);
                $auth->registrarAuditoria($_SESSION['user_id'], 'ASOCIAR_TARJETA_RFID', 'rfid_tarjetas', null, null, [
                    'uid' => $uid,
                    'id_cisterna' => $idCisterna
                ]);
                $mensaje = 'UID ' . $uid . ' asociado a la cisterna.';
            } else {
                throw new InvalidArgumentException('Acción no reconocida.');
            }
        } catch (Throwable $e) {
            $mensaje = $e instanceof PDOException
                ? 'No se pudo guardar. El código, la placa o el UID podrían estar duplicados.'
                : $e->getMessage();
            $tipoMensaje = 'danger';
        }
    }
}

$cisternas = $db->query("SELECT c.*, GROUP_CONCAT(t.uid ORDER BY t.uid SEPARATOR ', ') AS uids
    FROM cisternas c
    LEFT JOIN rfid_tarjetas t ON t.id_cisterna = c.id_cisterna AND t.activa = 1
    GROUP BY c.id_cisterna
    ORDER BY c.codigo")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-tags text-primary"></i> Cisternas y tarjetas RFID</h2>
            <p class="text-muted mb-0">Registra cada cisterna y vincula el UID impreso por el lector</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>modules/rfid/index.php"><i class="bi bi-arrow-left"></i> Volver a ingresos</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= htmlspecialchars($tipoMensaje) ?> alert-dismissible fade show">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <section class="card shadow-sm h-100">
                <div class="card-header"><h5 class="mb-0">Registrar cisterna</h5></div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="crear_cisterna">
                        <div class="col-md-6"><label class="form-label" for="codigo">Código</label><input class="form-control" id="codigo" name="codigo" maxlength="30" required></div>
                        <div class="col-md-6"><label class="form-label" for="placa">Placa</label><input class="form-control" id="placa" name="placa" maxlength="20" required></div>
                        <div class="col-md-8"><label class="form-label" for="nombre">Nombre/empresa</label><input class="form-control" id="nombre" name="nombre" maxlength="100" required></div>
                        <div class="col-md-4"><label class="form-label" for="capacidad">Capacidad (L)</label><input class="form-control" id="capacidad" name="capacidad_litros" type="number" min="0.01" step="0.01"></div>
                        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Guardar cisterna</button></div>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-xl-7">
            <section class="card shadow-sm h-100">
                <div class="card-header"><h5 class="mb-0">Asociar UID leído</h5></div>
                <div class="card-body">
                    <p class="text-muted">Pasa la tarjeta por el lector y copia el valor de <code>uid</code> que aparece en el monitor serial. Ejemplo: <code>3C7A2B39</code>.</p>
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="asociar_tarjeta">
                        <div class="col-md-5"><label class="form-label" for="uid">UID de la tarjeta</label><input class="form-control text-uppercase" id="uid" name="uid" maxlength="64" placeholder="3C7A2B39" required></div>
                        <div class="col-md-5"><label class="form-label" for="cisterna">Cisterna</label>
                            <select class="form-select" id="cisterna" name="id_cisterna" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($cisternas as $cisterna): if (!$cisterna['activa']) continue; ?>
                                    <option value="<?= (int)$cisterna['id_cisterna'] ?>"><?= htmlspecialchars($cisterna['codigo'] . ' - ' . $cisterna['placa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Asociar</button></div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <section class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Cisternas registradas</h5></div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Código</th><th>Nombre</th><th>Placa</th><th>Capacidad</th><th>UID(s) asociado(s)</th><th>Estado</th></tr></thead>
            <tbody><?php if (!$cisternas): ?><tr><td colspan="6" class="text-center text-muted py-3">Aún no hay cisternas</td></tr><?php endif; ?>
                <?php foreach ($cisternas as $cisterna): ?><tr>
                    <td><?= htmlspecialchars($cisterna['codigo']) ?></td>
                    <td><?= htmlspecialchars($cisterna['nombre']) ?></td>
                    <td><?= htmlspecialchars($cisterna['placa']) ?></td>
                    <td><?= $cisterna['capacidad_litros'] !== null ? number_format((float)$cisterna['capacidad_litros'], 2) . ' L' : '-' ?></td>
                    <td><code><?= htmlspecialchars($cisterna['uids'] ?: '-') ?></code></td>
                    <td><span class="badge bg-<?= $cisterna['activa'] ? 'success' : 'secondary' ?>"><?= $cisterna['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
                </tr><?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>