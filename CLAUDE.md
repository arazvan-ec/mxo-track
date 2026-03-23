# Para optimizar una ruta

1. Cada entrega debe tener una configuracion de volumen y peso
2. Tambien necesitamos la configuracion de volumen y peso que entra en cada vehiculo

# Demo para cliente

1. CSV para importar
2. Con ese CSV tenemos que crear X rutas, cada vehiculo puede hacer x entregas, poder configurar antes de acceptar la ruta

# CLAUDE.md: claude --resume 2a057aa1-7456-4257-ab81-debee0c6a901 <> eliminar customer vehicle -> seguir

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**transporte-tracking** (mxo-track) — Logistics tracking portal built on **Symfony 7.4 LTS** (strict lock, no 8.x components). Monorepo with `backend/` (Symfony) and `docs/`. Deployed on Railway.

The system tracks vehicles via Traccar integration, manages delivery routes with driver proof-of-delivery (POD), and provides real-time position updates via Mercure. Multi-tenant via `customer_id` Doctrine SQL filter.

**Core business value:** Route optimization — the business sells saved kilometers and saved time. Everything else (fleet management, multi-tenancy, portals, tracking) is infrastructure serving that goal.

## Tech Stack

- PHP 8.4 (Docker image: `php:8.4-cli-bookworm`), Symfony 7.4 LTS (Flex + recipes)
- PostgreSQL 16, Redis 7 (sessions), Mercure (realtime SSE)
- Doctrine ORM 3.x with attribute mapping (requires `naming_strategy: underscore_number_aware` in doctrine.yaml)
- Twig + Turbo (UX Turbo) for frontend
- Traccar for GPS device tracking

## Common Commands

```bash
cd backend && composer install          # Install dependencies
php bin/console about                   # Verify Symfony is working
php bin/console doctrine:migrations:migrate -n  # Run migrations
php bin/console doctrine:fixtures:load -n       # Load fixtures (admin user)
make lint                               # PHP syntax lint (all src files)
php vendor/bin/phpunit                  # Run tests
```

## SOLID Principles (mandatory)

Todo código nuevo **debe cumplir los 5 principios**. En code review, verificar cada uno.

### S — Single Responsibility

**Una clase debe tener una sola razón para cambiar.**

- Entidades: solo estado de dominio + transiciones de estado (`start()`, `finish()`, `markDelivered()`)
- Persistencia: en Infrastructure (mapping externo, repositories)
- Validación: en Value Objects (auto-validación en constructor) o Application layer (DTOs con Validator)
- Seguridad: en Security layer (voters, authenticators), no en la entidad

**Violación conocida:** `User.php` mezcla 5 responsabilidades (identidad, auth, roles, multi-tenancy, persistence lifecycle).
**Buen ejemplo:** `src/Domain/Event/StopDelivered.php` — POPO inmutable con un solo trabajo.

### O — Open/Closed

**Abierto para extensión, cerrado para modificación.**

- Múltiples implementaciones posibles → interface + registry o tagged services
- Nunca if/switch sobre tipos para seleccionar implementación → usar polimorfismo
- Nuevas funcionalidades se añaden con nuevas clases, no modificando las existentes

**Buen ejemplo:** Provider Framework — `ProviderFactoryInterface` + `#[AutoconfigureTag]` + `ProviderFactoryRegistry`. Añadir provider = nueva clase, cero cambios en código existente.

### L — Liskov Substitution

**Las implementaciones deben cumplir el contrato completo de su interface.**

- Si una implementación necesita stubs o no-ops → la interface es demasiado amplia → dividirla
- Nunca `throw new \RuntimeException('Not supported')` en un método de interface

**Violación conocida:** `WebhookGpsProvider` tiene stubs para `login()`, `getSessionCookie()`, `getDevices()` (deuda técnica documentada en backlog).

### I — Interface Segregation

**Los clientes no deben depender de interfaces que no usan.**

- Interfaces pequeñas y cohesivas (1-5 métodos relacionados)
- Si una implementación tiene stubs → ISP + LSP violados juntos
- Preferir composición de interfaces: `class X implements InterfaceA, InterfaceB`
- Interfaces marker (sin métodos) son aceptables

**Buen ejemplo:** `CustomerScopedEntityInterface` (1 método), `SoftDeletableInterface` (3 métodos cohesivos).

### D — Dependency Inversion

**Módulos de alto nivel dependen de abstracciones, no de módulos de bajo nivel.**

- Servicios de dominio y aplicación → dependen de interfaces definidas en Domain layer
- Infrastructure implementa las interfaces
- `EntityManagerInterface` directo → prohibido en contextos críticos. Usar `RepositoryInterface::save()`
- En contextos CRUD/pragmáticos → aceptable depender de repositorios concretos Symfony

```
Controller → Application Service → Domain Interface ← Infrastructure Implementation
```

**Violación conocida:** `DeliveryService` depende de `RouteStopRepository` y `ShipmentRepository` concretos.
**Buen ejemplo:** `RouteOptimizationService` depende de `RouteOptimizerInterface` y `RoutingEngineInterface`.

## DDD Architecture (mandatory)

Pureza híbrida: **contextos críticos → DDD puro, contextos CRUD → pragmático Symfony.** Todo código nuevo en contextos críticos sigue DDD desde el inicio.

### Bounded Contexts

**Críticos (DDD puro):** Route Planning (Route, RouteStop, RouteSnapshot, RouteEvent), Shipment/Delivery (Shipment, Parcel, DeliveryEvidence, POD), Route Optimization (ya bien separado).

**Pragmáticos (Symfony):** Identity/Auth (User), Tenant Management (Customer), Fleet (Vehicle, Driver), Notifications.

### Reglas

**Código nuevo en contexto crítico → siempre DDD:**
```
src/Domain/{Context}/Model/        # Entidades POPOs, Value Objects
src/Domain/{Context}/Repository/   # Interfaces de repositorio
src/Domain/{Context}/Service/      # Domain services
src/Domain/{Context}/Event/        # Domain events (POPOs)

src/Infrastructure/{Context}/Doctrine/   # Implementaciones repositorio
src/Infrastructure/{Context}/Symfony/    # Controllers, commands, listeners
```

**Al tocar código acoplado en contexto crítico:**
1. Extraer interface de repositorio al dominio
2. Crear implementación Doctrine
3. Cambiar servicio para depender de la interface
4. Implementar tu feature contra la interface

**Migración planificada:** Sprints dedicados por contexto. Prioridad: Route Planning → Shipment/Delivery.

### Qué debe cumplir el código DDD

- Entidades son POPOs — sin `#[ORM\...]`, sin `UserInterface`, sin Validator constraints
- Domain events son POPOs — sin dependencias de Symfony/Doctrine
- Servicios dependen de interfaces del dominio, no de Doctrine concreto
- Lógica de dominio testeable con unit tests puros (sin base de datos)
- La flecha de dependencia apunta al dominio: `Controller → App → Domain ← Infrastructure`

### Anti-Patterns

- `$em->persist()` en servicios de dominio → usar `RepositoryInterface::save()`
- `$em->getRepository()->createQueryBuilder()` en servicios → método en RepositoryInterface
- `EntityManagerInterface` en constructor de servicios de dominio → depender de RepositoryInterface
- Lifecycle callbacks en entidades DDD → timestamps en constructor o domain service

**Referencia completa con ejemplos de código:** `docs/knowledge/architecture-ddd.md`

## Design Patterns (mandatory)

Los patrones de diseño son herramientas, no recetas. **Empieza por el problema, no por el patrón.**

### Proceso de decisión

Antes de aplicar cualquier patrón:

1. **¿Es necesario?** Si el código directo (sin patrón) resuelve el problema igual de bien, no uses un patrón. Tres líneas claras > una abstracción prematura.
2. **¿Cuántas implementaciones reales hay?** Si solo una, no extraigas interface "por si acaso". Hazlo cuando exista la segunda.
3. **¿Qué trade-offs tiene?** Cada indirección (interface, factory, proxy) añade complejidad. ¿El beneficio supera el costo?
4. **¿Hay alternativas?** La mayoría de problemas se resuelven con 2-3 patrones diferentes. Evalúa antes de decidir.
5. **¿Mejora SOLID?** Si el patrón viola un principio SOLID, probablemente es el patrón equivocado.

### Señales de que elegiste mal

- Añadiste 3+ clases y solo hay 1 implementación real → over-engineering
- Necesitas mirar 5 archivos para entender un flujo simple → demasiada indirección
- El Facade crece sin parar (10+ dependencias) → se convierte en God Class
- Implementas Strategy con 1 sola implementación "por si acaso" → YAGNI
- Los eventos hacen imposible trazar qué pasa después de un cambio → exceso de desacoplamiento

### Consistencia con patrones existentes

El codebase ya usa patrones establecidos. Cuando el problema es del mismo tipo, seguirlos reduce carga cognitiva — pero no los copies sin evaluar si encajan:

- **Providers:** Factory + Strategy + Adapter + TenantAware Proxy (12 factories, 4 proxies)
- **Side-effects de dominio:** Domain Event + Listener (13 events, 13 listeners)
- **Operaciones async:** Command via Messenger (4 messages + handlers)
- **Graceful degradation:** Null Object (12 Null* classes)
- **Workflows complejos:** Facade en Application layer (RoutePlanningService, DeliveryService)

### Lo que NO hacer

- `if ($type === 'x') return new X()` → el Provider Framework ya resuelve esto con Factory + Registry
- Retornar null donde se espera un servicio → Null Object
- Side-effects directos en el servicio que cambia estado → Domain Event + Listener separado
- Modificar una clase para añadir comportamiento cross-cutting → Decorator o Proxy

### Feedback loop: Decision Log

Después de cada implementación que involucre una decisión de diseño significativa (patrón, arquitectura, trade-off), añadir una entrada breve a `docs/decisions/log.md`:

