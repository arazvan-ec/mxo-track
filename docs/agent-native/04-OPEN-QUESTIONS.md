# Preguntas Abiertas — Pendientes de Resolver

> Fecha: 2026-02-27
> Estado: En revisión — respuestas parciales recibidas, análisis en curso

---

## 🔴 CRÍTICAS (bloquean desarrollo)

### Q1: Tipos de Servicio Completos
**Pregunta:** Mencionas paquetería entrega, entrega+recogida, devolución, y que "en la web hay más ejemplos". ¿Cuáles son TODOS los tipos de servicio que necesitamos soportar?
**Impacto:** Define la entidad ServiceType y los flujos de cada uno.
**Respuesta del cliente (2026-02-27):** "Con esa me vale, pero tú puedes investigar y recomendarme más?"
**Acción:** Investigación en curso — análisis de tipos de servicio de SEUR, MRW, GLS, DHL, UPS, Correos Express. Ver documento `06-RESEARCH-SERVICE-TYPES.md`.
**Estado:** 🔄 Investigando

---

### Q2: Estados de Bultos/Entregas — Lista completa
**Pregunta:** Mencionas "cargado, en ruta, entregado, ausencia, etc." — ¿cuál es la lista COMPLETA de estados? ¿Cada bulto tiene su propio estado independiente, o todos los bultos de un envío comparten estado?
**Impacto:** Define el modelo de estados y las transiciones válidas.
**Respuesta del cliente (2026-02-27):** "Entrega, ausente, dañado — tú me puedes recomendar?"
**Acción:** Investigación en curso — análisis de máquinas de estado de operadores logísticos españoles/europeos. Ver documento `07-RESEARCH-STATUSES.md`.
**Estado:** 🔄 Investigando

---

### Q3: Modelo de Costes
**Pregunta:** Mencionas €/ruta y €/bulto. ¿Cómo se calcula el coste?
**Respuesta del cliente (2026-02-27):** "Si es agent-native eso no debería importar, deberíamos ser capaces de hacerlo de múltiples formas y actualizar su impacto en otras partes de forma correcta."
**Resolución:** ✅ **RESUELTO** — El cliente tiene razón. En un sistema agent-native, el modelo de costes NO se hardcodea. Se implementa como:
- **Tools atómicos** de datos: `get_route_distance_km`, `get_driver_hours`, `get_fuel_cost`, `count_parcels`, `get_tolls`, etc.
- **El agente compone** la fórmula según la petición: "coste por ruta", "coste por cliente", "comparar real vs presupuestado"
- **Nuevos factores de coste** = nuevo tool + actualizar prompt, sin tocar código del calculador
- Esto permite múltiples modelos de coste simultáneos (por cliente, por zona, por tipo de servicio)
**Estado:** ✅ Resuelto — enfoque agent-native

---

### Q4: Integración del Agente IA
**Pregunta:** ¿Quién/qué es el "agente" en el contexto de MXO-Track?
- **Opción A:** Un LLM (Claude/GPT) que recibe peticiones en lenguaje natural y ejecuta tools
- **Opción B:** Un sistema de reglas automatizado que procesa lógica de negocio
- **Opción C:** Ambos — IA para decisiones complejas, reglas para flujos predecibles
**Respuesta del cliente (2026-02-27):** "¿Cuál es la diferencia entre las operaciones? Hazme un análisis para saber elegir."
**Acción:** Análisis comparativo en curso. Ver documento `08-RESEARCH-AGENT-TYPES.md`.
**Estado:** 🔄 Investigando

---

## 🟡 IMPORTANTES (afectan diseño)

### Q5: SGA — Alcance del Módulo de Almacén
**Pregunta:** Mencionas "entrada de mercancía" en el SGA. ¿Esto implica gestión completa o solo registro?
**Respuesta del cliente (2026-02-27):** "Mejor gestión completa. Analízame las características de ambas opciones para que pueda leerlo luego."
**Acción:** Análisis comparativo en curso. Ver documento `09-RESEARCH-SGA-WMS.md`.
**Estado:** 🔄 Investigando

---

