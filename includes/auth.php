<?php
// includes/auth.php
// Sistema de autenticación y control de acceso

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Iniciar sesión
     */
    public function login($username, $password) {
        // ✅ CORREGIDO: dos parámetros distintos para username y email
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre_rol, r.nivel_jerarquia 
            FROM usuarios u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.username = :username OR u.email = :email
        ");
        $stmt->execute([
            ':username' => $username,
            ':email'    => $username
        ]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Usuario no encontrado'];
        }

        // Verificar si está bloqueado
        if ($user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
            return ['success' => false, 'message' => 'Cuenta bloqueada temporalmente. Intente más tarde.'];
        }

        // Verificar si está activo
        if (!$user['activo']) {
            return ['success' => false, 'message' => 'Usuario desactivado. Contacte al administrador.'];
        }

        // Verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            $this->registrarIntentoFallido($user['id_usuario']);
            return ['success' => false, 'message' => 'Contraseña incorrecta'];
        }

        // Login exitoso - Resetear intentos fallidos
        $this->resetearIntentos($user['id_usuario']);

        // Guardar en sesión
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nombre_completo'] = $user['nombre_completo'];
        $_SESSION['id_rol'] = $user['id_rol'];
        $_SESSION['nombre_rol'] = $user['nombre_rol'];
        $_SESSION['nivel_jerarquia'] = $user['nivel_jerarquia'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Actualizar último acceso
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id");
        $stmt->execute([':id' => $user['id_usuario']]);

        // Registrar en auditoría
        $this->registrarAuditoria($user['id_usuario'], 'LOGIN', 'usuarios', $user['id_usuario']);

        return ['success' => true, 'user' => $user];
    }

    /**
     * Cerrar sesión
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->registrarAuditoria($_SESSION['user_id'], 'LOGOUT', 'usuarios', $_SESSION['user_id']);
        }
        session_unset();
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function hasRole($role_id) {
        return isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == $role_id;
    }

    public function hasMinLevel($level) {
        return isset($_SESSION['nivel_jerarquia']) && $_SESSION['nivel_jerarquia'] >= $level;
    }

    public function isSuperAdmin() {
        return $this->hasRole(ROL_SUPER_ADMIN);
    }

    public function isAdmin() {
        return $this->hasMinLevel(NIVEL_ADMIN);
    }

    public function isOperador() {
        return $this->hasMinLevel(NIVEL_OPERADOR);
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    }

    public function requireSuperAdmin() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . 'dashboard.php?error=sin_permiso');
            exit();
        }
    }

    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: ' . BASE_URL . 'dashboard.php?error=sin_permiso');
            exit();
        }
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre_rol, r.nivel_jerarquia 
            FROM usuarios u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.id_usuario = :id
        ");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    }

    private function registrarIntentoFallido($user_id) {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = CASE 
                    WHEN intentos_fallidos + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE bloqueado_hasta
                END
            WHERE id_usuario = :id
        ");
        $stmt->execute([':id' => $user_id]);
    }

    private function resetearIntentos($user_id) {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET intentos_fallidos = 0, bloqueado_hasta = NULL 
            WHERE id_usuario = :id
        ");
        $stmt->execute([':id' => $user_id]);
    }

    public function registrarAuditoria($user_id, $accion, $tabla = null, $registro_id = null, $datos_ant = null, $datos_new = null) {
        $stmt = $this->db->prepare("
            INSERT INTO auditoria (id_usuario, accion, tabla_afectada, id_registro_afectado, datos_anteriores, datos_nuevos, ip_address, user_agent)
            VALUES (:uid, :accion, :tabla, :rid, :dant, :dnew, :ip, :ua)
        ");
        $stmt->execute([
            ':uid'    => $user_id,
            ':accion' => $accion,
            ':tabla'  => $tabla,
            ':rid'    => $registro_id,
            ':dant'   => $datos_ant ? json_encode($datos_ant) : null,
            ':dnew'   => $datos_new ? json_encode($datos_new) : null,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

$auth = new Auth();
?>