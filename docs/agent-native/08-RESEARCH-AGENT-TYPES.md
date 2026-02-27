# Análisis Comparativo: Tipo de Agente para MXO-Track

> Fecha: 2026-02-27
> Contexto: Respuesta a Q4 — ¿LLM, Reglas, o Híbrido?

---

## Resumen Ejecutivo

**Recomendación: OPCIÓN C (Híbrido)** — Reglas deterministas para operaciones predecibles (95% del sistema) + LLM para decisiones ambiguas que requieren juicio humano (5%).

---

## Las 3 Opciones

### Opción A: LLM Puro (Claude/GPT ejecutando todo)

**Cómo funciona** con "800 pedidos de Raúl":
1. Se serializa CSV completo como prompt (~200-400K tokens)
2. LLM razona sobre capacidad, clusters geográficos, conductores
3. Ejecuta tools iterativamente: `create_route` x N, `add_route_stop` x 800
4. Total: 1,600+ llamadas a tools en múltiples rondas de inferencia

| Aspecto | Valoración |
|---------|------------|
| Fiabilidad operaciones masivas | ❌ Pobre — puede perder pedidos, duplicar, ignorar restricciones |
| Peticiones ambiguas | ✅ Excelente — entiende lenguaje natural |
| Coste mensual (a escala) | ❌ $15K-60K/mes |
| Latencia (800 pedidos) | ❌ 30-120 segundos |
| Auditabilidad | ❌ Pobre — "el LLM decidió" |
| Escalabilidad | ❌ Pobre — lineal con tokens |
| Determinismo | ❌ No — mismos datos, resultados diferentes cada vez |
| Si la API se cae | ❌ Sistema muerto |

**Riesgos críticos en logística:**
- Inventa o modifica direcciones → conductor va al lugar equivocado
- Asigna pedido a vehículo incorrecto → mercancía refrigerada en furgoneta normal
- Calcula mal capacidad → vehículo sobrecargado (infracción + riesgo)
- Pierde pedidos silenciosamente → cliente descubre días después
- Ignora regulación de horas de conducción → multas, riesgo

---

### Opción B: Reglas Deterministas (Algoritmos)

**Cómo funciona** con "800 pedidos de Raúl":
1. **Validación**: Cada pedido validado — geocodificación, peso/volumen, ventanas
2. **Clustering**: Agrupación geográfica (k-means, grid)
3. **Asignación**: Bin-packing: `sum(peso) <= vehiculo.pesoMax AND sum(volumen) <= vehiculo.volumenMax`
4. **Secuenciación**: Nearest-neighbor + 2-opt para minimizar distancia
5. **Resultado**: 800 pedidos asignados en **2-5 segundos**, cero pedidos perdidos, $0 de coste API

| Aspecto | Valoración |
|---------|------------|
| Fiabilidad operaciones masivas | ✅ 99.9%+ — matemáticamente correcto |
| Peticiones ambiguas | ❌ No entiende lenguaje natural |
| Coste mensual | ✅ $0 de API |
| Latencia (800 pedidos) | ✅ 2-5 segundos |
| Auditabilidad | ✅ Excelente — cada decisión trazable |
| Escalabilidad | ✅ Lineal con hardware |
| Determinismo | ✅ Mismo input = mismo output siempre |
| Si la API se cae | ✅ No depende de API externa |

**Debilidades:**
- Cada nueva regla requiere código → desplegar
- No interpreta instrucciones ambiguas ("las rutas del centro no me convencen")
- El usuario interactúa con formularios, botones, opciones predefinidas

---

### Opción C: Híbrido (RECOMENDADO)

**Cómo funciona** con "800 pedidos de Raúl":
1. **Reglas** procesan los 800 pedidos: validación → clustering → bin-packing → secuenciación → 2-5 segundos, $0
2. Sistema presenta: "12 rutas creadas, 800 pedidos asignados"
3. Usuario dice: *"Las rutas de Salamanca están muy dispersas. Consolida las de Calle Serrano y mueve las del Retiro a la tarde"*
4. **Ahora** se invoca el LLM con contexto acotado: solo las 2-3 rutas afectadas (40-60 pedidos), tools de `move_stop` y `resequence_route`
5. LLM interpreta la instrucción ambigua, identifica paradas por dirección, ejecuta cambios
6. **Capa de validación** verifica cada cambio: ¿capacidad OK? ¿ventanas OK?
7. Coste del LLM: $0.05-0.15 (vs $5-20 en opción A)

```
Petición del Usuario
     │
     ▼
[Clasificador de Intención]
     │                    │
     │ Estructurada       │ Ambigua/Natural
     ▼                    ▼
[Motor de Reglas]     [Agente LLM]
     │                    │
     ▼                    ▼
[Resultado             [Llamadas a Tools]
 Determinista]              │
     │                    ▼
     │             [Capa de Validación] ← mismas reglas que el motor
     │                    │
     ▼                    ▼
[Base de Datos]      [Si válido → DB]
                     [Si no → Error + explicación]
```