### Q6: RGU e Isócronas — Fuente de Datos
**Pregunta:** Para calcular isócronas necesitamos una API de routing. ¿Usamos:
- **OpenRouteService** (ya integrado para mapas) — tiene endpoint de isócronas
- **Google Maps** (más preciso, con coste)
- **OSRM** (open source, self-hosted)
**Impacto:** Coste, precisión, dependencia de terceros.
**Propuesta:** Empezar con OpenRouteService (ya integrado) y migrar si es necesario.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q7: Notificaciones al Cliente B2B
**Pregunta:** ¿Qué canales de notificación necesitamos?
- Email ✅ (ya existe base)
- SMS ❓ (¿qué proveedor?)
- Webhook ✅ (ya existe en Customer)
- Push notifications ❓
- WhatsApp ❓
**Impacto:** Integraciones con terceros, costes.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q8: CSV — Formato Exacto
**Pregunta:** ¿Cuál es el formato exacto del CSV que entregarán los clientes?
- ¿Siempre el mismo formato o varía por cliente?
- ¿Incluye peso y volumen por bulto?
- ¿Incluye tipo de servicio?
- ¿Incluye franja horaria preferida?
**Impacto:** Define el importador CSV y la validación.
**Propuesta de formato:**
```csv
reference,service_type,recipient_name,address,lat,lng,phone,parcels_count,total_weight_kg,total_volume_m3,preferred_window_start,preferred_window_end,notes
```
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q9: Generación de Albarán
**Pregunta:** ¿Qué información debe llevar el albarán?
- ¿Hay un formato legal específico?
- ¿Necesita firma digital?
- ¿Se genera PDF?
- ¿Tiene numeración secuencial?
**Impacto:** Define el módulo de documentos.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q10: Productividad del Transportista
**Pregunta:** ¿Qué métricas definen el "caso de éxito" del transportista?
- ¿Entregas completadas vs planificadas?
- ¿Tiempo medio por entrega?
- ¿Cumplimiento de ventanas horarias (RGU)?
- ¿Ratio de excepciones?
**Impacto:** Define el dashboard de productividad.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

## 🟢 DESEABLES (pueden decidirse durante desarrollo)

### Q11: Multi-almacén
**Pregunta:** ¿Los clientes tienen un solo punto de origen o varios almacenes?
**Nota:** Ya existe `CustomerLocation` que soporta múltiples ubicaciones.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q12: Optimizador — Estrategia Preferida
**Pregunta:** Mencionas "empezar desde el punto más alejado". ¿Es una preferencia firme o podemos ofrecer varias estrategias?
- Nearest-neighbor (ya implementado)
- Farthest-first (empezar desde el más alejado)
- Time-window based (priorizar ventanas horarias)
- Hybrid (isócronas + distancia)
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q13: Escalabilidad — Volumen Esperado
**Pregunta:** ¿Cuántos pedidos/rutas/vehículos manejamos?
- ¿800 pedidos de Raúl es el caso típico o excepcional?
- ¿Cuántos clientes B2B simultáneos?
- ¿Cuántos vehículos en total?
**Impacto:** Arquitectura (monolito vs microservicios), infra, colas.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q14: Página Web de Servicios
**Pregunta:** Mencionas que "en la página web hay ejemplos de servicios". ¿Puedes compartir la URL o capturas? Necesito entender todos los tipos de servicio.
**Respuesta:**
**Estado:** ⬜ Pendiente

---

### Q15: SMS/Correo para Crear Servicio
**Pregunta:** ¿El cliente puede crear un servicio enviando un SMS o correo? ¿Cómo funciona esto?
- ¿Email a una dirección específica que se parsea automáticamente?
- ¿SMS a un número que se procesa?
- ¿Esto es un requisito de fase 1 o futuro?
**Respuesta:**
**Estado:** ⬜ Pendiente

---

## Historial de Preguntas y Respuestas

| Fecha | Pregunta | Respuesta del Cliente |
|-------|----------|-----------|
| 2026-02-27 | Documento inicial creado | — |
| 2026-02-27 | Q1: Tipos de servicio | "Con esa me vale, recomiéndame más" → Investigando |
| 2026-02-27 | Q2: Estados | "Entrega, ausente, dañado, recomiéndame" → Investigando |
| 2026-02-27 | Q3: Modelo de costes | "Agent-native = múltiples formas" → ✅ RESUELTO |
| 2026-02-27 | Q4: Tipo de agente | "Hazme análisis comparativo" → Investigando |
| 2026-02-27 | Q5: SGA alcance | "Gestión completa, analízame ambas opciones" → Investigando |
