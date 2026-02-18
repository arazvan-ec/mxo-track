claude --resume b6ad7773-e4bd-4c9b-8dd5-6ca95bdbe9a2

# transporte-tracking

Fase 1: bootstrap seguro base sobre Symfony **7.4 LTS** para portal logístico.

> Estándar del repo: **Symfony Flex + recipes** habilitado y **bloqueo estricto en 7.4 LTS** (sin componentes 8.x).

## Requisitos
- PHP 8.2+
- Composer 2+
- PostgreSQL 16+
- Redis 7+

## Estructura monorepo
- `backend/` Symfony
- `infra/` placeholders de infraestructura
- `docs/` documentación técnica

## Comandos base solicitados para la fase
```bash
composer create-project symfony/skeleton backend
composer require symfony/framework-bundle:^7.4
```

## Instalación local (estado actual del repo)
```bash
cp .env.example .env.local
cd backend
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
symfony server:start -d
```

## Login y sesión
- En APIs públicas DRIVER el campo de referencia de envío es `shipment_public_id` (no `shipment_id`).
- Endpoints públicos de Vehicle/Shipment usan `{publicId}` y exponen `public_id` en JSON.
- Login tradicional con `form_login`.
- Sesión en Redis (`RedisSessionHandler`) con prefijo `sess:transporte:`.
- Cookie de sesión: `HttpOnly=true`, `Secure=auto`, `SameSite=lax`.

## Turbo global
- Turbo se inyecta en `templates/base.html.twig` con `{{ turbo_include_tags() }}`.
- Nota de arquitectura: la pantalla de mapa realtime desactivará Turbo en su propia ruta en fases posteriores.

## Usuario ADMIN inicial
Fixture incluida: `App\DataFixtures\AdminUserFixture`.

Credenciales iniciales:
- email: `admin@transporte.local`
- password: `ChangeMe_123!` (cambiar en primer arranque)

## Mini-paso de verificación local (sin E2E)
```bash
bash scripts/phase1_signoff.sh
```

Este script valida: composer, consola Symfony, migraciones, fixtures y chequeo Redis (`sess:transporte:*`) cuando `redis-cli` está disponible.

## Checklist aceptación Fase 1
- [ ] composer install OK
- [ ] symfony console funciona
- [ ] migraciones OK
- [ ] login funciona y mantiene sesión
- [ ] Redis guarda sesiones (`sess:transporte:*`)
- [ ] Turbo activo globalmente
- [ ] Mercure opcional validado manualmente


## Verificación E2E local (criterio manual de Symfony funcionando)
```bash
bash scripts/symfony_e2e_boot_check.sh
```

Este check levanta `db`, `redis`, `mercure` y `traccar` con `docker-compose.local.yml`, ejecuta `composer install`, valida consola Symfony y migraciones en contenedor `app`, y comprueba respuesta de Mercure.

### Traccar (GPS tracking local)

El servicio `traccar` se incluye en `docker-compose.local.yml` con H2 embebida (sin MariaDB).

- **Web UI / API**: http://localhost:8082
- **Puerto GPS (protocolo osmand/etc.)**: `5055`
- **Credenciales por defecto**: `admin` / `admin` (se crean en el primer arranque)
- **Desde el contenedor app**: `curl http://traccar:8082/api/server`

Variables de entorno inyectadas en `app`:
```
TRACCAR_BASE_URL=http://traccar:8082
TRACCAR_USERNAME=admin
TRACCAR_PASSWORD=admin
```




## Arranque de aplicación (enfoque actual)
```bash
cd backend
composer install
php bin/console about
```

En esta etapa nos enfocamos en tener la aplicación Symfony arrancando correctamente; la estrategia de testing/CI se definirá en fases posteriores.




## Flujo de validación entre fases (continuidad + decisiones)
```bash
bash scripts/phase_flow_validate.sh
```

Este flujo revisa encaje entre decisiones de fases previas y estado actual (Symfony 7.4, contratos de Fase 2, rutas críticas, realtime map/Mercure) y genera un reporte con recomendaciones en `docs/PHASE_FLOW_VALIDATION.md`.


## Cierre formal de Fase 2
- Ver definición de cierre en `docs/PHASE2_SIGNOFF.md`.
- Este documento fija el “done” de Fase 2 para evitar reabrir decisiones en Fase 3.


## Preparación documental de Fase 3
- Plantilla inicial disponible en `docs/PHASE3_SIGNOFF.md`.
- Se usa como marco de “done” vivo mientras se implementa Fase 3.
