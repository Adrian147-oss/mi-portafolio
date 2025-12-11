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
]);
*/
// Obtener productos para asignar a combos
$stmt = $conn->query("SELECT * FROM productos WHERE activo = 1");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar agregar combo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_combo'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio_usd = $_POST['precio_usd'];
    $productos_seleccionados = $_POST['productos'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Insertar el combo
        $stmt = $conn->prepare("INSERT INTO combos (nombre, descripcion, precio_usd) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $descripcion, $precio_usd]);
        $combo_id = $conn->lastInsertId();
        
        // Insertar los productos del combo
        foreach ($productos_seleccionados as $producto_id => $cantidad) {
            if ($cantidad > 0) {
                $stmt = $conn->prepare("INSERT INTO combo_productos (combo_id, producto_id, cantidad) VALUES (?, ?, ?)");
                $stmt->execute([$combo_id, $producto_id, $cantidad]);
            }
        }
        
        $conn->commit();
        $combo_agregado = true;
    } catch (Exception $e) {
        $conn->rollBack();
        $combo_agregado = false;
        $error = $e->getMessage();
    }
}

// Procesar actualizar combo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_combo'])) {
    $combo_id = $_POST['combo_id'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio_usd = $_POST['precio_usd'];
    $productos_seleccionados = $_POST['productos'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Actualizar el combo
        $stmt = $conn->prepare("UPDATE combos SET nombre = ?, descripcion = ?, precio_usd = ? WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $precio_usd, $combo_id]);
        
        // Eliminar los productos actuales del combo
        $stmt = $conn->prepare("DELETE FROM combo_productos WHERE combo_id = ?");
        $stmt->execute([$combo_id]);
        
        // Insertar los nuevos productos del combo
        foreach ($productos_seleccionados as $producto_id => $cantidad) {
            if ($cantidad > 0) {
                $stmt = $conn->prepare("INSERT INTO combo_productos (combo_id, producto_id, cantidad) VALUES (?, ?, ?)");
                $stmt->execute([$combo_id, $producto_id, $cantidad]);
            }
        }
        
        $conn->commit();
        $combo_actualizado = true;
    } catch (Exception $e) {
        $conn->rollBack();
        $combo_actualizado = false;
        $error = $e->getMessage();
    }
}

// Procesar eliminar combo
if (isset($_GET['eliminar'])) {
    $combo_id = $_GET['eliminar'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM combos WHERE id = ?");
        $stmt->execute([$combo_id]);
        $combo_eliminado = true;
    } catch (Exception $e) {
        $combo_eliminado = false;
        $error = $e->getMessage();
    }
}

// Obtener combos existentes
$stmt = $conn->query("SELECT * FROM combos WHERE activo = 1");
$combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Para cada combo, obtener sus productos
foreach ($combos as &$combo) {
    $stmt = $conn->prepare("
        SELECT cp.producto_id, cp.cantidad, p.nombre 
        FROM combo_productos cp 
        JOIN productos p ON cp.producto_id = p.id 
        WHERE cp.combo_id = ?
    ");
    $stmt->execute([$combo['id']]);
    $combo['productos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($combo); // Romper la referencia
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
    
    <title>Gestión de Combos - Sistema de Comida Rápida</title>
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
            <h1>Gestión de Combos</h1>
            <img src="logo.png" alt="logo">
            <nav>
                <a href="index.php">Volver al Menú</a>
                <a href="editar_productos.php">Editar Productos</a>
                <a href="cierre_dia.php">Cierre del Día</a>
            </nav>
        </header>

        <main>
            <?php if (isset($combo_agregado) && $combo_agregado): ?>
                <div class="alert success">
                    <p>Combo agregado exitosamente.</p>
                </div>
            <?php elseif (isset($combo_agregado) && !$combo_agregado): ?>
                <div class="alert error">
                    <p>Error al agregar el combo: <?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($combo_actualizado) && $combo_actualizado): ?>
                <div class="alert success">
                    <p>Combo actualizado exitosamente.</p>
                </div>
            <?php elseif (isset($combo_actualizado) && !$combo_actualizado): ?>
                <div class="alert error">
                    <p>Error al actualizar el combo: <?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($combo_eliminado) && $combo_eliminado): ?>
                <div class="alert success">
                    <p>Combo eliminado exitosamente.</p>
                </div>
            <?php elseif (isset($combo_eliminado) && !$combo_eliminado): ?>
                <div class="alert error">
                    <p>Error al eliminar el combo: <?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <h2>Agregar Nuevo Combo</h2>
                <form method="POST" class="combo-form">
                    <div class="form-group">
                        <label for="nombre">Nombre del Combo:</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción:</label>
                        <textarea id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="precio_usd">Precio en Dólares:</label>
                        <input type="number" id="precio_usd" name="precio_usd" step="0.01" min="0" required>
                    </div>
                    
                    <div class="productos-combo">
                        <h3>Productos del Combo</h3>
                        <p>Selecciona los productos y la cantidad de cada uno que incluye el combo:</p>
                        
                        <div class="lista-productos">
                            <?php foreach ($productos as $producto): ?>
                            <div class="producto-combo">
                                <label>
                                    <input type="checkbox" name="productos[<?php echo $producto['id']; ?>]" value="1" class="check-producto">
                                    <?php echo $producto['nombre']; ?>
                                </label>
                                <input type="number" name="productos[<?php echo $producto['id']; ?>]" value="0" min="0" class="cantidad-producto" disabled>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" name="agregar_combo" class="btn agregar">Agregar Combo</button>
                </form>
            </div>

            <div class="combos-lista">
                <h2>Combos Existentes</h2>
                
                <?php if (empty($combos)): ?>
                    <p>No hay combos registrados.</p>
                <?php else: ?>
                    <?php foreach ($combos as $combo): ?>
                    <div class="combo-card">
                        <form method="POST" class="combo-form">
                            <input type="hidden" name="combo_id" value="<?php echo $combo['id']; ?>">
                            
                            <div class="form-group">
                                <label for="nombre_<?php echo $combo['id']; ?>">Nombre del Combo:</label>
                                <input type="text" id="nombre_<?php echo $combo['id']; ?>" name="nombre" value="<?php echo $combo['nombre']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="descripcion_<?php echo $combo['id']; ?>">Descripción:</label>
                                <textarea id="descripcion_<?php echo $combo['id']; ?>" name="descripcion" rows="3"><?php echo $combo['descripcion']; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="precio_usd_<?php echo $combo['id']; ?>">Precio en Dólares:</label>
                                <input type="number" id="precio_usd_<?php echo $combo['id']; ?>" name="precio_usd" value="<?php echo $combo['precio_usd']; ?>" step="0.01" min="0" required>
                            </div>
                            
                            <div class="productos-combo">
                                <h3>Productos del Combo</h3>
                                
                                <div class="lista-productos">
                                    <?php foreach ($productos as $producto): 
                                        $cantidad = 0;
                                        foreach ($combo['productos'] as $prod_combo) {
                                            if ($prod_combo['producto_id'] == $producto['id']) {
                                                $cantidad = $prod_combo['cantidad'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <div class="producto-combo">
                                        <label>
                                            <input type="checkbox" name="productos[<?php echo $producto['id']; ?>]" value="1" class="check-producto" <?php echo $cantidad > 0 ? 'checked' : ''; ?>>
                                            <?php echo $producto['nombre']; ?>
                                        </label>
                                        <input type="number" name="productos[<?php echo $producto['id']; ?>]" value="<?php echo $cantidad; ?>" min="0" class="cantidad-producto" <?php echo $cantidad > 0 ? '' : 'disabled'; ?>>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="acciones-combo">
                                <button type="submit" name="actualizar_combo" class="btn actualizar">Actualizar Combo</button>
                                <a href="combos.php?eliminar=<?php echo $combo['id']; ?>" class="btn eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este combo?')">Eliminar Combo</a>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Habilitar/deshabilitar inputs de cantidad cuando se marca/desmarca el checkbox
            document.querySelectorAll('.check-producto').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const cantidadInput = this.closest('.producto-combo').querySelector('.cantidad-producto');
                    if (this.checked) {
                        cantidadInput.disabled = false;
                        cantidadInput.value = cantidadInput.value || 1;
                    } else {
                        cantidadInput.disabled = true;
                        cantidadInput.value = 0;
                    }
                });
            });
        });
    </script>
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