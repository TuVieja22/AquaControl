<?php

use CodeIgniter\Router\RouteCollection;

/**
 * Rutas: que controlador atiende cada direccion de la pagina.
 * Filtros: 'auth' = hay que iniciar sesion; 'auth:administrador' = solo administradores;
 * 'deviceauth' = solo el ESP32 con su API key.
 *
 * @var RouteCollection $routes
 */

$routes->get('/', 'Home::index');

// Compra con Mercado Pago.
$routes->group('checkout/mercadopago', static function ($routes) {
    $routes->post('preference', 'Checkout::mercadoPagoPreference');
    $routes->get('success', 'Checkout::mercadoPagoSuccess');
    $routes->get('failure', 'Checkout::mercadoPagoFailure');
    $routes->get('pending', 'Checkout::mercadoPagoPending');
    // Avisos que manda Mercado Pago desde sus servidores (sin sesion ni CSRF).
    $routes->post('webhook', 'Checkout::mercadoPagoWebhook');
});

// Cuentas.
$routes->group('auth', static function ($routes) {
    $routes->match(['GET', 'POST'], 'register', 'Auth::register');
    $routes->match(['GET', 'POST'], 'login', 'Auth::login');
    $routes->post('logout', 'Auth::logout');
    $routes->match(['GET', 'POST'], 'recover', 'Auth::recover');
    $routes->match(['GET', 'POST'], 'reset/(:alphanum)', 'Auth::reset/$1');
    $routes->match(['GET', 'POST'], 'reset', 'Auth::reset');
});

// API del ESP32 (se autentica con la API key del dispositivo, no con la sesion web).
$routes->group('dashboard/api', ['filter' => 'deviceauth'], static function ($routes) {
    $routes->post('data', 'DeviceApi::data');
    $routes->get('commands', 'DeviceApi::commands');
    $routes->post('commands/(:num)/ack', 'DeviceApi::ack/$1');
});

// Panel de la pecera.
$routes->group('dashboard', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('history', 'Dashboard::history');
    $routes->get('settings', 'Dashboard::settings');
    $routes->get('profile', 'Dashboard::profile');
    $routes->post('profile', 'Perfil::actualizar');
    $routes->get('api/latest', 'Dashboard::latest');
    $routes->post('alerts/(:num)/read', 'Dashboard::markAlertRead/$1');
    $routes->post('control/feed', 'Dashboard::feedNow');
    $routes->post('control/vacation-toggle', 'Dashboard::toggleVacation');
    $routes->post('control/target-temperature', 'Dashboard::updateTargetTemperature');
    $routes->post('control/feeding-schedule', 'Dashboard::updateFeedingSchedule');
});

$routes->group('dispositivos', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Dispositivos::index');
    $routes->post('nuevo', 'Dispositivos::create');
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Dispositivos::edit/$1');
    $routes->post('eliminar/(:num)', 'Dispositivos::delete/$1');
    $routes->post('api-key/(:num)', 'Dispositivos::generateApiKey/$1');
    $routes->post('api-key/(:num)/revocar', 'Dispositivos::revokeApiKey/$1');
});

$routes->group('usuarios', ['filter' => 'auth:administrador'], static function ($routes) {
    $routes->get('/', 'Usuarios::index');
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Usuarios::edit/$1');
});

$routes->get('pedidos', 'Pedidos::index', ['filter' => 'auth:administrador']);

$routes->set404Override(static fn () => view('errors/404', ['title' => 'Página no encontrada']));
