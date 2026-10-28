<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lecturas que manda el ESP32 (temperatura, pH, nivel de agua...).
 * Cada lectura es de un dispositivo; el usuario se sabe por el dispositivo (3FN), asi
 * que "las lecturas de un usuario" son las de sus dispositivos.
 */
class SensorModel extends Model
{
    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'lecturas_sensores';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'dispositivo_id',
        'temperatura',
        'ph',
        'nivel_agua',
        'calefactor',
        'created_at',
    ];
    protected $useTimestamps = false;

    /** Condicion "la lectura es de un dispositivo del usuario" (para usar en un WHERE). */
    private const DE_SUS_DISPOSITIVOS = 'dispositivo_id IN (SELECT id FROM dispositivos WHERE usuario_id = ?)';

    // Lectura mas reciente del usuario (de cualquiera de sus dispositivos).
    public function ultimaLectura(int $userId): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE " . self::DE_SUS_DISPOSITIVOS . ' ORDER BY created_at DESC LIMIT 1',
            [$userId]
        )->getRowArray();
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
        // Condiciones de la consulta: dispositivos del usuario, rango de fechas y (opcional) uno solo.
        $filtro = self::DE_SUS_DISPOSITIVOS . ' AND created_at BETWEEN ? AND ?';
        $valores = [$userId, $desde, $hasta];
        if ($deviceId !== null) {
            $filtro .= ' AND dispositivo_id = ?';
            $valores[] = $deviceId;
        }

        // Cuantas lecturas hay y de a cuantas hay que agruparlas.
        $total = (int) $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE {$filtro}", $valores)->getRow()->total;
        $tanda = max(1, (int) ceil($total / $maxPuntos));

        // Promedio de temperatura y pH de cada grupo.
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
