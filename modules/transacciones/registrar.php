<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
$auth->requireLogin();

$db = Database::getInstance()->getConnection();
$user = $auth->getCurrentUser();
$mensaje = '';
$tipoMensaje = '';

// Obtener surtidores y productos
$surtidores = $db->query("SELECT s.*, p.nombre_producto, p.precio_actual 
                         FROM surtidores s 
                         INNER JOIN productos p ON s.id_producto = p.id_producto 
                         WHERE s.activo = 1 AND s.estado = 'operativo'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_surtidor = (int)$_POST['id_surtidor'];
        $volumen = (float)$_POST['volumen_litros'];
        $placa = trim($_POST['placa_vehiculo'] ?? '');
        $ci = trim($_POST['ci_cliente'] ?? '');
        $obs = trim($_POST['observaciones'] ?? '');

        if ($volumen <= 0) throw new Exception('El volumen debe ser mayor a 0');


        $stmt = $db->prepare("SELECT * FROM surtidores WHERE id_surtidor = :id");
        $stmt->execute([':id' => $id_surtidor]);
        $surtidor = $stmt->fetch();
        if (!$surtidor) throw new Exception('Surtidor no válido');

        $stmt = $db->prepare("SELECT * FROM productos WHERE id_producto = :id");
        $stmt->execute([':id' => $surtidor['id_producto']]);
        $producto = $stmt->fetch();

        $precio = $producto['precio_actual'];
        $monto = $volumen * $precio;


        $stmt = $db->prepare("
            INSERT INTO transacciones 
            (id_surtidor, id_producto, id_operador, volumen_litros, precio_unitario, monto_total, placa_vehiculo, ci_cliente, observaciones)
            VALUES (:s, :p, :o, :v, :pu, :mt, :pl, :ci, :ob)
        ");
        $stmt->execute([
            ':s' => $id_surtidor,
            ':p' => $surtidor['id_producto'],
            ':o' => $user['id_usuario'],
            ':v' => $volumen,
            ':pu' => $precio,
            ':mt' => $monto,
            ':pl' => $placa ?: null,
            ':ci' => $ci ?: null,
            ':ob' => $obs ?: null
        ]);
        $id_transaccion = $db->lastInsertId();

    
        $alertasGeneradas = evaluarAnomalias($db, $id_transaccion, $volumen, $id_surtidor);

        if ($alertasGeneradas > 0) {
            $mensaje = "Transacción registrada (ID: $id_transaccion). <strong>⚠️ Se generaron $alertasGeneradas alerta(s) de anomalía.</strong>";
            $tipoMensaje = 'warning';
        } else {
            $mensaje = "Transacción registrada correctamente (ID: $id_transaccion).";
            $tipoMensaje = 'success';
        }

        // Auditoría
        $auth->registrarAuditoria($user['id_usuario'], 'REGISTRAR_TRANSACCION', 'transacciones', $id_transaccion, null, $_POST);

    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipoMensaje = 'danger';
    }
}

/**
 * Evalúa las reglas de anomalía sobre una transacción
 */
