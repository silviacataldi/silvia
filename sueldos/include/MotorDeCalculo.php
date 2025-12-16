<?php
// MotorDeCalculo.php
require_once 'include/auth_check.php';

class MotorDeCalculo {
    private array $contexto = [];

    /**
     * Carga las variables iniciales del empleado (Contexto).
     * Ej: BASICO, ANTIGUEDAD, CATEGORIA.
     */
    public function cargarContexto(array $datosEmpleado) {
        $this->contexto = $datosEmpleado;
    }

    /**
     * Agrega un resultado calculado al contexto para que
     * fórmulas posteriores puedan usarlo.
     * Ej: El cálculo de JUBILACION necesita el TOTAL_REMUNERATIVO previo.
     */
    public function agregarAlContexto($variable, $valor) {
        $this->contexto[$variable] = $valor;
    }

    public function resolver(Concepto $concepto): float {
        $formula = $concepto->formula;

        // 1. Reemplazo de Variables (Parsing)
        // Buscamos palabras clave en la fórmula y las cambiamos por sus valores numéricos.
        foreach ($this->contexto as $variable => $valor) {
            // Usamos límites de palabra \b para no reemplazar parcialmente (ej. no romper "SUELDO_BASICO")
            $formula = preg_replace('/\b' . $variable . '\b/', $valor, $formula);
        }

        // 2. Validación de Seguridad (Sanitization)
        // Antes de ejecutar, nos aseguramos que SOLO queden números y operadores matemáticos.
        // Si queda alguna letra, significa que hubo una variable no definida -> Error.
        if (preg_match('/[a-zA-Z]/', $formula)) {
            // En producción, esto debería lanzar una Excepción controlada.
            echo "Error: La fórmula del concepto '{$concepto->nombre}' contiene variables desconocidas: $formula \n";
            return 0.00;
        }

        // 3. Ejecución Matemática (Eval Seguro)
        // Al haber validado arriba, el eval ya no es peligroso.
        try {
            $resultado = 0;
            // eval ejecuta el string como código PHP.
            eval("\$resultado = $formula;");
            
            // Redondeo a 2 decimales para evitar problemas con AFIP [cite: 44, 132]
            return round((float)$resultado, 2); 
        } catch (Throwable $e) {
            echo "Error matemático en '{$concepto->nombre}': " . $e->getMessage();
            return 0.00;
        }
    }
}
?>