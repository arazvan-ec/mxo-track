# Architecture Reference

> Este es un documento de **REFERENCIA** que el agente consulta para principios, patrones y criterios de calidad arquitectónica.
> Para análisis específico del proyecto, ver `openspec/specs/architecture-profile.yaml`.
> Para verificación automatizada, usar `/workflow-skill:solid-analyzer`.

---

## Principio Fundamental

**Cambio Fácil = Cambio Localizado**

Una arquitectura es buena cuando:
- Un cambio simple toca **pocos archivos** (≤3 para cambios triviales)
- Un cambio no genera **efectos cascada** inesperados
- Los patrones de diseño **reducen** la complejidad, no la aumentan

---

## SOLID Principles Reference

### S - Single Responsibility Principle

> "Una clase debe tener una, y solo una, razón para cambiar"

#### Indicadores

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Líneas por clase/módulo | ≤200 | 201-400 | >400 |
| Métodos públicos por clase | ≤7 | 8-12 | >12 |
| Dependencias en constructor | ≤7 | 8-10 | >10 |
| Razones para cambiar | 1 | 2 | >2 |

**Test Rápido**: "¿Puedo describir esta clase en UNA frase sin usar 'y'?"
- ✅ "Gestiona la persistencia de usuarios"
- ❌ "Gestiona usuarios y envía emails y valida permisos"

#### Violations and Corrective Patterns

**Violation: God Class**

Symptoms:
- Clase con >200 líneas, >7 métodos públicos, >10 dependencias
- Múltiples `#region` o secciones comentadas
- Nombre genérico: `Manager`, `Handler`, `Processor`, `Service`

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Strategy** | Múltiples algoritmos/comportamientos | `PaymentStrategy` → `CreditCardPayment`, `PayPalPayment` |
| **Extract Class** | Responsabilidades claramente separables | `UserService` → `UserValidator`, `UserNotifier` |
| **Facade** | Simplificar interfaz compleja | `OrderFacade` coordina `Inventory`, `Payment`, `Shipping` |
| **Mediator** | Reducir comunicación N-to-N | `ChatMediator` entre `User` objects |

Decision Tree:
```
¿Tiene múltiples algoritmos intercambiables?
  → SÍ: Strategy
  → NO: ¿Las responsabilidades son independientes?
    → SÍ: Extract Class
    → NO: ¿Coordina múltiples subsistemas?
      → SÍ: Facade
      → NO: ¿Hay comunicación N-to-N entre objetos?
        → SÍ: Mediator
        → NO: Extract Class con composición
```

**Violation: Mixed Concerns**

Symptoms:
- Lógica de negocio mezclada con acceso a datos
- HTTP handling en domain logic
- Logging/caching inline en métodos de negocio

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Repository** | Separar persistencia de dominio | `UserRepository` abstrae DB access |
| **Service Layer** | Coordinar casos de uso | `OrderService` usa `Repository` + `PaymentGateway` |
| **Decorator** | Cross-cutting concerns (logging, cache) | `CachedRepository` wraps `Repository` |

---

### O - Open/Closed Principle

> "Abierto para extensión, cerrado para modificación"

#### Indicadores

| Indicador | Bueno | Malo |
|-----------|-------|------|
| Añadir comportamiento | Crear nueva clase | Modificar clase existente |
| Switch/if-else por tipo | 0 | >0 (usar polimorfismo) |
| instanceof chains | 0 | >0 (usar Strategy) |

**Test Rápido**: "¿Puedo añadir un nuevo tipo de X sin modificar código existente?"

#### Violations and Corrective Patterns

**Violation: Type Switching**

Symptoms:
- `switch(type)` o `if-else` chains por tipo
- Añadir nuevo tipo requiere modificar código existente
- Métodos con múltiples `instanceof` checks

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Strategy** | Algoritmos intercambiables en runtime | `DiscountStrategy` → `PercentDiscount`, `FixedDiscount` |
| **Factory Method** | Creación de objetos por tipo | `NotificationFactory.create(type)` |
| **Chain of Responsibility** | Procesar por múltiples handlers | `ValidationChain` para validadores |
| **Visitor** | Operaciones sobre jerarquía de objetos | `ReportVisitor` genera diferentes reportes |

