# Estrategia de Knowledge Dinámico para Claude Code

**Fecha:** 2026-03-11
**Última actualización:** 2026-03-11
**Estado:** Vigente
**Contexto:** El CLAUDE.md actual tiene ~50KB (1200 líneas) y crece con cada feature. Esto consume contexto valioso en cada sesión. Se necesita una estrategia para cargar conocimiento bajo demanda en lugar de todo de golpe.

## Problema

1. **CLAUDE.md demasiado grande**: 50KB se carga en CADA mensaje. Con un context window de ~200K tokens, esto son ~12K tokens fijos por turno.
2. **Conocimiento irrelevante**: Si estoy trabajando en routing, no necesito la sección de Traccar, Mercure, o Twilio.
3. **Análisis no reutilizados**: Los análisis en `docs/analysis/` existen pero no se consultan automáticamente.
4. **Skills (superpowers) ocupan mucho**: Las 14 skills consumen ~25KB adicionales.

## Solución Propuesta: CLAUDE.md Slim + Knowledge Modules

### Fase 1: Modularizar CLAUDE.md

Dividir el CLAUDE.md monolítico en módulos temáticos que se consultan bajo demanda:

```
CLAUDE.md                          (~8KB, core + índice)
docs/knowledge/
├── index.md                       (índice de todos los módulos)
├── domain-model.md                (entidades, relaciones, enums)
├── provider-framework.md          (providers, factories, proxies, resolución)
├── api-surface.md                 (endpoints, DTOs, autenticación)
├── deployment.md                  (Docker, Railway, env vars)
├── testing.md                     (patterns, commands, coverage)
├── realtime.md                    (Mercure, SSE, topics, tokens)
├── gps-tracking.md                (Traccar, ingesta, simulación)
├── notifications.md               (SMS, WhatsApp, push, webhooks)
├── ai-ml.md                       (Claude, embeddings, predictions)
├── route-optimization.md          (VROOM, OSRM, capacity, constraints)
└── security.md                    (roles, multi-tenancy, CSRF, rate limiting)
```

### Fase 2: CLAUDE.md Core (~8KB)

El CLAUDE.md reducido contendría SOLO:

1. **Project overview** (2 líneas)
2. **Tech stack** (5 líneas)
3. **Common commands** (10 líneas)
4. **Conventions** (15 líneas: strict_types, naming_strategy, PublicId pattern)
5. **Índice de knowledge modules** con triggers de cuándo consultar cada uno
6. **Regla de consulta**: "Antes de trabajar en un subsistema, consulta el módulo relevante en `docs/knowledge/`"
7. **Skills** (solo nombres + triggers, no el contenido completo)
8. **Backlog arquitectónico** (se mantiene, es breve y crítico)

### Fase 3: Instrucción de Carga Dinámica

Añadir al CLAUDE.md core:

```markdown
## Knowledge Modules (consultar bajo demanda)

Antes de trabajar en un subsistema, LEE el módulo relevante:

| Si vas a trabajar en... | Lee primero |
|------------------------|-------------|
| Entidades, relaciones, migraciones | `docs/knowledge/domain-model.md` |
| Providers, factories, resolución per-tenant | `docs/knowledge/provider-framework.md` |
| Controllers, DTOs, APIs | `docs/knowledge/api-surface.md` |
| Docker, Railway, variables de entorno | `docs/knowledge/deployment.md` |
| Tests, PHPUnit, coverage | `docs/knowledge/testing.md` |
| Mercure, SSE, tokens JWT | `docs/knowledge/realtime.md` |
| Traccar, posiciones GPS, simulación | `docs/knowledge/gps-tracking.md` |
| SMS, WhatsApp, push, webhooks | `docs/knowledge/notifications.md` |
| Claude AI, embeddings, ML | `docs/knowledge/ai-ml.md` |
| VROOM, OSRM, capacidad, rutas | `docs/knowledge/route-optimization.md` |
| Roles, multi-tenancy, CSRF | `docs/knowledge/security.md` |
| Análisis previos del codebase | `docs/analysis/` |
```

### Fase 4: Workflow de Actualización

Cuando se modifica un subsistema:
1. Actualizar el módulo de knowledge correspondiente
2. NO duplicar info entre CLAUDE.md y los módulos
3. Los análisis en `docs/analysis/` son "descubrimientos" temporales; cuando se estabilizan, se mueven a `docs/knowledge/`

## Beneficios Esperados

| Aspecto | Antes | Después |
|---------|-------|---------|
| Context base por turno | ~50KB (12K tokens) | ~8KB (2K tokens) |
| Conocimiento relevante | Todo o nada | Solo lo necesario |
| Mantenibilidad | Archivo monolítico | Módulos independientes |
| Actualización | Editar 1 archivo gigante | Editar módulo específico |
| Onboarding nueva sesión | Lee todo, olvida la mitad | Lee core + módulo(s) relevante(s) |

## Limitaciones

1. **Claude no carga automáticamente**: Claude Code solo carga CLAUDE.md automáticamente. Los módulos requieren un `Read` explícito. La instrucción en CLAUDE.md debe ser lo suficientemente clara para que Claude los lea.

2. **Overhead de Read**: Cada módulo consultado es una herramienta Read extra. Para tareas que tocan múltiples subsistemas, puede necesitar 3-4 reads.

3. **Riesgo de desincronización**: Si los módulos no se actualizan, el conocimiento se queda obsoleto. Mitigación: fecha de última actualización y estado (Vigente/Desactualizado) en cada módulo.

## Alternativa Considerada: MCP Server

Un MCP server podría exponer el knowledge como tools:
```
get_domain_model() → devuelve el módulo de dominio
get_provider_docs() → devuelve docs de providers
search_knowledge(query) → búsqueda semántica en todos los módulos
```

**Pros**: Más integrado, búsqueda semántica posible.
**Contras**: Requiere mantener un servidor MCP, más complejidad.
**Decisión**: Empezar con archivos estáticos (Fase 1-4), evaluar MCP si la solución estática es insuficiente.

## Próximos Pasos

1. [ ] Crear `docs/knowledge/` con los módulos extraídos del análisis
2. [ ] Reducir CLAUDE.md a core + índice
3. [ ] Mover skills al mínimo (solo nombres + triggers en CLAUDE.md, contenido en archivos separados)
4. [ ] Probar en 3-5 sesiones para validar que Claude consulta los módulos correctamente
5. [ ] Iterar basado en resultados

## Historial de actualizaciones

- 2026-03-11: Creación inicial — propuesta de knowledge dinámico
