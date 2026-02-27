# Enfoque Híbrido: Explicación Detallada con Ejemplos

> Fecha: 2026-02-27
> Contexto: El cliente necesita entender el alcance real de cada parte del híbrido

---

## ¿Qué significa "Híbrido" en la práctica?

Tu sistema tiene **dos motores** trabajando juntos:

```
┌─────────────────────────────────┐
│        MOTOR DE REGLAS           │  ← Hace el trabajo pesado (95%)
│  Algoritmos, validaciones,       │
│  cálculos, flujos predecibles    │
└──────────────┬──────────────────┘
               │
               │  Los mismos services/tools
               │
┌──────────────▼──────────────────┐
│        CAPA LLM                  │  ← Interpreta lo ambiguo (5%)
│  Entiende lenguaje natural,      │
│  toma decisiones contextuales    │
└─────────────────────────────────┘
```

**Lo importante:** Ambos usan los mismos tools/services internos. La diferencia es QUIÉN decide qué tools llamar.

---

## 10 Ejemplos Concretos del Día a Día

### Ejemplo 1: "Raúl sube 800 pedidos por CSV"

**Motor de Reglas (100% determinista, 0% LLM):**
1. Parsea CSV → valida campos → crea 800 Shipments
2. Agrupa por zona geográfica (k-means clustering)
3. Para cada grupo: calcula capacidad de vehículos disponibles (bin-packing)
4. Crea 12 rutas, cada una respeta peso/volumen del vehículo
5. Optimiza secuencia de paradas (nearest-neighbor + 2-opt)
6. Notifica a Raúl: "12 rutas creadas"

**Tiempo:** 2-5 segundos. **Coste IA:** $0. **Fiabilidad:** 100%.

**¿Por qué NO usar LLM aquí?** Porque es un problema matemático con reglas claras. No hay ambigüedad.

---

### Ejemplo 2: "Raúl dice: las rutas del centro no me convencen"

**Aquí SÍ entra el LLM:**

El operador escribe en un chat: *"Las rutas del centro están muy dispersas, junta las de Serrano con las de Salamanca y mueve Retiro a la tarde"*

1. **LLM interpreta:** "centro" = zona centro de Madrid, "Serrano" y "Salamanca" = barrios específicos, "Retiro" = barrio, "tarde" = ventana 14:00-20:00
2. **LLM decide qué tools llamar:**
   - `search_stops_by_area("Serrano")` → 15 paradas
   - `search_stops_by_area("Salamanca")` → 12 paradas
   - `merge_stops_into_route(stops, vehicle)` → 1 ruta nueva
   - `search_stops_by_area("Retiro")` → 8 paradas
   - `update_delivery_window(stops, "14:00", "20:00")`
3. **Motor de reglas valida:** ¿cabe todo en el camión? ¿ventanas horarias posibles?
4. **Resultado:** Rutas reorganizadas según lo que pidió Raúl

**Tiempo:** 5-10 segundos. **Coste IA:** ~$0.10. **El LLM NO crea las rutas — solo interpreta qué quiere Raúl y llama a los tools correctos.**

---

### Ejemplo 3: "Un camión se avería a las 10:00 AM"

**Motor de Reglas (automático, sin LLM):**
1. Detecta que el vehículo TRK-003 reportó avería (evento GPS)
2. Busca rutas activas de TRK-003 → Ruta R-045 con 18 paradas pendientes
3. Busca vehículos disponibles con capacidad suficiente
4. Redistribuye paradas por proximidad geográfica
5. Notifica conductores afectados y clientes con nuevo ETA

**¿Y si el operador quiere intervenir?** Escribe: *"No muevas las entregas de Telefónica, son prioritarias, que las haga Juan con la furgoneta azul"*

**Ahora SÍ entra el LLM:**
- Interpreta "entregas de Telefónica" → busca shipments del cliente Telefónica
- Interpreta "Juan" → busca conductor Juan
- Interpreta "furgoneta azul" → busca vehículo por descripción
- Ejecuta: `assign_stops_to_route(telefonica_stops, juan_route)`

---

### Ejemplo 4: "¿Cuánto me ha costado este mes?"

**Motor de Reglas (100%):**
1. Consulta rutas completadas del mes
2. Calcula: km totales, horas conductor, bultos entregados
3. Aplica tarifas configuradas: €/km, €/hora, €/bulto
4. Genera tabla con totales

**No necesita LLM.** Es aritmética con datos conocidos.

**Pero si pregunta:** *"¿Por qué la zona norte me sale más cara que el mes pasado?"*

**Ahora SÍ entra el LLM:**
- Compara datos de zona norte entre mes actual y anterior
- Identifica: +15% km por nuevos clientes en periferia, +1 ruta extra por devoluciones
- Genera explicación en texto natural: "La zona norte subió un 18% porque se añadieron 3 clientes nuevos en Alcobendas y las devoluciones de Amazon aumentaron un 25%"

---

### Ejemplo 5: "El cliente envía un email con pedidos"

**LLM (parseo de datos no estructurados):**
El email dice: *"Hola, necesito que recojan 5 palets mañana en nuestro almacén de Getafe, son para entregar en Madrid centro. Peso aprox 200kg cada uno."*

