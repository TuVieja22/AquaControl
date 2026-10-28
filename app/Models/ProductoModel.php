<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Productos de la tienda. El que se vende hoy se configura en el .env (commerce.*):
 * cuando alguien compra, se busca aca por su sku (o se crea) y el pedido guarda su id.
 */
class ProductoModel extends Model
{
    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'productos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'sku',
        'nombre',
    ];

    // La base anota sola cuando se creo y cuando cambio cada producto.
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Id del producto con ese sku; lo crea si no existe y le actualiza el nombre si cambio. */
    public function idPorSku(string $sku, string $nombre): int
    {
        $producto = $this->where('sku', $sku)->first();

        if ($producto === null) {
            return (int) $this->insert(['sku' => $sku, 'nombre' => $nombre]);
        }

        if ($producto['nombre'] !== $nombre) {
            $this->update($producto['id'], ['nombre' => $nombre]);
        }

        return (int) $producto['id'];
    }
}
