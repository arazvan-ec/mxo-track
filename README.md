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

Este check levanta `db`, `redis` y `mercure` con `docker-compose.local.yml`, ejecuta `composer install`, valida consola Symfony y migraciones en contenedor `app`, y comprueba respuesta de Mercure.




## Arranque de aplicación (enfoque actual)
```bash
cd backend
composer install
php bin/console about
```

En esta etapa nos enfocamos en tener la aplicación Symfony arrancando correctamente; la estrategia de testing/CI se definirá en fases posteriores.


