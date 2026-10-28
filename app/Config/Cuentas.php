<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cuentas de prueba: quien todavia no tiene un AquaControl puede usar el panel unos dias.
 * Si en ese tiempo no conecta uno ni le aprueban una compra, la cuenta se borra.
 */
class Cuentas extends BaseConfig
{
    /** Dias que dura una cuenta de prueba (se cambia en .env: cuentas.diasDePrueba). */
    public int $diasDePrueba = 7;
}