function evaluarAnomalias($db, $id_transaccion, $volumen, $id_surtidor) {
    $alertas = 0;
    $reglas = $db->query("SELECT * FROM reglas_anomalias WHERE activa = 1")->fetchAll();

    foreach ($reglas as $regla) {
        $esAnomalia = false;
        $severidad = 'media';
        $descripcion = '';

        switch ($regla['parametro']) {
            case 'volumen':
                if ($regla['operador_comparacion'] === 'mayor' && $volumen > $regla['valor_umbral']) {
                    $esAnomalia = true;
                    $severidad = 'alta';
                    $descripcion = "Volumen de {$volumen} L excede el umbral de {$regla['valor_umbral']} L";
                }
                break;

            case 'frecuencia':
                // Contar transacciones del mismo surtidor en la última hora
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total FROM transacciones 
                    WHERE id_surtidor = :s 
                    AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->execute([':s' => $id_surtidor]);
                $frecuencia = $stmt->fetch()['total'];

                if ($regla['operador_comparacion'] === 'mayor' && $frecuencia > $regla['valor_umbral']) {
                    $esAnomalia = true;
                    $severidad = 'media';
                    $descripcion = "Surtidor con $frecuencia transacciones en 1 hora (umbral: {$regla['valor_umbral']})";
                }
                break;

            case 'horario':
                $hora = (int)date('H');
                if ($hora >= 0 && $hora <= (int)$regla['valor_umbral']) {
                    $esAnomalia = true;
                    $severidad = 'baja';
                    $descripcion = "Despacho en horario inusual ({$hora}:00 hrs)";
                }
                break;

            case 'colectiva':
                // Patrón: transacciones con montos similares en 30 min
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total FROM transacciones 
                    WHERE id_surtidor = :s 
                    AND fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ");
                $stmt->execute([':s' => $id_surtidor]);
                $similares = $stmt->fetch()['total'];

                if ($similares >= $regla['valor_umbral']) {
                    $esAnomalia = true;
                    $severidad = 'alta';
                    $descripcion = "Patrón colectivo: $similares transacciones en 30 min en el mismo surtidor";
                }
                break;
        }

        if ($esAnomalia) {
            $stmt = $db->prepare("
                INSERT INTO alertas_anomalias (id_transaccion, id_regla, nivel_severidad, descripcion)
                VALUES (:t, :r, :s, :d)
            ");
            $stmt->execute([
                ':t' => $id_transaccion,
                ':r' => $regla['id_regla'],
                ':s' => $severidad,
                ':d' => $descripcion
            ]);
            $alertas++;
        }
    }

    // Marcar transacción como sospechosa si tiene alertas
    if ($alertas > 0) {
        $db->prepare("UPDATE transacciones SET estado = 'sospechosa' WHERE id_transaccion = :id")
           ->execute([':id' => $id_transaccion]);
    }

    return $alertas;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="bi bi-plus-circle"></i> Registrar Nueva Transacción</h2>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show">
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" id="formTransaccion">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Surtidor *</label>
                                <select name="id_surtidor" id="id_surtidor" class="form-select" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach ($surtidores as $s): ?>
                                        <option value="<?= $s['id_surtidor'] ?>" 
                                                data-precio="<?= $s['precio_actual'] ?>"
                                                data-producto="<?= htmlspecialchars($s['nombre_producto']) ?>">
                                            <?= htmlspecialchars($s['codigo_surtidor']) ?> - <?= htmlspecialchars($s['nombre_producto']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Producto</label>
                                <input type="text" id="producto_mostrar" class="form-control" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Volumen (Litros) *</label>
                                <input type="number" step="0.001" min="0.001" name="volumen_litros" 
                                       id="volumen_litros" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Precio Unitario (Bs/L)</label>
                                <input type="text" id="precio_mostrar" class="form-control" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Monto Total (Bs)</label>
                                <input type="text" id="monto_mostrar" class="form-control fw-bold text-success" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Placa del Vehículo</label>
                                <input type="text" name="placa_vehiculo" class="form-control" 
                                       placeholder="Ej: ABC-1234" maxlength="15">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">CI del Cliente</label>
                                <input type="text" name="ci_cliente" class="form-control" maxlength="20">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="1"></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="listar.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Registrar Transacción
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm bg-light">
                <div class="card-body">
                    <h6><i class="bi bi-info-circle"></i> Información</h6>
                    <p class="small mb-2">EL VOLUMEN MAXIMO DE COMPRA ES DE 10 LITROS, El sistema evaluará automáticamente la transacción contra las reglas de detección de anomalías.</p>
                    <hr>
                    <h6><i class="bi bi-shield-exclamation"></i> Reglas activas:</h6>
                    <ul class="small mb-0">
                        <?php
                        $reglas = $db->query("SELECT nombre_regla, valor_umbral, parametro FROM reglas_anomalias WHERE activa = 1")->fetchAll();
                        foreach ($reglas as $r) {
                            echo "<li>" . htmlspecialchars($r['nombre_regla']) . " <span class='badge bg-secondary'>" . $r['valor_umbral'] . "</span></li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('id_surtidor').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const precio = parseFloat(opt.dataset.precio || 0);
    document.getElementById('producto_mostrar').value = opt.dataset.producto || '';
    document.getElementById('precio_mostrar').value = 'Bs ' + precio.toFixed(2);
    calcularTotal();
});

document.getElementById('volumen_litros').addEventListener('input', calcularTotal);

function calcularTotal() {
    const opt = document.getElementById('id_surtidor').options[document.getElementById('id_surtidor').selectedIndex];
    const precio = parseFloat(opt.dataset.precio || 0);
    const vol = parseFloat(document.getElementById('volumen_litros').value || 0);
    document.getElementById('monto_mostrar').value = 'Bs ' + (precio * vol).toFixed(2);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>