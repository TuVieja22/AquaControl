<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Alimentador automatico (servo controlado por el ESP32).
 */
class Feeding extends BaseConfig
{
    /**
     * Zona horaria en la que el usuario carga los horarios de alimentacion.
     * Hoy coincide con App::$appTimezone, pero se mantiene separada por si el
     * servidor pasa a otra zona: los horarios se calculan siempre en esta.
     */
    public string $timezone = 'America/Argentina/Buenos_Aires';

    /**
     * Minutos despues de la hora programada en los que todavia se dispara la
     * alimentacion (por si el ESP32 estuvo offline un rato). Pasado ese margen se omite.
     */
    public int $scheduleWindowMinutes = 30;

    /**
     * Minutos que un "Alimentar ahora" espera a que el dispositivo lo tome antes de expirar.
     */
    public int $manualCommandTtlMinutes = 5;

    /**
     * Segundos sin contacto a partir de los cuales el dispositivo se considera offline.
     */
    public int $onlineThresholdSeconds = 120;

    public float $minGrams = 0.1;
    public float $maxGrams = 20.0;
}
