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

La primera cuenta que se registra queda como administrador.

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
| Colores y tipografias | arriba de todo en `public/css/aqua.css` (`:root`) |
| Estilos de la portada / del panel / del login | `public/css/home.css`, `dashboard.css`, `auth.css` |
| Que direccion abre que pagina | `app/Config/Routes.php` |
| Que hace cada pagina | `app/Controllers/` |
| Como se calcula lo que muestra el panel | `app/Libraries/PanelPecera.php` |
| Consultas a la base de datos | `app/Models/` |
| Las tablas de la base | `app/Database/Migrations/` |
| Precio y datos del producto | `.env` (lineas `commerce.*`) |
| Horarios y limites del alimentador | `app/Config/Feeding.php` |

Los archivos de `app/Config/` que no se nombran aca son los que trae CodeIgniter.

## ESP32

Hardware: sensor DS18B20 en GPIO 18 y servo SG90 en GPIO 19.

- **WiFi (recomendado):** copiar `firmware/aquacontrol_esp32/secrets.example.h` como
  `secrets.h` y completar la red WiFi (2.4 GHz), la IP de la PC y la API key, que se
  genera en la pagina, en *Dispositivos*.
- **Cargar el firmware:** `powershell -ExecutionPolicy Bypass -File firmware\cargar_firmware.ps1`
  (con `-Modo usb` para la version por cable, que ademas usa `firmware\puente_usb.ps1`).

## Comandos utiles

| Comando | Para que |
| --- | --- |
| `php spark servir` | Levanta la pagina. |
| `php spark migrate` | Crea o actualiza las tablas de la base. |
| `php spark routes` | Lista todas las direcciones de la pagina. |

Para ver la barra de depuracion de CodeIgniter mientras se busca un error, agregar
`toolbar.activo = true` en `.env` (hace las paginas mas pesadas: sacarla al terminar).

## Mas detalle

- `BACKEND_DOCUMENTACION.md`: rutas, controladores, modelos, base de datos y API del ESP32.
- `FRONTEND_DOCUMENTACION.md`: vistas, estilos y JavaScript.
