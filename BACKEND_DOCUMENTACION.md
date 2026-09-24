# BACKEND_DOCUMENTACION

## Resumen general

El backend de AquaControl esta construido sobre CodeIgniter 4 con arquitectura MVC. El punto de entrada es `public/index.php`, las rutas estan en `app/Config/Routes.php`, los controladores en `app/Controllers`, los modelos en `app/Models` y la estructura de base de datos en `app/Database/Migrations`.

La aplicacion combina:

- Sitio publico de producto y checkout.
- Autenticacion con sesiones.
- Panel IoT con lecturas de sensores, alertas, alimentaciones y configuracion de pecera.
- CRUD de dispositivos por usuario, con API key propia para cada ESP32.
- API para dispositivos IoT (ingesta de lecturas y cola de comandos del alimentador con servo).
- Alimentacion manual y programada por horario.
- Administracion de usuarios y pedidos por rol.
- Integracion de checkout con Mercado Pago, con registro de pedidos y webhook.

No existe una capa formal de servicios de dominio. La logica de negocio vive principalmente en controladores y modelos, usando servicios nativos de CodeIgniter como `session`, `cache`, `email`, `response` y `db_connect()`.

## Tecnologias y dependencias backend

| Dependencia | Version / origen | Uso |
| --- | --- | --- |
| PHP | `^8.2` | Runtime requerido. |
| CodeIgniter 4 | `^4.7` | Framework MVC, routing, filtros, modelos, migraciones, sesiones. |
| MySQLi | Configuracion default | Base de datos principal. |
| `mercadopago/dx-php` | `3.10.0` | SDK PHP para crear preferencias de Mercado Pago. |
| SMTP | Config `Email.php` / `.env` | Envio de recuperacion de contrasena. |
| PHPUnit | `^10.5.16` | Tests. Actualmente hay tests starter. |
| Faker / vfsStream | dev | Soporte de tests starter. |

## Arquitectura

```mermaid
flowchart TD
    Client[Navegador / SDK externo] --> Front[public/index.php]
    Front --> Router[CodeIgniter Router]
    Router --> Filters[AuthFilter / RoleFilter]
    Filters --> Controller[Controlador]
    Controller --> Model[Modelo CodeIgniter]
    Model --> DB[(MySQL)]
    Controller --> View[Vista PHP]
    Controller --> JSON[Respuesta JSON]
    View --> Client
    JSON --> Client
```

Patrones usados:

- MVC server-side: controladores preparan datos y renderizan vistas.
- Active Record / Query Builder via modelos de CodeIgniter.
- Sesiones server-side con cookie `ci_session`.
- Respuestas mixtas: HTML para navegacion normal y JSON para dashboard/checkout.
- Configuracion sensible y comercial mediante `.env`.

## Estructura de carpetas backend

```text
app/
  Config/
    Routes.php
    Filters.php
    Database.php
    Email.php
    Security.php
    Session.php
    App.php
    ...
  Controllers/
    BaseController.php
    Home.php
    Auth.php
    Dashboard.php
    Checkout.php
    DeviceApi.php
    Dispositivos.php
    Pedidos.php
    Usuarios.php
  Filters/
    AuthFilter.php
    RoleFilter.php
    DeviceAuthFilter.php
    CsrfTokenHeaderFilter.php
  Libraries/
    DeviceAuth.php
  Models/
    UserModel.php
    SensorModel.php
    AlimentacionModel.php
    AlertaModel.php
    ComandoDispositivoModel.php
    ConfiguracionPeceraModel.php
    DispositivoModel.php
    PedidoModel.php
  Database/
    Migrations/
      2024-01-01-000001_CreateUsuariosTable.php
      2026-05-08-000002_CreateConfiguracionPeceraTable.php
      2026-05-29-000003_AddRolesAndLoginSecurityToUsuariosTable.php
      2026-05-29-000004_CreateDispositivosTable.php
      2026-09-23-000005_AddApiKeyToDispositivosTable.php
      2026-09-24-000006_CreateComandosDispositivoTable.php
      2026-09-24-000007_ConvertirFechasAHoraArgentina.php
      2026-09-24-000008_CreatePedidosTable.php
  Views/
firmware/
  aquacontrol_esp32_usb/aquacontrol_esp32_usb.ino   (modo USB: el que se usa hoy)
  puente_usb.ps1                                    (puente PC <-> pagina para el modo USB)
  config.example.ps1                                (copiar como config.local.ps1 con la API key; no se versiona)
  aquacontrol_esp32/aquacontrol_esp32.ino           (alternativa por WiFi)
iniciar_aquacontrol.bat                             (levanta MySQL, la pagina y el puente USB)
public/
  index.php
writable/
  cache/
  logs/
  session/
vendor/
```

## Punto de entrada y configuracion

### `public/index.php`

Front controller de CodeIgniter:

- Verifica PHP >= 8.2.
- Define `FCPATH`.
- Carga `app/Config/Paths.php`.
- Inicia `CodeIgniter\Boot::bootWeb($paths)`.

Todo request web debe entrar por `public/index.php`.

### `composer.json`

Define:

- Proyecto tipo `codeigniter4/appstarter`.
- Autoload PSR-4:
  - `App\` -> `app/`,
  - `Config\` -> `app/Config/`.
- Script `composer test` -> `phpunit`.

### `app/Config/App.php`

Config base:

- `baseURL` por defecto `http://localhost:8080/`, sobreescribible en `.env`.
- `indexPage = index.php`.
- `defaultLocale = en`.
- `appTimezone = America/Argentina/Buenos_Aires`. Todas las fechas se guardan y muestran en hora Argentina (la migracion `ConvertirFechasAHoraArgentina` corrigio los datos que estaban en UTC).
- `forceGlobalSecureRequests = false`.
- `CSPEnabled = false`.

### `app/Config/Database.php`

Conexion default:

