# Design Patterns — Guía de Decisión

**Última actualización:** 2026-03-16
**Estado:** Vigente

Las reglas obligatorias están en **CLAUDE.md > "Design Patterns (mandatory)"**. Este módulo es una guía para **evaluar y elegir** el patrón correcto, no un catálogo para copiar.

## Cómo Elegir un Patrón

**No empieces por el patrón. Empieza por el problema.**

### Paso 1: Identifica el problema real

| Pregunta | Si la respuesta es sí... |
|----------|------------------------|
| ¿Necesito crear objetos cuyo tipo varía según configuración? | Evalúa patrones creacionales |
| ¿Necesito combinar o envolver objetos para añadir comportamiento? | Evalúa patrones estructurales |
| ¿Necesito coordinar comunicación o flujo entre objetos? | Evalúa patrones de comportamiento |
| ¿El problema es de modelado de dominio? | Evalúa patrones DDD |

### Paso 2: Evalúa trade-offs

**Antes de elegir un patrón, pregúntate:**

1. **¿Es necesario?** ¿El código más simple (sin patrón) resuelve el problema igual de bien? Si sí, no uses un patrón. Tres líneas directas son mejor que una abstracción prematura.
2. **¿Cuántas implementaciones reales habrá?** Si solo una, un patrón de polimorfismo es over-engineering. Si hay 2+, vale la pena.
3. **¿Qué complejidad añade?** Cada indirección (interface, factory, proxy) añade un nivel de abstracción que alguien tiene que entender. ¿El beneficio supera ese costo?
4. **¿Existe ya en el codebase?** Si hay un patrón establecido para el mismo tipo de problema, seguirlo reduce la carga cognitiva. Pero si el patrón existente no encaja bien, no lo forces.
5. **¿Podría ser otro patrón?** La mayoría de problemas se pueden resolver con 2-3 patrones diferentes. Evalúa alternativas antes de decidir.

### Paso 3: Valida contra SOLID

El patrón elegido debe mejorar (o al menos no violar) los principios SOLID:
- ¿Reduce responsabilidades por clase? (SRP)
- ¿Permite extensión sin modificación? (OCP)
- ¿Las implementaciones son sustituibles? (LSP)
- ¿Las interfaces son estrechas? (ISP)
- ¿Las dependencias apuntan a abstracciones? (DIP)

Si el patrón viola algún principio, probablemente es el patrón equivocado.

---

## Patrones por Tipo de Problema

### "Necesito crear objetos de tipo variable"

| Patrón | Úsalo cuando | No lo uses cuando | Trade-off |
|--------|-------------|-------------------|-----------|
| **Factory Method** | El tipo del objeto depende de configuración o contexto runtime | Solo hay un tipo posible | Añade una clase por cada tipo |
| **Builder** | La construcción tiene múltiples fases o configuraciones | El constructor es simple (2-3 params) | Más código, pero la construcción queda explícita |
| **Null Object** | Un servicio puede no estar disponible y los callers no deben preocuparse | El caller necesita saber que no hay implementación (para tomar decisiones) | Silencia errores — puede ocultar problemas reales |

**Pregunta clave:** ¿Hay más de un tipo real? Si no, un `new` directo es mejor.

### "Necesito envolver o combinar objetos"

| Patrón | Úsalo cuando | No lo uses cuando | Trade-off |
|--------|-------------|-------------------|-----------|
| **Adapter** | Envuelves una API externa con una interface de tu dominio | La API externa ya encaja con tu interface | Una clase wrapper por API |
| **Decorator** | Añades comportamiento (cache, logging) sin cambiar la clase | Solo necesitas un comportamiento y no va a cambiar | Cada decorador es una nueva clase + indirección |
| **Proxy** | Interceptas acceso para resolver/delegar dinámicamente | No hay variabilidad en runtime (solo una implementación) | Oculta qué implementación real se ejecuta |
| **Facade** | Simplificas un workflow que coordina N servicios | El workflow es simple (1-2 servicios) — el facade es un pass-through | Centraliza orquestación pero puede convertirse en God Class |

**Pregunta clave:** ¿Estoy añadiendo valor (cache, traducción, simplificación) o solo añadiendo una capa inútil de indirección?

### "Necesito coordinar comportamiento entre objetos"

| Patrón | Úsalo cuando | No lo uses cuando | Trade-off |
|--------|-------------|-------------------|-----------|
| **Strategy** | Hay N algoritmos intercambiables para el mismo problema | Solo hay un algoritmo y no cambiará | Interface + N implementaciones |
| **Observer/Event** | Un cambio de estado debe notificar a N componentes que no conoces de antemano | La relación es 1:1 y ambos se conocen | Flujo implícito, difícil de trazar |
| **Command** | La operación debe ejecutarse async o diferida | La operación es síncrona y rápida | Message + Handler + infraestructura Messenger |
| **Chain of Responsibility** | N handlers intentan resolver, el primero que puede gana | Solo hay un handler posible | Orden importa, debugging complejo |
| **Template Method** | Hay lógica base compartida con hooks para subclases | No hay herencia natural o las variaciones son muy diferentes | Herencia es rígida vs composición |
| **State** | Las transiciones de estado tienen side-effects complejos y reglas condicionales | Las transiciones son simples (enum + guard en método) | Una clase por estado |

