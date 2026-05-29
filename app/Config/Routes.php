<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

$routes->group('checkout', function ($routes) {
    $routes->post('mercadopago/preference', 'Checkout::mercadoPagoPreference');
    $routes->get('mercadopago/success', 'Checkout::mercadoPagoSuccess');
    $routes->get('mercadopago/failure', 'Checkout::mercadoPagoFailure');
    $routes->get('mercadopago/pending', 'Checkout::mercadoPagoPending');
});

$routes->group('auth', function ($routes) {
    $routes->match(['get', 'post'], 'register', 'Auth::register');
    $routes->match(['get', 'post'], 'login', 'Auth::login');
    $routes->get('logout', 'Auth::logout');
    $routes->match(['get', 'post'], 'recover', 'Auth::recover');
    $routes->match(['get', 'post'], 'reset/(:alphanum)', 'Auth::reset/$1');
    $routes->match(['get', 'post'], 'reset', 'Auth::reset');
});

$routes->group('dashboard', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('history', 'Dashboard::history');
    $routes->get('settings', 'Dashboard::settings');
    $routes->get('api/latest', 'Dashboard::latest');
    $routes->post('api/data', 'Dashboard::receiveData');
    $routes->post('alerts/(:num)/read', 'Dashboard::markAlertRead/$1');
    $routes->post('control/feed', 'Dashboard::feedNow');
    $routes->post('control/vacation-toggle', 'Dashboard::toggleVacation');
    $routes->post('control/target-temperature', 'Dashboard::updateTargetTemperature');
    
});

$routes->group('dispositivos', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dispositivos::index');
    $routes->post('nuevo', 'Dispositivos::create');
    $routes->match(['get', 'post'], 'editar/(:num)', 'Dispositivos::edit/$1');
    $routes->post('eliminar/(:num)', 'Dispositivos::delete/$1');
});

$routes->group('usuarios', ['filter' => 'role:administrador'], function ($routes) {
    $routes->get('/', 'Usuarios::index');
    $routes->match(['get', 'post'], 'editar/(:num)', 'Usuarios::edit/$1');
});

$routes->set404Override(static function () {
    return view('errors/404');
});