- Driver `MySQLi`.
- Host `localhost`.
- Puerto `3306`.
- Credenciales y nombre de base normalmente sobreescritos desde `.env`.
- Charset `utf8mb4`.

Conexion de tests:

- SQLite en memoria.

### `app/Config/Email.php`

Fuerza protocolo SMTP:

- Host default Gmail.
- Puerto 587.
- Crypto TLS.
- Lee remitente, usuario, password y host desde `.env`.

Se usa en recuperacion de contrasena.

### `app/Config/Session.php`

Sesiones:

- Driver `FileHandler`.
- Cookie `ci_session`.
- Expiracion 7200 segundos.
- Save path `writable/session`.
- Regeneracion cada 300 segundos.

### `app/Config/Security.php`

Config CSRF:

- Proteccion por cookie.
- Cookie `csrf_cookie_name`.
- Header `X-CSRF-TOKEN`.
- Regenera token en cada submission.

El filtro `csrf` esta activo globalmente (ver `Filters.php`). Los formularios envian `csrf_field()` y las llamadas AJAX mandan el header `X-CSRF-TOKEN` leido de `<meta name="csrf-token">`. Como el token se regenera en cada POST, el filtro `csrfheader` devuelve el token nuevo en el header de respuesta y `window.AquaCsrf` (en `aqua.js`) lo actualiza en la pagina.

### `app/Config/Feeding.php`

Configuracion del alimentador automatico:

- `timezone`: zona en la que se interpretan los horarios (`America/Argentina/Buenos_Aires`).
- `scheduleWindowMinutes` (30): margen despues de la hora programada para disparar la racion si el ESP32 estuvo offline.
- `manualCommandTtlMinutes` (1): tiempo que espera un "Alimentar ahora" antes de expirar. Ademas, "Alimentar ahora" se rechaza si el ESP32 no esta en linea.
- `onlineThresholdSeconds` (30): sin contacto por mas tiempo, el dispositivo se muestra offline (el puente USB consulta cada 2 s).
- `minGrams` / `maxGrams`: limites de gramos por racion.

## Rutas y endpoints

Definidas en `app/Config/Routes.php`.

| Metodo | Ruta | Controlador | Filtro | Proposito |
| --- | --- | --- | --- | --- |
| GET | `/` | `Home::index` | publico | Landing y checkout. |
| POST | `/checkout/mercadopago/preference` | `Checkout::mercadoPagoPreference` | publico | Crear preferencia Mercado Pago. |
| GET | `/checkout/mercadopago/success` | `Checkout::mercadoPagoSuccess` | publico | Callback aprobado. |
| GET | `/checkout/mercadopago/failure` | `Checkout::mercadoPagoFailure` | publico | Callback fallido. |
| GET | `/checkout/mercadopago/pending` | `Checkout::mercadoPagoPending` | publico | Callback pendiente. |
| POST | `/checkout/mercadopago/webhook` | `Checkout::mercadoPagoWebhook` | publico, sin CSRF | Notificaciones de Mercado Pago. |
| GET/POST | `/auth/register` | `Auth::register` | publico | Alta de usuario. |
| GET/POST | `/auth/login` | `Auth::login` | publico | Login. |
| POST | `/auth/logout` | `Auth::logout` | publico + CSRF | Destruye sesion. |
| GET/POST | `/auth/recover` | `Auth::recover` | publico | Solicitud de recuperacion. |
| GET/POST | `/auth/reset/{token}` | `Auth::reset` | publico | Reset por token en URL. |
| GET/POST | `/auth/reset` | `Auth::reset` | publico | Reset por token POST/GET. |
| GET | `/dashboard` | `Dashboard::index` | `auth` | Panel principal. |
| GET | `/dashboard/history` | `Dashboard::history` | `auth` | Misma vista con historial activo. Acepta `?desde=AAAA-MM-DD&hasta=AAAA-MM-DD&dispositivo={id}`. |
| GET | `/dashboard/settings` | `Dashboard::settings` | `auth` | Misma vista con configuracion activa. |
| GET | `/dashboard/profile` | `Dashboard::profile` | `auth` | Misma vista con perfil activo. |
| GET | `/dashboard/api/latest` | `Dashboard::latest` | `auth` | Payload JSON actualizado (respeta los filtros del historial). |
| POST | `/dashboard/api/data` | `Dashboard::receiveData` | `deviceauth`, sin CSRF | El ESP32 envia lecturas con su API key. |
| GET | `/dashboard/api/commands` | `DeviceApi::commands` | `deviceauth` | El ESP32 retira comandos pendientes (alimentar). |
| POST | `/dashboard/api/commands/{id}/ack` | `DeviceApi::ack` | `deviceauth`, sin CSRF | El ESP32 confirma `ejecutado` o `fallido`. |
| POST | `/dashboard/profile` | `Dashboard::updateProfile` | `auth` | Actualiza nombre/email. |
| POST | `/dashboard/alerts/{id}/read` | `Dashboard::markAlertRead` | `auth` | Marca alerta leida. |
| POST | `/dashboard/control/feed` | `Dashboard::feedNow` | `auth` | Encola la orden de alimentar para el ESP32. |
| POST | `/dashboard/control/vacation-toggle` | `Dashboard::toggleVacation` | `auth` | Alterna modo vacaciones. |
| POST | `/dashboard/control/target-temperature` | `Dashboard::updateTargetTemperature` | `auth` | Guarda temperatura objetivo. |
| POST | `/dashboard/control/feeding-schedule` | `Dashboard::updateFeedingSchedule` | `auth` | Guarda horarios y gramos por racion. |
| GET | `/dispositivos` | `Dispositivos::index` | `auth` | Lista y alta de dispositivos. |
| POST | `/dispositivos/nuevo` | `Dispositivos::create` | `auth` | Crea dispositivo. |
| GET/POST | `/dispositivos/editar/{id}` | `Dispositivos::edit` | `auth` | Edita dispositivo. |
| POST | `/dispositivos/eliminar/{id}` | `Dispositivos::delete` | `auth` | Elimina dispositivo. |
| POST | `/dispositivos/api-key/{id}` | `Dispositivos::generateApiKey` | `auth` | Genera o regenera la API key (se muestra una sola vez). |
| POST | `/dispositivos/api-key/{id}/revocar` | `Dispositivos::revokeApiKey` | `auth` | Revoca la API key. |
| GET | `/usuarios` | `Usuarios::index` | `role:administrador` | Lista usuarios. |
| GET/POST | `/usuarios/editar/{id}` | `Usuarios::edit` | `role:administrador` | Edita usuario. |
| GET | `/pedidos` | `Pedidos::index` | `role:administrador` | Lista pedidos de la tienda (`?estado=` filtra). |

