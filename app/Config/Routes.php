<?php

/**
 * AquaControl – Rutas
 * app/Config/Routes.php
 */

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ── PÁGINA PRINCIPAL ────────────────────────────────────────────
$routes->get('/', 'Home::index');

// ── AUTENTICACIÓN ────────────────────────────────────────────────
$routes->group('auth', function ($routes) {

    // Registro
    $routes->match(['get', 'post'], 'register', 'Auth::register');

    // Login
    $routes->match(['get', 'post'], 'login', 'Auth::login');

    // Logout
    $routes->get('logout', 'Auth::logout');

    // Recuperar contraseña (solicitar enlace)
    $routes->match(['get', 'post'], 'recover', 'Auth::recover');

    // Resetear contraseña (con token en URL o POST)
    $routes->match(['get', 'post'], 'reset/(:alphanum)', 'Auth::reset/$1');
    $routes->match(['get', 'post'], 'reset',             'Auth::reset');
});

// ── DASHBOARD (requiere auth) ───────────────────────────────────
$routes->group('dashboard', ['filter' => 'auth'], function ($routes) {
    $routes->get('/',        'Dashboard::index');
    $routes->get('sensors',  'Dashboard::sensors');
    $routes->get('history',  'Dashboard::history');
    $routes->get('settings', 'Dashboard::settings');

    // API interna – recibe datos del ESP32
    $routes->post('api/data',    'Api::receiveData');
    $routes->get ('api/latest',  'Api::latestData');
    $routes->post('api/control', 'Api::sendCommand');
});

// ── API PÚBLICA (solo ESP32, protegida por API key) ─────────────
$routes->group('api/v1', ['filter' => 'apikey'], function ($routes) {
    $routes->post('data',    'Api::receiveData');
    $routes->get ('status',  'Api::status');
});

// ── ERRORES ─────────────────────────────────────────────────────
$routes->set404Override(function () {
    return view('errors/404');
});
