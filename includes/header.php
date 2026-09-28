<?php
if (!isset($auth)) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/auth.php';
}
$currentUser = $auth->getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    
    <div class="main-content">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'sin_permiso'): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3">
                <i class="bi bi-shield-x"></i> No tiene permisos para acceder a esa sección.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>