Existe override 404 que renderiza `app/Views/errors/404.php`.

## Filtros / middlewares

### `app/Filters/AuthFilter.php`

Protege rutas autenticadas:

- Verifica `session()->get('logged_in')`.
- Si falta, setea flash `error` y redirige a `auth/login`.

Se aplica a grupos `dashboard` y `dispositivos`.

### `app/Filters/RoleFilter.php`

Protege rutas por rol:

- Primero exige `logged_in`.
- Lee roles permitidos desde argumentos, por ejemplo `role:administrador`.
- Compara con `session()->get('user_role')`.
- Si no coincide, redirige a dashboard con flash de error.

Se aplica a los grupos `usuarios` y `pedidos`.

### `app/Filters/DeviceAuthFilter.php`

Protege la API de dispositivos, independiente de la sesion web:

- Lee la API key del header `X-Device-Key` o `Authorization: Bearer <key>`.
- Busca el dispositivo por el hash SHA-256 de la key (`App\Libraries\DeviceAuth`, servicio compartido `service('deviceAuth')`) y registra `ultima_conexion`.
- Sin key valida responde `401` JSON. El controlador obtiene el dispositivo con `service('deviceAuth')->device()`.

### `app/Filters/CsrfTokenHeaderFilter.php`

Filtro `after` global: agrega el header `X-CSRF-TOKEN` con el token vigente para que el JS lo renueve tras cada POST.

### `app/Config/Filters.php`

Aliases relevantes:

- `auth` -> `App\Filters\AuthFilter`.
- `role` -> `App\Filters\RoleFilter`.
- `deviceauth` -> `App\Filters\DeviceAuthFilter`.
- `csrfheader` -> `App\Filters\CsrfTokenHeaderFilter`.
- `csrf`, `toolbar`, `honeypot`, `secureheaders`, `cors`, etc.

Globales activos: `csrf` (before) y `csrfheader` (after), excepto en `dashboard/api/data`, `dashboard/api/commands*` y `checkout/mercadopago/webhook`, que no usan sesion de navegador. `honeypot`, `invalidchars` y `secureheaders` siguen inactivos.

## Controladores

### `BaseController.php`

Extiende `CodeIgniter\Controller`. Actualmente no precarga helpers, modelos ni servicios. Sirve como base comun para todos los controladores.

### `Home.php`

Responsabilidad: renderizar landing y preparar configuracion de compra.

`index()`:

- Lee variables `commerce.*` y `mercadopago.*` desde `.env`.
- Calcula datos de producto:
  - sku,
  - nombre,
  - headline,
  - descripcion,
  - precio unitario,
  - moneda,
  - maximo de cantidad,
  - mensaje de entrega.
- Calcula datos de pago:
  - locale,
  - URL de preferencia Mercado Pago,
  - public key Mercado Pago.

Mercado Pago es el unico medio de pago (PayPal fue retirado).
- Renderiza `home/index` con assets de compra.

La logica de negocio es comercial: permite que el producto y el checkout se configuren sin tocar la vista.

### `Auth.php`

Responsabilidad: ciclo de vida de usuarios y sesiones.

Constantes:

- `MAX_LOGIN_ATTEMPTS = 5`.
- `LOGIN_LOCK_MINUTES = 15`.
- `PASSWORD_RULE`: minimo 8, minuscula, mayuscula, numero y caracter especial.

Metodos publicos:

- `register()`: GET muestra formulario; POST procesa alta.
- `login()`: GET muestra formulario; POST autentica.
- `logout()`: solo POST con token CSRF; destruye sesion y redirige a login.
- `recover()`: GET muestra formulario; POST genera token y envia mail si el usuario existe.
- `reset($token)`: valida token, muestra formulario o actualiza password.

Flujo de registro:

```mermaid
sequenceDiagram
    participant F as Form registro
    participant A as Auth
    participant U as UserModel
    participant DB as usuarios
    F->>A: POST nombre/email/password
    A->>A: Valida reglas
    A->>U: registrar()
    U->>U: normaliza email y hashea password
    U->>DB: INSERT usuario activo
    A-->>F: Redirect login con flash success
```

Flujo de login:

- Valida email y password requeridos.
- Normaliza email.
- Revisa throttle cache por email + IP.
- Busca usuario activo por email.
- Si el usuario esta bloqueado por DB, rechaza.
- Verifica password con `password_verify`.
- Si falla, incrementa cache y contador DB.
- Si pasa, limpia intentos, setea sesion y regenera ID.

Datos de sesion:

```text
user_id
user_email
user_nombre
user_role
logged_in = true
```

Flujo de recuperacion:

- Valida email.
- Si existe usuario activo:
  - genera token aleatorio,
  - guarda hash SHA-256 del token,
  - guarda expiracion de 1 hora,
  - envia email HTML.
- Siempre responde con mensaje neutral para evitar enumeracion de cuentas.

### `Dashboard.php`

Responsabilidad: panel IoT, APIs del dashboard, perfil y acciones de control.

