<?php
// Concepto.php

class Concepto {
    public string $nombre;
    public string $codigo;
    public string $tipo; // REM, NO_REM, DES
    public string $formula; // Ej: "BASICO * 0.01 * ANTIGUEDAD"
    
    public function __construct($nombre, $codigo, $tipo, $formula) {
        $this->nombre = $nombre;
        $this->codigo = $codigo;
        $this->tipo = $tipo;
        $this->formula = $formula;
    }
}
?>