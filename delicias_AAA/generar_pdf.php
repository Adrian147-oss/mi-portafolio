<?php
include 'config.php';
// No cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('America/Caracas');

// Obtener fecha del reporte
$fecha_reporte = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$fecha_formateada = date('d/m/Y', strtotime($fecha_reporte));

// Obtener datos para el reporte - SEPARADO POR MONEDA
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN metodo_pago = 'dolares' THEN monto_pagado_usd ELSE 0 END) as total_usd,
        SUM(CASE WHEN metodo_pago != 'dolares' THEN monto_pagado_bs ELSE 0 END) as total_bs,
        SUM(CASE WHEN metodo_pago = 'dolares' THEN 1 ELSE 0 END) as ventas_dolares,
        SUM(CASE WHEN metodo_pago != 'dolares' THEN 1 ELSE 0 END) as ventas_bolivares,
        COUNT(DISTINCT id) as total_ventas
    FROM ventas 
    WHERE DATE(fecha) = ?
");
$stmt->execute([$fecha_reporte]);
$totales = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener ventas detalladas - ACTUALIZADO PARA INCLUIR COMBOS
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
$stmt->execute([$fecha_reporte]);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener resumen por método de pago - SEPARADO POR MONEDA
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
$stmt->execute([$fecha_reporte]);
$metodos_pago = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener resumen por producto/combo - ACTUALIZADO PARA INCLUIR COMBOS
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
$stmt->execute([$fecha_reporte]);
$resumen_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales manualmente como respaldo
$total_bs_manual = 0;
$total_usd_manual = 0;
foreach($ventas as $venta) {
    if($venta['metodo_pago'] == 'dolares') {
        $total_usd_manual += $venta['monto_pagado_usd'] ?? $venta['total_usd'] ?? 0;
    } else {
        $total_bs_manual += $venta['monto_pagado_bs'] ?? $venta['total_bs'] ?? 0;
    }
}

// Usar totales calculados manualmente si los de la consulta están vacíos
if (empty($totales['total_bs']) && empty($totales['total_usd'])) {
    $totales['total_bs'] = $total_bs_manual;
    $totales['total_usd'] = $total_usd_manual;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte <?php echo $fecha_reporte; ?> - Deliciastride</title>
    <link rel="stylesheet" href="style2.css">
    
</head>
<body>
    <div class="header">
        <div class="empresa">DELICIAS TRIPLE AAA</div>
        <div class="titulo">REPORTE DE CIERRE</div>
        <div><strong>Fecha:</strong> <?php echo $fecha_formateada; ?> | <strong>Generado:</strong> <?php echo date('d/m/Y H:i:s'); ?></div>
    </div>

    <!-- Resumen General -->
    <div class="resumen-general">
        <h3 style="color: #8B4513; margin: 0 0 15px 0;">RESUMEN GENERAL</h3>
        <div class="totales-grid">
            <div class="total-card">
                <div>Total Ventas</div>
                <div class="total-monto"><?php echo $totales['total_ventas'] ?? 0; ?></div>
            </div>
            <div class="total-card">
                <div>Ventas en Bs</div>
                <div class="total-monto"><?php echo $totales['ventas_bolivares'] ?? 0; ?></div>
            </div>
            <div class="total-card">
                <div>Ventas en USD</div>
                <div class="total-monto"><?php echo $totales['ventas_dolares'] ?? 0; ?></div>
            </div>
            <div class="total-card">
                <div>Total Recibido</div>
                <div class="total-monto">
                    <?php 
                    $total_bs = $totales['total_bs'] ?? 0;
                    $total_usd = $totales['total_usd'] ?? 0;
                    
                    if ($total_bs > 0 && $total_usd > 0) {
                        echo number_format($total_bs, 2) . ' Bs<br><small>+ $' . number_format($total_usd, 2) . ' USD</small>';
                    } elseif ($total_bs > 0) {
                        echo number_format($total_bs, 2) . ' Bs';
                    } else {
                        echo '$' . number_format($total_usd, 2) . ' USD';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen por Método de Pago -->
    <?php if (!empty($metodos_pago)): ?>
    <h3 style="color: #8B4513;">RESUMEN POR MÉTODO DE PAGO</h3>
    <table>
        <thead>
            <tr>
                <th>Método de Pago</th>
                <th>Cantidad Ventas</th>
                <th>Monto Recibido</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($metodos_pago as $metodo): 
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
    <?php endif; ?>

    <!-- Resumen por Producto/Combo -->
    <?php if (!empty($resumen_productos)): ?>
    <h3 style="color: #8B4513; margin-top: 20px;">RESUMEN POR PRODUCTO/COMBO</h3>
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
    <?php endif; ?>

    <!-- Detalle de Ventas -->
    <h3 style="color: #8B4513; margin-top: 20px;">DETALLE DE VENTAS</h3>
    <?php if (!empty($ventas)): ?>
    <table>
        <thead>
            <tr>
                <th>Producto/Combo</th>
                <th>Tipo</th>
                <th>Cant</th>
                <th>Método Pago</th>
                <th>Monto Recibido Real</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ventas as $venta): 
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
                <td>
                    <?php echo $nombre_producto; ?>
                    <?php if ($tipo_venta === 'Combo' && !empty($venta['descripcion_combo'])): ?>
                        <br><small><?php echo $venta['descripcion_combo']; ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?php echo $tipo_venta === 'Combo' ? 'badge-combo' : 'badge-producto'; ?>">
                        <?php echo $tipo_venta; ?>
                    </span>
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
                <td><?php echo date('H:i', strtotime($venta['fecha'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="text-align: center; color: #666;">No hay ventas registradas para esta fecha.</p>
    <?php endif; ?>

    <div class="footer">
        <p>Sistema Automatizado Delicias triple AAA - Reporte generado automáticamente</p>
    </div>

    <div class="botones-accion no-print">
        <button onclick="window.print()" class="btn">🖨️ Imprimir / Guardar como PDF</button>
        <button onclick="window.close()" class="btn">Cerrar</button>
    </div>
    
</body>
</html>