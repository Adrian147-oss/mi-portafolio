<?php
include 'config.php';
session_start();
// No cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

date_default_timezone_set('America/Caracas');

// Verificar si hay productos O combos en el carrito
if (empty($_SESSION['carrito']) && empty($_SESSION['carrito_combos'])) {
    header('Location: index.php');
    exit;
}

// Determinar el tipo de carrito
$solo_productos = !empty($_SESSION['carrito']) && empty($_SESSION['carrito_combos']);
$solo_combos = empty($_SESSION['carrito']) && !empty($_SESSION['carrito_combos']);
$productos_y_combos = !empty($_SESSION['carrito']) && !empty($_SESSION['carrito_combos']);

// Procesar la venta - NUEVA LÓGICA PARA PAGO POR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_venta'])) {
    
    try {
        $conn->beginTransaction();
        
        // NUEVA LÓGICA: Procesar pago por producto
        if ($solo_productos && isset($_POST['metodo_pago_producto'])) {
            // PAGO POR PRODUCTO INDIVIDUAL
            $metodos_por_producto = $_POST['metodo_pago_producto'];
            
            $total_bs_pagado = 0;
            $total_usd_pagado = 0;
            
            // Insertar cada producto con su método de pago específico
            foreach ($_SESSION['carrito'] as $producto_id => $item) {
                $metodo_pago = $metodos_por_producto[$producto_id];
                $subtotal_bs = $item['precio_bs'] * $item['cantidad'];
                $subtotal_usd = $item['precio_usd'] * $item['cantidad'];
                
                // Determinar montos según el método
                $monto_pagado_bs = null;
                $monto_pagado_usd = null;
                
                if ($metodo_pago == 'dolares') {
                    $monto_pagado_usd = $subtotal_usd;
                    $total_usd_pagado += $subtotal_usd;
                } else {
                    $monto_pagado_bs = $subtotal_bs;
                    $total_bs_pagado += $subtotal_bs;
                }
                
                $stmt = $conn->prepare("INSERT INTO ventas (producto_id, combo_id, cantidad, total_bs, total_usd, metodo_pago, monto_pagado_bs, monto_pagado_usd) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $producto_id, 
                    $item['cantidad'], 
                    $subtotal_bs, 
                    $subtotal_usd, 
                    $metodo_pago, 
                    $monto_pagado_bs,
                    $monto_pagado_usd
                ]);
            }
            
        } elseif ($solo_productos && isset($_POST['metodo_pago_unico'])) {
            // PAGO ÚNICO PARA TODOS LOS PRODUCTOS
            $metodo_pago_unico = $_POST['metodo_pago_unico'];
            
            foreach ($_SESSION['carrito'] as $producto_id => $item) {
                $subtotal_bs = $item['precio_bs'] * $item['cantidad'];
                $subtotal_usd = $item['precio_usd'] * $item['cantidad'];
                
                $monto_pagado_bs = null;
                $monto_pagado_usd = null;
                
                if ($metodo_pago_unico == 'dolares') {
                    $monto_pagado_usd = $subtotal_usd;
                } else {
                    $monto_pagado_bs = $subtotal_bs;
                }
                
                $stmt = $conn->prepare("INSERT INTO ventas (producto_id, combo_id, cantidad, total_bs, total_usd, metodo_pago, monto_pagado_bs, monto_pagado_usd) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $producto_id, 
                    $item['cantidad'], 
                    $subtotal_bs, 
                    $subtotal_usd, 
                    $metodo_pago_unico, 
                    $monto_pagado_bs,
                    $monto_pagado_usd
                ]);
            }
            
        } elseif ($productos_y_combos) {
            // CONFIGURACIÓN ORIGINAL: productos y combos
            $metodo_pago_productos = $_POST['metodo_pago_productos'] ?? null;
            $metodo_pago_combos = 'dolares';
            
            // Procesar productos individuales
            foreach ($_SESSION['carrito'] as $producto_id => $item) {
                $subtotal_bs = $item['precio_bs'] * $item['cantidad'];
                $subtotal_usd = $item['precio_usd'] * $item['cantidad'];
                
                $monto_pagado_bs = null;
                $monto_pagado_usd = null;
                
                if ($metodo_pago_productos == 'dolares') {
                    $monto_pagado_usd = $subtotal_usd;
                } else {
                    $monto_pagado_bs = $subtotal_bs;
                }
                
                $stmt = $conn->prepare("INSERT INTO ventas (producto_id, combo_id, cantidad, total_bs, total_usd, metodo_pago, monto_pagado_bs, monto_pagado_usd) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $producto_id, 
                    $item['cantidad'], 
                    $subtotal_bs, 
                    $subtotal_usd, 
                    $metodo_pago_productos, 
                    $monto_pagado_bs,
                    $monto_pagado_usd
                ]);
            }
            
            // Procesar combos (siempre USD)
            foreach ($_SESSION['carrito_combos'] as $combo_id => $combo) {
                $subtotal_usd = $combo['precio_usd'] * $combo['cantidad'];
                
                $stmt = $conn->prepare("INSERT INTO ventas (producto_id, combo_id, cantidad, total_bs, total_usd, metodo_pago, monto_pagado_bs, monto_pagado_usd) VALUES (NULL, ?, ?, 0, ?, ?, ?, ?)");
                $stmt->execute([
                    $combo_id, 
                    $combo['cantidad'], 
                    $subtotal_usd, 
                    $metodo_pago_combos, 
                    null,
                    $subtotal_usd
                ]);
            }
            
        } else {
            // SOLO COMBOS
            $metodo_pago = 'dolares';
            
            foreach ($_SESSION['carrito_combos'] as $combo_id => $combo) {
                $subtotal_usd = $combo['precio_usd'] * $combo['cantidad'];
                
                $stmt = $conn->prepare("INSERT INTO ventas (producto_id, combo_id, cantidad, total_bs, total_usd, metodo_pago, monto_pagado_bs, monto_pagado_usd) VALUES (NULL, ?, ?, 0, ?, ?, ?, ?)");
                $stmt->execute([
                    $combo_id, 
                    $combo['cantidad'], 
                    $subtotal_usd, 
                    $metodo_pago, 
                    null,
                    $subtotal_usd
                ]);
            }
        }
        
        $conn->commit();
        $venta_exitosa = true;
        // Vaciar ambos carritos después de la venta
        $_SESSION['carrito'] = [];
        $_SESSION['carrito_combos'] = [];
    } catch (Exception $e) {
        $conn->rollBack();
        $venta_exitosa = false;
        $error = $e->getMessage();
    }
}

