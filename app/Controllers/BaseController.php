<?php

namespace App\Controllers;

use CodeIgniter\Controller;

/**
 * Base de todos los controladores de AquaControl. Lo que se agregue aca queda
 * disponible en todos (por ejemplo $this->userId()).
 *
 * Por seguridad, los metodos que no son paginas tienen que ser protected o private.
 */
abstract class BaseController extends Controller
{
    /** Id del usuario que inicio sesion (0 si no hay sesion). */
    protected function userId(): int
    {
        return (int) session()->get('user_id');
    }
}