```markdown
### [YYYY-MM-DD] Contexto breve
- **Problema:** Qué se necesitaba resolver
- **Decisión:** Qué patrón/enfoque se eligió y por qué
- **Alternativas descartadas:** Qué otras opciones se evaluaron
- **Resultado:** (rellenar post-implementación) ¿Funcionó bien? ¿Qué se aprendió?
```

**Cuándo registrar:** Solo decisiones no triviales — nueva abstracción, nuevo patrón, refactor de arquitectura, trade-off con implicaciones. No registrar decisiones obvias.

**Cuándo actualizar knowledge:** Si el log muestra un patrón recurrente (mismo error, misma lección 3+ veces), actualizar `docs/knowledge/design-patterns.md` o la sección relevante de CLAUDE.md.

**Guía completa de decisión con trade-offs:** `docs/knowledge/design-patterns.md`

## Conventions

- All PHP files use `declare(strict_types=1)`
- Doctrine mappings via PHP attributes (not XML/YAML)
- Doctrine ORM 3.x: `naming_strategy: underscore_number_aware` required in doctrine.yaml
- Controllers use attribute routing
- API error responses via `ApiErrorResponder`
- DTOs in `src/Dto/` with `fromArray()` factory + Symfony Validator constraints
- Symfony 7.4 lock enforced: `extra.symfony.require=7.4.*`, `conflict >=8.0`

### Documentation Honesty (mandatory)

La documentación describe **lo que ES**, no lo que debería ser. Cuando el codebase tiene arquitectura aspiracional (e.g., DDD patterns planificados pero no implementados):

- **Estado actual** es la voz default: "Las entidades usan ORM attributes en `src/Entity/`"
- **Estado aspiracional** usa marcadores: "**[PLANNED]** Las entidades en contextos críticos migrarán a `src/Domain/{Context}/Model/` como POPOs"
- **Estado parcial** usa: "**[PARTIAL]** Domain events son POPOs (13 events), pero entidades permanecen en `src/Entity/` con ORM attributes"

Aplica a: knowledge modules, FEATURES.md, architecture docs. NO aplica a instrucciones de comportamiento en CLAUDE.md (que describen comportamiento deseado).

## Atomic Commits & Push (mandatory)

**Cada paso de progreso debe committearse y pushearse inmediatamente.** No acumular cambios.

### Cuándo commitear

- Después de crear/modificar cada archivo que funciona (compila, no rompe tests)
- Después de cada tarea completada en un plan
- Después de cada refactor que deja el código en estado verde
- Después de escribir un test (aunque falle — commit del test solo)
- Después de hacer pasar un test (commit de la implementación)
- Después de crear/actualizar documentación (specs, plans, knowledge modules)

### Cuándo hacer push

- Después de cada commit (o máximo cada 2-3 commits si son parte del mismo paso lógico)
- **Siempre** antes de lanzar subagentes (para que el progreso esté seguro)
- **Siempre** al terminar una tarea del TodoWrite
- **Siempre** ejecutar `make manifest` antes del push final o al finalizar una rama (mantiene el codebase manifest fresco)

### Artefactos de trabajo van al repo, no a rutas efímeras

- Los planes de implementación van a `docs/superpowers/plans/` — **nunca** solo en `/root/.claude/plans/` (que es efímero y se pierde al cerrar sesión)
- Los specs de diseño van a `docs/superpowers/specs/`
- El estado de progreso (qué tareas están hechas, cuáles faltan) debe quedar en commits, no solo en TodoWrite (que también es efímero)
- **Regla:** Si generas un documento de trabajo (plan, spec, análisis), debe committearse al repo inmediatamente
- **Outputs de subagentes de investigación:** Cuando un subagente (Explore, Plan, reviewer) produce hallazgos significativos (análisis de arquitectura, auditorías, investigaciones de codebase), el agente principal debe guardar un resumen en `docs/superpowers/agent-outputs/YYYY-MM-DD-<topic>.md` usando el template de `docs/superpowers/templates/subagent-output-template.md` y committearlo. No aplica a subagentes de implementación (su output es código committeado).

### Formato del commit message

- Prefijos: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
- Mensaje corto y descriptivo del **qué** y **por qué**
- Un commit = un cambio lógico atómico (no mezclar feat + refactor + docs en uno)

### Ejemplos

```
docs: add route optimization spec
test: add failing test for RouteStop reordering
feat: implement RouteStop reorder method
refactor: extract distance calculation to value object
fix: correct ETA calculation when stop is skipped
```

### Anti-patterns

- Commitear todo al final de la sesión en un solo commit gigante
- Acumular 5+ archivos modificados sin commit
- Push solo al hacer PR — pushear durante el desarrollo
- Commit messages genéricos: "WIP", "updates", "changes"
- Mezclar cambios no relacionados en un commit

## Principio de No-Redundancia (mandatory)

**Antes de ejecutar cualquier acción, pregúntate: ¿es necesaria? ¿Ya se hizo? ¿El resultado será diferente al estado actual?**

- Si la acción no cambia el estado del sistema → no la ejecutes
- Si puedes verificar con un tool de lectura (Read, Glob, Grep) antes de un tool de escritura/ejecución → verifica primero
- Si el tool destino ya maneja el caso (ej: `Write` crea directorios padre) → no hagas pasos previos innecesarios
- Preferir la ruta más corta: menos herramientas, menos comandos, mismo resultado

## Pre-Exploration Gate (mandatory)

**Antes de ejecutar Grep, Glob o Bash para descubrir estructura del codebase** (contar entidades, listar servicios, inventariar enums, explorar directorios), **DEBES leer `docs/codebase-manifest.md` primero**.

- Si el dato está ahí y `Generated` es < 7 días → **úsalo directamente, sin explorar**
- Si falta o está obsoleto → explora, luego ejecuta `make manifest` y commitea el resultado

### Mapeo exploración → documento existente

| En vez de ejecutar... | Lee... |
|-----------------------|--------|
| `ls src/Entity/` o contar entidades | `docs/codebase-manifest.md` → Entity List + Metrics |
| `ls src/Enum/` o contar enums | `docs/codebase-manifest.md` → Enum List + Metrics |
| `find src/ -name "*.php" \| wc -l` | `docs/codebase-manifest.md` → Metrics |
| `grep -r "class.*Controller"` | `docs/codebase-manifest.md` → Metrics; detalle en `docs/knowledge/api-surface.md` |
| Contar tests, migrations, services | `docs/codebase-manifest.md` → Metrics |
| Estructura de `src/` | `docs/codebase-manifest.md` → Directory Tree |
| Detalle de una entidad específica | `docs/knowledge/domain-model.md` |
| Inventario completo de features | `docs/FEATURES.md` |
| Arquitectura, bounded contexts | `docs/knowledge/architecture-ddd.md` |
| Cómo fluye un proceso end-to-end (shipment, ruta, tracking) | `docs/knowledge/domain-model.md` → End-to-End Flows |
| Qué providers/factories existen para un servicio | `docs/knowledge/provider-framework.md`; factories en `docs/codebase-manifest.md` → Factory Registry |
| Cómo se hace deploy, env vars, Docker | `docs/knowledge/deployment.md` |
| Cómo funcionan los tests, patterns, fixtures | `docs/knowledge/testing.md` |
| Cómo funciona Mercure/SSE/realtime | `docs/knowledge/realtime.md` |
| Cómo funciona el GPS/Traccar | `docs/knowledge/gps-tracking.md` |
| Cómo funciona la optimización de rutas | `docs/knowledge/route-optimization.md` |
| Roles, auth, seguridad, CSRF | `docs/knowledge/security.md` |
| Templates Twig, layout, sidebar, componentes UI | `docs/knowledge/ui-frontend.md` |
| Qué endpoints tiene un controller específico | `docs/codebase-manifest.md` → Route Map |
| Qué servicios implementan una interface | `docs/codebase-manifest.md` → Service Map |
| Qué factories existen y qué crean | `docs/codebase-manifest.md` → Factory Registry |
| Desglose de tests por tipo (unit/functional/domain) | `docs/codebase-manifest.md` → Test Breakdown |
| Qué templates Twig hay por sección | `docs/codebase-manifest.md` → Twig Template Map |

### Cuándo regenerar

Ejecutar `make manifest` y commitear el resultado **siempre** como último paso antes de push o al finalizar una rama. Sin condiciones — es barato (~1 segundo) y garantiza que el manifest esté siempre fresco.

### Exploración en capas (cuando el gate no tiene respuesta)

Cuando necesites entender algo que no está en el manifest ni en knowledge modules, explora en capas — cada capa más costosa que la anterior. **PARA cuando tengas suficiente información.**

**Capa 1: Manifest + Knowledge (0 tool calls adicionales)**
- Lee `docs/codebase-manifest.md` → Service Map, Route Map, Entity Relationships, Factory Registry, Template Map
- Lee el knowledge module relevante (tabla de Knowledge Modules abajo)
- Si esto responde tu pregunta → **PARA**

**Capa 2: Búsqueda dirigida (1-3 tool calls)**
- Grep por clase/función/ruta específica
- Glob para encontrar archivos por patrón
- Read del archivo más relevante identificado en Capa 1
- Si esto responde tu pregunta → **PARA**

**Capa 3: Exploración con agente (subagente Explore)**
- Solo si Capa 1 y 2 no fueron suficientes
- Dar al agente contexto de lo que YA encontraste en capas anteriores
- Pedir hallazgos específicos, no dumps de código

### Anti-patterns de exploración

- Lanzar agente Explore sin haber consultado manifest/knowledge primero
- Grep masivo (`grep -r "class"`) cuando el manifest tiene Service Map
- Leer archivos completos cuando solo necesitas una sección específica
- Explorar la misma área que ya está documentada en un knowledge module
- Explorar para contar/listar cuando el manifest ya tiene esa información

## Principio de Escalabilidad en Decisiones (mandatory)