Decision Tree:
```
¿El comportamiento varía en runtime?
  → SÍ: Strategy
  → NO: ¿Es sobre creación de objetos?
    → SÍ: Factory Method / Abstract Factory
    → NO: ¿Múltiples procesadores en secuencia?
      → SÍ: Chain of Responsibility
      → NO: ¿Operaciones sobre jerarquía de tipos?
        → SÍ: Visitor
        → NO: Strategy (default)
```

**Violation: Modification for Extension**

Symptoms:
- Añadir feature = modificar clase existente
- Métodos que crecen con cada nuevo requisito
- Flags booleanos para habilitar comportamientos

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Decorator** | Añadir comportamiento sin modificar | `LoggingService` wraps `Service` |
| **Template Method** | Algoritmo fijo con pasos variables | `DataImporter` con `parse()` abstracto |
| **Plugin/Extension** | Funcionalidad pluggable | `PluginManager` carga extensiones |

---

### L - Liskov Substitution Principle

> "Los subtipos deben ser sustituibles por sus tipos base"

#### Indicadores

| Indicador | Bueno | Malo |
|-----------|-------|------|
| Subclase reemplaza a padre | Sin sorpresas | Comportamiento diferente |
| Override de métodos | Mantiene contrato | Rompe expectativas |
| Excepciones en override | Mismas que padre | Nuevas excepciones |

**Test Rápido**: "¿Puedo usar cualquier implementación donde se espera la interfaz?"

#### Violations and Corrective Patterns

**Violation: Contract Breaking Subtypes**

Symptoms:
- Override que cambia comportamiento esperado
- Subtipo que lanza excepciones no esperadas
- Precondiciones más estrictas en subtipo
- Postcondiciones más débiles en subtipo

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Composition over Inheritance** | Evitar jerarquías problemáticas | `Car` HAS-A `Engine` vs IS-A `Vehicle` |
| **Adapter** | Adaptar interfaz incompatible | `LegacyPaymentAdapter` implements `PaymentInterface` |
| **Null Object** | Evitar null checks y excepciones | `NullLogger` implements `Logger` (no-op) |
| **Interface Extraction** | Definir contrato explícito | Extract `Readable` from `File` class |

Decision Tree:
```
¿El subtipo viola el contrato del padre?
  → SÍ: ¿Es por incompatibilidad fundamental?
    → SÍ: Composition over Inheritance
    → NO: ¿Es por sistema legacy?
      → SÍ: Adapter
      → NO: Interface Extraction
  → NO: ¿El problema es con null/ausencia?
    → SÍ: Null Object / Optional
    → NO: Revisar precondiciones/postcondiciones
```

---

### I - Interface Segregation Principle

> "Los clientes no deben depender de interfaces que no usan"

#### Indicadores

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Métodos por interfaz | ≤5 | 6-8 | >8 |
| Implementaciones que usan todo | 100% | >80% | <80% |
| Métodos vacíos/NotImplemented | 0 | 0 | >0 |

**Test Rápido**: "¿Alguna implementación tiene métodos vacíos o `throw NotImplemented`?"

#### Violations and Corrective Patterns

**Violation: Fat Interface**

Symptoms:
- Interface con >5 métodos
- Implementaciones con métodos vacíos o `throw NotImplemented`
- Clientes que solo usan 2-3 métodos de una interface grande

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Role Interfaces** | Dividir por responsabilidad | `Readable`, `Writable`, `Seekable` |
| **Adapter** | Implementar solo lo necesario | `ReadOnlyFileAdapter` only implements `Readable` |
| **Facade** | Simplificar interface compleja | `SimplePrinter` wraps complex `Printer` |

Segregation Guidelines:
```
Interface original → Interfaces segregadas

IRepository<T>           →  IReader<T>
  - getById()                 - getById()
  - getAll()                  - getAll()
  - save()                    - findBy()
  - delete()               IWriter<T>
  - findBy()                  - save()
  - count()                   - delete()
                           ICounter<T>
                              - count()
```

---

### D - Dependency Inversion Principle

> "Depender de abstracciones, no de concreciones"