Modelos usados:

- `SensorModel`.
- `AlimentacionModel`.
- `AlertaModel`.
- `ComandoDispositivoModel`.
- `ConfiguracionPeceraModel`.
- `DispositivoModel`.
- `UserModel`.

Tambien usa `db_connect()` para verificar si existen tablas antes de consultar.

Constante `DEFAULT_CONFIG`:

- temperatura minima y maxima,
- pH minimo y maximo,
- temperatura objetivo,
- modo vacaciones,
- horarios de alimentacion (`hora_alim_1`, `hora_alim_2`) y gramos por racion.

Metodos de navegacion:

- `index()`: overview.
- `history()`: misma vista con historial activo.
- `settings()`: misma vista con configuracion activa.
- `profile()`: misma vista con perfil activo.

Metodos JSON / acciones:

- `latest()`: devuelve payload actualizado (con los filtros del historial de la query string).
- `receiveData()`: inserta una lectura del ESP32. El usuario y el dispositivo salen de la API key (filtro `deviceauth`), no de la sesion. Acepta JSON o form-urlencoded, valida rangos (pH 0-14, etc.) y responde `201` con `temp_objetivo` y `modo_vacaciones` vigentes, o `422` si los datos son invalidos.
- `markAlertRead($alertId)`: marca alerta no leida del usuario.
- `feedNow()`: encola un comando `alimentar` para el ESP32 (no registra la alimentacion: eso ocurre cuando el dispositivo confirma). Rechaza si no hay alimentador con API key, si ya hay una orden en curso o si los gramos estan fuera de rango.
- `updateFeedingSchedule()`: guarda `hora_alim_1`, `hora_alim_2` (vacios = desactivado) y `cantidad_alim_gramos`.
- `toggleVacation()`: cambia `modo_vacaciones`.
- `updateTargetTemperature()`: guarda `temp_objetivo`.
- `updateProfile()`: actualiza nombre/email del usuario.

Payload principal:

```text
config
cards
alerts
feedings
charts            (labels, temperature, ph, count)
feeder            (hasDevice, online, pending, statusLevel, statusText, lastText, scheduleText)
latestTimestamp
endpoints
userName
```

Filtros del historial (`resolveHistoryFilters()`): `desde`/`hasta` en formato `AAAA-MM-DD` y `dispositivo` (solo propios). Sin rango se muestran las ultimas 24 h. Las series de mas de 500 puntos se promedian por tramos (`downsample()`). Las lecturas anteriores a la API key no tienen `dispositivo_id` y solo aparecen con "Todos los dispositivos".

Logica de negocio central:

- `ensureConfig()` garantiza una configuracion por usuario o usa defaults si no hay tabla/registro.
- `buildCards()` convierte datos crudos en tarjetas legibles para UI:
  - temperatura con estado segun rango,
  - pH con estado segun rango,
  - nivel de agua OK/Bajo,
  - calefactor Encendido/Apagado,
  - ultima alimentacion,
  - modo vacaciones.
- `rangeStatus()` clasifica valores como `ok`, `warn`, `danger` o `neutral`.
- `fetchSensorHistory()` aplica el rango y dispositivo elegidos (por defecto ultimas 24 horas).
- `buildFeederState()` arma el estado del alimentador para la UI.
- `fetchAlerts()` trae hasta 5 alertas no leidas.
- `fetchFeedings()` trae ultimas 10 alimentaciones.

Ejemplo de procesamiento de `latest()`:

```mermaid
flowchart TD
    A[GET dashboard/api/latest] --> B[userId desde sesion]
    B --> C[ensureConfig]
    B --> D[ultima lectura sensores]
    B --> E[alertas no leidas]
    B --> F[ultimas alimentaciones]
    B --> G[historial 24h]
    C --> H[buildCards]
    D --> H
    E --> I[JSON payload]
    F --> I
    G --> I
    H --> I
```

### `Checkout.php`

Responsabilidad: integracion de compra con Mercado Pago.

`mercadoPagoPreference()`:

- Lee JSON del request.
- Valida forma del pedido:
  - metodo `mercadopago`,
  - precio configurado,
  - sku correcto,
  - cantidad dentro del maximo,
  - email valido.
- Lee access token de `.env`.
- Configura SDK Mercado Pago.
- Crea preferencia con item, payer, back URLs, referencia externa, descriptor y metadata.
- Guarda el pedido en `pedidos` con estado `pendiente`, la referencia externa y el id de preferencia.
- Devuelve `preferenceId`, `initPoint` y `sandboxInitPoint`.

Callbacks (`mercadoPagoSuccess()`, `mercadoPagoFailure()`, `mercadoPagoPending()`):

- No confian en los parametros de la URL: toman `payment_id`, consultan el pago real a la API (`PaymentClient::get`) y actualizan el pedido.
- El mensaje flash se arma con el estado verificado y redirige a `/#checkout`.

`mercadoPagoWebhook()`:

- Recibe notificaciones `type=payment` (JSON o query `data.id`) y vuelve a consultar el pago a la API.
- Si esta definido `mercadopago.webhookSecret`, valida la firma `x-signature` (HMAC SHA-256 de `id`, `request-id` y `ts`).
- Responde `500` si la API de Mercado Pago falla, para que Mercado Pago reintente.
- La `notification_url` se toma de `mercadopago.notificationUrl`; si no esta definida y el sitio corre con HTTPS se usa `/checkout/mercadopago/webhook`. En `localhost` Mercado Pago no puede notificar: hace falta una URL publica (por ejemplo ngrok).

`PedidoModel::aplicarPagoMercadoPago()` mapea el estado del pago (`approved` -> `aprobado`, `rejected` -> `rechazado`, etc.). Un pago aprobado por otro monto u otra moneda queda `en_proceso` con detalle `monto_no_coincide` para revision manual.

### `DeviceApi.php`

