<?php
$nivel = $_SESSION['nivel_jerarquia'] ?? 0;
$paginaActual = basename($_SERVER['PHP_SELF']);
$carpetaActual = basename(dirname($_SERVER['PHP_SELF']));
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-fuel-pump"></i>
        <h5>Combustibles</h5>
        <small class="text-white-50"><?= htmlspecialchars($_SESSION['nombre_rol'] ?? '') ?></small>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="<?= BASE_URL ?>dashboard.php" class="<?= $paginaActual == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>

        <!-- TRANSACCIONES: todos -->
        <li>
            <a href="<?= BASE_URL ?>modules/transacciones/listar.php" class="<?= $carpetaActual == 'transacciones' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> Transacciones
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/transacciones/registrar.php">
                <i class="bi bi-plus-circle"></i> Nueva Transacción
            </a>
        </li>

        <?php if ($nivel >= NIVEL_ADMIN): ?>
        <!-- SECCIONES SOLO ADMIN Y SUPER ADMIN -->
        <li class="menu-title">Administración</li>
        <li>
            <a href="<?= BASE_URL ?>modules/anomalias/listar.php" class="<?= $carpetaActual == 'anomalias' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i> Alertas de Anomalías
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/reportes/index.php" class="<?= $carpetaActual == 'reportes' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Reportes
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/usuarios/listar.php" class="<?= $carpetaActual == 'usuarios' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Usuarios
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/surtidores/listar.php" class="<?= $carpetaActual == 'surtidores' ? 'active' : '' ?>">
                <i class="bi bi-fuel-pump"></i> Surtidores
            </a>
        </li>
        <?php endif; ?>

        <?php if ($nivel >= NIVEL_SUPER_ADMIN): ?>
        <!-- SECCIONES EXCLUSIVAS SUPER ADMIN -->
        <li class="menu-title text-warning">Super Admin</li>
        <li>
            <a href="<?= BASE_URL ?>modules/auditoria/listar.php" class="<?= $carpetaActual == 'auditoria' ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i> Auditoría del Sistema
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/configuracion/index.php" class="<?= $carpetaActual == 'configuracion' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i> Configuración Global
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/backup/index.php" class="<?= $carpetaActual == 'backup' ? 'active' : '' ?>">
                <i class="bi bi-database-fill-gear"></i> Copias de Seguridad
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>modules/rfid/index.php" class="<?= $carpetaActual == 'rfid' ? 'active' : '' ?>">
                <i class="bi bi-upc-scan"></i> Control RFID de Cisternas
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-light btn-sm w-100">
            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
        </a>
    </div>
</nav>