#### Indicadores

| Indicador | Bueno | Malo |
|-----------|-------|------|
| Dependencias en Domain | Solo abstracciones | Clases concretas de infra |
| Constructores | Reciben interfaces | Instancian dependencias |
| `new ConcreteClass()` en Domain | 0 | >0 |
| Static calls en Domain | 0 | >0 |

**Test Rápido**: "¿El Domain layer importa algo de Infrastructure?"

#### Violations and Corrective Patterns

**Violation: Concrete Dependencies**

Symptoms:
- `new ConcreteClass()` dentro de métodos
- Import de clases de Infrastructure en Domain
- Hardcoded paths, URLs, connection strings

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Dependency Injection** | Inyectar dependencias | Constructor injection via interface |
| **Abstract Factory** | Crear familias de objetos | `UIFactory` → `WindowsFactory`, `MacFactory` |
| **Ports & Adapters** | Aislar dominio de infraestructura | Domain Port + Infrastructure Adapter |

Architecture Pattern: Ports & Adapters (Hexagonal)
```
┌─────────────────────────────────────────────────────────┐
│                    APPLICATION CORE                      │
│  ┌─────────────────────────────────────────────────┐   │
│  │                   DOMAIN                         │   │
│  │  - Entities                                      │   │
│  │  - Value Objects                                 │   │
│  │  - Domain Services                               │   │
│  │  - Domain Events                                 │   │
│  └─────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────┐   │
│  │              PORTS (Interfaces)                  │   │
│  │  - RepositoryInterface                           │   │
│  │  - GatewayInterface                              │   │
│  │  - NotifierInterface                             │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                          │
         ┌────────────────┼────────────────┐
         ▼                ▼                ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  ADAPTER    │  │  ADAPTER    │  │  ADAPTER    │
│  (HTTP)     │  │  (Database) │  │  (Queue)    │
│             │  │             │  │             │
│ Controller  │  │ Repository  │  │ Publisher   │
│ Middleware  │  │ ORM Config  │  │ Consumer    │
└─────────────┘  └─────────────┘  └─────────────┘
```

---

## Paradigm Adaptation

Los principios SOLID aplican de forma diferente según el paradigma del proyecto. Consultar `openspec/specs/architecture-profile.yaml` para saber qué paradigma usa el proyecto.

### OOP (PHP, Java, C#)
- Todos los principios aplican directamente
- Interfaces explícitas → ISP es altamente relevante
- Herencia disponible → LSP es relevante
- Clases como unidad base → SRP se mide por clase

### Functional (Haskell, Elm, Clojure, JS funcional)
- **SRP**: Se mide por función/módulo, no por clase. Funciones puras con un propósito.
- **OCP**: Composición de funciones. Higher-order functions permiten extensión sin modificación.
- **LSP**: Aplica a interfaces de módulos y contratos de tipos (si hay type system).
- **ISP**: Se manifiesta en módulos que exportan solo lo necesario. APIs mínimas.
- **DIP**: Inversión via higher-order functions (inyectar funciones como dependencias).

### Mixed (TypeScript, Python, Kotlin, Scala)
- Adaptar según lo que el proyecto usa: si usa clases → OOP rules. Si usa funciones → functional rules.
- **SRP**: Aplicar tanto a clases como a módulos/funciones según el caso.
- **DIP**: Constructor injection para clases, function injection para módulos funcionales.
- **ISP**: TypeScript interfaces + module exports. Python protocols + `__all__`.

### Struct-based (Go, Rust)
- **ISP**: Go tiene interfaces implícitas → ISP se consigue naturalmente con interfaces pequeñas.
- **LSP**: No hay herencia clásica. Aplica a trait implementations (Rust) o interface satisfaction (Go).
- **DIP**: Via interfaces en Go, traits en Rust. Package boundaries como separación.
- **SRP**: Se mide por struct + sus métodos, o por package.

---

## Pattern Selection: Project-Based Approach

Cuando el agente necesita seleccionar un patrón para resolver una violación SOLID:

```
1. CONSULTAR el architecture-profile.yaml del proyecto
   └─ ¿Qué patrones ya usa el proyecto? (patterns_detected, learned_patterns)
   └─ ¿Hay reference_files que muestren cómo se resuelve este tipo de problema?

2. IDENTIFICAR la violación SOLID específica
   └─ Usar las tablas de "Symptoms" de este documento
   └─ Verificar qué principio(s) se violan

3. SELECCIONAR el patrón MÁS SIMPLE que:
   a. El proyecto ya use (preferir consistencia)
   b. Resuelva la violación identificada
   c. No introduzca complejidad innecesaria

4. Si el proyecto no tiene un patrón establecido:
   └─ Usar los Decision Trees de este documento
   └─ Elegir el patrón más simple de la tabla de Corrective Patterns

5. DOCUMENTAR la decisión
   └─ Qué principio se violaba
   └─ Qué patrón se eligió y por qué
   └─ Reference file del proyecto que ejemplifica el patrón (si existe)
```

---

## Architecture Quality Criteria

### C-BASE-01: Escalabilidad Estructural

La arquitectura permite crecer sin reescribir.

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Añadir nueva entidad | ≤5 archivos | 6-10 archivos | >10 archivos |
| Añadir nuevo endpoint | ≤4 archivos | 5-7 archivos | >7 archivos |
| Añadir nuevo campo a entidad | ≤3 archivos | 4-5 archivos | >5 archivos |

Anti-patrones a Evitar:
- God classes que requieren modificación para todo
- Configuraciones centralizadas que se tocan siempre
- Herencia profunda que propaga cambios

### C-BASE-03: Clean Code Metrics

| Indicador | Bueno | Aceptable | Malo |
|-----------|-------|-----------|------|
| Líneas por función | ≤20 | 21-40 | >40 |
| Parámetros por función | ≤3 | 4-5 | >5 |
| Niveles de indentación | ≤3 | 4 | >4 |
| Complejidad ciclomática | ≤10 | 11-15 | >15 |
| Nombres descriptivos | Sí (sin comentarios) | Con comentarios | Abreviaciones crípticas |

### C-BASE-04: Responsabilidades Definidas (Separation of Concerns)

Cada capa/módulo tiene una responsabilidad clara y única.

Estructura DDD Esperada:
```
src/
├── Domain/           # Reglas de negocio PURAS (sin dependencias externas)
│   ├── Entity/       # Entidades con identidad
│   ├── ValueObject/  # Objetos inmutables sin identidad
│   ├── Repository/   # Interfaces (NO implementaciones)
│   ├── Service/      # Lógica de dominio que no cabe en entidades
│   └── Event/        # Eventos de dominio
│
├── Application/      # Orquestación de casos de uso
│   ├── Command/      # Acciones que modifican estado
│   ├── Query/        # Acciones de lectura
│   ├── DTO/          # Objetos de transferencia
│   └── Service/      # Coordinación entre dominio e infra
│
└── Infrastructure/   # Detalles técnicos
    ├── Persistence/  # Implementaciones de repositorios
    ├── Controller/   # Entry points HTTP (thin)
    ├── External/     # APIs externas, servicios third-party
    └── Config/       # Configuración técnica
```

Reglas de Dependencia:
```
Domain ← Application ← Infrastructure

Domain NO conoce Application ni Infrastructure
Application NO conoce Infrastructure (usa interfaces)
Infrastructure conoce todo (implementa interfaces)
```

### C-BASE-05: Patrones de Diseño Adecuados

Criterio de Selección de Patrón:
```
¿Necesito este patrón?
    │
    ├─ ¿Resuelve un problema REAL que tengo AHORA?
    │      NO → No usar (YAGNI)
    │      SÍ ↓
    │
    ├─ ¿La solución sin patrón requiere >3 archivos modificados para cambios?
    │      NO → Solución simple es suficiente
    │      SÍ ↓
    │
    └─ ¿El equipo conoce este patrón?
           NO → Documentar o elegir alternativa conocida
           SÍ → Usar el patrón
```

### C-BASE-06: Invasividad de Cambios