Endpoints que consume el ESP32 (filtro `deviceauth`):

- `commands()`: genera los comandos programados que correspondan (`ComandoDispositivoModel::programarAlimentaciones`) y entrega los pendientes, marcandolos `enviado`. Los dispositivos tipo `sensor` no reciben comandos.
- `ack($id)`: recibe `{"estado": "ejecutado" | "fallido", "mensaje": "..."}`. Si fue `ejecutado`, registra la alimentacion (`manual`, `automatica` o `vacaciones` segun origen y modo).

### `Pedidos.php`

Panel de administracion (`role:administrador`) con el listado de pedidos, resumen por estado y filtro `?estado=`.

### `Dispositivos.php`

Responsabilidad: CRUD de dispositivos del usuario autenticado.

Logica:

- `index()` lista dispositivos del usuario actual.
- `create()` arma payload con `usuario_id` de sesion y datos del POST.
- `edit($id)` primero busca por `id` y `usuario_id`; si no coincide, redirige.
- `delete($id)` tambien exige pertenencia por usuario.
- `generateApiKey($id)` crea una key `aqk_` + 48 hex. Solo se guarda su hash SHA-256 y un prefijo visible; la key completa se muestra una unica vez (flash). Regenerarla invalida la anterior.
- `revokeApiKey($id)` borra la key: el dispositivo deja de poder enviar datos.

Tipos permitidos:

- `sensor`,
- `actuador`,
- `controlador`,
- `kit_iot`,
- `otro`.

### `Usuarios.php`

Responsabilidad: administracion de usuarios.

Solo accesible a administradores por `RoleFilter`.

Logica:

- `index()` lista usuarios por fecha de creacion descendente.
- `edit($id)` muestra formulario o procesa POST.
- `updateUser()` valida nombre, rol y password opcional.
- `wouldRemoveLastAdmin()` impide que el ultimo admin activo pierda su rol.
- Si el admin edita su propia cuenta, actualiza `user_nombre` y `user_role` en sesion.

## Modelos

### `UserModel.php`

Tabla: `usuarios`.

Campos permitidos:

```text
nombre, email, password, rol,
token_recuperacion, token_expira,
login_intentos, bloqueado_hasta,
activo, created_at, updated_at
```

Roles:

- `administrador`,
- `usuario`,
- `tecnico`.

Reglas:

- nombre requerido,
- email valido y unico,
- password fuerte,
- rol dentro de lista permitida.

Callbacks:

- `normalizeEmail`: lowercase + trim.
- `hashPassword`: bcrypt cost 12 en insert.
- `hashPasswordOnUpdate`: bcrypt cost 12 si cambia password.

Metodos de negocio:

- `findByEmail($email)`: solo usuarios activos.
- `verifyPassword($plain, $hash)`.
- `generarTokenRecuperacion($userId)`: token aleatorio, guarda hash y expiracion.
- `findByTokenValido($token)`: busca hash valido y activo; tambien tolera token plano legacy.
- `restablecerPassword($userId, $nuevaPassword)`.
- `registrar($datos)`: primer usuario queda admin; siguientes quedan usuario comun.
- `registrarIntentoFallido()`: contador DB y bloqueo temporal.
- `resetearSeguridadLogin()`.
- `estaBloqueado()` y `segundosBloqueoRestantes()`.
- `roleLabel()` e `isValidRole()`.

### `SensorModel.php`

Tabla: `lecturas_sensores`.

Campos:

```text
usuario_id, dispositivo_id, temperatura, ph, turbidez, nivel_agua,
calefactor, modo_vacaciones, created_at
```

Metodos:

- `ultimaLectura($userId)`: ultima por fecha.
- `historial($userId, $hours = 24)`: lecturas de las ultimas horas.
- `historialRango($userId, $desde, $hasta, $deviceId = null)`: lecturas entre dos fechas, opcionalmente de un dispositivo.

Representa la telemetria IoT del acuario.

### `AlimentacionModel.php`

Tabla: `alimentaciones`.

Campos:

```text
usuario_id, cantidad_gramos, tipo, created_at
```

Tipos segun migracion:

- `manual`,
- `automatica`,
- `vacaciones`.

Metodos:

- `ultimasPorUsuario($userId, $limit = 10)`.
- `ultimaPorUsuario($userId)`.

### `AlertaModel.php`

Tabla: `alertas`.

Campos:

```text
usuario_id, nivel, tipo, mensaje, leida, created_at
```

Metodos:

- `noLeidasPorUsuario($userId, $limit = 5)`.

Niveles:

- 1 aviso,
- 2 alerta,
- 3 critico.

### `ConfiguracionPeceraModel.php`

Tabla: `configuracion_pecera`.

Campos:

```text
usuario_id, temp_min, temp_max, ph_min, ph_max, temp_objetivo,
hora_alim_1, hora_alim_2, cantidad_alim_gramos, modo_vacaciones,
created_at, updated_at
```

`hora_alim_1` y `hora_alim_2` (TIME, hora Argentina) son los horarios del alimentador; `NULL` desactiva ese horario.

Metodo:

- `porUsuario($userId)`.

Relacionalmente es 1 a 1 con `usuarios` por unique key `usuario_id`.

### `DispositivoModel.php`

Tabla: `dispositivos`.

Campos:

```text
usuario_id, nombre, tipo, ubicacion, api_key_hash, api_key_prefijo,
api_key_generada_at, ultima_conexion, created_at, updated_at
```

Validaciones:

- usuario requerido,
- nombre requerido,
- tipo dentro de lista,
- ubicacion requerida.

Metodos:

- `porUsuario($userId)`.
- `buscarParaUsuario($deviceId, $userId)`.
- `generarApiKey($deviceId)`, `revocarApiKey($deviceId)`, `buscarPorApiKey($key)`, `registrarConexion($deviceId)`.

