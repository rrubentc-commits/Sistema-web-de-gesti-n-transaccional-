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

function responderRfid(int $estado, array $datos): void
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit();
}

function normalizarUid($uid): string
{
    $uid = strtoupper(preg_replace('/\s+/', '', trim((string)$uid)));
    if ($uid === '' || strlen($uid) > 64 || !preg_match('/^[A-Z0-9:-]+$/', $uid)) {
        throw new InvalidArgumentException('El UID RFID no tiene un formato válido.');
    }
    return $uid;
}

function registrarIngresoRfid(PDO $db, string $uid, int $usuarioId): array
{
    $consulta = $db->prepare("SELECT c.id_cisterna, c.codigo, c.nombre, c.placa
        FROM rfid_tarjetas t
        INNER JOIN cisternas c ON c.id_cisterna = t.id_cisterna
        WHERE t.uid = :uid AND t.activa = 1 AND c.activa = 1");
    $consulta->execute([':uid' => $uid]);
    $cisterna = $consulta->fetch();

    $insertar = $db->prepare("INSERT INTO ingresos_cisternas
        (uid_tarjeta, id_cisterna, resultado, registrada_por)
        VALUES (:uid, :cisterna, :resultado, :usuario)");
    $insertar->execute([
        ':uid' => $uid,
        ':cisterna' => $cisterna['id_cisterna'] ?? null,
        ':resultado' => $cisterna ? 'identificada' : 'no_identificada',
        ':usuario' => $usuarioId
    ]);

    return [
        'id_ingreso' => (int)$db->lastInsertId(),
        'uid' => $uid,
        'cisterna' => $cisterna ?: null,
        'resultado' => $cisterna ? 'identificada' : 'no_identificada'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    $datos = $esJson ? json_decode(file_get_contents('php://input'), true) : $_POST;
    $datos = is_array($datos) ? $datos : [];
    $accion = $datos['action'] ?? '';
    $token = $esJson ? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') : ($datos['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, (string)$token)) {
        if ($esJson) {
            responderRfid(403, ['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
        }
        http_response_code(403);
        $mensaje = 'Token de seguridad inválido. Recarga la página.';
        $tipoMensaje = 'danger';
    } else {
        try {
            if ($accion === 'registrar_ingreso') {
                $resultado = registrarIngresoRfid($db, normalizarUid($datos['uid'] ?? ''), (int)$_SESSION['user_id']);
                if ($esJson) {
                    responderRfid(200, ['success' => true] + $resultado);
                }
                $mensaje = $resultado['cisterna']
                    ? 'Ingreso registrado para ' . $resultado['cisterna']['codigo'] . ' (' . $resultado['cisterna']['placa'] . ').'
                    : 'Ingreso registrado; el UID no está asociado a una cisterna.';
            }

            elseif ($accion === 'guardar_configuracion') {
                $puerto = strtoupper(trim($datos['puerto_com'] ?? ''));
                $baudios = (int)($datos['velocidad_baudios'] ?? 0);
                if (!preg_match('/^COM[1-9][0-9]{0,2}$/', $puerto)) {
                    throw new InvalidArgumentException('Indica un puerto válido, por ejemplo COM3.');
                }
                if (!in_array($baudios, [9600, 19200, 38400, 57600, 115200], true)) {
                    throw new InvalidArgumentException('La velocidad serial seleccionada no es válida.');
                }
                $stmt = $db->prepare("UPDATE rfid_configuracion SET puerto_com = :puerto,
                    velocidad_baudios = :baudios, actualizado_por = :usuario WHERE id_configuracion = 1");
                $stmt->execute([':puerto' => $puerto, ':baudios' => $baudios, ':usuario' => $_SESSION['user_id']]);
                $auth->registrarAuditoria($_SESSION['user_id'], 'ACTUALIZAR_CONFIG_RFID', 'rfid_configuracion', 1, null, $datos);
                $mensaje = 'Configuración del lector guardada.';
            } else {
                throw new InvalidArgumentException('Acción no reconocida.');
            }
        } catch (Throwable $e) {
            if ($esJson) {
                responderRfid(400, ['success' => false, 'message' => $e instanceof PDOException
                    ? 'No se pudo guardar. Verifica que código, placa o UID no estén duplicados.'
                    : $e->getMessage()]);
            }
            $mensaje = $e instanceof PDOException
                ? 'No se pudo guardar. Verifica que código, placa o UID no estén duplicados.'
                : $e->getMessage();
            $tipoMensaje = 'danger';
        }
    }
}

$config = $db->query('SELECT * FROM rfid_configuracion WHERE id_configuracion = 1')->fetch();
$ingresos = $db->query("SELECT i.*, c.codigo, c.nombre, c.placa, u.username
    FROM ingresos_cisternas i
    LEFT JOIN cisternas c ON c.id_cisterna = i.id_cisterna
    LEFT JOIN usuarios u ON u.id_usuario = i.registrada_por
    ORDER BY i.fecha_hora DESC LIMIT 100")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-upc-scan text-primary"></i> Control RFID de Cisternas</h2>
            <p class="text-muted mb-0">Registro de ingresos por lectura de tarjeta</p>
        </div>
        <span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Solo Super Admin</span>
    </div>

    <div class="mb-3">
        <a class="btn btn-outline-primary" href="<?= BASE_URL ?>modules/rfid/cisternas.php">
            <i class="bi bi-tags"></i> Registrar cisternas y asociar UID
        </a>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= htmlspecialchars($tipoMensaje) ?> alert-dismissible fade show">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Lector y puerto serial</h5></div>
        <div class="card-body">
            <form method="post" class="row g-3 align-items-end mb-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="guardar_configuracion">
                <div class="col-sm-4 col-md-3">
                    <label for="puerto-com" class="form-label">Puerto COM configurado</label>
                    <input id="puerto-com" name="puerto_com" class="form-control" maxlength="10" pattern="COM[1-9][0-9]{0,2}" value="<?= htmlspecialchars($config['puerto_com'] ?? 'COM3') ?>" required>
                </div>
                <div class="col-sm-4 col-md-3">
                    <label for="baud-rate" class="form-label">Velocidad (baudios)</label>
                    <select id="baud-rate" name="velocidad_baudios" class="form-select">
                        <?php foreach ([9600, 19200, 38400, 57600, 115200] as $baud): ?>
                            <option value="<?= $baud ?>" <?= (int)($config['velocidad_baudios'] ?? 9600) === $baud ? 'selected' : '' ?>><?= number_format($baud) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-4 col-md-auto">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-success" id="rfid-connect" data-port="<?= htmlspecialchars($config['puerto_com'] ?? 'COM3') ?>" data-baud="<?= (int)($config['velocidad_baudios'] ?? 9600) ?>">
                    <i class="bi bi-usb-symbol"></i> Conectar lector
                </button>
                <button type="button" class="btn btn-outline-secondary" id="rfid-disconnect" disabled>
                    <i class="bi bi-plug"></i> Desconectar
                </button>
                <span id="rfid-status" class="text-muted" role="status" aria-live="polite">Lector desconectado</span>
            </div>
            <small class="form-text d-block mt-2">E.</small>
        </div>
    </section>

    <section class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Últimos ingresos RFID</h5></div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="table-dark"><tr><th>Fecha/hora</th><th>UID</th><th>Cisterna</th><th>Placa</th><th>Resultado</th><th>Registrado por</th></tr></thead>
            <tbody><?php if (!$ingresos): ?><tr><td colspan="6" class="text-center text-muted py-3">No hay ingresos registrados</td></tr><?php endif; ?>
                <?php foreach ($ingresos as $ingreso): ?><tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($ingreso['fecha_hora'])) ?></td><td><code><?= htmlspecialchars($ingreso['uid_tarjeta']) ?></code></td>
                    <td><?= htmlspecialchars($ingreso['codigo'] ? $ingreso['codigo'] . ' - ' . $ingreso['nombre'] : 'No identificada') ?></td><td><?= htmlspecialchars($ingreso['placa'] ?? '-') ?></td>
                    <td><span class="badge bg-<?= $ingreso['resultado'] === 'identificada' ? 'success' : 'warning text-dark' ?>"><?= $ingreso['resultado'] === 'identificada' ? 'Identificada' : 'UID desconocido' ?></span></td>
                    <td><?= htmlspecialchars($ingreso['username'] ?? 'Sistema') ?></td>
                </tr><?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>

<meta name="rfid-csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
<script src="<?= BASE_URL ?>modules/rfid/lector.js"></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>