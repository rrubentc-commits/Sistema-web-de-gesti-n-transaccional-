<?php
// config/config.php
// Configuración general del sistema

session_start();

// Zona horaria de Bolivia
date_default_timezone_set('America/La_Paz');

// Constantes del sistema
define('SITE_NAME', 'Sistema de Gestión de Combustibles');
define('SITE_URL', 'http://localhost/combustible/');
define('SITE_VERSION', '1.0.0');

// Rutas
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', SITE_URL);

// Roles del sistema
define('ROL_SUPER_ADMIN', 1);
define('ROL_ADMIN', 2);
define('ROL_OPERADOR', 3);

// Niveles de jerarquía
define('NIVEL_SUPER_ADMIN', 3);
define('NIVEL_ADMIN', 2);
define('NIVEL_OPERADOR', 1);

// Función de depuración
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}
?>