**Principio clave: El LLM PROPONE, el sistema determinista DISPONE.**

| Aspecto | Valoración |
|---------|------------|
| Fiabilidad operaciones masivas | ✅ Excelente (motor de reglas) |
| Peticiones ambiguas | ✅ Bueno (LLM acotado) |
| Coste mensual | ✅ $300-3K (vs $15K-60K del LLM puro) |
| Latencia (masiva) | ✅ 2-5 segundos (reglas) |
| Latencia (interactiva) | ✅ 2-8 segundos (LLM) |
| Auditabilidad | ✅ Buena-Excelente |
| Escalabilidad | ✅ Excelente |
| Si la API se cae | ✅ 95% sigue funcionando |
| Riesgo de alucinación | ✅ Bajo — validación atrapa errores del LLM |

---

## Qué Operaciones van a Reglas vs LLM

### REGLAS (determinista, sin LLM)

| Operación | Por qué |
|-----------|---------|
| Creación de rutas desde lista de pedidos | Problema de optimización combinatoria resuelto |
| Validación de capacidad de vehículo | Aritmética simple, debe ser 100% correcto |
| Asignación de conductores por disponibilidad | Matching de horarios determinista |
| Transiciones de estado de envío | Máquina de estados bien definida |
| Actualizaciones de tracking | Pipeline de datos, sin razonamiento |
| Geocodificación de direcciones | API call a servicio, determinista |
| Validación POD | Checklist |
| Importación CSV | Parseo + validación + creación batch |
| Cálculo de costes | Fórmulas matemáticas |
| Generación de albaranes | Template + datos |

### LLM (juicio humano necesario)

| Operación | Por qué |
|-----------|---------|
| "Mueve las entregas del centro a la mañana" | Referencia geográfica ambigua |
| "Esta ruta no me convence, arréglala" | Requiere entender contexto e inferir intención |
| Gestión de excepciones con comunicación al cliente | Respuesta matizada, dependiente de contexto |
| Parseo de datos no estructurados (emails, PDFs) | Comprensión de lenguaje natural |
| Explicar decisiones de ruta a clientes | Generación de lenguaje natural |
| "Divide la ruta 5 — el conductor dice que hay atasco en la A-2" | Contexto en tiempo real + instrucción ambigua |
| Análisis de patrones de clientes | Razonamiento sobre datos complejos |

---

## Plan de Implementación Recomendado

### Fase 1: Motor de Reglas (meses 1-3)
- Servicios de optimización (clustering, bin-packing, secuenciación)
- Validación de restricciones como servicio reutilizable
- Symfony Messenger para procesamiento async
- Audit logging completo
- **Esto solo entrega el 90% del valor operativo**

### Fase 2: Capa LLM (meses 3-5)
- Cliente Claude API como servicio Symfony
- Definiciones de tools mapeados a servicios existentes
- Interceptor de validación en todas las llamadas del LLM
- Modificación de rutas en lenguaje natural
- Procesamiento de input no estructurado

### Lo que NO hacer

| NO | Por qué |
|----|---------|
| No usar LLM para crear rutas | Problema resuelto en investigación operativa — usar algoritmos |
| No pasar listas completas de pedidos al LLM | Mantener contextos acotados |
| No dejar que el LLM escriba directamente en DB | Todo pasa por capa de validación |
| No construir Opción A primero "porque es más rápido" | El prototipo no será fiable y lo reconstruirás como Opción B |

---

## Principio Guía

> **Usar sistemas deterministas para problemas deterministas. Usar el LLM solo donde el juicio humano es genuinamente necesario.**
>
> En logística, el 95% de las operaciones son deterministas. El valor del LLM está en el 5% restante — las decisiones ambiguas, contextuales, que de otra forma requerirían un operador humano mirando una pantalla.

---

## Tabla Resumen Final

| Criterio | A (LLM Puro) | B (Reglas) | C (Híbrido) ✅ |
|----------|:---:|:---:|:---:|
| Fiabilidad masiva | 2/10 | 10/10 | 10/10 |
| Lenguaje natural | 10/10 | 0/10 | 8/10 |
| Coste mensual | 2/10 | 10/10 | 9/10 |
| Latencia | 3/10 | 10/10 | 9/10 |
| Auditabilidad | 2/10 | 10/10 | 9/10 |
| Escalabilidad | 3/10 | 10/10 | 10/10 |
| Capacidad emergente | 9/10 | 0/10 | 7/10 |
| Disponibilidad | 3/10 | 10/10 | 9/10 |
| **TOTAL** | **34/80** | **60/80** | **71/80** |