| Tipo de Cambio | Archivos Máximos | Capas Afectadas |
|----------------|------------------|-----------------|
| Nuevo campo en entidad | 3 | Domain, DTO, Migration |
| Nueva validación de negocio | 2 | Domain (Entity o VO) |
| Nuevo endpoint CRUD | 4 | Controller, UseCase, DTO, Route |
| Cambio en UI de un campo | 1 | Component |
| Nueva regla de autorización | 2 | Policy/Guard, Config |
| Cambio de proveedor externo | 1 | Infrastructure adapter |
| Cambio de API externa consumida | 1-2 | Infrastructure (adapter + mapper) |
| Nuevo consumer/plataforma de salida | 2-3 | Application (DTO + Transformer) |

---

## API Architecture Diagnostic Dimensions

> For the dimensional profile template, see `core/templates/api-architecture-diagnostic.yaml`.
> For the generated project profile, see `openspec/specs/api-architecture-diagnostic.yaml`.

The plugin classifies any API project across 6 architectural dimensions (DISCOVER Step 6c). PLAN (Step 3.1b) then reasons about which constraints apply to each specific feature. The constraints derive from SOLID principles; the patterns (AC-01 through AC-04 below) are the corrective solutions.

| Dimension | Question | SOLID Mapping |
|-----------|----------|---------------|
| **Data Flow** | In what direction does data move? (ingress, egress, aggregation, transformation, passthrough, bidirectional) | DIP, SRP |
| **Data Source Topology** | Where does data come from? (single_db, multi_external, mixed, event_driven, hybrid) | DIP, ISP |
| **Consumer Diversity** | Who consumes the output? (single, multi_platform, inter_service, public_api) | SRP, OCP |
| **Dependency Isolation** | How isolated are external dependencies? (fully_isolated, partially_wrapped, direct_coupling) | DIP |
| **Concurrency Model** | How are concurrent operations handled? (synchronous, async_capable, fully_concurrent) | SRP |
| **Response Customization** | How much does the response vary per consumer? (uniform, parameterized, per_consumer_shaped, context_dependent) | SRP, OCP |

**Derived constraints** emerge from dimension combinations:
- `multi_external + direct_coupling` → CRITICAL vendor risk → AC-01
- `aggregation + synchronous` → latency bottleneck → AC-03
- `multi_platform + per_consumer_shaped` → serialization complexity → AC-04
- `aggregation + multi_external` → assembly complexity → AC-02

---

## Anti-Patterns to Avoid

| Anti-Pattern | SOLID Violations | Better Alternative |
|--------------|------------------|-------------------|
| **God Class** | S, O, I | Extract Class + Strategy |
| **Service Locator** | D, I | Dependency Injection |
| **Singleton** | S, O, D | DI with scoped lifetime |
| **Anemic Domain** | S | Rich Domain Model |
| **Circular Dependencies** | D, S | Mediator, Events |
| **Primitive Obsession** | S, O | Value Objects |
| **Feature Envy** | S | Move Method |
| **Shotgun Surgery** | S, O | Extract Class |
| **Vendor SDK Leakage** | D, S | Anti-Corruption Layer (Port + Adapter) |
| **Fat Serializer** | S, O | Platform-specific DTOs + Transformer Strategy |
| **Sequential HTTP Calls** | S (perf) | Async HTTP Facade / Promise Aggregator |
| **Leaky Abstraction (External API)** | D, L | ResponseMapper + Domain DTO |

---

## API Consumer Architecture Patterns

> **Relationship to Diagnostic**: These patterns are CORRECTIVE — they address constraints
> generated by PLAN (Step 3.1b) from the project's dimensional profile.
> The flow is: DISCOVER classifies dimensions → PLAN reasons about constraints per-feature → these patterns are the solutions.
> See `core/templates/api-architecture-diagnostic.yaml` for the dimensional profile template.

Projects that consume external APIs (via vendor HTTP SDKs, REST clients, or third-party services) face specific architectural challenges not covered by standard CRUD/DDD patterns. These patterns address how to isolate, aggregate, serialize, and optimize outgoing HTTP communication.

> **Activation**: These patterns apply when the project's `architecture-profile.yaml` has `external_api_integration` or `http_client_pattern` populated.

### AC-01: Anti-Corruption Layer for Vendor SDKs

> "Never let an external vendor's data model leak into your Domain"

