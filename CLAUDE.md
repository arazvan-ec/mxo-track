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

## Priority Tiers

No todas las reglas tienen el mismo peso. Ante conflicto o presión de tiempo:

| Tier | Secciones | Cuándo aplicar |
|------|-----------|----------------|
| **T1: Siempre** | SOLID, DDD placement, Critical Patterns, Conventions | Todo código, sin excepciones |
| **T2: Features** | Brainstorming (Skill 2), Plans (Skill 3), TDD (Skill 7), Atomic Commits | Features y bug fixes |
| **T3: Proceso completo** | Execution Logs, Retrospectives, Learning Loop, Decision Log | Cambios no-triviales, cuando el tiempo lo permite |

**Regla:** T1 nunca se salta. T2 se salta solo con aprobación explícita del usuario. T3 se puede diferir si hay presión de tiempo, documentando qué se saltó.

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

**[CURRENT]** ~30% del codebase usa DDD puro (Route Planning parcial, Route Optimization). El resto está en `src/Entity/` con ORM attributes.
**[TARGET]** Contextos críticos completamente en `src/Domain/{Context}/Model/` como POPOs.

Pureza híbrida: **contextos críticos → DDD puro, contextos CRUD → pragmático Symfony.** Todo código nuevo en contextos críticos sigue DDD desde el inicio.

### Bounded Contexts

**Críticos (DDD puro):** Route Planning (Route, RouteStop, RouteSnapshot, RouteEvent), Shipment/Delivery (Shipment, Parcel, DeliveryEvidence, POD), Route Optimization (ya bien separado).

**Pragmáticos (Symfony):** Identity/Auth (User), Tenant Management (Customer), Fleet (Vehicle, Driver), Notifications.

### Reglas

**Código nuevo en contexto crítico → siempre DDD.** Código existente en `src/Entity/` es deuda técnica documentada — no replicar el patrón, pero no es necesario migrar al tocar un archivo existente salvo que el cambio lo justifique.
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

### Cuándo regenerar

Ejecutar `make manifest` y commitear el resultado **siempre** como último paso antes de push o al finalizar una rama. Sin condiciones — es barato (~1 segundo) y garantiza que el manifest esté siempre fresco.

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

| Tipo | Señal | Flujo |
|------|-------|-------|
| **Informational** | "qué hace X?", "explica Y", "dónde está Z?" | Micro-flow |
| **Documentation** | Editar docs, knowledge modules, specs | Light-flow |
| **Bug fix** | Error, test failure, comportamiento inesperado | Debug-flow |
| **Code change** | Feature nueva, refactor, enhancement | Full-flow |
| **Exploration** | "audita X", "analiza Y", "cómo funciona Z?", análisis de codebase, architecture review | Explore-flow |

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

1. **Consultar** — Leer decisiones pasadas, retrospectivas, métricas de negocio (ver Learning Loop)
2. **Brainstorm** — Invocar Skill 2 (obligatorio, sin escape "es simple")
3. **Plan** — Invocar Skill 3 (escribir plan en `docs/superpowers/plans/`)
4. **Ejecutar** — Invocar Skill 4 o 5 (TDD obligatorio via Skill 7)
5. **Verificar** — Invocar Skill 9 (evidencia antes de claims)
6. **Capturar** — Escribir execution log
7. **Retrospectiva** — Escribir entrada de retrospectiva
8. **Finalizar** — Invocar Skill 12

### Anti-racionalizaciones

| Pensamiento | Realidad |
|-------------|----------|
| "Es un cambio de una línea" | Los cambios de una línea rompen producción. Full-flow. |
| "Ya sé la respuesta" | La consulta revela lo que no sabes que no sabes. |
| "El micro-flow es overkill para esta pregunta" | 10 segundos de consulta nunca son overkill. |
| "Saltemos brainstorming, la solución es obvia" | Las soluciones "obvias" que saltan brainstorming son las que pierden edge cases. |
| "Nadie va a leer la retrospectiva" | Las futuras instancias de Claude sí la leerán. Ese es el learning loop. |

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

## Knowledge Modules

Antes de trabajar en un subsistema, consulta el módulo relevante. Índice completo en `docs/knowledge/index.md`.

**Regla:** No duplicar info entre CLAUDE.md y los módulos. Al modificar un subsistema, actualizar el módulo correspondiente.

## Regla de Gobernanza de CLAUDE.md

**CLAUDE.md contiene dos tipos de contenido con reglas distintas:**

1. **Instrucciones de comportamiento** (process gates Skills 1-6, convenciones, critical patterns, interaction flow) — **SIEMPRE inline en CLAUDE.md**. Son instrucciones que Claude debe seguir en cada interacción. Moverlas a módulos externos degrada su efectividad.

2. **Referencia bajo demanda** (domain model, deployment, API surface, execution skills 7-15, etc.) — **En `docs/knowledge/`**. Son datos de contexto o guías de ejecución que se consultan cuando aplican. No necesitan estar presentes en cada turno.

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
3. **Offer visual companion** (if topic will involve visual questions)
4. **Ask clarifying questions** — one at a time, understand purpose/constraints/success criteria
5. **Propose 2-3 approaches** — with trade-offs and your recommendation. If bounded context is critical, every approach MUST respect DDD placement rules from step 1.
6. **Present design** — in sections scaled to their complexity, get user approval after each section
7. **Write design doc** — save to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` and commit
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

> **Nota:** Problemas conocidos de subagentes (infrastructure failures, tool_use id errors) y límites de output documentados en `docs/knowledge/superpowers-skills.md` § Problemas Conocidos.

---

### Skills 7-15 (referencia completa en `docs/knowledge/superpowers-skills.md`)

| Skill | Trigger |
|-------|---------|
| 7: TDD | Antes de implementar cualquier feature o bugfix |
| 8: Systematic Debugging | Al encontrar bug, test failure, o comportamiento inesperado |
| 9: Verification | Antes de declarar trabajo completado |
| 10: Receiving Code Review | Al recibir feedback de code review |
| 11: Requesting Code Review | Al completar feature o antes de merge |
| 12: Finishing Branch | Al completar implementación |
| 13: Git Worktrees | Para aislar feature work |
| 14: Writing Skills | Al crear o editar skills |
| 15: Learning Review | Reviews periódicos de feedback |
