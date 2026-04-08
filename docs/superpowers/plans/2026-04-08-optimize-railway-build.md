# Plan — Optimize Railway Docker Build

**Spec:** `docs/superpowers/specs/2026-04-08-optimize-railway-build-design.md`
**Enfoque:** A — Layer caching optimizado

## Phase 1 (v0): Implementación directa

### [parallel] Wave 1: Tarea 1a + 1b

- **1a:** Crear `.dockerignore` en raíz del proyecto
  → produce: build context reducido (~2MB vs ~8MB)

- **1b:** Reestructurar `Dockerfile.railway` Stage 2 — separar lock files de código
  → produce: layer caching efectivo para `composer install`

### Wave 2: Tarea 2 (necesita Wave 1)

- **2:** Verificación — build local del Dockerfile para confirmar que la imagen se genera correctamente
  → produce: evidencia de que los cambios no rompen el build

## Phase 2 (Mature): N/A

No aplica — estos son cambios infraestructurales sin refactor posterior necesario.

## Notas

- No hay tests automatizados para Dockerfiles. La verificación es un `docker build` exitoso.
- El impacto real en tiempos se verifica en el siguiente deploy a Railway.