// Calcular totales para mostrar
$total_bs_productos = 0;
$total_usd_productos = 0;
$total_usd_combos = 0;

foreach ($_SESSION['carrito'] as $item) {
    $total_bs_productos += $item['precio_bs'] * $item['cantidad'];
    $total_usd_productos += $item['precio_usd'] * $item['cantidad'];
}

foreach ($_SESSION['carrito_combos'] as $combo) {
    $total_usd_combos += $combo['precio_usd'] * $combo['cantidad'];
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
    
    <title>Factura - Sistema de Comida Rápida</title>
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
            <h1>Factura de Venta</h1>
            <nav>
                <a href="index.php">Volver al Menú</a>
                <a href="cierre_dia.php">Cierre del Día</a>
            </nav>
        </header>

        <main>
            <?php if (isset($venta_exitosa) && $venta_exitosa): ?>
                <div class="alert success">
                    <h3>¡Venta registrada exitosamente!</h3>
                    <p>La factura ha sido generada y los productos vendidos han sido registrados en el sistema.</p>
                    <a href="index.php" class="btn">Realizar Nueva Venta</a>
                </div>
            <?php elseif (isset($venta_exitosa) && !$venta_exitosa): ?>
                <div class="alert error">
                    <h3>Error al procesar la venta</h3>
                    <p><?php echo $error; ?></p>
                    <a href="factura.php" class="btn">Reintentar</a>
                </div>
            <?php else: ?>
                <div class="factura">
                    <div class="factura-header">
                        <h2>Delicias Triple AAA</h2>
                        <p>Fecha: <?php echo date('d/m/Y H:i:s'); ?></p>
                    </div>

                    <!-- Mostrar productos individuales -->
                    <?php if (!empty($_SESSION['carrito'])): ?>
                    <div class="table-container">
                        <h3>Productos Individuales</h3>
                        <table class="factura-detalle">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unitario (Bs)</th>
                                    <th>Precio Unitario (USD)</th>
                                    <th>Subtotal (Bs)</th>
                                    <th>Subtotal (USD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                                <tr>
                                    <td><?php echo $item['nombre']; ?></td>
                                    <td><?php echo $item['cantidad']; ?></td>
                                    <td><?php echo number_format($item['precio_bs'], 2); ?> Bs</td>
                                    <td>$<?php echo number_format($item['precio_usd'], 2); ?> USD</td>
                                    <td><?php echo number_format($item['precio_bs'] * $item['cantidad'], 2); ?> Bs</td>
                                    <td>$<?php echo number_format($item['precio_usd'] * $item['cantidad'], 2); ?> USD</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Mostrar combos -->
                    <?php if (!empty($_SESSION['carrito_combos'])): ?>
                    <div class="combos-factura">
                        <h3>Combos (Solo USD)</h3>
                        <div class="table-container">
                            <table class="factura-detalle">
                                <thead>
                                    <tr>
                                        <th>Combo</th>
                                        <th>Descripción</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unitario (USD)</th>
                                        <th>Subtotal (USD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['carrito_combos'] as $id => $combo): ?>
                                    <tr class="combo-en-carrito">
                                        <td><strong><?php echo $combo['nombre']; ?></strong></td>
                                        <td>
                                            <small><?php echo $combo['descripcion']; ?></small><br>
                                            <em>Incluye: <?php echo $combo['productos_incluidos']; ?></em>
                                        </td>
                                        <td><?php echo $combo['cantidad']; ?></td>
                                        <td>$<?php echo number_format($combo['precio_usd'], 2); ?> USD</td>
                                        <td>$<?php echo number_format($combo['precio_usd'] * $combo['cantidad'], 2); ?> USD</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Totales -->
                    <div class="resumen-total-factura">
                        <div class="totales">
                            <?php if (!empty($_SESSION['carrito'])): ?>
                            <div class="total-card">
                                <h3>Total Productos Individuales (Bs)</h3>
                                <p class="monto"><?php echo number_format($total_bs_productos, 2); ?> Bs</p>
                            </div>
                            <div class="total-card">
                                <h3>Total Productos Individuales (USD)</h3>
                                <p class="monto">$<?php echo number_format($total_usd_productos, 2); ?> USD</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($_SESSION['carrito_combos'])): ?>
                            <div class="total-card">
                                <h3>Total Combos (USD)</h3>
                                <p class="monto">$<?php echo number_format($total_usd_combos, 2); ?> USD</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="POST" class="confirmar-venta" id="form-venta">
                        
                        <?php if ($solo_productos): ?>
                        <!-- NUEVO: SELECTOR DE TIPO DE PAGO (ÚNICO O MÚLTIPLE) -->
                        <div class="selector-tipo-pago">
                            <h4>Seleccione el Tipo de Pago</h4>
                            <div class="opciones-tipo-pago">
                                <label class="opcion-tipo-pago" id="opcion-unico">
                                    <input type="radio" name="tipo_pago" value="unico" checked>
                                    <span>Pago Único</span>
                                </label>
                                <label class="opcion-tipo-pago" id="opcion-multiple">
                                    <input type="radio" name="tipo_pago" value="multiple">
                                    <span>Pago Múltiple</span>
                                </label>
                            </div>
                        </div>

                        <!-- MODO PAGO ÚNICO -->
                        <div class="metodo-pago-unico-container" id="metodo-unico-container">
                            <h4>Método de Pago Único</h4>
                            <select name="metodo_pago_unico" class="metodo-unico-selector" id="metodo-unico-selector" required>
                                <option value="">Seleccione un método de pago</option>
                                <option value="efectivo_bs">Efectivo (Bs)</option>
                                <option value="pago_movil">Pago Móvil</option>
                                <option value="punto">Punto de Venta</option>
                                <option value="dolares">Dólares (USD)</option>
                            </select>
                            
                            <div class="resumen-pago-unico" id="resumen-pago-unico" style="display: none;">
                                <div>Total a Pagar:</div>
                                <div id="total-unico-bs">0.00 Bs</div>
                                <div id="total-unico-usd" style="display: none;">$0.00 USD</div>
                            </div>
                        </div>

                        <!-- MODO PAGO MÚLTIPLE -->
                        <div class="metodo-pago-multiple-container" id="metodo-multiple-container">
                            <h4>Seleccione el Método de Pago para Cada Producto</h4>
                            <p>Elige en qué moneda pagarás cada producto individual:</p>
                            
                            <div class="productos-pago-lista">
                                <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                                <div class="producto-pago-fila" id="producto-<?php echo $id; ?>">
                                    <div class="producto-info">
                                        <div class="producto-nombre"><?php echo $item['nombre']; ?></div>
                                        <div class="producto-precios">
                                            <?php echo number_format($item['precio_bs'] * $item['cantidad'], 2); ?> Bs | 
                                            $<?php echo number_format($item['precio_usd'] * $item['cantidad'], 2); ?> USD
                                        </div>
                                    </div>
                                    <div class="selector-metodo">
                                        <select name="metodo_pago_producto[<?php echo $id; ?>]" class="selector-metodo-pago" data-producto="<?php echo $id; ?>">
                                            <option value="">Seleccionar...</option>
                                            <option value="efectivo_bs">Efectivo (Bs)</option>
                                            <option value="pago_movil">Pago Móvil</option>
                                            <option value="punto">Punto de Venta</option>
                                            <option value="dolares">Dólares (USD)</option>
                                        </select>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="resumen-pago-final" id="resumen-pago-final" style="display: none;">
                                <h4>Resumen Final de Pago</h4>
                                <div class="total-final">
                                    Total a Pagar en Bs: <span id="total-final-bs">0.00</span> Bs
                                </div>
                                <div class="total-final">
                                    Total a Pagar en USD: $<span id="total-final-usd">0.00</span> USD
                                </div>
                            </div>
                        </div>

                        <?php elseif ($productos_y_combos): ?>
                        <!-- CONFIGURACIÓN ORIGINAL: PRODUCTOS Y COMBOS -->
                        <div class="metodos-pago-separados">
                            <div class="metodo-pago-seccion">
                                <h4>Método de Pago para Productos Individuales</h4>
                                <div class="opciones-pago">
                                    <label class="opcion-pago">
                                        <input type="radio" name="metodo_pago_productos" value="efectivo_bs" required>
                                        <span class="checkmark"></span>
                                        Efectivo (Bs)
                                    </label>
                                    <label class="opcion-pago">
                                        <input type="radio" name="metodo_pago_productos" value="pago_movil" required>
                                        <span class="checkmark"></span>
                                        Pago Móvil
                                    </label>
                                    <label class="opcion-pago">
                                        <input type="radio" name="metodo_pago_productos" value="punto" required>
                                        <span class="checkmark"></span>
                                        Punto de Venta
                                    </label>
                                    <label class="opcion-pago">
                                        <input type="radio" name="metodo_pago_productos" value="dolares" required checked>
                                        <span class="checkmark"></span>
                                        Dólares (USD)
                                    </label>
                                </div>
                            </div>

                            <div class="metodo-pago-seccion">
                                <h4>Método de Pago para Combos</h4>
                                <div class="opciones-pago">
                                    <label class="opcion-pago" style="background: #e8f5e8; padding: 10px; border-radius: 5px;">
                                        <input type="radio" name="metodo_pago_combos" value="dolares" checked disabled>
                                        <span class="checkmark"></span>
                                        <strong>Dólares (USD) - Obligatorio</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <?php else: ?>
                        <!-- MÉTODO ÚNICO PARA SOLO COMBOS -->
                        <div class="metodo-pago-unico">
                            <h4>Método de Pago</h4>
                            <div class="opciones-pago">
                                <?php if ($solo_combos): ?>
                                <label class="opcion-pago" style="background: #e8f5e8; padding: 10px; border-radius: 5px;">
                                    <input type="radio" name="metodo_pago_unico" value="dolares" checked disabled>
                                    <span class="checkmark"></span>
                                    <strong>Dólares (USD) - Obligatorio</strong>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="botones-accion">
                            <button type="submit" name="confirmar_venta" class="btn confirmar" id="btn-confirmar">Confirmar Venta</button>
                            <a href="index.php" class="btn cancelar">Modificar Pedido</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Solo para el modo de solo productos individuales
            <?php if ($solo_productos): ?>
            
            // Elementos del DOM
            const opcionUnico = document.getElementById('opcion-unico');
            const opcionMultiple = document.getElementById('opcion-multiple');
            const containerUnico = document.getElementById('metodo-unico-container');
            const containerMultiple = document.getElementById('metodo-multiple-container');
            const selectorUnico = document.getElementById('metodo-unico-selector');
            const resumenUnico = document.getElementById('resumen-pago-unico');
            const totalUnicoBs = document.getElementById('total-unico-bs');
            const totalUnicoUsd = document.getElementById('total-unico-usd');
            const selectoresMetodo = document.querySelectorAll('.selector-metodo-pago');
            const resumenFinal = document.getElementById('resumen-pago-final');
            const btnConfirmar = document.getElementById('btn-confirmar');
            
            // Totales globales
            const totalBsGlobal = <?php echo $total_bs_productos; ?>;
            const totalUsdGlobal = <?php echo $total_usd_productos; ?>;
            
            // Inicializar mostrando el modo único
            containerUnico.style.display = 'block';
            containerMultiple.style.display = 'none';
            opcionUnico.classList.add('seleccionada');
            
            // Event listeners para cambiar entre tipos de pago
            opcionUnico.addEventListener('click', function() {
                document.querySelector('input[name="tipo_pago"][value="unico"]').checked = true;
                opcionUnico.classList.add('seleccionada');
                opcionMultiple.classList.remove('seleccionada');
                containerUnico.style.display = 'block';
                containerMultiple.style.display = 'none';
                validarFormulario();
            });
            
            opcionMultiple.addEventListener('click', function() {
                document.querySelector('input[name="tipo_pago"][value="multiple"]').checked = true;
                opcionMultiple.classList.add('seleccionada');
                opcionUnico.classList.remove('seleccionada');
                containerUnico.style.display = 'none';
                containerMultiple.style.display = 'block';
                validarFormulario();
            });
            
            // Función para validar el formulario completo
            function validarFormulario() {
                const tipoPago = document.querySelector('input[name="tipo_pago"]:checked').value;
                
                if (tipoPago === 'unico') {
                    // Validar pago único
                    const metodoUnico = selectorUnico.value;
                    btnConfirmar.disabled = !metodoUnico;
                } else {
                    // Validar pago múltiple
                    let todosSeleccionados = true;
                    selectoresMetodo.forEach(selector => {
                        if (!selector.value) {
                            todosSeleccionados = false;
                        }
                    });
                    btnConfirmar.disabled = !todosSeleccionados;
                }
            }
            
            // Event listener para el selector único
            selectorUnico.addEventListener('change', function() {
                const metodo = this.value;
                
                if (metodo) {
                    resumenUnico.style.display = 'block';
                    
                    if (metodo === 'dolares') {
                        totalUnicoBs.style.display = 'none';
                        totalUnicoUsd.style.display = 'block';
                        totalUnicoUsd.textContent = '$' + totalUsdGlobal.toFixed(2) + ' USD';
                    } else {
                        totalUnicoBs.style.display = 'block';
                        totalUnicoUsd.style.display = 'none';
                        totalUnicoBs.textContent = totalBsGlobal.toFixed(2) + ' Bs';
                    }
                } else {
                    resumenUnico.style.display = 'none';
                }
                
                validarFormulario();
            });
            
            // Datos de precios de productos para modo múltiple
            const preciosProductos = {};
            <?php foreach ($_SESSION['carrito'] as $id => $item): ?>
                preciosProductos[<?php echo $id; ?>] = {
                    bs: <?php echo $item['precio_bs'] * $item['cantidad']; ?>,
                    usd: <?php echo $item['precio_usd'] * $item['cantidad']; ?>
                };
            <?php endforeach; ?>
            
            // Función para calcular totales finales (modo múltiple)
            function calcularTotalesFinales() {
                let totalBs = 0;
                let totalUsd = 0;
                let todosSeleccionados = true;
                
                selectoresMetodo.forEach(selector => {
                    const productoId = selector.getAttribute('data-producto');
                    const metodoSeleccionado = selector.value;
                    const productoElement = document.getElementById(`producto-${productoId}`);
                    
                    // Remover clases anteriores
                    productoElement.classList.remove('metodo-seleccionado-bs', 'metodo-seleccionado-usd');
                    
                    if (metodoSeleccionado) {
                        if (metodoSeleccionado === 'dolares') {
                            totalUsd += preciosProductos[productoId].usd;
                            productoElement.classList.add('metodo-seleccionado-usd');
                        } else {
                            totalBs += preciosProductos[productoId].bs;
                            productoElement.classList.add('metodo-seleccionado-bs');
                        }
                    } else {
                        todosSeleccionados = false;
                    }
                });
                
                // Actualizar resumen
                document.getElementById('total-final-bs').textContent = totalBs.toFixed(2);
                document.getElementById('total-final-usd').textContent = totalUsd.toFixed(2);
                
                // Mostrar/ocultar resumen
                if (totalBs > 0 || totalUsd > 0) {
                    resumenFinal.style.display = 'block';
                } else {
                    resumenFinal.style.display = 'none';
                }
                
                return todosSeleccionados;
            }
            
            // Event listeners para selectores de método (modo múltiple)
            selectoresMetodo.forEach(selector => {
                selector.addEventListener('change', function() {
                    const todosSeleccionados = calcularTotalesFinales();
                    if (document.querySelector('input[name="tipo_pago"]:checked').value === 'multiple') {
                        btnConfirmar.disabled = !todosSeleccionados;
                    }
                });
            });
            
            // Inicializar
            validarFormulario();
            
            <?php endif; ?>
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