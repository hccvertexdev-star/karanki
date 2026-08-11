<?php
/**
 * ============================================================
 * INSTALADOR AUTOMÁTICO
 * Ejecuta este archivo UNA VEZ desde el navegador:
 *   http://tudominio.com/install.php
 *
 * Crea las tablas, inserta datos iniciales y el usuario admin.
 * ¡ELIMINA este archivo después de instalar por seguridad!
 * ============================================================
 */
require_once __DIR__ . '/config/config.php';

// Helper inline para escape HTML dentro del instalador
if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$mensajes = [];
$error = null;
$instalado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminNombre = trim($_POST['admin_nombre'] ?? 'Administrador');
    $adminEmail  = trim($_POST['admin_email'] ?? '');
    $adminPass   = $_POST['admin_pass'] ?? '';

    try {
        // Conectar SIN base de datos para poder crearla
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        $mensajes[] = "Base de datos '" . DB_NAME . "' verificada/creada.";

        // Ejecutar schema
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        $pdo->exec($schema);
        $mensajes[] = "Tablas creadas correctamente.";

        // Ejecutar seed (datos iniciales) - ignorar duplicados
        $seed = file_get_contents(__DIR__ . '/database/seed.sql');
        // Quitar el INSERT del usuario placeholder, lo crearemos abajo
        $seed = preg_replace('/INSERT INTO `usuarios`.*?;/s', '', $seed, 1);
        try {
            $pdo->exec($seed);
            $mensajes[] = "Datos iniciales (categorías, menús, emprendimientos, mapa) insertados.";
        } catch (PDOException $e) {
            $mensajes[] = "Datos iniciales ya existían (omitido).";
        }

        // Crear / actualizar usuario admin con contraseña real
        if ($adminEmail && $adminPass) {
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo)
                                   VALUES (?, ?, ?, 'superadmin', 1)
                                   ON DUPLICATE KEY UPDATE password = VALUES(password), nombre = VALUES(nombre)");
            $stmt->execute([$adminNombre, $adminEmail, $hash]);
            $mensajes[] = "Usuario administrador creado: <strong>$adminEmail</strong>";
        }

        $instalado = true;
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalador - Plan de Vida Karanki</title>
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',system-ui,sans-serif}
body{background:linear-gradient(135deg,#1B3A2A,#2E7D32);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.3);max-width:560px;width:100%;overflow:hidden}
.head{background:linear-gradient(135deg,#2E7D32,#FFB300);color:#fff;padding:32px;text-align:center}
.head i{font-size:48px;margin-bottom:12px}
.head h1{font-size:24px}
.body{padding:32px}
label{display:block;font-weight:600;margin:16px 0 6px;color:#1B3A2A}
input{width:100%;padding:13px 16px;border:2px solid #e0e0e0;border-radius:10px;font-size:15px;transition:.3s}
input:focus{outline:none;border-color:#2E7D32}
button{width:100%;margin-top:24px;padding:15px;background:linear-gradient(135deg,#2E7D32,#43A047);color:#fff;border:none;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;transition:.3s}
button:hover{transform:translateY(-2px);box-shadow:0 10px 25px rgba(46,125,50,.4)}
.msg{padding:12px 16px;border-radius:10px;margin-bottom:10px;font-size:14px}
.msg.ok{background:#e8f5e9;color:#2e7d32;border-left:4px solid #2e7d32}
.msg.err{background:#ffebee;color:#c62828;border-left:4px solid #c62828}
.success{text-align:center;padding:20px}
.success i{font-size:64px;color:#2e7d32;margin-bottom:16px}
.btn-link{display:inline-block;margin-top:16px;padding:13px 28px;background:#2E7D32;color:#fff;text-decoration:none;border-radius:10px;font-weight:700}
.warn{background:#fff3e0;color:#e65100;padding:12px;border-radius:8px;font-size:13px;margin-top:16px}
small{color:#777;font-size:12px}
</style>
</head>
<body>
<div class="card">
  <div class="head">
    <i class="fas fa-feather-alt"></i>
    <h1>Instalador Karanki</h1>
    <p>Plan de Vida · Pueblo Kichwa Karanki</p>
  </div>
  <div class="body">
    <?php if ($error): ?>
        <div class="msg err"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <?php foreach ($mensajes as $m): ?>
        <div class="msg ok"><i class="fas fa-check-circle"></i> <?= $m ?></div>
    <?php endforeach; ?>

    <?php if ($instalado): ?>
        <div class="success">
            <i class="fas fa-check-circle"></i>
            <h2>¡Instalación completada!</h2>
            <p>Tu sitio está listo para usarse.</p>
            <a href="admin/login.php" class="btn-link"><i class="fas fa-sign-in-alt"></i> Ir al Panel Admin</a>
            <div class="warn">
                <i class="fas fa-shield-alt"></i> <strong>IMPORTANTE:</strong> Elimina el archivo
                <code>install.php</code> de tu servidor ahora por seguridad.
            </div>
        </div>
    <?php else: ?>
        <form method="post">
            <p style="color:#555;margin-bottom:8px">Verifica primero los datos de conexión en <code>config/config.php</code>, luego crea tu cuenta de administrador:</p>
            <label>Nombre del administrador</label>
            <input type="text" name="admin_nombre" value="Administrador Karanki" required>
            <label>Email (para iniciar sesión)</label>
            <input type="email" name="admin_email" placeholder="admin@karanki.org" required>
            <label>Contraseña</label>
            <input type="password" name="admin_pass" placeholder="Mínimo 6 caracteres" minlength="6" required>
            <button type="submit"><i class="fas fa-rocket"></i> Instalar Sistema</button>
            <small style="display:block;margin-top:12px;text-align:center">Esto creará la base de datos, las tablas y los datos de ejemplo.</small>
        </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
