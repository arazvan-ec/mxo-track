# Knowledge Modules Index

**Última actualización:** 2026-03-11
**Estado:** Vigente

Índice de módulos de conocimiento del proyecto mxo-track. Cada módulo cubre un subsistema específico y se consulta bajo demanda.

## Módulos Disponibles

| Módulo | Archivo | Descripción | Cuándo consultarlo |
|--------|---------|-------------|-------------------|
| Arquitectura DDD | `domain-driven-architecture.md` | Interfaces de dominio puro, capas, bridges, reglas de dependencias | Brainstorming de features que tocan lógica de negocio |
| Modelo de Dominio | `domain-model.md` | Entidades, relaciones, enums, patrones de identidad | Trabajar con entidades, migraciones, relaciones |
| Provider Framework | `provider-framework.md` | Providers configurables, factories, proxies, resolución per-tenant | Añadir/modificar providers, CustomerIntegration |
| API Surface | `api-surface.md` | Endpoints, DTOs, autenticación, error handling | Trabajar con controllers, APIs, DTOs |
| Deployment | `deployment.md` | Docker local, Railway producción, env vars | Deploy, configuración, infraestructura |
| Testing | `testing.md` | Patterns, comandos, coverage, factories de test | Escribir/modificar tests |
| Realtime | `realtime.md` | Mercure, SSE, topics, tokens JWT | SSE, publicación en tiempo real |
| GPS Tracking | `gps-tracking.md` | Traccar, ingesta, simulación, posiciones | GPS, tracking de vehículos |
| Notifications | `notifications.md` | SMS, WhatsApp, push, webhooks, in-app | Sistema de notificaciones |
| AI/ML | `ai-ml.md` | Claude API, embeddings, clasificación, clustering | Funcionalidades de IA |
| Route Optimization | `route-optimization.md` | VROOM, OSRM, capacidad, constraints, planning | Optimización y planificación de rutas |
| Security | `security.md` | Roles, multi-tenancy, CSRF, rate limiting, audit | Seguridad, autenticación, autorización |
| Superpowers Skills | `superpowers-skills.md` | Skills completas: TDD, brainstorming, debugging, verification, etc. | Aplicar cualquier skill de desarrollo |

## Cómo Usar

1. **Antes de trabajar en un subsistema**, lee el módulo relevante con `Read`
2. **Si tocas múltiples subsistemas**, lee los módulos correspondientes
3. **Al modificar un subsistema**, actualiza el módulo de knowledge correspondiente
4. **No duplicar** información entre CLAUDE.md y los módulos

## Análisis Complementarios

- `docs/analysis/2026-03-11-full-codebase-analysis.md` — Análisis exhaustivo del codebase completo
- `docs/analysis/2026-03-11-dynamic-knowledge-strategy.md` — Estrategia de modularización

## Historial

- 2026-03-11: Creación inicial — 11 módulos extraídos del CLAUDE.md y análisis del codebase
- 2026-03-11: Añadido módulo `superpowers-skills.md` — restaura contenido completo de skills recortado en modularización
- 2026-03-16: Añadido módulo `domain-driven-architecture.md` — patrón de interfaces de dominio puro extraído de refactoring UserIdentity/UserAccount
