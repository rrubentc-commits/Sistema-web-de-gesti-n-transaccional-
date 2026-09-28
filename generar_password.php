<?php
// generar_password.php - ACTUALIZA DIRECTAMENTE EN LA BD
// ¡ELIMINAR DESPUÉS DE USAR!

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

$passwords = [
    'superadmin' => 'dario123',
    'admin'      => 'dario123',
    'operador'   => 'dario123'
];

echo "<h2>Actualizando contraseñas en la base de datos...</h2>";

foreach ($passwords as $user => $pass) {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE username = :user");
    $stmt->execute([':hash' => $hash, ':user' => $user]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ <strong>$user</strong> actualizado → Password: <strong>$pass</strong><br>";
    } else {
        echo "⚠️ <strong>$user</strong> no encontrado en la BD<br>";
    }
}

echo "<hr>";
echo "<h3>Verificación:</h3>";

$stmt = $db->query("SELECT username, password_hash FROM usuarios");
foreach ($stmt->fetchAll() as $u) {
    $ok = password_verify('dario123', $u['password_hash']) ? '✅ OK' : '❌ FALLA';
    echo "{$u['username']}: $ok<br>";
}

echo "<hr>";
echo "<a href='index.php'>→ Ir al login</a>";
?>