### `ComandoDispositivoModel.php`

Tabla: `comandos_dispositivo`. Cola de ordenes para el ESP32 (hoy solo `alimentar`).

Ciclo de vida: `pendiente` -> `enviado` (el ESP32 lo tomo) -> `ejecutado` | `fallido`. Un pendiente vencido pasa a `expirado`. La entrega es "a lo sumo una vez": un comando enviado no se reenvia (mejor saltear una racion que alimentar dos veces).

- `crearAlimentacionManual($userId, $gramos)`.
- `programarAlimentaciones($userId, $config)`: crea los comandos de los horarios vencidos dentro del margen. Se ejecuta cada vez que el ESP32 consulta, sin cron. El indice unico `(usuario_id, accion, programado_para)` evita duplicados.
- `reclamarPendientes($device)`: entrega y marca `enviado` con un UPDATE condicionado, para que dos dispositivos no tomen el mismo comando.
- `finalizar($id, $deviceId, $estado, $mensaje)`.

### `PedidoModel.php`

Tabla: `pedidos`. Estados: `pendiente`, `en_proceso`, `aprobado`, `rechazado`, `cancelado`, `reembolsado`.

- `porReferencia($ref)`, `listado($estado)`, `resumen()`.
- `aplicarPagoMercadoPago($pago)`: actualiza el pedido con un pago consultado a la API.

## Base de datos

### Entidades y relaciones

```mermaid
erDiagram
    USUARIOS ||--o{ LECTURAS_SENSORES : registra
    USUARIOS ||--o{ ALIMENTACIONES : tiene
    USUARIOS ||--o{ ALERTAS : recibe
    USUARIOS ||--|| CONFIGURACION_PECERA : configura
    USUARIOS ||--o{ DISPOSITIVOS : posee
    DISPOSITIVOS ||--o{ LECTURAS_SENSORES : envia
    USUARIOS ||--o{ COMANDOS_DISPOSITIVO : ordena
    DISPOSITIVOS ||--o{ COMANDOS_DISPOSITIVO : ejecuta
    USUARIOS |o--o{ PEDIDOS : compra

    USUARIOS {
        int id PK
        varchar nombre
        varchar email UK
        varchar password
        varchar rol
        varchar token_recuperacion
        datetime token_expira
        tinyint login_intentos
        datetime bloqueado_hasta
        tinyint activo
        datetime created_at
        datetime updated_at
    }

    LECTURAS_SENSORES {
        int id PK
        int usuario_id FK
        decimal temperatura
        decimal ph
        decimal turbidez
        tinyint nivel_agua
        tinyint calefactor
        tinyint modo_vacaciones
        datetime created_at
    }

    ALIMENTACIONES {
        int id PK
        int usuario_id FK
        decimal cantidad_gramos
        enum tipo
        datetime created_at
    }

    ALERTAS {
        int id PK
        int usuario_id FK
        tinyint nivel
        varchar tipo
        varchar mensaje
        tinyint leida
        datetime created_at
    }

    CONFIGURACION_PECERA {
        int id PK
        int usuario_id FK_UK
        decimal temp_min
        decimal temp_max
        decimal ph_min
        decimal ph_max
        decimal temp_objetivo
        time hora_alim_1
        time hora_alim_2
        decimal cantidad_alim_gramos
        tinyint modo_vacaciones
        datetime created_at
        datetime updated_at
    }

    DISPOSITIVOS {
        int id PK
        int usuario_id FK
        varchar nombre
        varchar tipo
        varchar ubicacion
        char api_key_hash UK
        varchar api_key_prefijo
        datetime api_key_generada_at
        datetime ultima_conexion
        datetime created_at
        datetime updated_at
    }

    COMANDOS_DISPOSITIVO {
        int id PK
        int usuario_id FK
        int dispositivo_id FK
        varchar accion
        text parametros
        varchar origen
        varchar estado
        datetime programado_para
        datetime expira_at
        datetime enviado_at
        datetime finalizado_at
        varchar mensaje
    }

    PEDIDOS {
        int id PK
        varchar referencia UK
        int usuario_id FK
        varchar email
        int cantidad
        decimal total
        char moneda
        varchar preferencia_id
        varchar pago_id
        varchar estado
        decimal monto_pagado
        datetime pagado_at
    }
```

### Migraciones

#### `2024-01-01-000001_CreateUsuariosTable.php`

Crea:

- `usuarios`.
- `lecturas_sensores`.
- `alimentaciones`.
- `alertas`.

Todas las tablas IoT tienen FK a `usuarios.id` con cascade update/delete.

#### `2026-05-08-000002_CreateConfiguracionPeceraTable.php`

Crea `configuracion_pecera`:

- Unique key en `usuario_id`.
- FK a `usuarios`.
- Defaults de temperatura, pH, objetivo y modo vacaciones.

#### `2026-05-29-000003_AddRolesAndLoginSecurityToUsuariosTable.php`

Agrega a `usuarios`:

- `rol`,
- `login_intentos`,
- `bloqueado_hasta`.

Ademas promueve el primer usuario existente a administrador si no hay admin.

#### `2026-05-29-000004_CreateDispositivosTable.php`

Crea `dispositivos` con FK a `usuarios`.

#### `2026-09-23-000005_AddApiKeyToDispositivosTable.php`

Agrega a `dispositivos` las columnas de API key (hash unico, prefijo, fecha de generacion, ultima conexion) y a `lecturas_sensores` la columna `dispositivo_id` (FK con `ON DELETE SET NULL` e indice `(dispositivo_id, created_at)`).

#### `2026-09-24-000006_CreateComandosDispositivoTable.php`

Crea `comandos_dispositivo` (cola de ordenes del alimentador).

#### `2026-09-24-000007_ConvertirFechasAHoraArgentina.php`

