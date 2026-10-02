<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lecturas que manda el ESP32 (temperatura, pH, nivel de agua...).
 */
class SensorModel extends Model
{
    protected $table            = 'lecturas_sensores';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'dispositivo_id',
        'temperatura',
        'ph',
        'turbidez',
        'nivel_agua',
        'calefactor',
        'modo_vacaciones',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function ultimaLectura(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    /**
     * Serie para el grafico entre dos fechas (inclusive), opcionalmente de un solo dispositivo.
     *
     * Si hay mas de $maxPuntos lecturas se agrupan de a tandas consecutivas y se promedian
     * dentro de MySQL: asi PHP recibe como mucho $maxPuntos filas aunque el rango tenga
     * decenas de miles de lecturas.
     *
     * @return array{puntos: list<array{created_at: string, temperatura: ?string, ph: ?string}>, total: int}
     */
    public function serieParaGrafico(int $userId, string $desde, string $hasta, ?int $deviceId, int $maxPuntos): array
    {
        $filtro = 'usuario_id = ? AND created_at BETWEEN ? AND ?';
        $valores = [$userId, $desde, $hasta];
        if ($deviceId !== null) {
            $filtro .= ' AND dispositivo_id = ?';
            $valores[] = $deviceId;
        }

        $total = (int) $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE {$filtro}", $valores)->getRow()->total;
        $tanda = max(1, (int) ceil($total / $maxPuntos));

        $puntos = $this->db->query(
            "SELECT MIN(created_at) AS created_at, ROUND(AVG(temperatura), 2) AS temperatura, ROUND(AVG(ph), 2) AS ph
             FROM (
                 SELECT created_at, temperatura, ph, ROW_NUMBER() OVER (ORDER BY created_at) - 1 AS fila
                 FROM {$this->table} WHERE {$filtro}
             ) AS lecturas
             GROUP BY FLOOR(fila / ?)
             ORDER BY created_at",
            [...$valores, $tanda]
        )->getResultArray();

        return ['puntos' => $puntos, 'total' => $total];
    }
}
