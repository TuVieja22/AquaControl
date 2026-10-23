<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas: que controlador atiende cada direccion de la pagina.
 * Filtros: 'auth' = hay que iniciar sesion; 'deviceauth' = solo el ESP32 con su API key.
 *
 * @var RouteCollection $routes
 */

// Portada.
$routes->get('/', 'Home::index');

// Compra con Mercado Pago.
$routes->group('checkout/mercadopago', static function ($routes) {
    // Crear el cobro.
    $routes->post('preference', 'Checkout::mercadoPagoPreference');
    // Regreso despues de pagar (aprobado, fallido o pendiente).
    $routes->get('success', 'Checkout::mercadoPagoSuccess');
    $routes->get('failure', 'Checkout::mercadoPagoFailure');
    $routes->get('pending', 'Checkout::mercadoPagoPending');
    // Avisos que manda Mercado Pago desde sus servidores (sin sesion ni CSRF).
    $routes->post('webhook', 'Checkout::mercadoPagoWebhook');
});

// Cuentas.
$routes->group('auth', static function ($routes) {
    // Crear cuenta.
    $routes->match(['GET', 'POST'], 'register', 'Auth::register');
    // Iniciar sesion.
    $routes->match(['GET', 'POST'], 'login', 'Auth::login');
    // Salir.
    $routes->post('logout', 'Auth::logout');
    // Pedir el enlace para recuperar la contrasena.
    $routes->match(['GET', 'POST'], 'recover', 'Auth::recover');
    // Elegir la contrasena nueva (con el codigo del enlace).
    $routes->match(['GET', 'POST'], 'reset/(:alphanum)', 'Auth::reset/$1');
    $routes->match(['GET', 'POST'], 'reset', 'Auth::reset');
});

// API del ESP32 (se autentica con la API key del dispositivo, no con la sesion web).
$routes->group('dashboard/api', ['filter' => 'deviceauth'], static function ($routes) {
    // El ESP32 manda una lectura.
    $routes->post('data', 'DeviceApi::data');
    // El ESP32 pregunta si tiene ordenes.
    $routes->get('commands', 'DeviceApi::commands');
    // El ESP32 avisa como termino una orden.
    $routes->post('commands/(:num)/ack', 'DeviceApi::ack/$1');
});

// Panel de la pecera.
$routes->group('dashboard', ['filter' => 'auth'], static function ($routes) {
    // Las cuatro paginas del panel.
    $routes->get('/', 'Dashboard::index');
    $routes->get('history', 'Dashboard::history');
    $routes->get('settings', 'Dashboard::settings');
    $routes->get('profile', 'Dashboard::profile');
    // Guardar "Mi cuenta".
    $routes->post('profile', 'Perfil::actualizar');
    // Datos actualizados (los pide el JavaScript cada pocos segundos).
    $routes->get('api/latest', 'Dashboard::latest');
    // Marcar una alerta como leida.
    $routes->post('alerts/(:num)/read', 'Dashboard::markAlertRead/$1');
    // Acciones: alimentar ahora, modo vacaciones, temperatura objetivo y horarios.
    $routes->post('control/feed', 'Dashboard::feedNow');
    $routes->post('control/vacation-toggle', 'Dashboard::toggleVacation');
    $routes->post('control/target-temperature', 'Dashboard::updateTargetTemperature');
    $routes->post('control/feeding-schedule', 'Dashboard::updateFeedingSchedule');
});

// Dispositivos del usuario: lista, alta, edicion, baja y API keys.
$routes->group('dispositivos', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Dispositivos::index');
    $routes->post('nuevo', 'Dispositivos::create');
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Dispositivos::edit/$1');
    $routes->post('eliminar/(:num)', 'Dispositivos::delete/$1');
    $routes->post('api-key/(:num)', 'Dispositivos::generateApiKey/$1');
    $routes->post('api-key/(:num)/revocar', 'Dispositivos::revokeApiKey/$1');
});

// Pagina que se muestra cuando la direccion no existe.
$routes->set404Override(static fn () => view('errors/404', ['title' => 'Página no encontrada']));