1. **LLM extrae:** tipo=PICKUP+DELIVERY, bultos=5, peso=200kg/u, origen=Getafe, destino=Madrid centro, fecha=mañana
2. **LLM llama tools:** `create_shipment(...)`, `add_parcels(5, 200kg, ...)`, `suggest_route(...)`
3. **Motor de reglas valida:** ¿hay vehículo con 1000kg libres mañana? ¿llega a tiempo?

**Sin LLM** esto requeriría que el cliente rellene un formulario. Con LLM acepta texto libre.

---

### Ejemplo 6: "Operador configura nueva ruta recurrente"

**Motor de Reglas (100%):**
1. Operador selecciona: cliente, paradas, vehículo, conductor, frecuencia (L-M-X-J-V)
2. Sistema crea template de ruta
3. Cada día a las 6:00 AM genera la ruta del día automáticamente
4. Optimiza secuencia según tráfico del día

**No hay ambigüedad. Todo son formularios con opciones claras.**

---

### Ejemplo 7: "Conductor reporta incidencia"

**Motor de Reglas (100%):**
1. Conductor pulsa "Ausente" en la app
2. Sistema registra: ShipmentEvent(DELIVERY_ATTEMPTED, ABSENT)
3. Según configuración del cliente:
   - Si permite reintento → mover a final de ruta
   - Si no → marcar para devolución
4. Notifica al cliente: "Intento fallido, destinatario ausente"

**Flujo predecible con reglas claras.**

---

### Ejemplo 8: "Dame un análisis de productividad"

**Motor de Reglas genera los datos:**
- Entregas/día por conductor, ratio éxito, tiempo medio, km/entrega

**LLM genera el insight (opcional):**
*"Juan tiene el mejor ratio en zona centro (97%) pero tarda un 20% más que la media. Ana es la más rápida pero tiene más ausencias. Sugerencia: asignar a Juan las entregas B2B donde la fiabilidad importa más que la velocidad, y a Ana las zonas residenciales con mayor densidad."*

---

### Ejemplo 9: "Cliente B2B consulta estado de su pedido"

**Motor de Reglas (100%):**
1. Cliente entra al portal, ve lista de sus envíos con estados
2. Filtra, busca, exporta CSV
3. Recibe webhook automático por cada cambio de estado

**No necesita LLM.** Es consulta de datos con UI predefinida.

---

### Ejemplo 10: "Planificar rutas para la semana que viene considerando festivos"

**Motor de Reglas:**
1. Genera rutas L-V basado en pedidos pendientes
2. Excluye festivo local del miércoles (configurado)
3. Redistribuye pedidos del miércoles entre martes y jueves

**LLM si el operador dice:** *"El miércoles es festivo en Madrid pero no en Getafe, así que las entregas de Getafe sí van pero las de Madrid pásalas al jueves, excepto las de Telefónica que son urgentes y van el martes"*

---

## Resumen: ¿Cuándo usa cada motor?

| Situación | Motor | Por qué |
|-----------|-------|---------|
| Crear rutas desde CSV | Reglas | Problema matemático, sin ambigüedad |
| "Junta las rutas de Serrano y Salamanca" | LLM | Referencia geográfica ambigua |
| Validar capacidad del camión | Reglas | Aritmética pura |
| "¿Por qué zona norte sale más cara?" | LLM | Requiere análisis y explicación |
| Cambiar estado de envío | Reglas | Máquina de estados predefinida |
| Parsear email con pedido | LLM | Texto no estructurado |
| Generar albarán PDF | Reglas | Template + datos |
| "Dame insights de productividad" | LLM | Razonamiento sobre datos |
| Redistribuir por avería | Reglas | Algoritmo de reasignación |
| "No muevas las de Telefónica" | LLM | Instrucción contextual |
| Notificar cliente por webhook | Reglas | Trigger predefinido |
| Importar CSV | Reglas | Parseo estructurado |

---

## ¿Qué alcance tiene cada parte?

### Con solo Reglas (sin LLM) ya tienes:
- Creación automática de rutas optimizadas
- Validación de capacidad de vehículos
- Máquina de estados completa de envíos
- Importación CSV
- Dashboard con métricas y costes
- Notificaciones automáticas
- Tracking GPS en tiempo real
- Portal B2B para clientes
- SGA (almacén)
- Generación de albaranes/documentos
- **→ Es un TMS/SGA completamente funcional**

### Con LLM encima añades:
- Interfaz de chat para operadores: instrucciones en lenguaje natural
- Parseo de emails, PDFs, WhatsApps → pedidos automáticos
- Explicaciones y análisis inteligentes ("¿por qué...?")
- Reorganización de rutas por instrucciones ambiguas
- Sugerencias proactivas basadas en patrones
- Onboarding de clientes más fácil (menos formularios)
- **→ Es la diferencia entre "software que haces cosas" y "software que entiende lo que quieres"**

### Sin ninguno de los dos:
- Interfaz manual con formularios
- El operador decide todo
- **→ Es lo que tiene hoy mxo-track**

---

## Mi recomendación

1. **Fase 1-3:** Construir el motor de reglas completo (TMS+SGA funcional)
2. **Fase 4:** Añadir capa LLM para chat del operador
3. **Fase 5:** Añadir LLM para parseo de inputs no estructurados (email, WhatsApp)
4. **Fase 6:** Añadir LLM para insights y análisis inteligentes

Así tienes un producto funcional desde la Fase 3, y el LLM es cereza del pastel.
