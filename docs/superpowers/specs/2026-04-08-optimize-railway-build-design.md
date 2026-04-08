# Spec — Optimize Railway Docker Build

**Fecha:** 2026-04-08
**Tipo:** Infrastructure optimization
**Enfoque aprobado:** A — Layer caching optimizado

## Problema

El build de `Dockerfile.railway` tarda ~3:21 en cada deploy porque:
1. No existe `.dockerignore` → build context incluye `.git/`, `docs/`, `tools/`, etc. (~8MB innecesario)
2. `COPY backend/ /app/` antes de `composer install` invalida el layer cache en cada cambio de código PHP → reinstala todas las dependencias (~30-45s) innecesariamente

## Diseño

### 1. Crear `.dockerignore`

Excluir del build context todo lo que no necesita el Dockerfile:
- `.git/`, `docs/`, `tools/`, `ml-service/`, `docker/` (excepto archivos referenciados)
- Dockerfiles no usados, `*.md`, `.claude/`, `.github/`
- `node_modules/`, `vendor/` locales

### 2. Reestructurar Stage 2 (PHP) para cachear `composer install`

**Antes:**
```dockerfile
COPY backend/ /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction
```

**Después:**
```dockerfile
COPY backend/composer.json backend/composer.lock backend/symfony.lock /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
COPY backend/ /app/
RUN composer run-script post-install-cmd --no-interaction 2>/dev/null || true
```

`--no-scripts` porque los scripts de Symfony (cache:clear, assets:install) necesitan el código completo. Se ejecutan después del COPY completo o se dejan al startup.

### 3. No tocar Stage 1 (frontend)

Ya está bien estructurado: `package*.json` → `npm ci` → `COPY frontend/`.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Dockerfile.railway Stage 1 (frontend) | Include — sin cambios | Ya optimizado |
| Dockerfile.railway Stage 2 (PHP) | Transform | Separar lock files de código |
| .dockerignore | Transform — crear | No existe, necesario |
| railway.toml | Include — sin cambios | Solo restart policy |
| nginx-railway.conf | Include — sin cambios | No afectado |
| railway-start.sh | Include — sin cambios | No afectado |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Dockerfile.worker | Omit | Servicio separado, misma optimización se puede aplicar después |
| docker/php/Dockerfile (dev) | Omit | Solo desarrollo local |
| Dockerfiles de otros servicios | Omit | Independientes |
| BuildKit cache mounts | Omit | Dependencia de soporte Railway, riesgo sin beneficio garantizado |

## Resultado esperado

- Build con cambios solo en PHP: ~1:30-2:00 (vs 3:21 actual)
- Build con cambios en dependencias: ~3:00-3:20 (similar a hoy)
- Zero riesgo funcional — mismo imagen Docker resultante
