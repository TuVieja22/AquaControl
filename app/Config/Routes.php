<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

$routes->group('checkout', function ($routes) {
    $routes->post('mercadopago/preference', 'Checkout::mercadoPagoPreference');
    $routes->get('mercadopago/success', 'Checkout::mercadoPagoSuccess');
    $routes->get('mercadopago/failure', 'Checkout::mercadoPagoFailure');
    $routes->get('mercadopago/pending', 'Checkout::mercadoPagoPending');
    // Notificaciones server-to-server de Mercado Pago (sin sesion ni CSRF).
    $routes->post('mercadopago/webhook', 'Checkout::mercadoPagoWebhook');
});

$routes->group('auth', function ($routes) {
    $routes->match(['GET', 'POST'], 'register', 'Auth::register');
    $routes->match(['GET', 'POST'], 'login', 'Auth::login');
    $routes->post('logout', 'Auth::logout');
    $routes->match(['GET', 'POST'], 'recover', 'Auth::recover');
    $routes->match(['GET', 'POST'], 'reset/(:alphanum)', 'Auth::reset/$1');
    $routes->match(['GET', 'POST'], 'reset', 'Auth::reset');
});

// Ingesta de lecturas desde el ESP32: autenticada por API key del dispositivo, sin sesion web.
$routes->post('dashboard/api/data', 'Dashboard::receiveData', ['filter' => 'deviceauth']);
// Cola de comandos (servo del alimentador): el ESP32 consulta y confirma.
$routes->get('dashboard/api/commands', 'DeviceApi::commands', ['filter' => 'deviceauth']);
$routes->post('dashboard/api/commands/(:num)/ack', 'DeviceApi::ack/$1', ['filter' => 'deviceauth']);

$routes->group('dashboard', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('history', 'Dashboard::history');
    $routes->get('settings', 'Dashboard::settings');
    $routes->get('profile', 'Dashboard::profile');
    $routes->get('api/latest', 'Dashboard::latest');
    $routes->post('profile', 'Dashboard::updateProfile');
    $routes->post('alerts/(:num)/read', 'Dashboard::markAlertRead/$1');
    $routes->post('control/feed', 'Dashboard::feedNow');
    $routes->post('control/vacation-toggle', 'Dashboard::toggleVacation');
    $routes->post('control/target-temperature', 'Dashboard::updateTargetTemperature');
    $routes->post('control/feeding-schedule', 'Dashboard::updateFeedingSchedule');
    
});

$routes->group('dispositivos', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dispositivos::index');
    $routes->post('nuevo', 'Dispositivos::create');
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Dispositivos::edit/$1');
    $routes->post('eliminar/(:num)', 'Dispositivos::delete/$1');
    $routes->post('api-key/(:num)', 'Dispositivos::generateApiKey/$1');
    $routes->post('api-key/(:num)/revocar', 'Dispositivos::revokeApiKey/$1');
});

$routes->group('usuarios', ['filter' => 'role:administrador'], function ($routes) {
    $routes->get('/', 'Usuarios::index');
    $routes->match(['GET', 'POST'], 'editar/(:num)', 'Usuarios::edit/$1');
});

$routes->group('pedidos', ['filter' => 'role:administrador'], function ($routes) {
    $routes->get('/', 'Pedidos::index');
});

$routes->set404Override(static function () {
    return view('errors/404');
});