**Problem**: Domain layer directly uses vendor SDK classes (e.g., `Guzzle\Response`, `Symfony\HttpClient\Response`). Changes in vendor SDK break domain logic.

**Symptoms**:
- Vendor SDK classes imported in Domain/ or Application/ layers
- Domain entities shaped by external API response structure instead of business needs
- `new VendorClient()` or vendor-specific configuration in Domain/Application
- Vendor exceptions propagating through domain code

**Corrective Pattern**: Anti-Corruption Layer (ACL)

```
Domain Layer:
  - ExternalContentProviderInterface  (Port)
  - ContentDTO                         (Domain DTO)

Infrastructure Layer:
  - VendorApiContentProvider           (Adapter, implements Port)
  - VendorResponseMapper              (maps vendor response → Domain DTO)
```

**SOLID Mapping**:
| Principle | How ACL Satisfies It |
|-----------|---------------------|
| SRP | Adapter only handles vendor communication; Mapper only transforms data |
| OCP | New vendor = new Adapter, no domain changes |
| LSP | All adapters fulfill the same Port interface |
| ISP | Port interface has only the methods the domain needs |
| DIP | Domain depends on abstraction (Port), not vendor SDK |

**Decision Tree**:
```
Does your Domain import vendor SDK classes?
  → YES: Create Anti-Corruption Layer
    ├── Define Port interface in Domain
    ├── Create Adapter in Infrastructure
    ├── Create ResponseMapper in Infrastructure
    └── Vendor SDK only used inside Adapter
  → NO: Is vendor response structure leaking into DTOs?
    → YES: Create ResponseMapper
    → NO: Current structure is acceptable
```

### AC-02: Complex Aggregate Data Assembly

> "An aggregate that requires data from multiple sources needs an Assembly Service, not a fat constructor"

**Problem**: Domain object (e.g., Editorial) requires data from many sources (tags API, sections API, journalists API, body parser, widget service). A single service fetches all data sequentially in one method with >10 dependencies.

**Symptoms**:
- Service/UseCase with >7 constructor dependencies, most being HTTP clients or providers
- Single method that makes 4+ HTTP calls sequentially
- Domain entity receiving 5+ data sources in its factory method
- "Assembler" or "Builder" class with >400 LOC

**Corrective Patterns**:

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Data Assembler** | Orchestrate multiple data sources into one aggregate | `EditorialAssembler` coordinates TagProvider, SectionProvider, etc. |
| **Aggregate Factory** | Complex creation logic for rich domain objects | `EditorialFactory::fromSources(TagCollection, SectionList, ...)` |
| **Provider Pattern** | Each data source behind its own interface | `TagProviderInterface`, `SectionProviderInterface` |

**SOLID Mapping**:
| Principle | How It's Satisfied |
|-----------|-------------------|
| SRP | Each Provider handles one data source; Assembler only coordinates |
| OCP | New data source = new Provider, Assembler extended not modified |
| DIP | Assembler depends on Provider interfaces, not HTTP clients |
| ISP | Each Provider interface is small (1-3 methods) |

**Decision Tree**:
```
Does your aggregate need data from >3 sources?
  → YES: Use Data Assembler + Provider pattern
    ├── Each source gets its own ProviderInterface (Domain)
    ├── Each source gets its own Adapter (Infrastructure)
    ├── Assembler orchestrates in Application layer
    └── Consider async grouping (see AC-03)
  → NO: Is the single source response complex?
    → YES: ResponseMapper is sufficient
    → NO: Direct mapping is acceptable
```

### AC-03: Async HTTP Call Grouping

> "Sequential HTTP calls to independent APIs are a performance anti-pattern"

**Problem**: When assembling aggregate data, HTTP calls to independent external APIs are made sequentially. Each call adds latency.

**Symptoms**:
- 3+ sequential `$httpClient->request()` calls in one method
- Total response time = sum of all individual API calls
- Independent API calls that have no data dependency between them
- No usage of async/concurrent HTTP features despite framework support