**Pregunta clave:** ¿El acoplamiento implícito (events, chain) es aceptable, o necesito flujo explícito?

### "Necesito modelar el dominio"

| Patrón | Úsalo cuando | No lo uses cuando | Trade-off |
|--------|-------------|-------------------|-----------|
| **Repository (interface)** | El servicio necesita desacoplarse de la persistencia | Contexto CRUD pragmático donde Doctrine directo es aceptable | Interface + implementación por aggregate |
| **Value Object** | Datos inmutables sin identidad (coordenadas, dinero, ranges) | El objeto necesita identidad y ciclo de vida | Más objetos pequeños, pero más expresividad |
| **Aggregate Root** | Un grupo de entidades debe ser consistente como unidad | Las entidades son independientes | Todas las operaciones pasan por la raíz |
| **Domain Event** | Un cambio de estado debe notificar a otros bounded contexts | La reacción es interna al mismo servicio (un `if` basta) | Desacoplamiento a costa de trazabilidad |
| **Specification** | Queries complejas que se combinan y reutilizan | La query es simple y no se reutiliza | Más clases, pero queries componibles |
| **Policy** | Reglas de negocio configurables o que varían por contexto | La regla es fija y simple | Flexibilidad a costa de indirección |

**Pregunta clave:** ¿Estoy modelando un concepto del dominio de negocio, o estoy sobre-modelando infraestructura?

---

## Patrones ya Establecidos en el Codebase

Para mantener consistencia, estos patrones ya están en uso. No los cambies sin razón — pero tampoco los copies sin pensar.

| Patrón | Dónde se usa | Notas |
|--------|-------------|-------|
| Factory Method | `src/Provider/*Factory.php` (12 factories) | Bien establecido. Nuevos providers deben seguirlo. |
| Builder | `RouteBuilder`, `DemoScenarioBuilder` | Para construcción multi-fase. |
| Null Object | 12 `Null*` classes | Para graceful degradation de providers. |
| Adapter | Providers: VROOM, Google, Traccar, OSRM | Cada API externa se envuelve con port interface. |
| Proxy | 4 `TenantAware*` classes | Multi-tenancy transparente. |
| Decorator | `CachedProviderResolver` | Cache layer. |
| Facade | `RoutePlanningService`, `DeliveryService`, etc. | Orquestación de workflows. |
| Observer | 13 domain events + 13 listeners | Desacoplamiento de side-effects. |
| Strategy | Familias: Optimizer(3), Routing(3), GPS(3), Realtime(3) | Port interfaces con N implementaciones. |
| Command | 4 Messenger messages + handlers | Operaciones async (AI, análisis). |
| Chain of Responsibility | `FallbackChain` | Fallback entre providers. |
| Template Method | `BaseVoter` | Lógica base de seguridad con hooks. |
| Repository | 17 repos Doctrine | Concretos (migración DDD añadirá interfaces). |
| Value Object | 85+ readonly classes, 17 enums | Inmutabilidad extensa. |
| Aggregate Root | Route→RouteStop, Shipment→Parcel | Consistencia transaccional por cascade. |

---

## Señales de que Elegiste Mal

| Señal | Posible problema |
|-------|-----------------|
| El patrón añade 3+ clases y solo hay 1 implementación real | Over-engineering. ¿Necesitas realmente la abstracción? |
| Tienes que mirar 5 archivos para entender un flujo simple | Demasiada indirección. Simplifica. |
| El Null Object oculta un error que deberías haber visto | Null Object no es para errores — es para "no hay provider configurado". |
| El Facade crece y crece (10+ dependencias) | Se está convirtiendo en God Class. Divide en facades más pequeños. |
| Los eventos hacen imposible trazar qué pasa después de un cambio | Demasiados listeners encadenados. Considera hacer el flujo explícito. |
| Implementas un Strategy con 1 sola implementación "por si acaso" | YAGNI. Extrae la interface cuando exista la segunda implementación. |
| El Builder tiene 15 métodos de configuración y se usan 2 | El builder está modelando más de lo que necesitas. Simplifica. |

---

## Mejora Continua

Esta guía se enriquece con cada implementación via el **Decision Log** (`docs/decisions/log.md`):

```
Implementar → Registrar decisión → Evaluar resultado → Actualizar guía
```

- **Después de implementar:** Registrar decisiones de diseño significativas en el log
- **Al cerrar rama:** Retrospectiva (paso 5 de finishing-a-development-branch) evalúa si las decisiones fueron acertadas
- **Cuando un patrón aparezca 3+ veces en el log:** Actualizar esta guía con el aprendizaje

**Qué actualizar:**
- Nuevas señales de "elegiste mal" descubiertas en la práctica
- Trade-offs que no estaban documentados
- Patrones nuevos que se establezcan en el codebase (añadir a inventario)
- Patrones que se hayan abandonado (documentar por qué)

---

## Historial

- 2026-03-16: Creación inicial como catálogo de patrones
- 2026-03-16: Reescrito como guía de decisión — foco en evaluar trade-offs, no copiar patrones
- 2026-03-16: Añadida sección de mejora continua — feedback loop con Decision Log y retrospectiva
