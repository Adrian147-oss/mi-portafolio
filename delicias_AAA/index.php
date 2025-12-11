<?php
include 'config.php';
session_start();
/* No cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
// Al inicio de cada archivo PHP, después de session_start()
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

// Inicializar carritos si no existen - CORREGIDO
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}
if (!isset($_SESSION['carrito_combos'])) {
    $_SESSION['carrito_combos'] = [];
}

// DEBUG removido para producción

// Procesar agregar producto al carrito - CORREGIDO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? max(1, (int)$_POST['cantidad']) : 0;
    
    // Validar que la cantidad sea mayor a 0
    if ($cantidad > 0) {
        $stmt = $conn->prepare("SELECT id, nombre, precio_bs, precio_usd FROM productos WHERE id = ? AND activo = 1");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            // Si ya existe en el carrito, sumar cantidad
            if (isset($_SESSION['carrito'][$producto_id])) {
                $_SESSION['carrito'][$producto_id]['cantidad'] += $cantidad;
            } else {
                $_SESSION['carrito'][$producto_id] = [
                    'nombre' => $producto['nombre'],
                    'precio_bs' => floatval($producto['precio_bs']),
                    'precio_usd' => floatval($producto['precio_usd']),
                    'cantidad' => $cantidad
                ];
            }
            
            // Redireccionar para evitar reenvío del formulario
            header('Location: index.php?agregado=1');
            exit;
        }
    }
}

// Procesar agregar combo al carrito - CORREGIDO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_combo'])) {
    $combo_id = isset($_POST['combo_id']) ? (int)$_POST['combo_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? max(1, (int)$_POST['cantidad']) : 0;
    
    // Validar que la cantidad sea mayor a 0
    if ($cantidad > 0) {
        $stmt = $conn->prepare("
            SELECT c.*, GROUP_CONCAT(CONCAT(cp.cantidad, ' x ', p.nombre) SEPARATOR ', ') as productos_incluidos
            FROM combos c
            LEFT JOIN combo_productos cp ON c.id = cp.combo_id
            LEFT JOIN productos p ON cp.producto_id = p.id
            WHERE c.id = ? AND c.activo = 1
            GROUP BY c.id
        ");
        $stmt->execute([$combo_id]);
        $combo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($combo) {
            // Los combos SOLO se pagan en dólares
            if (isset($_SESSION['carrito_combos'][$combo_id])) {
                $_SESSION['carrito_combos'][$combo_id]['cantidad'] += $cantidad;
            } else {
                $_SESSION['carrito_combos'][$combo_id] = [
                    'nombre' => $combo['nombre'],
                    'descripcion' => $combo['descripcion'],
                    'precio_usd' => floatval($combo['precio_usd']),
                    'cantidad' => $cantidad,
                    'productos_incluidos' => $combo['productos_incluidos']
                ];
            }
            
            // Redireccionar para evitar reenvío del formulario
            header('Location: index.php?agregado_combo=1');
            exit;
        }
    }
}

// Procesar eliminar producto del carrito
if (isset($_GET['eliminar'])) {
    $id = isset($_GET['eliminar']) ? (int)$_GET['eliminar'] : 0;
    if (isset($_SESSION['carrito'][$id])) {
        unset($_SESSION['carrito'][$id]);
    }
    header('Location: index.php');
    exit;
}

// Procesar eliminar combo del carrito
if (isset($_GET['eliminar_combo'])) {
    $id = isset($_GET['eliminar_combo']) ? (int)$_GET['eliminar_combo'] : 0;
    if (isset($_SESSION['carrito_combos'][$id])) {
        unset($_SESSION['carrito_combos'][$id]);
    }
    header('Location: index.php');
    exit;
}

// Procesar vaciar carrito completo
if (isset($_GET['vaciar'])) {
    $_SESSION['carrito'] = [];
    $_SESSION['carrito_combos'] = [];
    header('Location: index.php');
    exit;
}

// Mostrar combos disponibles
$stmt = $conn->query("SELECT id, nombre, descripcion, precio_usd FROM combos WHERE activo = 1");
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
unset($combo);

// Calcular total de items en carrito
$total_items = count($_SESSION['carrito']) + count($_SESSION['carrito_combos']);
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
    
    <title>Sistema Delicias Triple AAA</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="logo.png" type="image/png">
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">

    <!-- Icons para diferentes dispositivos -->
    <link rel="icon" type="image/png" href="icon-192.png">
    <link rel="apple-touch-icon" href="icon-192.png">
</head>
<body>
    <div class="container">
        <header>
            <button id="installButton" class="btn install-btn">
                📱 Instalar App
            </button>
            <h1>Delicias Triple AAA</h1>
            <img src="logo.png" alt="logo">
            <nav>
                <a href="index.php">Menú</a>
                <a href="facturar.php">Ver Factura</a>
                <a href="editar_productos.php">Editar Productos</a>
                <a href="combos.php">Gestión de Combos</a>
                <a href="cierre_dia.php">Cierre del Día</a>
            </nav>
        </header>

        <main>
            <!-- Mensajes de confirmación -->
            <?php if (isset($_GET['agregado'])): ?>
                <div class="alert success">
                    <p>✅ Producto agregado al carrito correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['agregado_combo'])): ?>
                <div class="alert success">
                    <p>✅ Combo agregado al carrito correctamente.</p>
                </div>
            <?php endif; ?>

            <h2>Menú Triple AAA</h2>
            
            <div class="carrito-info">
                <h3>Carrito de Compras</h3>
                <p>Productos en carrito: <?php echo $total_items; ?></p>
                <?php if ($total_items > 0): ?>
                    <a href="index.php?vaciar=true" class="btn vaciar">Vaciar Carrito</a>
                    <a href="facturar.php" class="btn facturar">Generar Factura</a>
                <?php endif; ?>
            </div>

            <!-- Productos Individuales -->
            <div class="productos-grid">
                <?php
                $stmt = $conn->query("SELECT id, nombre, precio_bs, precio_usd FROM productos WHERE activo = 1");
                while ($producto = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <div class="producto-card">
                    <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                    <p class="precio">Precio: <?php echo number_format($producto['precio_bs'], 2); ?> Bs / $<?php echo number_format($producto['precio_usd'], 2); ?> USD</p>
                    <form method="POST" action="index.php">
                        <input type="hidden" name="producto_id" value="<?php echo $producto['id']; ?>">
                        <div class="cantidad-input">
                            <label for="cantidad_<?php echo $producto['id']; ?>">Cantidad:</label>
                            <input type="number" id="cantidad_<?php echo $producto['id']; ?>" name="cantidad" value="1" min="1" required>
                        </div>
                        <button type="submit" name="agregar" class="btn agregar">Agregar al Carrito</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Combos Especiales -->
            <?php if (!empty($combos)): ?>
            <div class="combos-section">
                <h2>Combos Especiales (Solo Dólares)</h2>
                <div class="productos-grid">
                    <?php foreach ($combos as $combo): ?>
                    <div class="producto-card combo-card">
                        <h3><?php echo htmlspecialchars($combo['nombre']); ?></h3>
                        <?php if ($combo['descripcion']): ?>
                            <p class="descripcion"><?php echo htmlspecialchars($combo['descripcion']); ?></p>
                        <?php endif; ?>
                        <p class="precio">Precio: $<?php echo number_format($combo['precio_usd'], 2); ?> USD</p>
                        
                        <div class="combo-detalle">
                            <p><strong>Incluye:</strong></p>
                            <ul>
                                <?php foreach ($combo['productos'] as $producto): ?>
                                <li><?php echo $producto['cantidad']; ?> x <?php echo htmlspecialchars($producto['nombre']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <form method="POST" action="index.php">
                            <input type="hidden" name="combo_id" value="<?php echo $combo['id']; ?>">
                            <div class="cantidad-input">
                                <label for="cantidad_combo_<?php echo $combo['id']; ?>">Cantidad:</label>
                                <input type="number" id="cantidad_combo_<?php echo $combo['id']; ?>" name="cantidad" value="1" min="1" required>
                            </div>
                            <button type="submit" name="agregar_combo" class="btn agregar">Agregar al Carrito</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Mostrar contenido del carrito -->
            <?php 
            // Calcular totales para mostrar
            $total_bs = 0;
            $total_usd = 0;
            $total_combos_usd = 0;
            
            foreach ($_SESSION['carrito'] as $item) {
                $total_bs += $item['precio_bs'] * $item['cantidad'];
                $total_usd += $item['precio_usd'] * $item['cantidad'];
            }
            
            foreach ($_SESSION['carrito_combos'] as $combo) {
                $total_combos_usd += $combo['precio_usd'] * $combo['cantidad'];
            }
            ?>

            <!-- Detalle del Carrito - Productos Individuales -->
            <?php if (!empty($_SESSION['carrito'])): ?>
            <div class="carrito-detalle">
                <h3>Productos en el Carrito</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario (Bs)</th>
                                <th>Precio Unitario (USD)</th>
                                <th>Subtotal (Bs)</th>
                                <th>Subtotal (USD)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                                <td><?php echo $item['cantidad']; ?></td>
                                <td><?php echo number_format($item['precio_bs'], 2); ?> Bs</td>
                                <td>$<?php echo number_format($item['precio_usd'], 2); ?> USD</td>
                                <td><?php echo number_format($item['precio_bs'] * $item['cantidad'], 2); ?> Bs</td>
                                <td>$<?php echo number_format($item['precio_usd'] * $item['cantidad'], 2); ?> USD</td>
                                <td><a href="index.php?eliminar=<?php echo $id; ?>" class="btn eliminar">Eliminar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="total-label">TOTAL PRODUCTOS:</td>
                                <td class="total"><?php echo number_format($total_bs, 2); ?> Bs</td>
                                <td class="total">$<?php echo number_format($total_usd, 2); ?> USD</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Detalle del Carrito - Combos -->
            <?php if (!empty($_SESSION['carrito_combos'])): ?>
            <div class="carrito-detalle carrito-combos">
                <h3>Combos en el Carrito (Solo Dólares)</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Combo</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario (USD)</th>
                                <th>Subtotal (USD)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['carrito_combos'] as $id => $combo): ?>
                            <tr class="combo-en-carrito">
                                <td><strong><?php echo htmlspecialchars($combo['nombre']); ?></strong></td>
                                <td>
                                    <small><?php echo htmlspecialchars($combo['descripcion']); ?></small><br>
                                    <em>Incluye: <?php echo htmlspecialchars($combo['productos_incluidos']); ?></em>
                                </td>
                                <td><?php echo $combo['cantidad']; ?></td>
                                <td>$<?php echo number_format($combo['precio_usd'], 2); ?> USD</td>
                                <td>$<?php echo number_format($combo['precio_usd'] * $combo['cantidad'], 2); ?> USD</td>
                                <td><a href="index.php?eliminar_combo=<?php echo $id; ?>" class="btn eliminar">Eliminar</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="total-label">TOTAL COMBOS:</td>
                                <td class="total">$<?php echo number_format($total_combos_usd, 2); ?> USD</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Total General -->
            <?php if (!empty($_SESSION['carrito']) || !empty($_SESSION['carrito_combos'])): ?>
            <div class="resumen-total">
                <h3>Resumen General del Pedido</h3>
                <div class="totales">
                    <?php 
                    $total_general_usd = $total_usd + $total_combos_usd;
                    ?>
                    <?php if (!empty($_SESSION['carrito'])): ?>
                    <div class="total-card">
                        <h3>Total Productos (Bs)</h3>
                        <p class="monto"><?php echo number_format($total_bs, 2); ?> Bs</p>
                    </div>
                    <div class="total-card">
                        <h3>Total Productos (USD)</h3>
                        <p class="monto">$<?php echo number_format($total_usd, 2); ?> USD</p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['carrito_combos'])): ?>
                    <div class="total-card">
                        <h3>Total Combos (USD)</h3>
                        <p class="monto">$<?php echo number_format($total_combos_usd, 2); ?> USD</p>
                    </div>
                    <?php endif; ?>
                    <div class="total-card">
                        <h3>Total General (USD)</h3>
                        <p class="monto">$<?php echo number_format($total_general_usd, 2); ?> USD</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Mostrar mensajes temporales
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 1000);
                }, 3000);
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