**Corrective Patterns**:

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Async HTTP Facade** | Group independent HTTP calls for concurrent execution | `AsyncHttpFacade::batch([tagRequest, sectionRequest, ...])` |
| **Promise Aggregator** | Collect results from concurrent operations | `PromiseAggregator::all([tagPromise, sectionPromise])` |
| **Parallel Data Loader** | Framework-specific concurrent loading | Symfony HttpClient's `stream()`, Guzzle's `Pool` |

**Stack-Specific Implementation**:

| Stack | Mechanism | Example |
|-------|-----------|---------|
| PHP/Symfony | `HttpClient` with response streaming / `amphp` | `$responses = []; foreach($urls as $url) $responses[] = $client->request('GET', $url); foreach($responses as $r) $r->getContent();` |
| PHP/Guzzle | `Pool` or `Promise\Utils::all()` | `Promise\Utils::all($promises)->wait()` |
| Node.js | `Promise.all()` / `Promise.allSettled()` | `await Promise.all([fetchTags(), fetchSections()])` |
| Go | `goroutines` + `sync.WaitGroup` or `errgroup` | `g.Go(func() error { return fetchTags(ctx) })` |
| Python | `asyncio.gather()` or `concurrent.futures` | `await asyncio.gather(fetch_tags(), fetch_sections())` |

**SOLID Mapping**:
| Principle | How It's Satisfied |
|-----------|-------------------|
| SRP | Facade handles concurrency; individual providers handle data |
| OCP | New concurrent call = add to batch, no modification |
| DIP | Facade depends on Provider interfaces, not specific HTTP clients |

### AC-04: Multi-Platform Response Serialization

> "A single domain object should produce different representations for different consumers without the domain knowing about consumers"

**Problem**: Same domain data (e.g., Editorial) needs different JSON structures for mobile apps vs. web apps. Serialization logic ends up in entities or in fat "transformer" classes with if/else chains.

**Symptoms**:
- Entity or DTO with `toMobileJson()` and `toWebJson()` methods
- Serializer/Transformer with switch/if-else by platform
- Serialization groups that keep growing with each new consumer
- Domain entities containing `@Groups` or `@SerializedName` annotations mixed with business logic

**Corrective Patterns**:

| Pattern | When to Use | Example |
|---------|-------------|---------|
| **Platform-specific DTOs** | Each consumer gets its own response shape | `MobileEditorialDTO`, `WebEditorialDTO` |
| **DTO Transformer Strategy** | Strategy pattern for transforming domain → platform DTO | `EditorialTransformerInterface` with `MobileTransformer`, `WebTransformer` |
| **Read Model per Consumer** | CQRS-like separation for different read needs | `MobileEditorialReadModel`, `WebEditorialReadModel` |
| **Serialization Profile** | Named serialization configurations | Symfony Serializer groups, JMS Serializer exclusion strategies |

**SOLID Mapping**:
| Principle | How It's Satisfied |
|-----------|-------------------|
| SRP | Domain entity has no serialization logic; each Transformer handles one platform |
| OCP | New platform = new Transformer class, no modification to existing |
| LSP | All Transformers implement the same interface |
| ISP | Transformer interface is minimal: `transform(DomainObject): PlatformDTO` |
| DIP | Controller depends on TransformerInterface, not concrete transformers |

**Decision Tree**:
```
Does the same data serve multiple consumers with different shapes?
  → YES: How different are the shapes?
    ├── Slightly different (field subset): Serialization Profiles/Groups
    ├── Significantly different (structure change): Platform-specific DTOs + Transformer Strategy
    └── Completely different (different data): CQRS Read Models
  → NO: Standard DTO serialization is sufficient
```

---

## Excepciones Documentadas

Hay casos donde romper un criterio es aceptable:

| Excepción | Cuándo es OK | Debe Documentar |
|-----------|--------------|-----------------|
| Clase >200 líneas | Entidad de dominio compleja con muchas reglas | Justificación en ADR |
| >3 parámetros | DTO de request con muchos campos | N/A (DTOs son contenedores) |
| Cambio toca >5 archivos | Refactor planificado | Scope en PR description |
| Patrón "innecesario" | Preparación para feature conocida en roadmap | Link a roadmap item |

Formato de Excepción:
```markdown
## Exception: [Criterio violado]
**Razón**: [Por qué es necesario]
**Mitigación**: [Cómo se compensa]
**Revisión**: [Cuándo reconsiderar]
```

