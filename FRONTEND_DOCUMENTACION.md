# FRONTEND_DOCUMENTACION

## Resumen general

AquaControl es una aplicacion web renderizada en servidor con CodeIgniter 4. El frontend no es una SPA: las pantallas se generan con vistas PHP en `app/Views`, reciben datos desde controladores y luego se enriquecen con JavaScript vanilla en `public/js`.

La experiencia se divide en cinco superficies principales:

- Portada publica y compra del kit AquaControl.
- Autenticacion: registro, login, recuperacion y reseteo de contrasena.
- Panel de la pecera con metricas, grafico, alertas y controles.
- Gestion de dispositivos del usuario autenticado.
- Gestion de usuarios y pedidos para administradores.

El estado persistente real vive en backend: sesion, base de datos y configuracion en `.env`. El frontend mantiene solo estado temporal de UI: formularios, datos del panel, resumen de compra y el grafico.

## Tecnologias y dependencias frontend

| Tecnologia | Uso |
| --- | --- |
| Vistas PHP de CodeIgniter | Renderizado HTML server-side, con layouts (`extend` / `section`). |
| HTML5 + CSS3 | Maquetacion, formularios, tablas, panel y portada. |
| JavaScript vanilla | Validaciones, cartel de carga, compra, panel en vivo. |
| Chart.js 4 por CDN | Grafico del panel. `dashboard.js` lo carga recien cuando hace falta. |
| Mercado Pago JS SDK | Boton de pago, cargado por `purchase.js` al confirmar la compra. |
| Google Fonts | Inter y Poppins, cargadas desde `layouts/main.php` sin frenar el dibujo de la pagina. |

No hay `package.json`, bundler, React ni Vue. La arquitectura es de paginas PHP con un archivo JS por pantalla.

## Arquitectura frontend

```mermaid
flowchart TD
    U[Usuario] --> B[Navegador]
    B --> R[Ruta CodeIgniter]
    R --> C[Controlador]
    C --> V[Vista de la pagina]
    V --> P[layouts/panel.php: menu lateral]
    V --> L[layouts/main.php: head, barra y pie]
    P --> L
    L --> CSS[aqua.css + CSS de la pagina]
    L --> JS[aqua.js + JS de la pagina]
    JS --> API[Endpoints JSON o formularios POST]
    API --> JS
    JS --> DOM[Actualizacion del DOM]
```

### Como se arma una pagina

Todas las vistas empiezan con `$this->extend(...)` y completan secciones del molde:

- **`layouts/main.php`** es el molde de todas las paginas: `<head>`, barra de navegacion y pie. Secciones: `contenido` (obligatoria), `estilos` y `scripts` (opcionales).
- **`layouts/panel.php`** extiende a `main` y agrega el menu lateral. Lo usan el panel, Dispositivos, Usuarios y Pedidos. Secciones: `lateral` (titulo sobre el menu), `panel` (contenido) y `scripts`.

Cada vista pide sus propios archivos: un `<link>` en la seccion `estilos` y un `<script>` en la seccion `scripts`. Los controladores no saben nada de CSS ni de JS.

Ejemplo minimo de una pagina nueva del panel:

```php
<?= $this->extend('layouts/panel') ?>

<?= $this->section('lateral') ?>
  <h1>Mi pagina</h1>
<?= $this->endSection() ?>

<?= $this->section('panel') ?>
  <p>Contenido.</p>
<?= $this->endSection() ?>
```

## Estructura de carpetas frontend

