<?php
// ARCHIVO DE PRUEBA — eliminar después de verificar
// Colocar en: public/test_email.php
// Acceder en: http://localhost:8080/test_email.php

// Cargar CI4
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH . '..');
require 'vendor/autoload.php';

$app = \Config\Services::email();

$app->setFrom('noreply@aquacontrol.com', 'AquaControl');
$app->setTo('leandroastiz@alumnos.itr3.edu.ar'); // ← cambiá esto por tu correo real
$app->setSubject('Prueba de email – AquaControl');
$app->setMessage('<h2>Si ves esto, el email funciona ✅</h2><p>AquaControl puede enviar correos correctamente.</p>');
$app->setMailType('html');

if ($app->send()) {
    echo '<h2 style="color:green">✅ Email enviado correctamente</h2>';
    echo '<p>Revisá tu bandeja de entrada.</p>';
} else {
    echo '<h2 style="color:red">❌ Error al enviar email</h2>';
    echo '<pre>' . $app->printDebugger(['headers', 'subject', 'body']) . '</pre>';
}
