# Knowledge Modules Index

**Última actualización:** 2026-03-19
**Estado:** Vigente

Índice de módulos de conocimiento del proyecto mxo-track. Cada módulo cubre un subsistema específico y se consulta bajo demanda.

## Consulta Rápida: Codebase Manifest

**Antes de explorar el codebase con herramientas**, lee `docs/codebase-manifest.md`. Contiene counts, listas de entidades/enums, directory tree y bounded contexts — todo auto-generado con `make manifest`. Ver sección "Pre-Exploration Gate" en CLAUDE.md.

## Módulos Disponibles

| Módulo | Archivo | Verificado | Descripción | Cuándo consultarlo |
|--------|---------|------------|-------------|-------------------|
| Modelo de Dominio | `domain-model.md` | 2026-03-19 | Entidades, relaciones, enums, patrones de identidad | Trabajar con entidades, migraciones, relaciones |
| Provider Framework | `provider-framework.md` | -- | Providers configurables, factories, proxies, resolución per-tenant | Añadir/modificar providers, CustomerIntegration |
| API Surface | `api-surface.md` | -- | Endpoints, DTOs, autenticación, error handling | Trabajar con controllers, APIs, DTOs |
| Deployment | `deployment.md` | -- | Docker local, Railway producción, env vars | Deploy, configuración, infraestructura |
| Testing | `testing.md` | -- | Patterns, comandos, coverage, factories de test | Escribir/modificar tests |
| Realtime | `realtime.md` | -- | Mercure, SSE, topics, tokens JWT | SSE, publicación en tiempo real |
| GPS Tracking | `gps-tracking.md` | -- | Traccar, ingesta, simulación, posiciones | GPS, tracking de vehículos |
| Notifications | `notifications.md` | -- | SMS, WhatsApp, push, webhooks, in-app | Sistema de notificaciones |
| AI/ML | `ai-ml.md` | -- | Claude API, embeddings, clasificación, clustering | Funcionalidades de IA |
| Route Optimization | `route-optimization.md` | -- | VROOM, OSRM, capacidad, constraints, arquitectura 4 capas, dos ejes, gaps | Optimización y planificación de rutas |
| Architecture DDD/SOLID | `architecture-ddd.md` | 2026-03-19 | Bounded contexts, desacoplamiento, patrones de migración, anti-patterns | Crear código nuevo, refactorizar entidades/servicios, code review |
| Design Patterns | `design-patterns.md` | -- | Catálogo GoF + DDD: 15 en uso, 4 candidatos, con ejemplos del codebase | Elegir patrón para nuevo código, entender patrones existentes |
| Security | `security.md` | -- | Roles, multi-tenancy, CSRF, rate limiting, audit | Seguridad, autenticación, autorización |
| Superpowers Skills | `superpowers-skills.md` | -- | Skills completas: TDD, brainstorming, debugging, verification, etc. | Aplicar cualquier skill de desarrollo |
| Feedback & Learning | `feedback-learning.md` | -- | Sistema de captura de datos, execution logs, retrospectives, learning loop, métricas de negocio | Feedback, execution logs, learning loop, retrospectives |
| UI & Frontend | `ui-frontend.md` | 2026-03-22 | Templates Twig, Alpine.js, Tailwind, componentes, React frontend, layout architecture | Trabajar con templates, UI, sidebar, frontend |
| UI Layout Contracts | `ui-layout-contracts.md` | 2026-04-14 | Invariantes de positioning, containing blocks, flex scroll, preset independence | Cualquier cambio a CSS, layout, animaciones, o presets |

## Cómo Usar

1. **Antes de trabajar en un subsistema**, lee el módulo relevante con `Read`
2. **Si tocas múltiples subsistemas**, lee los módulos correspondientes
3. **Al modificar un subsistema**, actualiza el módulo de knowledge correspondiente
4. **No duplicar** información entre CLAUDE.md y los módulos
5. **Frescura:** Un módulo es **potencialmente obsoleto** si `Verificado` es `--` o tiene más de 2 semanas. Al consultar un módulo potencialmente obsoleto, dedicar 2 minutos a spot-check de sus claims clave contra el código

## Análisis Complementarios

- `docs/analysis/2026-03-11-full-codebase-analysis.md` — Análisis exhaustivo del codebase completo
- `docs/analysis/2026-03-15-business-requirements-audit.md` — Auditoría de requisitos de negocio, arquitectura 4 capas, gaps UI, decisiones pendientes
- `docs/analysis/2026-03-11-dynamic-knowledge-strategy.md` — Estrategia de modularización

## Historial

- 2026-03-11: Creación inicial — 11 módulos extraídos del CLAUDE.md y análisis del codebase
- 2026-03-11: Añadido módulo `superpowers-skills.md` — restaura contenido completo de skills recortado en modularización
- 2026-03-16: Añadido business-requirements-audit a análisis complementarios; actualizada descripción de Route Optimization
- 2026-03-16: Añadido módulo `architecture-ddd.md` — guía de desacoplamiento DDD/SOLID con bounded contexts y patrones de migración
- 2026-03-16: Añadido módulo `design-patterns.md` — catálogo completo GoF + DDD con 15 patrones en uso y 4 candidatos
- 2026-03-19: Añadida columna `Verificado` para tracking de frescura; regla de spot-check para módulos potencialmente obsoletos
