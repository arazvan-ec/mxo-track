# Principios Agent-Native Aplicados a Logística

> Fuente: https://every.to/guides/agent-native
> Fecha: 2026-02-27

---

## Resumen del Artículo

El software **agent-native** posiciona a los agentes IA como ciudadanos de primera clase, no como complementos. Las funcionalidades son **resultados conseguidos por un agente operando en un loop**, no capacidades pre-codificadas.

---

## Los 5 Principios Fundamentales

### 1. Paridad (Parity)
> Los agentes deben poder conseguir mediante herramientas (tools) todo lo que los usuarios consiguen vía UI.

**Aplicación en MXO-Track:**
- Si un operador puede crear una ruta manualmente en la UI, un agente debe poder hacerlo via API/tools
- Si un cliente puede importar un CSV manualmente, un agente debe poder procesarlo automáticamente
- Cada acción del dashboard debe tener su equivalente como "tool" para agentes

### 2. Granularidad (Granularity)
> Las herramientas deben ser primitivas atómicas. La toma de decisiones se delega al juicio del agente via prompts.

**Aplicación en MXO-Track:**
- `create_shipment` — crear un envío individual
- `add_parcel_to_shipment` — añadir bulto a un envío
- `calculate_route` — calcular ruta óptima
- `assign_vehicle` — asignar vehículo a ruta
- `validate_vehicle_capacity` — validar peso/volumen
- `get_isochrone` — obtener isócrona desde un punto
- `update_shipment_status` — actualizar estado
- `generate_delivery_note` — generar albarán
- NO: `process_800_orders_and_create_routes` (esto lo compone el agente)

### 3. Composabilidad (Composability)
> Las herramientas atómicas permiten nuevas funcionalidades via orquestación basada en prompts.

**Aplicación en MXO-Track:**
- "Procesar 800 pedidos de Raúl" = el agente combina:
  1. `import_csv` → parsear pedidos
  2. `validate_parcels` → validar peso/volumen de cada bulto
  3. `get_vehicles` → obtener vehículos disponibles
  4. `calculate_isochrones` → agrupar por zonas
  5. `optimize_routes` → crear rutas óptimas
  6. `validate_vehicle_capacity` → verificar que cabe todo
  7. `create_routes` → crear las rutas
  8. `notify_client` → informar al cliente

### 4. Capacidad Emergente (Emergent Capability)
> Los agentes logran tareas no anticipadas combinando herramientas creativamente.

**Aplicación en MXO-Track:**
- Un agente podría descubrir que ciertos clientes frecuentes siempre piden en la misma franja horaria y sugerir pre-rutas
- Podría detectar que un transportista es más eficiente en ciertas zonas y reasignar
- Podría combinar datos de isócronas con frecuencia de clientes para proponer nuevas RGUs

### 5. Mejora Continua (Improvement Over Time)
> Las aplicaciones mejoran acumulando contexto y refinando prompts, sin desplegar código.

**Aplicación en MXO-Track:**
- El sistema aprende las preferencias de cada cliente (franjas horarias, frecuencia)
- Los prompts de optimización se refinan con datos reales de entregas exitosas/fallidas
- La productividad por transportista se acumula como contexto para futuras asignaciones

---

## Patrones Técnicos Agent-Native

### Archivos como Interfaz Universal
- Los agentes trabajan naturalmente con filesystem (`cat`, `grep`, `mv`)
- Transparencia: todo es visible y auditable
- Portabilidad: sin dependencia de base de datos para estado del agente

### Estructura de Directorios por Entidad
```
{entity_type}/{entity_id}/
├── contenido principal
├── metadata
└── logs del agente
```

### Patrón Context.md
- Los agentes mantienen memoria de trabajo portátil
- Quién son, qué le importa al usuario, qué existe, actividad reciente, guías

### Señales de Completitud
- Las herramientas indican explícitamente si completaron con éxito o fallo
- Las señales de control distinguen éxito/fallo de decisiones de continuación

---

## Anti-Patrones a Evitar

| Anti-Patrón | Descripción | Riesgo |
|-------------|-------------|--------|
| Agent-as-router | Usar IA solo para despachar a funciones pre-construidas | Desperdicia capacidad del agente |
| Restricción defensiva excesiva | Validación excesiva impide manejar escenarios no anticipados | Limita capacidad emergente |
| UI Actions huérfanas | Features de UI que el agente no puede ejecutar | Viola paridad |
| Workflow embebido | Codificar secuencias de decisión en código en vez de prompts | Reduce flexibilidad |
| Inanición de contexto | No inyectar recursos disponibles en prompts del sistema | Limita la conciencia del agente |

---

## Diferencias Clave: Tradicional vs Agent-Native

| Aspecto | Tradicional | Agent-Native |
|---------|-------------|--------------|
| Entrega de features | Escribir código | Escribir prompts + tools atómicos |
| Lógica de decisión | Embebida en funciones | Juicio del agente via loops |
| Descubrimiento de capacidad | Features pre-planificadas | Emerge de peticiones de usuarios |
| Flexibilidad | Caminos predefinidos | Compone tools para resultados nuevos |
| Modificación de comportamiento | Desplegar cambios de código | Actualizar prompts al instante |

---

## Test de Éxito

> "Describe un resultado al agente que esté dentro del dominio de tu aplicación pero para el que no hayas construido una funcionalidad específica. ¿Puede lograrlo?"

Ejemplo para MXO-Track:
- "Necesito reorganizar las rutas de mañana porque un camión se ha averiado"
- "Dame un análisis de costes por cliente del último mes"
- "Encuentra los 3 transportistas más eficientes para la zona norte"