```text
app/Views/
  layouts/
    main.php                    (molde de todas las paginas)
    panel.php                   (molde con menu lateral)
  components/
    flash_messages.php          (mensajes de exito / error / aviso)
    logout_button.php           (boton "Salir")
    campo_password.php          (campo de contrasena con el ojito)
  home/
    index.php                   (portada: lista de secciones en orden)
    secciones/
      hero.php                  (titulo principal y botones)
      producto.php              (galeria)
      caracteristicas.php
      como_funciona.php         (pasos)
      beneficios.php
      demo.php                  (panel de muestra)
      comparacion.php
      testimonios.php           (carrusel)
      planes.php
      compra.php                (formulario de compra)
  auth/
    login.php
    register.php
    recover.php
    reset.php
  dashboard/
    index.php                   (panel: lista de partes en orden)
    partes/
      filtros.php               (filtros del historial)
      en_vivo.php               (tarjetas y grafico)
      alertas_y_alimentaciones.php
      control.php               (modo vacaciones y temperatura objetivo)
      alimentador.php           (alimentar ahora y horarios)
      perfil.php                (mi cuenta)
  devices/
    index.php
    edit.php
  users/
    index.php
    edit.php
  orders/
    index.php
  emails/
    recuperar_contrasena.php    (cuerpo del email de recuperacion)
  errors/
    404.php

public/
  css/
    aqua.css                    (global: se carga en todas las paginas)
    home.css                    (portada y compra)
    dashboard.css               (panel, dispositivos, usuarios y pedidos)
    auth.css                    (login, registro, recuperar)
  js/
    aqua.js                     (global: se carga en todas las paginas)
    auth.js
    dashboard.js
    purchase.js
  img/
    aquacontrol-product.webp
  favicon.ico
```

## Carga de assets por pantalla

| Pantalla | Vista | Molde | CSS propio | JS propio |
| --- | --- | --- | --- | --- |
| Portada / compra | `home/index.php` | `main` | `home.css` | `purchase.js` |
| Cuentas | `auth/*.php` | `main` | `auth.css` | `auth.js` |
| Panel de la pecera | `dashboard/index.php` | `panel` | `dashboard.css` | `dashboard.js` |
| Dispositivos | `devices/*.php` | `panel` | `dashboard.css` | ninguno |
| Usuarios | `users/*.php` | `panel` | `dashboard.css` | ninguno |
| Pedidos | `orders/index.php` | `panel` | `dashboard.css` | ninguno |
| 404 | `errors/404.php` | `main` | ninguno | ninguno |

`aqua.css` y `aqua.js` se cargan siempre desde `layouts/main.php`. `dashboard.css` lo carga `layouts/panel.php`.

## Archivos importantes

### `app/Views/layouts/main.php`

Define la estructura del documento:

- Calcula si hay usuario autenticado y si es administrador.
- `<meta name="csrf-token">` y `<meta name="csrf-header">`: de ahi saca `aqua.js` el token para los pedidos AJAX.
- Un script de una linea agrega la clase `js` a `<html>` (las animaciones de aparicion solo se aplican si hay JavaScript).
- Tipografias con `media="print" onload="this.media='all'"`: se descargan sin frenar el primer dibujo de la pagina.
- Cartel de "cargando" (`[data-page-loader]`), oculto hasta que `aqua.js` lo muestra.
- Barra de navegacion: los invitados ven login/registro; los usuarios ven panel, dispositivos y salir; los administradores tambien ven usuarios y pedidos.
- Pie de pagina.
- Carga `aqua.js` y despues la seccion `scripts` de la pagina.

Orden importante: `aqua.js` corre antes que el JS de la pagina, que puede usar `window.AquaCsrf` y `window.AquaLoader`.

### `app/Views/layouts/panel.php`

Menu lateral del panel. El link activo se marca solo con `url_is()` segun la direccion. Muestra los mensajes flash arriba del contenido.

### `app/Views/components/flash_messages.php`

Muestra los mensajes de un solo uso que deja el controlador con `->with('success' | 'error' | 'info', '...')`. Escapa el texto con `esc()`. `aqua.js` los desvanece a los 5 segundos.

### `app/Views/components/logout_button.php`

Boton "Salir". Es un `<form method="POST">` con `csrf_field()` hacia `auth/logout` (el logout no acepta GET).

### `app/Views/components/campo_password.php`

Campo de contrasena con el boton para mostrarla. Variables: `$nombre`, `$placeholder`, `$autocomplete` y `$medidor` (`true` agrega la barra de seguridad). Pasar siempre `medidor` de forma explicita:

```php
<?= view('components/campo_password', ['nombre' => 'password', 'placeholder' => 'Tu contrasena', 'autocomplete' => 'current-password', 'medidor' => false]) ?>
```

### Ayudas de formulario (`app/Helpers/formulario_helper.php`)

Disponibles en todas las vistas:

- `error_campo($errors, 'email')`: cajita roja con el error de ese campo (oculta si no hay error).
- `clase_error($errors, 'email')`: devuelve ` is-invalid` para pintar el borde del campo.
- `set_value('email')` y `set_checkbox('terms', '1')` (de CodeIgniter): conservan lo que el usuario habia escrito si el formulario vuelve con errores.

### `app/Views/home/index.php` y `home/secciones/`

La portada es una lista de `include`, una linea por seccion. Para mover una seccion se cambia el orden de las lineas; para sacarla se borra su linea.

Recibe de `Home::index()`:

- `$producto`: datos ya listos para mostrar (nombre, precio formateado, cantidad maxima, mensaje de entrega).
- `$compra`: se imprime como JSON en `<script id="purchase-data">` para `purchase.js`.

```json
{
  "product": {
    "sku": "aquacontrol",
    "name": "AquaControl",
    "unitPrice": 130000,
    "currency": "ARS",
    "maxQuantity": 6
  },
  "payment": {
    "locale": "es-AR",
    "mercadoPagoPublicKey": "...",
    "mercadoPagoPreferenceUrl": "http://.../checkout/mercadopago/preference"
  }
}
```

No se documentan valores sensibles: el ejemplo muestra solo forma y proposito.

La imagen del producto es `img/aquacontrol-product.webp` (unos 95 KB). Se precarga con `<link rel="preload">` porque es lo primero que se ve.

### `app/Views/auth/*.php`

Los cuatro formularios (`login`, `register`, `recover`, `reset`) tienen la misma forma:

- POST a `auth/...` con `csrf_field()`.
- `data-auth-form="login|register|recover|reset"` para que `auth.js` los valide antes de enviar.
- Errores del servidor con `error_campo()`.

Detalles:

- `login.php`: el checkbox "Recordarme" es solo visual; no hay logica de sesion persistente.
- `register.php`: incluye medidor de seguridad de la contrasena y aceptacion de terminos.
- `recover.php`: el backend responde con un mensaje neutral aunque el email no exista.
- `reset.php`: lleva el token en un campo oculto.

### `app/Views/dashboard/index.php` y `dashboard/partes/`

Es la pantalla mas importante. La usan las cuatro direcciones del panel (principal, historial, configuracion y mi cuenta); en Historial se agrega la parte `filtros`.

- `filtros.php`: formulario GET con Desde, Hasta y Dispositivo, y la cantidad de lecturas del rango.
- `en_vivo.php`: temperatura, pH, alertas, estado del ecosistema, grafico `canvas#ecosystemChart` y estado de agua, alimentacion y modo vacaciones.
- `alertas_y_alimentaciones.php`: alertas sin leer y tabla de las ultimas alimentaciones.
- `control.php`: modo vacaciones y temperatura objetivo.
- `alimentador.php`: estado del ESP32 (conectado, offline, orden en curso, ultima orden), "Alimentar ahora" y horarios con gramos por racion.
- `perfil.php`: formulario "Mi cuenta" (lo guarda el controlador `Perfil`).

Los datos llegan en `$dashboardData` y se imprimen como JSON en `<script id="dashboard-data">`. El primer dibujo lo hace PHP con esos mismos datos; despues `dashboard.js` los va refrescando.

### `app/Views/devices/index.php`

Pantalla de dispositivos del usuario:

- Formulario POST `dispositivos/nuevo`.
- Tabla de dispositivos existentes, con el prefijo de la API key y la ultima conexion.
- Acciones editar/eliminar y generar/regenerar/revocar API key.
- Las acciones delicadas piden confirmacion con el atributo `data-confirm="..."` en el `<form>` (lo atiende `aqua.js`).
- Tras generar una key muestra un panel (`#api-key-nueva`) con la key completa (una sola vez), boton Copiar (`data-copy-target`) y ejemplo de request.

### `app/Views/devices/edit.php`

Formulario de edicion de dispositivo (POST a `dispositivos/editar/{id}`), con nombre, tipo y ubicacion precargados.

### `app/Views/users/index.php` y `users/edit.php`

Pantallas administrativas (filtro `auth:administrador`):

- Lista de usuarios con nombre, email, rol, estado y accion editar.
- Edicion de nombre, rol y, opcionalmente, password. El email se muestra readonly.
- El backend impide dejar el sistema sin administradores activos.

### `app/Views/orders/index.php`

Pantalla administrativa de pedidos:

- Resumen de aprobados (cantidad y monto) y pendientes.
- Filtros por estado (`?estado=`) con contador por estado.
- Tabla con fecha, referencia, pago de Mercado Pago, cliente, producto, total y estado.
- Marca los pagos cuyo monto no coincide con el pedido para revision manual.

### `app/Views/errors/404.php`

Error 404 personalizado para rutas no encontradas. Usa el molde comun y un boton al inicio.

## JavaScript

### `public/js/aqua.js`

Se carga en todas las paginas. Responsabilidades:

- **`window.AquaCsrf`**: `headers()` devuelve el header `X-CSRF-TOKEN` para un `fetch`; `refresh(response)` guarda el token nuevo que devuelve el servidor (cambia en cada POST) y lo actualiza tambien en los formularios de la pagina.
- **`window.AquaLoader`**: `mostrar()` y `ocultar()` el cartel de "cargando". Aparece solo si la espera pasa de 300 ms, asi en las cargas rapidas no se ve. Se muestra al hacer clic en un link interno y al enviar un formulario.
- **`data-confirm`**: un `<form data-confirm="Seguro?">` pide confirmacion antes de enviarse.
- **Mensajes flash**: se desvanecen a los 5 segundos.
- **`data-reveal`**: los elementos aparecen cuando entran en pantalla (`IntersectionObserver`).
- **Demo de la portada**: numeros de ejemplo que cambian cada 2,8 s (se pausa con la pestana oculta).
- **Carrusel de testimonios**: botones anterior/siguiente.
- **`data-copy-target="id"`**: boton que copia el texto de ese elemento.

### `public/js/auth.js`

Paginas de cuenta:

- Vincula formularios con `data-auth-form`.
- Valida email y contrasena segura (longitud, mayuscula, minuscula, numero y caracter especial) antes de enviar.
- Muestra los errores en las cajitas `.invalid-feedback` de cada campo.
- Actualiza el medidor de seguridad.
- Boton del ojito para mostrar u ocultar la contrasena.

El servidor vuelve a validar todo: esto solo avisa antes.

### `public/js/dashboard.js`

Panel de la pecera. Los textos ya vienen armados desde `PanelPecera.php`; el JS solo los pone en su lugar.

1. Lee el JSON de `#dashboard-data` y lo guarda en `state`.
2. Dibuja tarjetas, resumen, alertas, tabla de alimentaciones, configuracion y estado del alimentador.
3. Carga Chart.js desde el CDN y dibuja el grafico. Si los datos no cambiaron no lo toca; si cambiaron lo actualiza en el lugar, sin volver a animarlo.
4. Atiende acciones (todas envian el header `X-CSRF-TOKEN` via `window.AquaCsrf`):
   - marcar alerta leida,
   - alimentar ahora (encola la orden para el ESP32 y muestra el mensaje del backend),
   - guardar horarios de alimentacion,
   - alternar modo vacaciones,
   - guardar temperatura objetivo.
5. Vuelve a pedir `dashboard/api/latest` cada 5 segundos, o cada 2 segundos mientras el alimentador tiene una orden en curso. Con la pestana oculta no pide nada y retoma al volver. En Historial, las direcciones incluyen los filtros elegidos para no volver a las 24 h.
6. Mezcla lo recibido en `state` y vuelve a dibujar.

Forma del estado:

```json
{
  "config": {},
  "cards": {},
  "summary": {},
  "alerts": [],
  "feedings": [],
  "charts": { "labels": [], "temperature": [], "ph": [], "count": 0 },
  "feeder": {},
  "latestTimestamp": null,
  "userName": "...",
  "endpoints": {
    "latest": "...",
    "feed": "...",
    "vacationToggle": "...",
    "targetTemperature": "...",
    "feedingSchedule": "...",
    "markAlertTemplate": "..."
  }
}
```

### `public/js/purchase.js`

Formulario de compra de la portada:

- Lee `#purchase-data`.
- Mantiene cantidad, precio unitario y total.
- Valida email, cantidad y terminos.
- Al confirmar pide la preferencia (`POST checkout/mercadopago/preference` con el header `X-CSRF-TOKEN`) y carga el SDK de Mercado Pago al mismo tiempo; despues muestra el boton de pago.
- Al volver de Mercado Pago el backend verifica el pago y muestra el resultado como mensaje flash en `#checkout`.