---

## Quick Reference: Violation → Pattern

| Violación SOLID | Síntoma | Patrón Correctivo | Alternativa |
|-----------------|---------|-------------------|-------------|
| **SRP** God Class | Clase >200 líneas, >7 métodos | **Strategy** + Extract Class | Facade + Services |
| **SRP** Mixed Concerns | Lógica de negocio + I/O juntos | **Repository** + Service Layer | Ports & Adapters |
| **OCP** Switch/If-else por tipo | `if (type === 'A')` chains | **Strategy** | Factory Method |
| **OCP** Modificar para extender | Añadir feature = cambiar clase | **Decorator** | Template Method |
| **LSP** Subtipo incompatible | Override que cambia contrato | **Composition over Inheritance** | Adapter |
| **LSP** Excepciones inesperadas | Subtipo lanza excepciones nuevas | **Null Object** | Optional/Maybe |
| **ISP** Interface gorda | Interface >5 métodos | **Interface Segregation** | Role Interfaces |
| **ISP** Métodos no usados | `throw NotImplemented` | **Adapter** | Facade |
| **DIP** Dependencia concreta | `new ConcreteClass()` directo | **Dependency Injection** | Abstract Factory |
| **DIP** Alto→Bajo nivel | Domain importa Infrastructure | **Ports & Adapters** | Gateway Pattern |
| **DIP** Vendor SDK en Domain | Domain importa Guzzle/HttpClient | **Anti-Corruption Layer** | Ports & Adapters |
| **SRP** Fat Assembler | Service con >7 deps, fetches de 5+ fuentes | **Data Assembler + Providers** | Facade |
| **SRP** Fat Serializer | Transformer con platform if/else | **DTO Transformer Strategy** | Serialization Profiles |
| **OCP** Platform switching | `if (platform === 'mobile')` en serializer | **Strategy (Transformer)** | Visitor |

---

## SOLID Verdict Matrix

When evaluating SOLID compliance (in planning Phase 3, work checkpoints, and review), use this verdict protocol instead of numeric scores:

### Verdict Values

| Verdict | Meaning | Action |
|---------|---------|--------|
| **COMPLIANT** | Principle is satisfied with evidence | Proceed |
| **NEEDS_WORK** | Partially satisfied, specific improvement identified | Refactor before checkpoint |
| **NON_COMPLIANT** | Principle violated, no justification | BLOCKS advancement |
| **N/A** | Principle not relevant to this context (with justification) | Proceed |

### SOLID Justification Format

For each relevant principle, provide a one-line justification with code evidence:

```
SRP: COMPLIANT — UserEntity handles only user data. Evidence: src/Domain/Entity/User.php (no service logic)
OCP: N/A — No extension point needed for this entity (standalone value holder)
LSP: N/A — No inheritance hierarchy involved
ISP: COMPLIANT — UserRepositoryInterface has only CRUD methods. Evidence: 4 methods, all used by consumers
DIP: COMPLIANT — Domain depends on interface, not Doctrine. Evidence: UserRepositoryInterface in Domain/
```

### Gate Rules

- **Planning (Phase 3)**: All relevant principles must be COMPLIANT or N/A (with justification) to pass Quality Gate
- **Work (checkpoint)**: NON_COMPLIANT blocks the checkpoint. NEEDS_WORK triggers refactor before proceeding
- **Review**: Reviewer verifies justifications match actual code

---

## Related Documents

- `openspec/specs/architecture-profile.yaml` - Perfil arquitectónico del proyecto (generado por discover)
- `core/templates/architecture-profile-template.yaml` - Template para el perfil
- `skills/workflow-skill-solid-analyzer.md` - Análisis automático de SOLID (3 modos: BASELINE, DESIGN_VALIDATE, CODE_VERIFY)
- `agents/review/architecture-reviewer.md` - Validación de arquitecturas

---

**Versión**: 2.0
**Última Actualización**: 2026-02-15
**Origen**: Fusión de `solid-pattern-matrix.md` + `architecture-quality-criteria.md`
**Aplicable a**: Todos los proyectos que usen el plugin
