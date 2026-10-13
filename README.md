# test-manager-library-3145556-api

> **Prueba técnica · Ficha ADSO 3145556**
> Entrega: **martes 13 de octubre de 2026, a las 5:00 p. m.** (17:00, hora Colombia).

Este repositorio es el **backend** de *Manager Library*, en **PHP con Laravel**. **Empieza vacío a propósito**: se construye en el fork de cada aprendiz.

## Instrucciones

Cada aprendiz debe **crear el fork** de los seis repositorios del proyecto y **resolver el proyecto
con el spec planteado**.

1. Hacer fork, a su cuenta de GitHub, de cada repositorio de la tabla del final.
2. Leer el spec en [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs).
   Se entrega en dos versiones: `spec-python/` y `spec-.net/`.
3. Desarrollar en los forks.

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Entrega

Cuenta lo que quede **subido a los forks antes de las 5:00 p. m.** (17:00). Lo que llegue después no se revisa.
No hay prórroga: suban avances parciales con commits pequeños durante la prueba.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3145556-api`](https://github.com/code-sena/test-manager-library-3145556-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3145556-app`](https://github.com/code-sena/test-manager-library-3145556-app) | Frontend en React |
| [`test-manager-library-3145556-page`](https://github.com/code-sena/test-manager-library-3145556-page) | Sitio público estático de presentación |
| [`test-manager-library-3145556-infra`](https://github.com/code-sena/test-manager-library-3145556-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3145556-tool`](https://github.com/code-sena/test-manager-library-3145556-tool) | Utilidades: sembrador de datos de demostración |

---

## Stage 1: Laravel skeleton and health check

The original challenge instructions above are preserved. The implementation notes
below are in English as requested for this fork; user-facing messages must remain
in Spanish.

### Scope and architecture

Only `GET /api/v1/health` is implemented. It is public and returns HTTP 200 with
`Content-Type: application/json` and the exact body `{"status":"ok"}`.
This is a process health check, not a database readiness check.

- `app/Domain/`: reserved for framework-independent business rules; currently empty.
- `app/Application/`: reserved for use cases and consumer-defined ports; currently empty.
- `app/Infrastructure/Http/`: Laravel HTTP adapters, including the health controller.
- `app/Infrastructure/CompositionServiceProvider.php`: the single future port-to-adapter
  binding point, registered in `bootstrap/providers.php`; no bindings are needed yet.
- `bootstrap/app.php` and `routes/api.php`: framework startup and versioned API routing.

No authentication, business models, database migrations, seeds, CRUD, cover storage,
or frontend are implemented. The generated welcome page, `/up`, and filesystem
download routes are not registered. Laravel supports HEAD for the GET route.
No database engine or PDO database driver is needed for this stage.

The domain and application contain only placeholders: they have no Laravel,
Eloquent, HTTP, or third-party dependencies. The stage-one architecture test checks
that they remain empty. Dependency-boundary tests must replace this stage-specific
check when core code is introduced; this is not proof of a completed architecture.
Common API error formatting and Spanish error messages are also deferred.
The container startup evidence in documentation task T-01 is not provided by this
local-only stage; container orchestration remains in the infrastructure repository.
No ADR or product decision has been changed.

### Requirements and verified versions

- PHP 8.4.x (verified: 8.4.26 NTS x64 on Windows).
- Composer 2.x (verified: 2.10.3).
- Laravel 13.x (resolved framework: 13.36.0; scaffold: 13.11.0).
- PHPUnit 12.x and Laravel Pint, development dependencies only.
- PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl,
  pcre, PDO, session, tokenizer, xml. PHPUnit additionally uses xmlwriter;
  zip is enabled in the portable installation for Composer archives.

Laravel 13 supports PHP 8.3-8.5. This project selects PHP `^8.4` to match the
approved environment and PHPUnit 12; `composer.lock` records exact dependencies.
The official scaffold's unused optional development packages and Tinker were
excluded. Do not use `--ignore-platform-reqs`.

Official references:

- [Laravel release support](https://laravel.com/docs/13.x/releases).
- [Laravel server requirements](https://laravel.com/docs/13.x/deployment#server-requirements).
- [Official Windows PHP releases](https://downloads.php.net/~windows/releases/releases.json).
- [Composer 2.10.3](https://getcomposer.org/download/2.10.3/composer.phar).

The isolated portable tools are outside the repository at
`%LOCALAPPDATA%\TestManagerTools\php-8.4.26` and
`%LOCALAPPDATA%\TestManagerTools\composer-2.10.3`.
Only the portable PHP's `php.ini` was configured; no administrator access,
persistent PATH changes, or global environment changes are required.

Verified official SHA256 values:

```text
php-8.4.26-nts-Win32-vs17-x64.zip
da68394f9193b7f6b89d0c76861a4034ae10efee7fd55a7255d8118c2acf70d7

composer.phar (2.10.3)
7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6
```

### Install, run, and test on Windows

Open PowerShell in this repository. Run the following setup in each new terminal;
PATH changes last only for that PowerShell process and its child processes:

```powershell
$phpDirectory = Join-Path $env:LOCALAPPDATA 'TestManagerTools\php-8.4.26'
$php = Join-Path $phpDirectory 'php.exe'
$composer = Join-Path $env:LOCALAPPDATA 'TestManagerTools\composer-2.10.3\composer.phar'
$env:PATH = "$phpDirectory;$env:PATH"
& $php -v
& $php -m
& $php $composer --version
```

Install locked dependencies, then prepare a local environment file and key:

```powershell
& $php $composer install --no-interaction --prefer-dist
& $php $composer check-platform-reqs
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
}
& $php artisan key:generate
```

Never commit `.env`, application keys, or `vendor/`. No migration or Node.js
command is part of stage-one setup. Cache and session defaults do not require a database.

Start the API:

```powershell
& $php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal:

```powershell
curl.exe --fail --include http://127.0.0.1:8000/api/v1/health
```

Stop the development server with Ctrl+C. To inspect routes and run validation
after setting the portable variables in the current terminal:

```powershell
& $php artisan route:list --except-vendor
& $php $composer test
& $php vendor\bin\pint --test
& $php $composer validate --strict
```

Tests verify the exact public response, no database connection, the sole registered
health route, absence of default routes, and the reserved core-layer structure.

### Stage-one verification

Verified on the portable Windows environment described above:

- PHPUnit: 4 tests, 23 assertions, all passing.
- Laravel Pint: passing.
- Composer strict manifest validation and platform checks: passing.
- Route list: only `GET|HEAD api/v1/health`.
- Live HTTP request without credentials: HTTP 200, `application/json`,
  exact body `{"status":"ok"}`.

Stop here before implementing stage 2.

## Bloque funcional posterior

La sección anterior conserva la evidencia histórica de la etapa 1. Ahora hay
migraciones de las seis tablas, categorías fijas, JWT HS256 de ocho horas,
provisión de administrador por entorno, bibliotecarios, catálogo, socios,
préstamos, devoluciones individuales y reporte con snapshots históricos.
El dominio y los casos de uso no importan Laravel ni Eloquent.

Decisiones confirmadas por el usuario durante la implementación:

- Testigo optimista: `xmin` de PostgreSQL, solo dentro del adaptador; no se añade
  ninguna columna. Un guardado compara la versión y devuelve conflicto si cambió.
- Paginación: página 1 y tamaño 20 por defecto; entradas inválidas se rechazan
  con 422, tamaño entre 1 y 50.
- Errores nuevos confirmados: `UNAUTHENTICATED` (401) e `INTERNAL_ERROR` (500).
- Categorías como lista `{id,name}`; socios creados/listados con
  `{id,full_name,document_number}`, detalle con `active_loans`; préstamos usan
  el ejemplo existente; retiro de libros responde 204.

La transacción usa un advisory lock por socio para preservar el límite global
entre préstamos y devoluciones concurrentes. No bloquea pesimistamente los libros:
sus escrituras usan `xmin`. Una devolución actualiza `returned_at` solo si sigue
nulo, y el cambio de inventario pertenece a la misma transacción.

### Ejecutar y probar

Configurar `.env` local con PostgreSQL, `JWT_SECRET` de al menos 32 bytes y las
credenciales del administrador. Con las variables `$php` y `$composer` anteriores:

```powershell
& $php $composer install
& $php artisan migrate
& $php artisan library:provision-admin
& $php artisan serve --host=127.0.0.1 --port=8000
```

Las pruebas normales no requieren una base de datos:

```powershell
& $php $composer test
& $php vendor\bin\pint --test
```

Las pruebas de integración destruyen únicamente una base aislada denominada
`library_test`, con consentimiento explícito y credenciales suministradas por
variables de proceso. **Nunca configurarlas contra una base compartida.**

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '55432'
$env:DB_DATABASE = 'library_test'
# Configurar DB_USERNAME y DB_PASSWORD sin guardarlos en Git.
$env:LIBRARY_TEST_DESTRUCTIVE = 'yes'
& $php vendor\phpunit\phpunit\phpunit -c phpunit.pgsql.xml
```

La suite incluye dos procesos PHP independientes, solicitudes HTTP al kernel real
de Laravel y conexiones reales a PostgreSQL: último ejemplar (201/409) y
devolución simultánea (200/409). No usa un contador en memoria ni mocks de la base.
Esta prueba no sustituye una prueba de carga de transporte HTTP.

### Pendientes explícitos

- Portadas: no se han publicado sus operaciones; rol de descarga pendiente.
- Lista operativa de vencidos y redondeo de `days_late`: pendiente.
- Formato de fechas de filtros de préstamos: pendiente; esos parámetros se
  rechazan explícitamente mientras no se confirme el contrato.
- Modificación de ISBN: pendiente; el PUT conserva los campos documentados.
- T-18: CHECK de total positivo y de roles no adelantados al esquema inicial.
- Rendimiento RNF-1 y despliegue de producción: no verificados.
- El sembrador no puede crear vencidos inmediatos sin una decisión autorizada.

La configuración Docker sirve para desarrollo local, no para producción. No
se usa `migrate:fresh` en el arranque ni se eliminan datos existentes.
