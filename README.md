# AquaControl

Pagina web para controlar una pecera con un ESP32: muestra la temperatura en vivo,
mueve el alimentador (servo) a mano o por horario, avisa alertas y vende el kit con
Mercado Pago.

Hecha con **CodeIgniter 4** (PHP 8.2), **MySQL/MariaDB** (XAMPP) y JavaScript sin
frameworks. El firmware del ESP32 esta en `firmware/`.

## Instalacion

1. `composer install`
2. Copiar `.env.example` como `.env` y completar los valores vacios (base de datos, Gmail,
   Mercado Pago). El `.env` real nunca se sube a GitHub.
3. Con MySQL (XAMPP) encendido, crear la base `aquacare` y correr `php spark migrate`.
4. Doble clic en `iniciar_aquacontrol.bat`: enciende MySQL, levanta la pagina y abre
   `http://localhost:8080`.

## Levantar la pagina a mano

```bash
php spark servir
```

Es el servidor de este proyecto (`app/Commands/Servidor.php`). Usarlo en lugar de
`php spark serve`: atiende por IPv4 e IPv6 a la vez y activa OPcache, que es lo que hace
que la pagina cargue rapido. Tambien deja entrar desde el celular u otra PC de la misma
red con `http://IP-DE-ESTA-PC:8080`.

## Donde esta cada cosa

| Quiero cambiar... | Archivo |
| --- | --- |
| Los textos de la portada | `app/Views/home/secciones/` (un archivo por seccion) |
| La barra de arriba y el pie | `app/Views/layouts/main.php` |
| El menu lateral del panel | `app/Views/layouts/panel.php` |
| Las partes del panel de la pecera | `app/Views/dashboard/partes/` |
| Colores y tipografias | arriba de todo en `public/css/layouts/main.css` (`:root`) |
| Los estilos de una vista | `public/css/`, con la misma ruta y nombre que la vista: los de `app/Views/home/secciones/hero.php` estan en `public/css/home/secciones/hero.css` |
| Estilos que comparten todas las paginas / las del panel / las de cuenta | `public/css/layouts/main.css`, `panel.css`, `auth.css` |
| Una animacion o algo que es solo visual (cartel de "cargando", bloques que aparecen, carrusel, dibujo del grafico) | `public/js/animaciones/` |
| Lo que hace el trabajo en el navegador (validar, comprar, pedir y mostrar los datos del panel) | `public/js/funciones/` |
| Que direccion abre que pagina | `app/Config/Routes.php` |
| Que hace cada pagina | `app/Controllers/` |
| Como se calcula lo que muestra el panel | `app/Libraries/PanelPecera.php` |
| Consultas a la base de datos | `app/Models/` |
| Las tablas de la base | `app/Database/Migrations/` |
| El diagrama de la base (normalizada hasta 3FN) | `base_de_datos.dbml` (se abre pegandolo en https://dbdiagram.io/d) |
| Precio y datos del producto | `.env` (lineas `commerce.*`) |
| Horarios y limites del alimentador | `app/Config/Feeding.php` |

Los archivos de `app/Config/` que no se nombran aca son los que trae CodeIgniter.

## ESP32

Hardware: temperatura DS18B20 en GPIO 18, servo SG90 en GPIO 5, pH (PH-4502C con divisor:
Po -> 3k -> GPIO 34 -> 1k -> GND) en GPIO 34, rele del calefactor en GPIO 21 (IN1 del modulo) y flotante del nivel de agua
en GPIO 17. El pH se calibra desde el Monitor Serie del Arduino IDE (comandos `cal7` y `cal4`).

- **WiFi (recomendado):** no hay que tocar el codigo.
  1. El ESP32 crea la red `AquaControl` (contrasena `aquacontrol`, igual en todos). En
     *Dispositivos* → *Conectar mi AquaControl* aparece su QR (`public/img/qr-red-aquacontrol.png`,
     el mismo que va en el manual): el celular lo escanea y se conecta como a cualquier WiFi.
  2. Se abre sola una pagina del ESP32: se elige el WiFi de la casa (2.4 GHz) y su contrasena.
  3. Con internet, el ESP32 avisa a la pagina su MAC. Quien entra a *Dispositivos* desde el
     mismo WiFi ve «Encontrado en tu red» y lo agrega con un toque (no hay que copiar nada).
  Para cambiarlo de WiFi no hay que tocar la placa: si no encuentra su red, en un minuto
  prende la suya; si sigue conectado, el boton *Cambiar WiFi* de la lista le pide que la prenda.
  La direccion de la pagina viene en el programa (`SERVER_URL`, al principio del `.ino`) y se
  puede cambiar en «Avanzado» de la pagina del ESP32.
- **Cargar el firmware:** `powershell -ExecutionPolicy Bypass -File firmware\cargar_firmware.ps1`
  (con `-Modo usb` para la version por cable, que ademas usa `firmware\puente_usb.ps1`).

## Cuentas de prueba

Cualquiera puede crear una cuenta y usar el panel, pero las cuentas nuevas son *de prueba*:
duran `cuentas.diasDePrueba` dias (en `.env`, 7 por defecto) y el panel muestra un aviso con la
fecha. Pasan a ser permanentes al conectar un AquaControl o cuando se aprueba una compra hecha
con esa cuenta. Si vence, la sesion se cierra y la cuenta se borra (los pedidos quedan, sin
cuenta). Las de quienes no vuelven a entrar las borra `php spark cuentas:limpiar`, que
`php spark servir` corre solo una vez por hora.

## Alertas y notificaciones

La pagina crea alertas cuando la temperatura o el pH salen del rango configurado, cuando el
nivel de agua esta bajo, cuando una comida falla y cuando un AquaControl deja de mandar datos
5 minutos. Se ven en el panel y, si se activaron en *Mi cuenta* → *Notificaciones*, llegan
como notificacion al celular o a la PC (aunque la pagina este cerrada).

- Las notificaciones necesitan la pagina en `https` (o `localhost`) y las claves `webpush.*`
  en `.env` (se crean con `php spark notificaciones:claves`).
- Las manda `php spark alertas:revisar --siempre`, que arranca solo con `php spark servir`.
- En iPhone solo funcionan desde el acceso directo de la pantalla de inicio.

## Comandos utiles

| Comando | Para que |
| --- | --- |
| `php spark servir` | Levanta la pagina (y la revision de alertas). |
| `php spark migrate` | Crea o actualiza las tablas de la base. |
| `php spark routes` | Lista todas las direcciones de la pagina. |
| `php spark alertas:revisar` | Revisa desconexiones y manda las notificaciones pendientes una vez. |
| `php spark notificaciones:claves` | Crea las claves de las notificaciones (para `.env`). |
| `php spark cuentas:limpiar` | Borra las cuentas de prueba vencidas. |

Para ver la barra de depuracion de CodeIgniter mientras se busca un error, agregar
`toolbar.activo = true` en `.env` (hace las paginas mas pesadas: sacarla al terminar).