Resta 3 horas a todas las columnas `DATETIME` (UTC -> hora Argentina) al pasar `appTimezone` a `America/Argentina/Buenos_Aires`. `down()` las vuelve a UTC.

#### `2026-09-24-000008_CreatePedidosTable.php`

Crea `pedidos`.

## Flujos de procesamiento

### Render inicial del dashboard

```mermaid
sequenceDiagram
    participant N as Navegador
    participant F as AuthFilter
    participant D as Dashboard::index
    participant M as Modelos
    participant V as dashboard/index.php
    N->>F: GET /dashboard con cookie de sesion
    F->>F: valida logged_in
    F->>D: request autorizado
    D->>M: obtiene config, lectura, historial, alertas, alimentaciones
    M-->>D: arrays
    D->>D: buildDashboardPayload + endpoints
    D->>V: render con dashboardData
    V-->>N: HTML + JSON hidratado
```

### Insercion de lectura de sensores

```mermaid
sequenceDiagram
    participant C as ESP32
    participant F as DeviceAuthFilter
    participant D as Dashboard::receiveData
    participant S as SensorModel
    participant DB as lecturas_sensores
    C->>F: POST /dashboard/api/data + X-Device-Key
    F->>F: busca dispositivo por hash de la key (401 si no existe)
    F->>D: request autorizado
    D->>D: valida valores (422 si son invalidos)
    D->>S: insert usuario_id + dispositivo_id + lecturas
    S->>DB: INSERT
    D-->>C: 201 {lectura_id, config}
```

### Alimentador (servo)

```mermaid
sequenceDiagram
    participant U as Usuario (dashboard)
    participant W as Dashboard
    participant Q as comandos_dispositivo
    participant E as ESP32
    participant A as DeviceApi
    U->>W: POST control/feed (o llega un horario programado)
    W->>Q: INSERT comando alimentar (pendiente)
    loop cada 5 s
        E->>A: GET api/commands + X-Device-Key
        A->>Q: programa horarios vencidos y marca pendientes como enviados
        A-->>E: [{id, accion: alimentar, gramos}]
    end
    E->>E: mueve el servo
    E->>A: POST api/commands/{id}/ack {estado: ejecutado}
    A->>Q: estado ejecutado
    A->>A: registra alimentacion en historial
```

El servidor no puede enviar ordenes directamente al ESP32, por eso el dispositivo consulta la cola. Hay dos formas de conectarlo:

**Modo USB (el que se usa hoy).** El ESP32 esta enchufado por USB a la PC que corre la pagina y no usa WiFi.

- Firmware: `firmware/aquacontrol_esp32_usb` (DS18B20 en GPIO 18, servo SG90 en GPIO 19). Protocolo de lineas por el puerto serie a 115200: el ESP32 envia `T:24.56` cada 2 s y `ACK:<id>:OK` al terminar de alimentar; la PC envia `FEED:<id>:<gramos>`.
- Puente: `firmware/puente_usb.ps1` detecta el puerto (CP210x/CH340), envia la temperatura a `/dashboard/api/data` cada 5 s, consulta `/dashboard/api/commands` cada 2 s (solo si el ESP32 esta respondiendo) y confirma cada orden. Si el ESP32 no confirma en 60 s, la orden se marca `fallido`. Lee la API key de `firmware/config.local.ps1` (ignorado por git).
- `iniciar_aquacontrol.bat` levanta todo con doble clic. El Monitor Serie del Arduino IDE tiene que estar cerrado (solo un programa puede usar el puerto COM).
- Cada orden hace un solo giro del servo (ida a `SERVO_ABIERTO`, espera `MS_ABIERTO`, vuelta a `SERVO_CERRADO`), sin importar los gramos; los gramos quedan solo en el historial. La cantidad de comida se ajusta con `MS_ABIERTO` y `SERVO_ABIERTO`.

**Modo WiFi (alternativa).** `firmware/aquacontrol_esp32/aquacontrol_esp32.ino` habla directo con la API. El servidor debe escuchar en la red (`php spark serve --host 0.0.0.0`) y el ESP32 debe estar en una red WiFi de 2.4 GHz.

### Actualizacion de perfil

```mermaid
flowchart TD
    A[POST dashboard/profile] --> B[Buscar usuario por session user_id]
    B --> C[Validar nombre y email unico]
    C --> D{Cambio email?}
    D -->|si| E[Exigir password actual]
    D -->|no| F[Actualizar nombre]
    E --> G[Verificar password]
    G --> H[Actualizar nombre/email y limpiar tokens/lockouts]
    F --> I[Actualizar sesion]
    H --> I
    I --> J[Regenerar sesion y redirect a perfil]
```

### Checkout Mercado Pago

```mermaid
flowchart TD
    A[POST checkout/mercadopago/preference] --> B[Leer JSON]
    B --> C[Validar metodo, sku, cantidad, email, precio]
    C --> D[Leer access token .env]
    D --> E[Configurar MercadoPagoConfig]
    E --> F[buildPreferenceRequest]
    F --> G[PreferenceClient create]
    G --> P[Guardar pedido pendiente]
    P --> H[Responder preferenceId/initPoint]
    H --> I[Comprador paga en Mercado Pago]
    I --> J[back_url success/failure/pending con payment_id]
    I --> K[webhook POST checkout/mercadopago/webhook]
    J --> L[PaymentClient get: estado real del pago]
    K --> L
    L --> M[Actualizar pedido: aprobado / rechazado / pendiente]
```

## Autenticacion y autorizacion

### Autenticacion

El login es por email y password:

- Email se normaliza a lowercase.
- Password se almacena con bcrypt cost 12.
- Se usa `password_verify` para validar.
- Despues de login exitoso se llama `session()->regenerate(true)`.

### Lockout y throttling

Hay dos capas:

- Cache por email + IP:
  - clave hash `login_attempts_*`,
  - 5 intentos,
  - bloqueo 15 minutos.