**La mejor solución es siempre la que más escala, independientemente de la cantidad de cambios que requiera.**

### Reglas

1. **Escala sobre comodidad** — Al evaluar approaches, elegir el que mejor escale a futuro, no el que requiera menos cambios hoy. Una solución que toca 20 archivos pero escala correctamente es superior a un parche de 3 líneas que no escala.

2. **Cambios grandes ≠ riesgo alto** — La cantidad de archivos o líneas modificadas no determina el riesgo. El riesgo lo determina la ausencia de plan. Con un buen plan (brainstorming → spec → plan → TDD → review), cambios masivos son seguros y controlados.

3. **El flujo es la red de seguridad** — Los principios documentados en este archivo (SOLID, DDD, Design Patterns, TDD, Atomic Commits, Verification) existen para habilitar cambios ambiciosos. Usarlos todos en la toma de decisiones, no solo en la implementación.

4. **Nunca descartar la mejor solución por volumen de trabajo** — Si la solución correcta requiere refactorizar un subsistema completo, esa es la solución correcta. El plan se adapta al alcance, no el alcance al miedo de cambiar.

### Anti-patterns

- Elegir un parche rápido cuando existe una solución estructural mejor "porque toca menos código"
- Rechazar un refactor necesario argumentando "es mucho cambio"
- Proponer soluciones intermedias que no escalan "para ir paso a paso" cuando el paso completo está claro
- Optimizar para minimizar diff en lugar de maximizar calidad arquitectónica

## Flujo Obligatorio para Toda Interacción (mandatory)

**Toda interacción sigue un flujo estructurado. Sin excepciones. La profundidad escala con el tipo de interacción, pero la estructura siempre está presente.**

### Clasificación de interacción (PRIMER paso antes de cualquier respuesta)

| Tipo | Señal | Flujo | `flow_type` en session-state |
|------|-------|-------|------------------------------|
| **Informational** | "qué hace X?", "explica Y", "dónde está Z?" | Micro-flow | `micro` |
| **Documentation** | Editar docs, knowledge modules, specs | Light-flow | `light` |
| **Bug fix** | Error, test failure, comportamiento inesperado | Debug-flow | `debug` |
| **Code change** | Feature nueva, refactor, enhancement | Full-flow | `full` |
| **Exploration** | "audita X", "analiza Y", "cómo funciona Z?", análisis de codebase, architecture review | Explore-flow | `explore` |

**Inmediatamente después de clasificar**, actualizar `session-state.json`:

```bash
jq '.flow_type = "<tipo>" | .current_phase = "<fase-inicial>"' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

Fases iniciales por flow: `micro` → `null`, `light` → `null`, `debug` → `consult`, `full` → `consult`, `explore` → `null`.

### Micro-flow (preguntas informativas)

1. **Consultar** — Buscar en `docs/decisions/log.md` y `docs/knowledge/` si ya existe respuesta
2. **Responder** — Respuesta estructurada con referencias a archivos
3. **Capturar** — Si la pregunta revela un gap de documentación, declarar: "Gap de documentación: [módulo] — [descripción]". Si el gap es significativo, añadir entrada en `docs/superpowers/agent-outputs/YYYY-MM-DD-doc-gaps.md` (append al archivo diario si ya existe).

### Explore-flow (exploraciones del codebase)

Aplica cuando la interacción produce hallazgos sustantivos sobre el codebase (conteos, patrones de arquitectura, detalles de implementación, gaps entre docs y código). Si es un fact lookup simple, usar Micro-flow.

1. **Consultar** — Leer `docs/codebase-manifest.md` primero (counts, listas, directory tree). Luego verificar si hallazgos ya existen en `docs/knowledge/`, `docs/superpowers/agent-outputs/`, o `docs/analysis/`
2. **Explorar** — Leer código, trazar patrones, recopilar evidencia
3. **Responder** — Respuesta estructurada con hallazgos y referencias a archivos
4. **Capturar** — Si los hallazgos son sustantivos (revelan gaps, corrigen misconceptions, producen conocimiento reutilizable):
   a. Escribir en `docs/superpowers/agent-outputs/YYYY-MM-DD-<topic>.md` usando template de subagent-output
   b. Si un knowledge module es directamente contradictorio, marcar: "STALE: [módulo] — [qué está mal]"
   c. Commit y push
5. **Proponer actualización** (opcional) — Si un knowledge module o FEATURES.md necesita actualizarse, proponer el cambio específico al usuario. No actualizar silenciosamente.

### Light-flow (cambios de documentación)

1. **Consultar** — Verificar docs existentes para overlap/conflictos
2. **Proponer** — Declarar qué cambiará y por qué (1-2 frases)
3. **Ejecutar** — Hacer cambios
4. **Verificar** — Comprobar consistencia con docs relacionados

### Debug-flow (bug fixes)

1. **Consultar** — Buscar en `docs/superpowers/retrospectives/` bugs similares pasados
2. **Systematic Debugging** — Invocar Skill 8 (obligatorio, sin atajos)
3. **TDD** — Invocar Skill 7 (test que falla antes del fix)
4. **Capturar** — Escribir execution log en `docs/superpowers/execution-logs/`
5. **Retrospectiva** — Añadir entrada al log de retrospectiva

### Full-flow (cambios de código)

Cada paso actualiza `session-state.json` via `jq`. El workflow-engine.sh (PreToolUse hook en Edit/Write) **bloquea ediciones de código** si las fases previas no se completaron.

1. **Consultar** — Leer decisiones pasadas, retrospectivas, métricas de negocio (ver Learning Loop)
   → Actualizar: `current_phase = "consult"`, `evidence.decisions_read = true` y/o `evidence.logs_scanned = true`
2. **Brainstorm** — Invocar Skill 2 (obligatorio, sin escape "es simple")
   → Actualizar: `current_phase = "brainstorming"`, y durante la conversación incrementar `evidence.user_turns` por cada respuesta del usuario, luego `evidence.alternatives_proposed = true`, `evidence.user_approved = true`, `evidence.spec_path = "docs/superpowers/specs/..."` al guardar spec
3. **Plan** — Invocar Skill 3 (escribir plan en `docs/superpowers/plans/`)
   → Actualizar: `current_phase = "planning"`, `evidence.plan_path = "docs/superpowers/plans/..."`
4. **Ejecutar** — Invocar Skill 4 o 5 (TDD obligatorio via Skill 7)
   → Actualizar: `current_phase = "implementation"`, incrementar `evidence.tests_written` al crear tests
5. **Verificar** — Invocar Skill 9 (evidencia antes de claims)
   → Actualizar: `current_phase = "verification"`, `evidence.tests_passed = true`, `evidence.lint_clean = true`
6. **Capturar** — Escribir execution log
   → Actualizar: `current_phase = "capture"`, `evidence.execution_log_path = "docs/superpowers/execution-logs/..."`
7. **Retrospectiva** — Escribir entrada de retrospectiva
   → Actualizar: `current_phase = "retrospective"`
8. **Finalizar** — Invocar Skill 12
   → Actualizar: `current_phase = "finalize"`, `evidence.branch_strategy = "merge|pr|keep|discard"`

### Anti-racionalizaciones

| Pensamiento | Realidad |
|-------------|----------|
| "Es un cambio de una línea" | Los cambios de una línea rompen producción. Full-flow. |
| "Ya sé la respuesta" | La consulta revela lo que no sabes que no sabes. |
| "El micro-flow es overkill para esta pregunta" | 10 segundos de consulta nunca son overkill. |
| "Saltemos brainstorming, la solución es obvia" | Las soluciones "obvias" que saltan brainstorming son las que pierden edge cases. |
| "Nadie va a leer la retrospectiva" | Las futuras instancias de Claude sí la leerán. Ese es el learning loop. |

## Workflow Engine Integration (mandatory)

**Los hooks en `.claude/hooks/` refuerzan mecánicamente el flujo descrito arriba.** Claude debe actualizar `.claude/session-state.json` para progresar por las fases; si no lo hace, los hooks bloquean ediciones de código.

### Cómo funciona

1. **SessionStart hook** (`session-start.sh`) — resetea `session-state.json` al inicio de cada día (misma sesión del día se preserva)
2. **PreToolUse hook** (`workflow-engine.sh`) — antes de Edit/Write, verifica:
   - `flow_type` está declarado (hard gate para archivos en `src/`, `tests/`)
   - Para `full` flow: las fases previas están completadas con evidencia
   - Para `micro|light|debug|explore`: no bloquea (pasa directo)
3. **PostToolUse hooks** — validan commits (prefijos, longitud) y ejecutan `make manifest` post-push

### session-state.json — Campos que Claude debe gestionar

```jsonc
{
  "flow_type": "micro|light|debug|full|explore|null",  // Declarar al clasificar interacción
  "current_phase": "consult|brainstorming|planning|implementation|verification|capture|retrospective|finalize|null",
  "evidence": {
    "decisions_read": false,        // true tras leer docs/decisions/log.md
    "logs_scanned": false,          // true tras escanear execution-logs/
    "user_turns": 0,                // +1 por cada respuesta del usuario en brainstorm
    "alternatives_proposed": false,  // true tras proponer 2-3 approaches
    "user_approved": false,          // true cuando usuario aprueba diseño
    "spec_path": null,               // ruta al spec guardado
    "plan_path": null,               // ruta al plan guardado
    "tests_written": 0,             // +1 por cada test file creado
    "tests_passed": null,           // true/false tras correr tests
    "lint_clean": null,             // true/false tras correr linter
    "execution_log_path": null,     // ruta al execution log
    "branch_strategy": null          // merge|pr|keep|discard
  },
  "deviation": {
    "active": false,                 // true si se saltó una fase con razón
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
```

### Cómo actualizar session-state.json

Usar `jq` para actualizaciones atómicas. Ejemplo al completar consult:

```bash
jq '.current_phase = "consult" | .evidence.decisions_read = true' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

### Gates del workflow-engine por fase

| Para editar... | Requiere fases completadas | Gate |
|---------------|---------------------------|------|
| `docs/superpowers/specs/*` | consult ✓ | HARD |
| `docs/superpowers/plans/*` | consult ✓, brainstorming ✓ | HARD |
| `src/*`, `tests/*` | consult ✓, brainstorming ✓, planning ✓ | HARD |
| `docs/superpowers/execution-logs/*` | (self) | SOFT |
| `docs/decisions/*` | (self) | SOFT |

**HARD** = bloquea la edición (exit 2). **SOFT** = muestra warning pero permite continuar (exit 1).

### Validators — Qué evidencia necesita cada fase

| Fase | Evidencia requerida | Nivel |
|------|-------------------|-------|
| `consult` | `decisions_read` OR `logs_scanned` | SOFT |
| `brainstorming` | `user_turns ≥ 3` + `alternatives_proposed` + `user_approved` + `spec_path` (archivo ≥500B con keywords) | HARD |
| `planning` | `plan_path` (archivo ≥300B con keywords) | HARD |
| `implementation` | plan existe (HARD) + `tests_written > 0` (SOFT warning) | MIXED |
| `verification` | `tests_passed = true` + `lint_clean = true` | HARD |
| `capture` | `execution_log_path` existe | SOFT |
| `retrospective` | (siempre recuerda actualizar decision log) | SOFT |
| `finalize` | `branch_strategy` declarado | SOFT |

### Deviation mode

Si necesitas saltarte una fase (urgencia, hotfix), activar deviation:

```bash
jq '.deviation.active = true | .deviation.reason = "hotfix: ..." | .deviation.skipped_phases = ["brainstorming","planning"]' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

El engine mostrará warnings pero no bloqueará. **Requiere confirmación del usuario** antes de activar.

### Anti-patterns

- Editar `src/` sin haber declarado `flow_type` → bloqueado
- Declarar `flow_type = "full"` pero no actualizar evidence → bloqueado al intentar editar código
- Setear `evidence.user_approved = true` sin que el usuario realmente haya aprobado → viola el espíritu del proceso
- Nunca editar `session-state.json` manualmente para "saltarse" gates sin deviation mode

## On-Demand Session Context (mandatory)

**El SessionStart hook solo resetea session-state.json. No genera contexto.** Claude consulta contexto bajo demanda según estas reglas:

| Cuándo | Qué consultar |
|--------|---------------|
| Primera interacción de la sesión | `git log --oneline -10`, `git status`, `git branch -v` |
| Antes de cualquier code change (ya en full-flow) | `docs/decisions/log.md` (Learning Loop) |
| No sé en qué branch estoy | `git branch -v` |
| Tarea toca un subsistema específico | Knowledge module correspondiente (tabla en "Knowledge Modules") |

**Regla:** No depender de contexto pre-generado. Si necesitas saber algo, consúltalo.

## Feedback Capture (mandatory)

**Toda interacción no-trivial produce datos de feedback estructurados. Esto es lo que cierra el learning loop.**

### Execution Logs

Después de CADA code change o bug fix, crear/actualizar:
`docs/superpowers/execution-logs/YYYY-MM-DD-<feature-name>.md`

Template en: `docs/superpowers/templates/execution-log-template.md`

**Datos a capturar por fase:**

| Fase | Datos obligatorios |
|------|-------------------|
| **Brainstorming** | Alternativas evaluadas, approach elegido + razón, estimación complejidad (S/M/L/XL), confianza |
| **Planning** | Task count, archivos afectados, estimación tiempo, risk assessment |
| **Implementation** | Tiempo real, blockers, desviaciones del plan, episodios debugging |
| **Verification** | Resultados tests, lint, coverage delta |
| **Retrospective** | Accuracy estimaciones, qué funcionó, qué no, lecciones, tags de contexto |

### Cuándo capturar

| Tipo interacción | Execution Log | Retrospectiva | Decision Log |
|-----------------|---------------|---------------|--------------|
| Informational | No | No | Solo si se encuentra gap |
| Documentation | No | No | Si decisión no-trivial |
| Bug fix | Sí | Sí | Si root cause revela patrón |
| Code change | Sí | Sí | Si decisión de diseño |

## Learning Loop (mandatory)

**Doble loop de aprendizaje: consulta inmediata por interacción + análisis periódico para actualizar guías permanentes.**

### Loop inmediato (antes de cada brainstorming)

Antes de proponer approaches, Claude **DEBE**:

1. **Leer** `docs/decisions/log.md` — buscar keywords relacionados con la tarea actual
2. **Escanear** `docs/superpowers/execution-logs/` recientes — buscar lecciones sobre temas similares
3. **Escanear** `docs/superpowers/retrospectives/` — reviews recientes
4. **Para features de route optimization:** ejecutar `php bin/console app:learning:metrics --period=30d`
5. **Declarar** explícitamente qué se encontró: "Consulté decisiones pasadas: [encontré X relevante / nada relevante]"

### Loop periódico (mensual)

Cuando el usuario solicite un review periódico (o mensualmente si se acuerda):

1. **Recopilar datos:**
   - Leer todos los `docs/superpowers/execution-logs/YYYY-MM-*.md` del periodo
   - Ejecutar `php bin/console app:learning:metrics --period=30d`
   - Leer entradas de `docs/decisions/log.md` del periodo

2. **Analizar patrones:**
   - Accuracy de estimaciones: calcular ratio over/under en todos los execution logs
   - Frecuencia de blockers: categorizar y contar
   - Outcomes de decisiones: conectar decisiones con datos de RoutePerformanceMetric

3. **Producir review:**
   - Escribir en `docs/superpowers/retrospectives/YYYY-MM-review.md`
   - Usar template de `docs/superpowers/templates/retrospective-review-template.md`

4. **Actuar sobre hallazgos:**
   - Actualizar `docs/knowledge/` con nuevos patrones
   - Proponer actualizaciones a CLAUDE.md (presentar al usuario para aprobación)
   - Ajustar factores de calibración de estimaciones
   - Actualizar recomendaciones de estrategias de optimización

## Critical Patterns

### Entity Identity (mandatory)

- **Internal PK**: BIGINT auto-increment (`id`) — joins, internal processing
- **Public ID**: ULID (`public_id`) via `PublicIdTrait` — APIs, URLs, Mercure topics
- **NEVER expose internal `id` in public APIs**

### Multi-Tenancy

- `CustomerTenantFilter` (Doctrine SQL filter) + `CustomerScopedEntityInterface`
- Admin/Operator bypass; ROLE_CUSTOMER and ROLE_DRIVER scoped

### Role Hierarchy

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

### Constructor Signature Changes (mandatory)

When modifying a class constructor (adding/removing/changing parameters):

1. **Search ALL call sites** — `grep -r "new ClassName("` across `src/` AND `tests/`
2. **Check Factory classes** — This project uses the Provider/Factory pattern (`*Factory.php`). Factories instantiate services with `new` — they are NOT auto-wired by Symfony and WILL break silently if not updated.
3. **Check DI config** — If the class is manually wired in `services.yaml` or similar, update there too.
4. **Run tests** — Verify no `ArgumentCountError` or `TypeError` at runtime.

**Why:** Symfony auto-wires most services, but Factories use `new` directly. Changing a constructor without updating its Factory causes runtime errors that tests may not catch if the Factory path isn't covered.

## Anti-Omission Rule (mandatory)

**Todo spec DEBE inventariar la funcionalidad existente afectada y documentar decisiones de omisión. Sin excepciones.**

### Regla

Todo spec de diseño (resultado del brainstorming) debe incluir:

1. **Inventariar** — Enumerar la funcionalidad existente en el área afectada (endpoints, métodos, campos, comportamientos, UI elements). Si no existe funcionalidad previa, declarar explícitamente: "No existing functionality affected".
2. **Decidir item por item** — Para CADA elemento inventariado: ¿se mantiene, se modifica, se omite? Documentar la decisión.
3. **Documentar omisiones** — Toda omisión requiere justificación explícita. Si no hay omisiones, declarar: "No omissions — all inventory items addressed".
4. **Nunca omitir por defecto** — Si un elemento no se menciona en las decisiones de omisión, se asume que debe incluirse. La omisión silenciosa es un defecto, no una decisión.

### Anti-patterns

- Crear funcionalidad nueva sin inventariar qué existe en el área afectada
- "Solo incluí lo que me pareció relevante" — sin documentar qué se excluyó y por qué
- Asumir que el usuario sabe qué se omitió — documentar explícitamente
- Pensar "esto es nuevo, no hay nada que inventariar" sin verificar

### Secciones obligatorias en todo spec

```markdown
## Existing Functionality Inventory
[Lista de funcionalidad existente en el área afectada, o "No existing functionality affected"]

## Omission Decisions
| Element | Decision | Justification |
|---------|----------|---------------|
| [item]  | Include / Omit / Transform | [razón] |
[O "No omissions — all inventory items addressed"]
```

## Knowledge Modules (consultar bajo demanda)

Antes de trabajar en un subsistema, **LEE el módulo relevante** en `docs/knowledge/`:

| Si vas a trabajar en... | Lee primero |
|------------------------|-------------|
| Entidades, relaciones, migraciones, enums | `docs/knowledge/domain-model.md` |
| Providers, factories, resolución per-tenant | `docs/knowledge/provider-framework.md` |
| Controllers, DTOs, APIs, endpoints | `docs/knowledge/api-surface.md` |
| Docker, Railway, variables de entorno | `docs/knowledge/deployment.md` |
| Tests, PHPUnit, coverage | `docs/knowledge/testing.md` |
| Mercure, SSE, tokens JWT | `docs/knowledge/realtime.md` |
| Traccar, posiciones GPS, simulación | `docs/knowledge/gps-tracking.md` |
| SMS, WhatsApp, push, webhooks | `docs/knowledge/notifications.md` |
| Claude AI, embeddings, ML | `docs/knowledge/ai-ml.md` |
| VROOM, OSRM, capacidad, rutas | `docs/knowledge/route-optimization.md` |
| DDD, SOLID, desacoplamiento, bounded contexts | `docs/knowledge/architecture-ddd.md` |
| Patrones de diseño GoF + DDD, catálogo completo | `docs/knowledge/design-patterns.md` |
| Roles, multi-tenancy, CSRF, seguridad | `docs/knowledge/security.md` |
| Skills de Superpowers (completo) | `docs/knowledge/superpowers-skills.md` |
| Feedback, execution logs, learning loop, retrospectives | `docs/knowledge/feedback-learning.md` |
| Templates Twig, Alpine.js, Tailwind, componentes UI | `docs/knowledge/ui-frontend.md` |
| Índice completo de módulos | `docs/knowledge/index.md` |
| Requisitos de negocio, gaps, decisiones | `docs/analysis/2026-03-15-business-requirements-audit.md` |
| Análisis previos del codebase | `docs/analysis/` |

**Regla:** No duplicar info entre CLAUDE.md y los módulos. Al modificar un subsistema, actualizar el módulo correspondiente.

### Freshness Protocol

- **Módulos verificados (fecha < 14 días):** Usar directamente sin spot-check
- **Módulos no verificados (`--`) o > 14 días:** Spot-check 2-3 claims clave contra el código antes de confiar. Si incorrectos → actualizar módulo, commitear, actualizar fecha en `docs/knowledge/index.md`
- **Al terminar cualquier tarea que tocó un subsistema:** Actualizar el knowledge module correspondiente si cambió algo relevante. Incluir en el mismo commit o en commit separado `docs: update {module} knowledge module`
- **Nunca dejar un módulo stale si ya descubriste la discrepancia** — actualizar es parte del trabajo, no una tarea separada
- **Visibilidad rápida:** `docs/codebase-manifest.md` → Knowledge Module Status muestra freshness de todos los módulos de un vistazo

## Regla de Gobernanza de CLAUDE.md

**CLAUDE.md contiene dos tipos de contenido con reglas distintas:**

1. **Instrucciones de comportamiento** (skills, convenciones, critical patterns) — **SIEMPRE inline en CLAUDE.md**. Son instrucciones que Claude debe seguir en cada interacción. Moverlas a módulos externos degrada su efectividad porque Claude puede no leerlas a tiempo.

2. **Referencia bajo demanda** (domain model, deployment, API surface, etc.) — **En `docs/knowledge/`**. Son datos de contexto que se consultan cuando se trabaja en un subsistema específico. No necesitan estar presentes en cada turno.

**Antes de modificar CLAUDE.md, preguntarse:**
- ¿Es una instrucción de comportamiento? → **Debe quedarse inline en CLAUDE.md**
- ¿Es información de referencia consultable? → **Va a `docs/knowledge/`**
- ¿No estoy seguro? → **Preguntar al usuario antes de mover o recortar contenido**

**Prohibido:** Recortar, resumir o mover instrucciones de comportamiento (skills, convenciones, patterns) a módulos externos sin aprobación explícita del usuario.

## Features Document

`docs/FEATURES.md` — descripción completa de todas las características. **Debe mantenerse actualizado** con cada PR que añada, modifique o elimine funcionalidad. Los conteos (entidades, enums) y listados deben reflejar la realidad — verificar antes de usarlos como referencia. Cuando una exploración revele discrepancia, actualizar el archivo o registrar el gap en agent-outputs.

## Backlog Arquitectónico

### [2026-03-11] Providers configurables: Proxy + Factory vs alternativas

**Estado:** Pendiente de implementación
**Decisión:** Transparent Proxy + Provider Factory + CustomerIntegration entity
**Spec:** `docs/superpowers/specs/2026-03-11-user-configurable-providers-design.md`
**Plan:** `docs/superpowers/plans/2026-03-11-user-configurable-providers.md`
**Trigger para revisitar:** Si boilerplate de proxies > 6 servicios, considerar codegen o proxy genérico.

### [2026-03-11] GpsDeviceProviderInterface: Métodos Traccar-específicos

**Estado:** Pendiente
**Decisión:** Stubs en WebhookGpsProvider (login→no-op, getSessionCookie→null)
**Trigger:** Al implementar tercer provider GPS, refactoring obligatorio.

### [2026-03-11] Mercure listeners usan HubInterface directamente

**Estado:** Pendiente
**Decisión:** Deuda técnica documentada. Refactorizar antes de configurar tenant con HttpPolling.

### [2026-03-11] Sin encriptación de credenciales en CustomerIntegration

**Estado:** Pendiente
**Trigger:** Antes de producción con customers configurando API keys de terceros.

### [2026-03-15] Selección de estrategia de optimización

**Estado:** Pendiente
**Contexto:** Actualmente la estrategia se selecciona por provider configuration (CustomerIntegration). Sin visibilidad para admin ni comparación.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 1`
**Trigger:** Cuando se diseñe el flujo UI de creación de rutas (GAP-3.1).

### [2026-03-15] Política de re-optimización automática vs manual

**Estado:** Pendiente
**Contexto:** RouteOptimizationService puede re-optimizar paradas PENDING, pero no hay política definida de cuándo hacerlo automáticamente.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 2`
**Trigger:** Cuando se defina la política de negocio de re-optimización.

### [2026-03-15] Datos históricos para alimentar planificación futura

**Estado:** Pendiente
**Contexto:** Existen AddressRisk, DriverFeedback, RouteComparison, PostRouteAnalyzer — potencialmente útiles para mejorar planificación.
**Ref:** `docs/analysis/2026-03-15-business-requirements-audit.md > Decisión 3`
**Trigger:** Cuando se diseñe el módulo de aprendizaje/mejora continua.

---

## Superpowers Skills (from [obra/superpowers](https://github.com/obra/superpowers))

Las siguientes skills definen el flujo de trabajo y la disciplina de desarrollo que se debe seguir en este proyecto.

---

### Skill 1: Using Superpowers

```yaml
name: using-superpowers
description: Use when starting any conversation - establishes how to find and use skills, requiring Skill tool invocation before ANY response including clarifying questions
```

<EXTREMELY-IMPORTANT>
If you think there is even a 1% chance a skill might apply to what you are doing, you ABSOLUTELY MUST invoke the skill.

IF A SKILL APPLIES TO YOUR TASK, YOU DO NOT HAVE A CHOICE. YOU MUST USE IT.

This is not negotiable. This is not optional. You cannot rationalize your way out of this.
</EXTREMELY-IMPORTANT>

#### Instruction Priority

Superpowers skills override default system prompt behavior, but **user instructions always take precedence**:

1. **User's explicit instructions** (CLAUDE.md, AGENTS.md, direct requests) — highest priority
2. **Superpowers skills** — override default system behavior where they conflict
3. **Default system prompt** — lowest priority

#### The Rule

**Invoke relevant or requested skills BEFORE any response or action.** Even a 1% chance a skill might apply means you should invoke the skill.

#### Interaction Classification (FIRST step)

**Before checking skills, classify the interaction** per "Flujo Obligatorio para Toda Interacción". This determines the flow depth (micro/light/debug/full) and which skills are mandatory.

#### Red Flags (rationalizations to STOP)

| Thought | Reality |
|---------|---------|
| "This is just a simple question" | Questions are tasks. Check for skills. |
| "I need more context first" | Skill check comes BEFORE clarifying questions. |
| "Let me explore the codebase first" | Skills tell you HOW to explore. Check first. |
| "This doesn't need a formal skill" | If a skill exists, use it. |
| "The skill is overkill" | Simple things become complex. Use it. |
| "I'll just do this one thing first" | Check BEFORE doing anything. |

#### Skill Priority

1. **Process skills first** (brainstorming, debugging) - determine HOW to approach the task
2. **Implementation skills second** - guide execution

#### Skill Types

**Rigid** (TDD, debugging): Follow exactly. Don't adapt away discipline.
**Flexible** (patterns): Adapt principles to context.

---

### Skill 2: Brainstorming

```yaml
name: brainstorming
description: "You MUST use this before any creative work - creating features, building components, adding functionality, or modifying behavior. Explores user intent, requirements and design before implementation."
```

Help turn ideas into fully formed designs and specs through natural collaborative dialogue. Start by understanding the current project context, then ask questions one at a time to refine the idea.

**Do NOT invoke any implementation skill, write any code, scaffold any project, or take any implementation action until you have presented a design and the user has approved it.**

#### Anti-Pattern: "This Is Too Simple To Need A Design"

Every project goes through this process. A todo list, a single-function utility, a config change — all of them. "Simple" projects are where unexamined assumptions cause the most wasted work.

#### Checklist (MUST complete in order)

0. **Consult past decisions (Learning Loop)** — Read `docs/decisions/log.md`, scan recent `docs/superpowers/execution-logs/` and `docs/superpowers/retrospectives/`. State explicitly: "Consulté decisiones pasadas: [found X relevant / nothing relevant]"
1. **Classify bounded context (Architecture Gate)** — Identify which bounded context(s) the work touches. Declare explicitly:
   - "Bounded context: [nombre] — **crítico** (DDD puro)" o "**pragmático** (Symfony)"
   - Si es **crítico**: toda entidad nueva va en `src/Domain/{Context}/Model/` como POPO, interfaces de repositorio en `src/Domain/{Context}/Repository/`, implementaciones Doctrine en `src/Infrastructure/{Context}/Doctrine/`. Sin `#[ORM\...]` en modelos de dominio. Ver sección "DDD Architecture" y `docs/knowledge/architecture-ddd.md`.
   - Si es **pragmático**: entidades en `src/Entity/` con ORM attributes es aceptable.
   - Si toca **ambos**: separar claramente qué partes van a cada layer. El contexto crítico no se relaja por conveniencia.
   - **Anti-racionalización:** "Sigo el patrón existente en src/Entity/" NO es razón para poner código nuevo de contexto crítico ahí. El patrón existente es deuda técnica documentada, no un ejemplo a seguir.
2. **Explore project context** — check files, docs, recent commits
3. **Inventory existing functionality (Anti-Omission Gate)** — Always inventory the existing functionality in the area being changed:
   - Enumerate existing elements in the affected area (endpoints, methods, fields, behaviors, UI elements)
   - This inventory becomes the `## Existing Functionality Inventory` section of the spec
   - Every element must have an explicit decision: Include / Omit / Transform with justification
   - This becomes the `## Omission Decisions` section of the spec
   - If no existing functionality is affected, declare: "No existing functionality affected" in the inventory section
4. **Offer visual companion** (if topic will involve visual questions)
5. **Ask clarifying questions** — one at a time, understand purpose/constraints/success criteria
6. **Propose 2-3 approaches** — with trade-offs and your recommendation. If bounded context is critical, every approach MUST respect DDD placement rules from step 1.
7. **Present design** — in sections scaled to their complexity, get user approval after each section
8. **Write design doc** — save to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` and commit
8. **Transition to implementation** — invoke writing-plans skill to create implementation plan

#### Key Principles

- **One question at a time** - Don't overwhelm with multiple questions
- **Multiple choice preferred** - Easier to answer than open-ended when possible
- **YAGNI ruthlessly** - Remove unnecessary features from all designs
- **Explore alternatives** - Always propose 2-3 approaches before settling
- **Incremental validation** - Present design, get approval before moving on

#### Design for Isolation and Clarity

- Break the system into smaller units with one clear purpose, well-defined interfaces, testable independently
- Can someone understand what a unit does without reading its internals?
- Smaller, well-bounded units are easier to work with

#### Working in Existing Codebases

- Explore the current structure before proposing changes. Follow existing patterns **only in pragmatic contexts**.
- **In critical contexts:** follow the DDD rules from CLAUDE.md, NOT the existing patterns in `src/Entity/`. Existing entities with ORM in critical contexts are documented technical debt, not examples to replicate.
- Where existing code has problems that affect the work, include targeted improvements as part of the design.
- Don't propose unrelated refactoring. Stay focused on what serves the current goal.

#### After the Design

1. Write the validated design (spec) to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
2. Dispatch spec-document-reviewer subagent; fix issues until Approved (max 5 iterations)
3. Invoke the **writing-plans** skill to create implementation plan

---

### Skill 3: Writing Plans

```yaml
name: writing-plans
description: Use when you have a validated design and need to create a detailed implementation plan before coding
```

Write comprehensive implementation plans assuming the engineer has zero context for the codebase.

#### Key Principles

- Assume skilled developers with minimal domain knowledge
- Map file structure and responsibilities upfront
- Break work into 2-5 minute steps following TDD pattern
- Save plans to `docs/superpowers/plans/YYYY-MM-DD-<feature-name>.md`

#### Code Quality

- DRY, YAGNI, TDD patterns
- Frequent commits after each task
- Focused files with single responsibilities
- Exact file paths and complete code samples

#### Task Structure (cycle)

1. Write failing test
2. Verify failure
3. Implement minimal solution
4. Verify pass
5. Commit

#### Plan Document Requirements

Every plan must include:
- Header with goal, architecture, tech stack
- File structure mapping
- Numbered tasks with exact file paths
- Complete code snippets (not pseudocode)
- Exact commands with expected outputs
- Checkbox tracking (`- [ ]`)

---

### Skill 4: Executing Plans

```yaml
name: executing-plans
description: Use when you have a written implementation plan to execute in a separate session with review checkpoints
```

Load plan, review critically, execute all tasks, report when complete.

#### The Process

**Step 1: Load and Review Plan**
1. Read plan file
2. Review critically - identify any questions or concerns
3. If concerns: Raise them with user before starting
4. If no concerns: Create TodoWrite and proceed

**Step 2: Execute Tasks**
For each task:
1. Mark as in_progress
2. Follow each step exactly
3. Run verifications as specified
4. **Capture to execution log** — Update `docs/superpowers/execution-logs/` with implementation phase data (blockers, deviations, time)
5. Mark as completed

**Step 3: Complete Development**
After all tasks complete:
1. **Finalize execution log** — Complete all phases including verification results and retrospective
2. Use **finishing-a-development-branch** skill.

#### When to Stop and Ask for Help

**STOP executing immediately when:**
- Hit a blocker (missing dependency, test fails, instruction unclear)
- Plan has critical gaps
- You don't understand an instruction
- Verification fails repeatedly

**Ask for clarification rather than guessing.**

---

### Skill 5: Subagent-Driven Development

```yaml
name: subagent-driven-development
description: Use when executing implementation plans with independent tasks in the current session
```

Execute plan by dispatching fresh subagent per task, with two-stage review after each: **spec compliance review first, then code quality review**.

**Core principle:** Fresh subagent per task + two-stage review (spec then quality) = high quality, fast iteration

#### When to Use

- Implementation plan already written
- Tasks that are mostly independent
- Staying in the current session

#### The Process

1. Read plan once; extract all tasks with full text and context
2. Create TodoWrite with all tasks
3. Per task (loop):
   - Dispatch implementer subagent
   - Handle questions or concerns if raised
   - Implementer implements, tests, commits, self-reviews
   - Dispatch spec compliance reviewer
   - If issues found: implementer fixes, reviewer re-reviews
   - Dispatch code quality reviewer
   - If issues found: implementer fixes, reviewer re-reviews
   - Mark task complete
4. After all tasks complete, dispatch final code reviewer
5. Use **finishing-a-development-branch** skill

#### Model Selection

- **Mechanical implementation** (1-2 files, clear specs): fast, cheap model
- **Integration and judgment** (multi-file coordination): standard model
- **Architecture, design, review**: most capable model

#### Handling Implementer Status

- **DONE:** Proceed to spec compliance review
- **DONE_WITH_CONCERNS:** Read concerns before proceeding
- **NEEDS_CONTEXT:** Provide context and re-dispatch
- **BLOCKED:** Assess: context problem → provide context; task too large → break into pieces; plan wrong → escalate to human

#### Red Flags

- Never skip reviews (spec compliance OR code quality)
- Never dispatch multiple implementation subagents in parallel (conflicts)
- Never make subagent read plan file (provide full text instead)
- Never start code quality review before spec compliance is approved
- If subagent asks questions, answer clearly before proceeding
- If reviewer finds issues, implementer fixes and reviewer re-reviews

---

### Skill 6: Dispatching Parallel Agents

```yaml
name: dispatching-parallel-agents
description: Use when facing 2+ independent tasks that can be worked on without shared state or sequential dependencies
```

When you have multiple unrelated failures, investigating them sequentially wastes time.

**Core principle:** Dispatch one agent per independent problem domain. Let them work concurrently.

#### When to Use

- 3+ test files failing with different root causes
- Multiple subsystems broken independently
- Each problem can be understood without context from others
- No shared state between investigations

#### When NOT to Use

- Failures are related (fix one might fix others)
- Need to understand full system state
- Agents would interfere with each other

#### The Pattern

1. **Identify Independent Domains** - Group failures by what's broken
2. **Create Focused Agent Tasks** - Each agent gets: specific scope, clear goal, constraints, expected output
3. **Dispatch in Parallel**
4. **Review and Integrate** - Read each summary, verify fixes don't conflict, run full test suite

#### Agent Prompt Structure

Good agent prompts are:
1. **Focused** - One clear problem domain
2. **Self-contained** - All context needed to understand the problem
3. **Specific about output** - What should the agent return?

#### Common Mistakes

- **Too broad:** "Fix all the tests" → agent gets lost
- **No context:** "Fix the race condition" → agent doesn't know where
- **No constraints:** Agent might refactor everything
- **Vague output:** "Fix it" → you don't know what changed

---

### Problema conocido: Fallos de infraestructura en subagentes

Los subagentes (Agent tool) pueden fallar con errores de runtime del entorno de ejecución, como `undefined is not an object (evaluating 'H.includes')`. Cuando esto ocurre, **todas** las herramientas del subagente fallan (Read, Bash, Grep, Glob) y el agente no puede hacer ningún trabajo útil.

**Síntomas:**
- El subagente reporta que no puede ejecutar ninguna herramienta
- Errores JavaScript internos en las llamadas a herramientas
- El resultado del agente dice "infrastructure errors" o similar

**Solución:**
1. **No reintentar el mismo subagente** — el entorno está roto y reintentar no lo arregla
2. **Ejecutar la tarea en el hilo principal** — si el subagente falla, hacer el trabajo directamente sin delegar
3. **Alternativa: lanzar un nuevo subagente** — un nuevo agente obtiene un entorno fresco que puede funcionar
4. **Si persiste:** informar al usuario y sugerir reiniciar la sesión de Claude Code

**Regla:** Cuando un subagente falla por infraestructura, no marcar la tarea como completada. Reintentarla en el hilo principal o con un nuevo subagente.

---

### Problema conocido: Error "tool_use ids must be unique" (API 400)

La API de Claude rechaza peticiones con HTTP 400 y mensaje `tool_use ids must be unique` cuando el historial de conversación contiene bloques `tool_use` con IDs duplicados. Esto es un **bug del cliente** (Claude Code / Agent SDK), no del servidor.

**Causas principales:**
- Llamadas a herramientas en paralelo que generan IDs duplicados
- Conversaciones largas con muchos turnos de tool_use donde la reconstrucción del historial introduce duplicados
- Sesiones reanudadas (`--resume`) con historial corrupto

**Síntomas:**
- Error 400: `messages.N.content.M: tool_use ids must be unique`
- La conversación se corta abruptamente y no se puede continuar
- Las herramientas dejan de funcionar en la sesión actual

**Mitigación (qué hacer Claude para reducir riesgo):**
1. **Hacer commits frecuentes** — cada tarea completada debe committearse inmediatamente para que el progreso no se pierda si la sesión se corrompe
2. **Documentar estado en TodoWrite** — mantener el todo list actualizado para que al reanudar se sepa qué falta
3. **Preferir tareas atómicas** — dividir trabajo grande en pasos pequeños e independientes; si la sesión se rompe a mitad de un paso, se pierde menos trabajo
4. **Limitar profundidad de subagentes** — conversaciones con muchas llamadas paralelas a herramientas son más propensas al error; si una tarea necesita >20 tool calls secuenciales, considerar dividirla

**Recuperación (qué hacer cuando ocurre):**
1. **Usar `/clear`** — resetea el historial de la conversación y puede permitir continuar
2. **Iniciar nueva sesión** — `claude` sin `--resume` empieza con historial limpio
3. **Resumir sesión anterior con cuidado** — `claude --resume <id>` puede funcionar si el error fue puntual, pero si el historial está corrupto fallará de nuevo
4. **Revisar git log** — verificar qué commits se hicieron antes del error para saber desde dónde continuar
5. **Leer TodoWrite** — si había una lista de tareas, verificar cuáles están completadas y cuáles pendientes

**Regla:** Ante este error, NUNCA asumir que el trabajo previo se guardó. Verificar con `git log` y `git status` antes de continuar. Hacer commits más frecuentes es la mejor protección.

---

### Problema conocido: Error "assistant message prefill" (API 400)

La API de Claude rechaza peticiones con HTTP 400 y mensaje `This model does not support assistant message prefill` cuando el cliente intenta pre-llenar la respuesta del asistente con un modelo que no lo soporta. Es un **bug del cliente** (Claude Code / Agent SDK), no del flujo de trabajo.

**Causas principales:**
- El cliente construye mal la petición API (envía mensaje assistant como último mensaje)
- Conversaciones largas donde la compresión de contexto corrompe la estructura de mensajes
- Sesiones reanudadas con historial malformado

**Síntomas:**
- Error 400: `This model does not support assistant message prefill`
- La sesión se interrumpe abruptamente
- Idéntico comportamiento al error de tool_use ids duplicados

**Mitigación y recuperación:** Idénticas al error "tool_use ids must be unique" (ver arriba). Las mismas 4 reglas de mitigación y 5 pasos de recuperación aplican. La mejor protección sigue siendo: **commits frecuentes + tareas atómicas + TodoWrite actualizado**.

---

### Subagent Output Limits (mandatory)

Los subagentes (Agent tool) tienen un límite de lectura de 25,000 tokens en el entorno web. Si el output de un subagente excede este límite, el agente padre no puede leer el resultado y el trabajo se pierde.

#### Reglas para subagentes

1. **Límite absoluto:** El output final de cualquier subagente no debe exceder **300 líneas** o **15,000 tokens** (lo que se alcance primero)
2. **Preferir escribir a archivo:** Si el análisis produce contenido extenso, el subagente debe escribirlo a un archivo en el repo (e.g., `docs/superpowers/agent-outputs/`) y retornar solo un resumen de ~50-100 líneas con la ruta al archivo
3. **Nunca incluir código fuente completo en el output:** Referenciar archivos y líneas en vez de copiar bloques de código
4. **Resúmenes ejecutivos:** Todo output de subagente debe empezar con un resumen de 5-10 líneas con los hallazgos clave

#### Reglas para el agente principal (al despachar subagentes)

1. **Incluir en CADA prompt de subagente:** "Tu output final no debe exceder 200 líneas. Si necesitas documentar más, escribe a un archivo y retorna la ruta."
2. **Para agentes Explore:** Pedir hallazgos específicos, no dumps de código
3. **Para agentes Plan:** Pedir plan conciso con file paths y pasos, sin código completo inline

#### Anti-patterns

- Subagente que retorna el contenido completo de múltiples archivos → referenciar paths y líneas
- Subagente que lista todos los resultados de grep/glob → filtrar y resumir
- Plan de 500+ líneas con código completo inline → extraer código a archivos, plan solo con referencias

---

### Skill 7: Test-Driven Development

```yaml
name: test-driven-development
description: Use when implementing any feature or bugfix, before writing implementation code
```

Write the test first. Watch it fail. Write minimal code to pass.

**Core principle:** If you didn't watch the test fail, you don't know if it tests the right thing.

**Violating the letter of the rules is violating the spirit of the rules.**

#### The Iron Law

```
NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST
```

Write code before the test? Delete it. Start over.

**No exceptions:**
- Don't keep it as "reference"
- Don't "adapt" it while writing tests
- Don't look at it
- Delete means delete

#### Red-Green-Refactor

**RED - Write Failing Test**
- One behavior, clear name, real code (no mocks unless unavoidable)

**Verify RED - Watch It Fail (MANDATORY)**
- Test fails (not errors), failure message is expected, fails because feature missing

**GREEN - Minimal Code**
- Write simplest code to pass the test. Don't add features.

**Verify GREEN - Watch It Pass (MANDATORY)**
- Test passes, other tests still pass, output pristine

**REFACTOR - Clean Up**
- After green only: remove duplication, improve names, extract helpers
- Keep tests green. Don't add behavior.

#### Common Rationalizations

| Excuse | Reality |
|--------|---------|
| "Too simple to test" | Simple code breaks. Test takes 30 seconds. |
| "I'll test after" | Tests passing immediately prove nothing. |
| "Need to explore first" | Fine. Throw away exploration, start with TDD. |
| "TDD will slow me down" | TDD faster than debugging. |
| "Already spent X hours, deleting is wasteful" | Sunk cost fallacy. |

#### Red Flags - STOP and Start Over

- Code before test
- Test passes immediately
- Can't explain why test failed
- Rationalizing "just this once"
- "Tests after achieve the same purpose"
- "Keep as reference"
- "TDD is dogmatic, I'm being pragmatic"

**All of these mean: Delete code. Start over with TDD.**

#### Verification Checklist

- [ ] Every new function/method has a test
- [ ] Watched each test fail before implementing
- [ ] Each test failed for expected reason
- [ ] Wrote minimal code to pass each test
- [ ] All tests pass
- [ ] Output pristine (no errors, warnings)
- [ ] Tests use real code (mocks only if unavoidable)
- [ ] Edge cases and errors covered

---

### Skill 8: Systematic Debugging

```yaml
name: systematic-debugging
description: Use when encountering any bug, test failure, or unexpected behavior, before proposing fixes
```

**Core principle:** ALWAYS find root cause before attempting fixes. Symptom fixes are failure.

**Violating the letter of this process is violating the spirit of debugging.**

#### The Iron Law

```
NO FIXES WITHOUT ROOT CAUSE INVESTIGATION FIRST
```

#### The Four Phases

**Phase 1: Root Cause Investigation (MANDATORY before any fix)**

1. **Read Error Messages Carefully** - Don't skip. Read stack traces completely.
2. **Reproduce Consistently** - Can you trigger it reliably? If not → gather more data, don't guess.
3. **Check Recent Changes** - Git diff, recent commits, new dependencies, config changes.
4. **Gather Evidence in Multi-Component Systems** - For EACH component boundary: log what enters/exits, verify config propagation, check state at each layer. Run once to gather evidence.
5. **Trace Data Flow** - Where does bad value originate? Keep tracing up until you find the source. Fix at source, not at symptom.

**Phase 2: Pattern Analysis**
1. Find working examples in same codebase
2. Compare against references COMPLETELY (don't skim)
3. Identify ALL differences between working and broken
4. Understand dependencies

**Phase 3: Hypothesis and Testing**
1. Form SINGLE hypothesis: "I think X is the root cause because Y"
2. Make SMALLEST possible change to test
3. One variable at a time
4. If didn't work → form NEW hypothesis, DON'T add more fixes on top

**Phase 4: Implementation**
1. Create failing test case (MUST have before fixing)
2. Implement SINGLE fix addressing root cause
3. Verify fix: test passes, no other tests broken
4. **If 3+ fixes failed:** STOP. Question the architecture. Discuss with user before attempting more fixes.

#### Red Flags - STOP and Follow Process

- "Quick fix for now, investigate later"
- "Just try changing X and see if it works"
- Proposing solutions before tracing data flow
- "One more fix attempt" (when already tried 2+)
- Each fix reveals new problem in different place

**ALL of these mean: STOP. Return to Phase 1.**

#### Real-World Impact

- Systematic approach: 15-30 minutes to fix
- Random fixes approach: 2-3 hours of thrashing
- First-time fix rate: 95% vs 40%

---

### Skill 9: Verification Before Completion

```yaml
name: verification-before-completion
description: Use when about to claim work is complete, fixed, or passing - requires running verification commands and confirming output before making any success claims
```

**Core principle:** Evidence before claims, always.

**Violating the letter of this rule is violating the spirit of this rule.**

#### The Iron Law

```
NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE
```

If you haven't run the verification command in this message, you cannot claim it passes.

#### The Gate Function

```
BEFORE claiming any status:
1. IDENTIFY: What command proves this claim?
2. RUN: Execute the FULL command (fresh, complete)
3. READ: Full output, check exit code, count failures
4. VERIFY: Does output confirm the claim?
5. CAPTURE: Record results in execution log (tests, lint, coverage delta)
6. ONLY THEN: Make the claim

Skip any step = lying, not verifying
```

#### Common Failures

| Claim | Requires | Not Sufficient |
|-------|----------|----------------|
| Tests pass | Test command output: 0 failures | Previous run, "should pass" |
| Linter clean | Linter output: 0 errors | Partial check, extrapolation |
| Build succeeds | Build command: exit 0 | Linter passing, logs look good |
| Bug fixed | Test original symptom: passes | Code changed, assumed fixed |
| Agent completed | VCS diff shows changes | Agent reports "success" |
| Requirements met | Line-by-line checklist | Tests passing |

#### Red Flags - STOP

- Using "should", "probably", "seems to"
- Expressing satisfaction before verification ("Great!", "Perfect!", "Done!")
- About to commit/push/PR without verification
- Trusting agent success reports
- Relying on partial verification

#### Rationalization Prevention

| Excuse | Reality |
|--------|---------|
| "Should work now" | RUN the verification |
| "I'm confident" | Confidence ≠ evidence |
| "Just this once" | No exceptions |
| "Agent said success" | Verify independently |
| "Partial check is enough" | Partial proves nothing |

---

### Skill 10: Receiving Code Review

```yaml
name: receiving-code-review
description: Use when receiving code review feedback - requires technical rigor and verification, not performative agreement or blind implementation
```

**Core principle:** Verify before implementing. Ask before assuming. Technical correctness over social comfort.

#### The Response Pattern

```
1. READ: Complete feedback without reacting
2. UNDERSTAND: Restate requirement in own words (or ask)
3. VERIFY: Check against codebase reality
4. EVALUATE: Technically sound for THIS codebase?
5. RESPOND: Technical acknowledgment or reasoned pushback
6. IMPLEMENT: One item at a time, test each
```

#### Forbidden Responses

**NEVER:** "You're absolutely right!", "Great point!", "Let me implement that now" (before verification)

**INSTEAD:** Restate the technical requirement, ask clarifying questions, push back with technical reasoning if wrong, just start working.

#### Handling Unclear Feedback

If ANY item is unclear: **STOP** - do not implement anything yet. Ask for clarification on unclear items.

#### When To Push Back

- Suggestion breaks existing functionality
- Reviewer lacks full context
- Violates YAGNI (unused feature)
- Technically incorrect for this stack
- Conflicts with user's architectural decisions

#### Implementation Order (for multi-item feedback)

1. Clarify anything unclear FIRST
2. Blocking issues (breaks, security)
3. Simple fixes (typos, imports)
4. Complex fixes (refactoring, logic)
5. Test each fix individually
6. Verify no regressions

---

### Skill 11: Requesting Code Review

```yaml
name: requesting-code-review
description: Use when completing tasks, implementing major features, or before merging to verify work meets requirements
```

#### When to Request Review

**Mandatory:**
- After each task in subagent-driven development
- After completing major feature
- Before merge to main

**Optional but valuable:**
- When stuck (fresh perspective)
- Before refactoring (baseline check)
- After fixing complex bug

#### How to Request

1. Get git SHAs (BASE_SHA and HEAD_SHA)
2. Dispatch code-reviewer subagent with: what was implemented, plan/requirements, base SHA, head SHA, description
3. Act on feedback: Fix Critical immediately, Fix Important before proceeding, Note Minor for later

---

### Skill 12: Finishing a Development Branch

```yaml
name: finishing-a-development-branch
description: Use when implementation is complete and you need to decide how to integrate the work
```

**Core principle:** Verify tests → Validate merge → Present options → Execute choice → Clean up.

#### The Process

**Step 1: Verify Tests** - Run project test suite. If tests fail, STOP. Don't proceed.

**Step 2: Determine Base Branch**

**Step 3: Validate Merge with Base Branch (MANDATORY)**

Antes de presentar opciones, verificar que la rama puede mergearse limpiamente con la rama base:

```bash
git fetch origin <base-branch>
git merge --no-commit --no-ff origin/<base-branch>
# Inspeccionar resultado
git merge --abort
```

- **Sin conflictos** → Continuar al Step 4.
- **Con conflictos** → Reportar al usuario los archivos en conflicto. Resolver TODOS los conflictos antes de continuar. Proceso:
  1. Listar archivos en conflicto
  2. Para cada archivo: determinar si es auto-generated (`codebase-manifest.md` → tomar base y regenerar) o código real (resolver manualmente)
  3. Ejecutar `git merge origin/<base-branch>`, resolver conflictos, commit del merge
  4. Re-ejecutar tests para verificar que el merge no rompió nada
  5. Solo entonces continuar al Step 4
- **Nunca** crear un PR con conflictos pendientes contra la rama base

**Step 4: Present Options**
```
1. Merge back to <base-branch> locally
2. Push and create a Pull Request
3. Keep the branch as-is (I'll handle it later)
4. Discard this work
```

**Step 5: Execute Choice**
- Option 1: Merge locally, verify tests on merged result, delete feature branch
- Option 2: Push branch, create PR via `gh pr create`
- Option 3: Keep as-is, report location
- Option 4: Confirm with user before deleting (require typed "discard")

**Step 6: Design Retrospective** (before cleanup — MANDATORY write to file)
- Revisar decisiones de diseño tomadas en la rama (consultar `docs/decisions/log.md` si se usó)
- ¿Algún patrón se siente forzado o sobre-engineered? → Simplificar antes de merge
- ¿Se descubrió algo que debería actualizar la documentación? → Actualizar knowledge modules
- ¿Hay lecciones que mejoren las guías de CLAUDE.md? → Proponer al usuario
- **Completar la fase Retrospective del execution log** en `docs/superpowers/execution-logs/` con: estimate accuracy, what worked, what didn't, lessons learned
- **Añadir entrada a `docs/decisions/log.md`** si hubo decisiones de diseño no-triviales
- **Commit y push** del execution log y decision log actualizados

**Step 7: Cleanup Worktree** (for Options 1, 2, 4 only)

#### Red Flags

- Never proceed with failing tests
- Never merge without verifying tests on result
- Never create a PR with unresolved conflicts against the base branch
- Never delete work without confirmation
- Never force-push without explicit request

---

### Skill 13: Using Git Worktrees

```yaml
name: using-git-worktrees
description: Use when starting feature work that needs isolation from current workspace
```

**Core principle:** Systematic directory selection + safety verification = reliable isolation.

#### Directory Selection Process

1. Check existing: `.worktrees/` (preferred, hidden) or `worktrees/`
2. Check CLAUDE.md for preference
3. Ask user if no directory exists

#### Safety Verification

**MUST verify directory is gitignored before creating worktree.** If NOT ignored: add to `.gitignore`, commit, then proceed.

#### Creation Steps

1. Detect project name
2. Create worktree with new branch: `git worktree add "$path" -b "$BRANCH_NAME"`
3. Run project setup (auto-detect: `composer install`, `npm install`, etc.)
4. Verify clean baseline (run tests)
5. Report location and test status

---

### Skill 14: Writing Skills

```yaml
name: writing-skills
description: Use when creating new skills, editing existing skills, or verifying skills work before deployment
```

**Writing skills IS Test-Driven Development applied to process documentation.**

#### What is a Skill?

A **skill** is a reference guide for proven techniques, patterns, or tools. Skills help future Claude instances find and apply effective approaches.

**Skills are:** Reusable techniques, patterns, tools, reference guides
**Skills are NOT:** Narratives about how you solved a problem once

#### The Iron Law (Same as TDD)

```
NO SKILL WITHOUT A FAILING TEST FIRST
```

#### TDD Mapping for Skills

| TDD Concept | Skill Creation |
|-------------|----------------|
| Test case | Pressure scenario with subagent |
| Production code | Skill document (SKILL.md) |
| Test fails (RED) | Agent violates rule without skill (baseline) |
| Test passes (GREEN) | Agent complies with skill present |
| Refactor | Close loopholes while maintaining compliance |

#### RED-GREEN-REFACTOR for Skills

**RED:** Run pressure scenario WITHOUT skill. Document exact behavior and rationalizations.
**GREEN:** Write minimal skill addressing those specific violations. Verify agents now comply.
**REFACTOR:** Identify new rationalizations → add explicit counters → re-test until bulletproof.

#### SKILL.md Structure

```markdown
---
name: skill-name-with-hyphens
description: Use when [specific triggering conditions]
---

# Skill Name

## Overview - Core principle in 1-2 sentences
## When to Use - Symptoms and use cases
## Core Pattern - Before/after code comparison
## Quick Reference - Table or bullets for scanning
## Common Mistakes - What goes wrong + fixes
```

#### Claude Search Optimization (CSO)

- Description starts with "Use when..." — triggering conditions only
- **NEVER summarize the skill's process in the description** (Claude may follow description instead of reading full skill)
- Use concrete triggers, symptoms, and situations
- Keywords throughout for search (errors, symptoms, tools)

---

### Skill 15: Learning Review

```yaml
name: learning-review
description: Use when conducting monthly or quarterly retrospective reviews of accumulated feedback data from execution logs and business metrics
```

**Core principle:** Los datos acumulados solo generan valor si se analizan y se actúa sobre los hallazgos.

#### When to Use

- Usuario solicita review periódico
- Ha pasado 1+ mes desde el último review en `docs/superpowers/retrospectives/`
- Se han acumulado 5+ execution logs sin analizar

#### The Process

1. **Recopilar datos:**
   - Leer todos los `docs/superpowers/execution-logs/` del periodo
   - Ejecutar `php bin/console app:learning:metrics --period=30d` (si disponible)
   - Leer entradas recientes de `docs/decisions/log.md`

2. **Analizar patrones:**
   - Accuracy de estimaciones (over/under ratio)
   - Frecuencia y categorías de blockers
   - Outcomes de decisiones de diseño
   - Métricas de negocio (km saved, delivery rate, optimizer performance)

3. **Producir review:**
   - Escribir en `docs/superpowers/retrospectives/YYYY-MM-review.md`
   - Usar template de `docs/superpowers/templates/retrospective-review-template.md`

4. **Actuar:**
   - Actualizar `docs/knowledge/` con nuevos patrones descubiertos
   - Proponer actualizaciones a CLAUDE.md (presentar al usuario para aprobación)
   - Ajustar factores de calibración de estimaciones
   - Commit y push de todos los cambios

#### Red Flags

- Producir review sin datos suficientes (menos de 3 execution logs)
- No actuar sobre los hallazgos (review sin acciones = review inútil)
- Modificar CLAUDE.md sin aprobación del usuario
