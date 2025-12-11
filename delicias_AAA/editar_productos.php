<?php
include 'config.php';
session_start();
// No cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
/* Al inicio de cada archivo PHP, después de session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Si usas HTTPS
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);*/

// Obtener productos
$stmt = $conn->query("SELECT id, nombre, precio_bs, precio_usd, activo FROM productos");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar agregar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_producto'])) {
    $nombre = trim($_POST['nombre']);
    $precio_bs = isset($_POST['precio_bs']) ? (float)$_POST['precio_bs'] : 0.0;
    $precio_usd = isset($_POST['precio_usd']) ? (float)$_POST['precio_usd'] : 0.0;
    
    $stmt = $conn->prepare("INSERT INTO productos (nombre, precio_bs, precio_usd) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $precio_bs, $precio_usd]);
    
    header('Location: editar_productos.php');
    exit;
}

// Procesar actualizar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_producto'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = trim($_POST['nombre']);
    $precio_bs = isset($_POST['precio_bs']) ? (float)$_POST['precio_bs'] : 0.0;
    $precio_usd = isset($_POST['precio_usd']) ? (float)$_POST['precio_usd'] : 0.0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE productos SET nombre = ?, precio_bs = ?, precio_usd = ?, activo = ? WHERE id = ?");
    $stmt->execute([$nombre, $precio_bs, $precio_usd, $activo, $id]);
    
    header('Location: editar_productos.php');
    exit;
}

// Procesar eliminar producto
if (isset($_GET['eliminar'])) {
    $id = isset($_GET['eliminar']) ? (int)$_GET['eliminar'] : 0;
    
    // Verificar si el producto tiene ventas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ventas WHERE producto_id = ?");
    $stmt->execute([$id]);
    $ventas_count = $stmt->fetchColumn();
    
    if ($ventas_count == 0) {
        $stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // Si tiene ventas, solo desactivar
        $stmt = $conn->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    header('Location: editar_productos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#8B4513">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeliciasApp">
    <meta name="description" content="Sistema de gestión de ventas para comida rápida">
    <meta name="keywords" content="comida, ventas, gestión, restaurante">
    
    <title>Editar Productos - Sistema Delicias Triple AAA</title>
    <link rel="stylesheet" href="styles.css">
    <Link rel="icon" href="logo.png" type="image/png"></Link>
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Icons para diferentes dispositivos -->
    <link rel="icon" type="image/png" href="icon-192.png">
    <link rel="apple-touch-icon" href="icon-192.png">
    
</head>
<body>
    <div class="container">
        <header>
            <button id="installButton" class="btn install-btn" style="display: none;">
                📱 Instalar App
            </button>
            <h1>Editar Productos</h1>
            <img src="logo.png" alt="logo">
            <nav>
                <a href="index.php">Volver al Menú</a>
                <a href="factura.php">Ver Factura</a>
                <a href="combos.php">Gestión de Combos</a>
                <a href="cierre_dia.php">Cierre del Día</a>
            </nav>
        </header>

        <main>
            <div class="form-section">
                <h2>Agregar Nuevo Producto</h2>
                <form method="POST" class="producto-form">
                    <div class="form-group">
                        <label for="nombre">Nombre del Producto:</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="precio_bs">Precio en Bolívares:</label>
                        <input type="number" id="precio_bs" name="precio_bs" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="precio_usd">Precio en Dólares:</label>
                        <input type="number" id="precio_usd" name="precio_usd" step="0.01" min="0" required>
                    </div>
                    <button type="submit" name="agregar_producto" class="btn agregar">Agregar Producto</button>
                </form>
            </div>

            <div class="productos-lista">
                <h2>Productos Existentes</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Precio (Bs)</th>
                            <th>Precio (USD)</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                <td><?php echo $producto['id']; ?></td>
                                <td>
                                    <input type="text" name="nombre" value="<?php echo $producto['nombre']; ?>" required>
                                </td>
                                <td>
                                    <input type="number" name="precio_bs" value="<?php echo $producto['precio_bs']; ?>" step="0.01" min="0" required>
                                </td>
                                <td>
                                    <input type="number" name="precio_usd" value="<?php echo $producto['precio_usd']; ?>" step="0.01" min="0" required>
                                </td>
                                <td>
                                    <input type="checkbox" name="activo" <?php echo $producto['activo'] ? 'checked' : ''; ?>> Activo
                                </td>
                                <td class="acciones">
                                    <button type="submit" name="actualizar_producto" class="btn actualizar">Actualizar</button>
                                    <a href="editar_productos.php?eliminar=<?php echo $producto['id']; ?>" class="btn eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este producto?')">Eliminar</a>
                                </td>
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <!-- Service Worker Registration -->
    <script>
    // Registrar el Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
        .then(function(registration) {
        console.log('Service Worker registrado con éxito:', registration.scope);
        })
        .catch(function(error) {
        console.log('Error al registrar el Service Worker:', error);
        });
    });
}

    // Detectar si la app está en modo standalone
    function isInStandaloneMode() {
        return (window.matchMedia('(display-mode: standalone)').matches) ||
        (window.navigator.standalone) ||
        document.referrer.includes('android-app://');
}

    // Mostrar botón de instalación si es compatible
    let deferredPrompt;
    const installButton = document.getElementById('installButton');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (installButton) {
            installButton.style.display = 'block';
    }
});

    if (installButton) {
        installButton.addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                installButton.style.display = 'none';
            }
            deferredPrompt = null;
        }
        });
    }
    </script>
</body>
</html>