- DB por usuario:
  - `login_intentos`,
  - `bloqueado_hasta`.

Si el usuario no existe, igual se registra throttle en cache por email+IP.

### Recuperacion de contrasena

- Genera token aleatorio de 32 bytes.
- Guarda SHA-256 del token.
- Expira en 1 hora.
- Email HTML con link `auth/reset/{token}`.
- Al resetear, limpia token, expiracion, intentos y bloqueo.

### Autorizacion

- Rutas `dashboard` y `dispositivos`: requieren `logged_in`.
- Rutas `usuarios` y `pedidos`: requieren rol `administrador`.
- API de dispositivos (`dashboard/api/data`, `dashboard/api/commands*`): requieren API key de dispositivo; el usuario sale del dispositivo, no de la sesion.
- Dispositivos se consultan por `id` + `usuario_id`, evitando editar recursos de otros usuarios.
- Alertas se marcan leidas solo si pertenecen al usuario en sesion.

## Seguridad implementada

| Area | Estado actual |
| --- | --- |
| Passwords | bcrypt cost 12. |
| Sesion | Server-side file session, cookie HTTPOnly, SameSite Lax. |
| Regeneracion de sesion | Al login y al actualizar perfil. |
| Roles | `administrador`, `usuario`, `tecnico`. |
| Lockout login | Cache por email/IP y campos DB por usuario. |
| Tokens recovery | Token aleatorio, hash en DB, expiracion 1 hora. |
| CSRF | Filtro global activo; formularios con `csrf_field()` y AJAX con header `X-CSRF-TOKEN`. |
| API de dispositivos | API key por dispositivo, guardada solo como hash SHA-256; revocable. |
| Webhook Mercado Pago | Re-consulta el pago a la API; firma `x-signature` opcional con `mercadopago.webhookSecret`. |
| CORS | Sin origen permitido por defecto; filtro no activo. |
| CSP | Desactivado en `App.php`. |
| Secure cookies | `secure = false`, apto local pero no ideal produccion. |
| Logout | POST `/auth/logout` con CSRF. |

## Relaciones entre modulos

```mermaid
flowchart LR
    Home --> Checkout
    Auth --> UserModel
    Dashboard --> UserModel
    Dashboard --> SensorModel
    Dashboard --> AlimentacionModel
    Dashboard --> AlertaModel
    Dashboard --> ConfiguracionPeceraModel
    Dashboard --> ComandoDispositivoModel
    DeviceApi --> ComandoDispositivoModel
    DeviceApi --> AlimentacionModel
    Checkout --> PedidoModel
    Pedidos --> PedidoModel
    Dispositivos --> DispositivoModel
    Usuarios --> UserModel
    AuthFilter --> Dashboard
    AuthFilter --> Dispositivos
    DeviceAuthFilter --> DeviceApi
    DeviceAuthFilter --> Dashboard
    RoleFilter --> Usuarios
    RoleFilter --> Pedidos
```

## Dependencias criticas y puntos de fallo

- Base de datos MySQL:
  - Si no corren migraciones, dashboard usa fallbacks en algunas lecturas, pero `receiveData()` devuelve 503 si falta `lecturas_sensores`.
- `.env`:
  - Define DB, SMTP, comercio y Mercado Pago. Credenciales incorrectas rompen login recovery, checkout o conexion DB.
- Mercado Pago:
  - `mercadopago.accessToken` es obligatorio para crear preferencia y verificar pagos.
  - Fallos de API devuelven 502 (preferencia) o 500 (webhook, para que Mercado Pago reintente).
  - El webhook necesita una URL publica; en `localhost` los pedidos solo se actualizan al volver del pago.
- ESP32:
  - Debe alcanzar el servidor por red (IP de la PC, `spark serve --host 0.0.0.0`).
  - Si esta offline mas de 30 min despues de un horario, esa racion se omite.
- SMTP:
  - Recovery depende de SMTP configurado.
  - El catch registra error pero la respuesta al usuario sigue siendo neutral.
- Sesiones en archivos:
  - Dependen de permisos en `writable/session`.
- Cache en archivos:
  - Throttle depende de `writable/cache`.
- SDKs externos:
  - La preferencia se genera con SDK PHP, pero el boton se monta con SDK JS externo.

## Tests

Existen tests starter:

- `tests/unit/HealthTest.php`: valida `APPPATH` y `baseURL`.
- `tests/database/ExampleDatabaseTest.php`: prueba modelo ejemplo del starter.
- `tests/session/ExampleSessionTest.php`: prueba basica de sesion.

No hay cobertura actual para:

- registro/login,
- lockout,
- recuperacion,
- dashboard payload,
- CRUD dispositivos,
- roles/admin,
- checkout Mercado Pago.

## Observaciones y mejoras posibles

- Validar rango de la temperatura objetivo en backend (sensores y gramos ya se validan).
- Enviar un email de confirmacion al comprador cuando un pedido pasa a `aprobado`.
- Limitar intentos fallidos contra la API de dispositivos (rate limit por IP) para frenar fuerza bruta de API keys.
- Revisar secretos en `.env` y rotarlos si fueron compartidos o quedaron versionados. La documentacion no debe incluir valores de credenciales.
- Activar `secureheaders` y CSP en produccion, ajustando fuentes externas necesarias: Chart.js, Mercado Pago y Google Fonts.
- Usar cookies `secure = true` y `forceGlobalSecureRequests = true` en HTTPS productivo.
- `Auth::findByTokenValido()` acepta token plano ademas de hash, util para compatibilidad pero menos estricto.
- `Dashboard::fromTable()` captura excepciones y devuelve fallback; mejora la UX pero puede ocultar errores de datos o migraciones.
- `welcome_message.php` y tests ejemplo son remanentes del starter.
- Hay caracteres con mojibake en comentarios y textos visibles; conviene normalizar a UTF-8.
