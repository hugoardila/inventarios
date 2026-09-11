<?php
require_once 'views/funciones.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Precios - TECNOXPERT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .calculator-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
        }
        .result-card {
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="calculator-card p-4 mb-4">
                    <h2 class="text-center mb-0">
                        <i class="bi bi-calculator"></i> Calculadora de Precios
                    </h2>
                    <p class="text-center mb-0">Calcula precios base, con IVA y desglose</p>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <form id="calculadoraForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="bi bi-arrow-up-circle"></i> Calcular Precio Base</h5>
                                    <div class="mb-3">
                                        <label class="form-label">Precio Final (con IVA)</label>
                                        <input type="number" class="form-control" id="precioFinal" placeholder="45000" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">IVA (%)</label>
                                        <input type="number" class="form-control" id="ivaPorcentaje" placeholder="19" step="0.01">
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="calcularPrecioBase()">
                                        <i class="bi bi-calculator"></i> Calcular Precio Base
                                    </button>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5><i class="bi bi-arrow-down-circle"></i> Calcular Precio Final</h5>
                                    <div class="mb-3">
                                        <label class="form-label">Precio Base (sin IVA)</label>
                                        <input type="number" class="form-control" id="precioBase" placeholder="37815.13" step="0.01">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">IVA (%)</label>
                                        <input type="number" class="form-control" id="ivaPorcentaje2" placeholder="19" step="0.01">
                                    </div>
                                    <button type="button" class="btn btn-success" onclick="calcularPrecioFinal()">
                                        <i class="bi bi-calculator"></i> Calcular Precio Final
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <div id="resultados" class="result-card p-4" style="display: none;">
                            <h5><i class="bi bi-graph-up"></i> Resultados del Cálculo</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="text-muted">Precio Base</h6>
                                        <h4 class="text-primary" id="resultadoPrecioBase">$0</h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="text-muted">IVA</h6>
                                        <h4 class="text-warning" id="resultadoIva">$0</h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h6 class="text-muted">Precio Final</h6>
                                        <h4 class="text-success" id="resultadoPrecioFinal">$0</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5><i class="bi bi-info-circle"></i> Ejemplos Prácticos</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Ejemplo 1: Precio Final $45,000 con IVA 19%</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Precio Base:</strong> $37,815.13</li>
                                    <li><strong>IVA (19%):</strong> $7,184.87</li>
                                    <li><strong>Total:</strong> $45,000.00</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Ejemplo 2: Precio Base $50,000 con IVA 19%</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Precio Base:</strong> $50,000.00</li>
                                    <li><strong>IVA (19%):</strong> $9,500.00</li>
                                    <li><strong>Total:</strong> $59,500.00</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calcularPrecioBase() {
            const precioFinal = parseFloat(document.getElementById('precioFinal').value);
            const ivaPorcentaje = parseFloat(document.getElementById('ivaPorcentaje').value);
            
            if (!precioFinal || !ivaPorcentaje) {
                alert('Por favor ingresa ambos valores');
                return;
            }
            
            // Calcular precio base
            const precioBase = precioFinal / (1 + (ivaPorcentaje / 100));
            const iva = precioFinal - precioBase;
            
            mostrarResultados(precioBase, iva, precioFinal);
        }
        
        function calcularPrecioFinal() {
            const precioBase = parseFloat(document.getElementById('precioBase').value);
            const ivaPorcentaje = parseFloat(document.getElementById('ivaPorcentaje2').value);
            
            if (!precioBase || !ivaPorcentaje) {
                alert('Por favor ingresa ambos valores');
                return;
            }
            
            // Calcular precio final
            const iva = precioBase * (ivaPorcentaje / 100);
            const precioFinal = precioBase + iva;
            
            mostrarResultados(precioBase, iva, precioFinal);
        }
        
        function mostrarResultados(precioBase, iva, precioFinal) {
            document.getElementById('resultadoPrecioBase').textContent = '$' + precioBase.toLocaleString('es-CO', {minimumFractionDigits: 2});
            document.getElementById('resultadoIva').textContent = '$' + iva.toLocaleString('es-CO', {minimumFractionDigits: 2});
            document.getElementById('resultadoPrecioFinal').textContent = '$' + precioFinal.toLocaleString('es-CO', {minimumFractionDigits: 2});
            
            document.getElementById('resultados').style.display = 'block';
        }
    </script>
</body>
</html>

