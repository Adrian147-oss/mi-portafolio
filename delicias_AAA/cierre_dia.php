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

// Establecer zona horaria correcta
date_default_timezone_set('America/Caracas');

// Obtener fecha (hoy o fecha seleccionada) - CORREGIDO
$fecha_consulta = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Obtener ventas del día seleccionado - ACTUALIZADO PARA INCLUIR COMBOS
$stmt = $conn->prepare("
    SELECT 
        v.id, v.producto_id, v.combo_id, v.cantidad, v.total_bs, v.total_usd, v.metodo_pago, v.monto_pagado_bs, v.monto_pagado_usd, v.fecha,
        COALESCE(p.nombre, CONCAT('COMBO: ', c.nombre)) as nombre_producto,
        CASE 
            WHEN v.combo_id IS NOT NULL THEN 'Combo'
            ELSE 'Producto Individual'
        END as tipo_producto,
        c.nombre as nombre_combo,
        c.descripcion as descripcion_combo
    FROM ventas v
    LEFT JOIN productos p ON v.producto_id = p.id
    LEFT JOIN combos c ON v.combo_id = c.id
    WHERE DATE(v.fecha) = ?
    ORDER BY v.fecha DESC
");
$stmt->execute([$fecha_consulta]);
$ventas_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales del día seleccionado - CORREGIDO: SEPARADO POR MONEDA
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN metodo_pago = 'dolares' THEN monto_pagado_usd ELSE 0 END) as total_usd_recibido,
        SUM(CASE WHEN metodo_pago != 'dolares' THEN monto_pagado_bs ELSE 0 END) as total_bs_recibido,
        SUM(CASE WHEN metodo_pago = 'dolares' THEN 1 ELSE 0 END) as ventas_dolares,
        SUM(CASE WHEN metodo_pago != 'dolares' THEN 1 ELSE 0 END) as ventas_bolivares,
        COUNT(DISTINCT id) as total_ventas
    FROM ventas 
    WHERE DATE(fecha) = ?