## CSS y sistema visual

| Archivo | Responsabilidad |
| --- | --- |
| `aqua.css` | Colores y medidas (`:root`), base, fondo, barra de navegacion, botones, formularios, mensajes, panel "en vivo" compartido (portada y panel), pie, cartel de carga, 404 y animaciones. |
| `home.css` | Secciones de la portada, en el mismo orden que `home/secciones/`, y la compra. |
| `dashboard.css` | Estructura con menu lateral, partes del panel, y las tablas de dispositivos, usuarios y pedidos. |
| `auth.css` | Tarjeta centrada de login, registro, recuperar y nueva contrasena. |

Cada archivo esta dividido con comentarios de seccion y termina con sus reglas para pantallas mas chicas (`@media`).

Los colores se definen una sola vez como variables en `:root`, al principio de `aqua.css` (por ejemplo `--aqua-cyan`, el color principal). Cambiar una variable cambia ese color en toda la pagina.

## Rendimiento: decisiones que conviene mantener

- **Imagen de portada en WebP** y precargada.
- **Tipografias** cargadas sin bloquear el dibujo, y solo los pesos que se usan (Inter 400-900, Poppins 500-800).
- **`backdrop-filter` (desenfoque) solo en cuatro lugares**: barra de navegacion, boton secundario de la portada, y dos carteles sobre la foto. Ponerlo en muchas tarjetas sobre un fondo animado hace lenta la pagina.
- **Cartel de carga** que no aparece en cargas rapidas.
- **Barra de depuracion apagada** salvo que se active en `.env` (agregaba miles de elementos a cada pagina).
- **El panel no pide datos con la pestana oculta** y no vuelve a animar el grafico en cada refresco.
- **Chart.js se descarga solo en el panel**, despues de dibujar la pagina.

## Flujo de navegacion

```mermaid
flowchart LR
    Inicio["/"] --> Login["/auth/login"]
    Inicio --> Registro["/auth/register"]
    Inicio --> Checkout["#checkout"]
    Login --> Dashboard["/dashboard"]
    Registro --> Login
    Login --> Recuperar["/auth/recover"]
    Recuperar --> Reset["/auth/reset/{token}"]
    Dashboard --> Historial["/dashboard/history#filtros-historial"]
    Dashboard --> Config["/dashboard/settings#configuracion"]
    Dashboard --> Alimentador["/dashboard/settings#alimentador"]
    Dashboard --> Perfil["/dashboard/profile#perfil"]
    Dashboard --> Dispositivos["/dispositivos"]
    Dashboard --> Usuarios["/usuarios (admin)"]
    Dashboard --> Pedidos["/pedidos (admin)"]
```

El panel usa direcciones distintas para activar secciones, pero dibuja la misma vista `dashboard/index.php` y navega a anclas internas.

## Gestion de estado

### Estado server-side

- Sesion de CodeIgniter:
  - `user_id`,
  - `user_email`,
  - `user_nombre`,
  - `user_role`,
  - `logged_in`.
- Flashdata para mensajes y errores.
- Base de datos para usuarios, sensores, alertas, alimentaciones, configuracion, dispositivos y pedidos.

### Estado client-side

- `dashboard.js`:
  - mantiene `state` con lo que mando el backend,
  - lo actualiza con cada respuesta JSON,
  - vuelve a dibujar el DOM y el grafico.
- `purchase.js`:
  - calcula el resumen de compra,
  - monta el boton de Mercado Pago.
- Formularios:
  - validacion visual y errores temporales.

No se guarda nada en el navegador (ni token, ni usuario, ni datos de la pecera).

## Integracion con APIs

