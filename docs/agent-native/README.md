# MXO-Track Agent-Native — Documentación

> Sistema de logística agent-native sobre Symfony 7.4

## Índice

### Documentación Base
| # | Documento | Descripción |
|---|-----------|-------------|
| 00 | [Requisitos](00-REQUIREMENTS.md) | Todos los requisitos del cliente tal como fueron comunicados |
| 01 | [Principios Agent-Native](01-AGENT-NATIVE-PRINCIPLES.md) | Los 5 principios del artículo aplicados a logística |
| 02 | [Codebase Reutilizable](02-CODEBASE-REUSABLE.md) | Qué componentes existentes reutilizar y cómo extenderlos |
| 03 | [Arquitectura](03-ARCHITECTURE-VISION.md) | Visión arquitectónica: tools atómicos, entidades, flujos |
| 04 | [Preguntas Abiertas](04-OPEN-QUESTIONS.md) | Q&A con el cliente — respuestas y estado |
| 05 | [Plan Desarrollo v1](05-DEVELOPMENT-PLAN.md) | Plan inicial (superado por doc 13) |

### Investigaciones
| # | Documento | Descripción |
|---|-----------|-------------|
| 06 | [Tipos de Servicio](06-RESEARCH-SERVICE-TYPES.md) | 17 tipos en 3 tiers (SEUR, MRW, GLS, DHL, UPS) |
| 07 | [Estados y Máquina](07-RESEARCH-STATUSES.md) | Shipment states, events, exception codes, transiciones |
| 08 | [Tipos de Agente](08-RESEARCH-AGENT-TYPES.md) | LLM vs Reglas vs Híbrido (71/80 para Híbrido) |
| 09 | [SGA/WMS](09-RESEARCH-SGA-WMS.md) | Registro simple vs gestión completa de almacén |

### Decisiones de Diseño
| # | Documento | Descripción |
|---|-----------|-------------|
| 10 | [Ejemplos Híbrido](10-HYBRID-EXAMPLES.md) | 10 ejemplos concretos de Reglas vs LLM con alcance |
| 11 | [Diseño ServiceType](11-SERVICETYPE-DESIGN.md) | Enum+Flags vs Todo-Enum (65/80 vs 31/80) |

### Planes Detallados
| # | Documento | Descripción |
|---|-----------|-------------|
| 12 | [SGA Fases Completas](12-SGA-PHASES-COMPLETE.md) | 6 fases SGA con entidades y tareas detalladas |
| 13 | [Plan Maestro](13-MASTER-PHASE-PLAN.md) | **12 fases del proyecto completo con dependencias** |

## Estado Actual

- **Fase:** Debate y refinamiento del plan
- **Última actualización:** 2026-02-27
- **Pendiente:** Decisiones del cliente sobre enfoque híbrido, ServiceType, y priorización de roadmap