");
$stmt->execute([$fecha_consulta]);
$totales_dia = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular totales por método de pago - CORREGIDO: SEPARADO POR MONEDA
$stmt = $conn->prepare("
    SELECT 
        metodo_pago,
        COUNT(*) as cantidad_ventas,
        SUM(CASE WHEN metodo_pago = 'dolares' THEN monto_pagado_usd ELSE 0 END) as monto_usd,
        SUM(CASE WHEN metodo_pago != 'dolares' THEN monto_pagado_bs ELSE 0 END) as monto_bs
    FROM ventas 
    WHERE DATE(fecha) = ?
    GROUP BY metodo_pago
");
$stmt->execute([$fecha_consulta]);
$totales_metodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener resumen por producto/combo - CORREGIDO: USAR MONEDA REAL DE PAGO
$stmt = $conn->prepare("
    SELECT 
        COALESCE(p.nombre, c.nombre) as nombre,
        CASE 
            WHEN v.combo_id IS NOT NULL THEN 'Combo'
            ELSE 'Producto'
        END as tipo,
        SUM(v.cantidad) as total_cantidad,
        SUM(CASE WHEN v.metodo_pago = 'dolares' THEN v.monto_pagado_usd ELSE v.monto_pagado_bs END) as total_moneda_pago,
        v.metodo_pago
    FROM ventas v
    LEFT JOIN productos p ON v.producto_id = p.id
    LEFT JOIN combos c ON v.combo_id = c.id
    WHERE DATE(v.fecha) = ?
    GROUP BY COALESCE(p.nombre, c.nombre), v.metodo_pago, 
    CASE WHEN v.combo_id IS NOT NULL THEN 'Combo' ELSE 'Producto' END
    ORDER BY total_cantidad DESC
");
$stmt->execute([$fecha_consulta]);
$resumen_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales manualmente como respaldo
$total_bs_manual = 0;
$total_usd_manual = 0;
foreach($ventas_dia as $venta) {
    if($venta['metodo_pago'] == 'dolares') {
        $total_usd_manual += $venta['monto_pagado_usd'] ?? $venta['total_usd'] ?? 0;
    } else {
        $total_bs_manual += $venta['monto_pagado_bs'] ?? $venta['total_bs'] ?? 0;
    }
}

// Usar totales calculados manualmente si los de la consulta están vacíos
if (empty($totales_dia['total_bs_recibido']) && empty($totales_dia['total_usd_recibido'])) {
    $totales_dia['total_bs_recibido'] = $total_bs_manual;
    $totales_dia['total_usd_recibido'] = $total_usd_manual;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- PWA Meta Tags -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#8B4513">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="DeliciasApp">
    <meta name="description" content="Sistema de gestión de ventas para comida rápida">
    <meta name="keywords" content="comida, ventas, gestión, restaurante">

    <title>Cierre del Día - Sistema de Comida Rápida</title>
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
            <button id="installButton" class="btn install-btn" style="display: none;">
                📱 Instalar App
            </button>
            <h1>Cierre del Día</h1>
            <img src="logo.png" alt="logo" class="logo">
            <nav>
                <a href="index.php">Volver al Menú</a>
                <a href="factura.php">Ver Factura</a>
                <a href="combos.php">Gestión de Combos</a>
                <a href="editar_productos.php">Editar Productos</a>
            </nav>
        </header>

        <main>
            <!-- Selector de Fecha -->
            <div class="selector-fecha">
                <h3>Consulta de Cierre</h3>
                <form method="GET" class="fecha-form">
                    <div class="form-group">
                        <label for="fecha">Seleccionar Fecha:</label>
                        <input type="date" id="fecha" name="fecha" 
                               value="<?php echo $fecha_consulta; ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button type="submit" class="btn">Consultar</button>
                    <a href="cierre_dia.php" class="btn">Hoy</a>
                    
                    <!-- Botón PDF -->
                    <a href="generar_pdf.php?fecha=<?php echo $fecha_consulta; ?>" 
                       target="_blank" 
                       class="btn pdf">
                        📄 Generar PDF
                    </a>
                </form>
                
                <div class="info-consulta">
                    <p><strong>Mostrando datos del:</strong> <?php echo date('d/m/Y', strtotime($fecha_consulta)); ?></p>
                    <p><strong>Total de ventas:</strong> <?php echo $totales_dia['total_ventas'] ?? 0; ?></p>
                    <p><strong>Hora del servidor:</strong> <?php echo date('H:i:s'); ?></p>
                </div>
            </div>

            <div class="resumen-dia">
                <h2>Resumen del Día: <?php echo date('d/m/Y', strtotime($fecha_consulta)); ?></h2>
                
                <div class="totales">
                    <div class="total-card">
                        <h3>Total de Ventas</h3>
                        <p class="monto"><?php echo $totales_dia['total_ventas'] ?? 0; ?> ventas</p>
                    </div>
                    <div class="total-card">
                        <h3>Ventas en Bolívares</h3>
                        <p class="monto"><?php echo $totales_dia['ventas_bolivares'] ?? 0; ?> ventas</p>
                    </div>
                    <div class="total-card">
                        <h3>Ventas en Dólares</h3>
                        <p class="monto"><?php echo $totales_dia['ventas_dolares'] ?? 0; ?> ventas</p>
                    </div>
                    <div class="total-card">
                        <h3>Total Recibido en Bs</h3>
                        <p class="monto"><?php echo number_format($totales_dia['total_bs_recibido'] ?? 0, 2); ?> Bs</p>
                    </div>
                    <div class="total-card">
                        <h3>Total Recibido en USD</h3>
                        <p class="monto">$<?php echo number_format($totales_dia['total_usd_recibido'] ?? 0, 2); ?> USD</p>
                    </div>
                </div>

                <div class="metodos-pago-resumen">
                    <h3>Resumen por Método de Pago</h3>
                    <?php if (!empty($totales_metodos)): ?>
                        <div class="table-container">
                            <table class="tabla_cierre">
                                <thead>
                                    <tr>
                                        <th>Método de Pago</th>
                                        <th>Cantidad de Ventas</th>
                                        <th>Monto Recibido Real</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($totales_metodos as $metodo): 
                                        $nombre_metodo = '';
                                        $moneda = '';
                                        $monto_recibido = 0;
                                        
                                        switch($metodo['metodo_pago']) {
                                            case 'efectivo_bs': 
                                                $nombre_metodo = 'Efectivo (Bs)';
                                                $moneda = 'Bs';
                                                $monto_recibido = $metodo['monto_bs'];
                                                break;
                                            case 'pago_movil': 
                                                $nombre_metodo = 'Pago Móvil';
                                                $moneda = 'Bs';
                                                $monto_recibido = $metodo['monto_bs'];
                                                break;
                                            case 'punto': 
                                                $nombre_metodo = 'Punto de Venta';
                                                $moneda = 'Bs';
                                                $monto_recibido = $metodo['monto_bs'];
                                                break;
                                            case 'dolares': 
                                                $nombre_metodo = 'Dólares (USD)';
                                                $moneda = 'USD';
                                                $monto_recibido = $metodo['monto_usd'];
                                                break;
                                            default: 
                                                $nombre_metodo = $metodo['metodo_pago'];
                                                $moneda = 'Bs';
                                                $monto_recibido = $metodo['monto_bs'];
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $nombre_metodo; ?></td>
                                        <td><?php echo $metodo['cantidad_ventas']; ?></td>
                                        <td>
                                            <?php 
                                            if ($moneda === 'USD') {
                                                echo '$' . number_format($monto_recibido, 2) . ' USD';
                                            } else {
                                                echo number_format($monto_recibido, 2) . ' Bs';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No hay ventas registradas para esta fecha.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detalle-ventas">
                <h3>Detalle de Ventas del Día</h3>
                
                <?php if (empty($ventas_dia)): ?>
                    <p>No hay ventas registradas para esta fecha.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID Venta</th>
                                    <th>Tipo</th>
                                    <th>Producto/Combo</th>
                                    <th>Cantidad</th>
                                    <th>Método de Pago</th>
                                    <th>Monto Recibido Real</th>
                                    <th>Fecha/Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas_dia as $venta): 
                                    $nombre_metodo = '';
                                    $moneda = '';
                                    $monto_real = 0;
                                    $tipo_venta = $venta['tipo_producto'];
                                    $nombre_producto = $venta['nombre_producto'];
                                    
                                    switch($venta['metodo_pago']) {
                                        case 'efectivo_bs': 
                                            $nombre_metodo = 'Efectivo (Bs)';
                                            $moneda = 'Bs';
                                            $monto_real = $venta['monto_pagado_bs'] ?? $venta['total_bs'] ?? 0;
                                            break;
                                        case 'pago_movil': 
                                            $nombre_metodo = 'Pago Móvil';
                                            $moneda = 'Bs';
                                            $monto_real = $venta['monto_pagado_bs'] ?? $venta['total_bs'] ?? 0;
                                            break;
                                        case 'punto': 
                                            $nombre_metodo = 'Punto de Venta';
                                            $moneda = 'Bs';
                                            $monto_real = $venta['monto_pagado_bs'] ?? $venta['total_bs'] ?? 0;
                                            break;
                                        case 'dolares': 
                                            $nombre_metodo = 'Dólares (USD)';
                                            $moneda = 'USD';
                                            $monto_real = $venta['monto_pagado_usd'] ?? $venta['total_usd'] ?? 0;
                                            break;
                                        default: 
                                            $nombre_metodo = $venta['metodo_pago'];
                                            $moneda = 'Bs';
                                            $monto_real = $venta['monto_pagado_bs'] ?? $venta['total_bs'] ?? 0;
                                    }
                                ?>
                                <tr class="<?php echo $tipo_venta === 'Combo' ? 'combo-en-carrito' : ''; ?>">
                                    <td><?php echo $venta['id']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $tipo_venta === 'Combo' ? 'badge-combo' : 'badge-producto'; ?>">
                                            <?php echo $tipo_venta; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $nombre_producto; ?>
                                        <?php if ($tipo_venta === 'Combo' && !empty($venta['descripcion_combo'])): ?>
                                            <br><small><?php echo $venta['descripcion_combo']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $venta['cantidad']; ?></td>
                                    <td><?php echo $nombre_metodo; ?></td>
                                    <td>
                                        <?php 
                                        if ($moneda === 'USD') {
                                            echo '$' . number_format($monto_real, 2) . ' USD';
                                        } else {
                                            echo number_format($monto_real, 2) . ' Bs';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="productos-vendidos">
                <h3>Resumen por Producto/Combo</h3>
                
                <?php if (empty($resumen_productos)): ?>
                    <p>No hay productos vendidos para esta fecha.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Producto/Combo</th>
                                    <th>Tipo</th>
                                    <th>Cantidad Vendida</th>
                                    <th>Total (Moneda de Pago)</th>
                                    <th>Método de Pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumen_productos as $producto): 
                                    $moneda = ($producto['metodo_pago'] === 'dolares') ? 'USD' : 'Bs';
                                    $simbolo_moneda = ($moneda === 'USD') ? '$' : '';
                                    $sufijo_moneda = ($moneda === 'USD') ? ' USD' : ' Bs';
                                ?>
                                <tr>
                                    <td><?php echo $producto['nombre']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $producto['tipo'] === 'Combo' ? 'badge-combo' : 'badge-producto'; ?>">
                                            <?php echo $producto['tipo']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $producto['total_cantidad']; ?></td>
                                    <td>
                                        <?php echo $simbolo_moneda . number_format($producto['total_moneda_pago'], 2) . $sufijo_moneda; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        switch($producto['metodo_pago']) {
                                            case 'efectivo_bs': echo 'Efectivo (Bs)'; break;
                                            case 'pago_movil': echo 'Pago Móvil'; break;
                                            case 'punto': echo 'Punto de Venta'; break;
                                            case 'dolares': echo 'Dólares (USD)'; break;
                                            default: echo $producto['metodo_pago'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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