| Trigger frontend | Metodo/ruta | Tipo | Proposito |
| --- | --- | --- | --- |
| Confirmar compra | `POST /checkout/mercadopago/preference` | JSON | Crear preferencia de pago. |
| Regreso de Mercado Pago | `GET /checkout/mercadopago/success` | Redirect | Flash de pago aprobado. |
| Regreso de Mercado Pago | `GET /checkout/mercadopago/failure` | Redirect | Flash de pago rechazado. |
| Regreso de Mercado Pago | `GET /checkout/mercadopago/pending` | Redirect | Flash de pago pendiente. |
| Refresco del panel | `GET /dashboard/api/latest` | JSON | Datos actualizados. |
| Boton "Marcar leida" | `POST /dashboard/alerts/{id}/read` | JSON | Marcar alerta como leida. |
| Form "Alimentar ahora" | `POST /dashboard/control/feed` | form-urlencoded, responde JSON | Encolar la orden para el ESP32. |
| Form horarios | `POST /dashboard/control/feeding-schedule` | form-urlencoded, responde JSON | Guardar horarios y gramos. |
| Boton modo vacaciones | `POST /dashboard/control/vacation-toggle` | JSON | Alternar modo vacaciones. |
| Form temperatura objetivo | `POST /dashboard/control/target-temperature` | form-urlencoded, responde JSON | Guardar temperatura objetivo. |
| Form "Mi cuenta" | `POST /dashboard/profile` | HTML redirect | Actualizar nombre/email. |
| Formularios de cuenta | `POST /auth/*` | HTML redirect | Registro, login, recuperar, reset. |
| Formularios de dispositivos | `POST /dispositivos/*` | HTML redirect | ABM de dispositivos y API keys. |
| Formulario de usuarios | `POST /usuarios/editar/{id}` | HTML redirect | Edicion administrativa. |

## Ejemplos de flujo de ejecucion

### Login

```mermaid
sequenceDiagram
    participant U as Usuario
    participant F as login.php + auth.js
    participant B as Auth::login
    participant DB as usuarios
    participant S as Sesion
    U->>F: Ingresa email y password
    F->>F: Valida formato
    F->>B: POST /auth/login
    B->>DB: Busca usuario activo por email
    DB-->>B: Usuario + hash
    B->>B: Verifica password y throttle
    B->>S: Guarda user_id, rol y logged_in
    B-->>F: Redirect /dashboard
```

### Refresco del panel

```mermaid
sequenceDiagram
    participant F as dashboard.js
    participant API as Dashboard::latest
    participant P as PanelPecera
    F->>API: GET /dashboard/api/latest cada 5 s (pestana visible)
    API->>P: datos(filtros)
    P-->>API: tarjetas, resumen, alertas, alimentaciones, grafico, alimentador
    API-->>F: JSON
    F->>F: mezcla en state
    F->>F: vuelve a dibujar (el grafico solo si cambio)
```

### Alimentar ahora

```mermaid
sequenceDiagram
    participant U as Usuario
    participant F as feedNowForm
    participant API as Dashboard::feedNow
    participant Q as comandos_dispositivo
    participant E as ESP32
    U->>F: Carga gramos y envia
    F->>API: POST /dashboard/control/feed + X-CSRF-TOKEN
    API->>Q: Encola comando alimentar
    API-->>F: JSON con feeder.pending = true
    F->>F: Muestra "Orden en cola" y consulta cada 2 s
    E->>Q: Toma el comando, mueve el servo y confirma
    F->>F: Al confirmar, vuelve a dibujar la tabla y el estado
```

### Compra con Mercado Pago

```mermaid
sequenceDiagram
    participant U as Usuario
    participant F as purchase.js
    participant API as Checkout::mercadoPagoPreference
    participant MP as Mercado Pago
    U->>F: Email, cantidad, terminos
    F->>F: Valida pedido
    par
        F->>API: POST JSON preference
        API->>MP: Crea preferencia con SDK PHP
        MP-->>API: preferenceId
        API-->>F: JSON preferenceId
    and
        F->>MP: Descarga el SDK JS
    end
    F->>MP: Muestra el boton de pago
```

## Observaciones y mejoras posibles

- En celulares la barra de arriba solo muestra el logo y "Registrarse" (o "Salir"): los links "Iniciar sesion", "Panel principal" y "Dispositivos" se ocultan por debajo de 768 px y no hay menu desplegable.
- Chart.js, Mercado Pago y Google Fonts dependen de servicios externos. Si fallan o estan bloqueados, la experiencia queda degradada (sin grafico, sin boton de pago o con la tipografia del sistema).
- El checkbox "Recordarme" del login no tiene implementacion backend.
- Los links de redes del pie (`wa.me`, Instagram, GitHub) y el de "terminos y condiciones" del registro son genericos: falta poner los reales.
