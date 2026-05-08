<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

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

$routes->set404Override(static function () {
    return view('errors/404');
});
