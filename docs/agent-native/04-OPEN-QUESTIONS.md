# Preguntas Abiertas — Pendientes de Resolver

> Fecha: 2026-02-27
> Estado: Pendiente de respuestas del cliente

---

## 🔴 CRÍTICAS (bloquean desarrollo)

### Q1: Tipos de Servicio Completos
**Pregunta:** Mencionas paquetería entrega, entrega+recogida, devolución, y que "en la web hay más ejemplos". ¿Cuáles son TODOS los tipos de servicio que necesitamos soportar?
**Impacto:** Define la entidad ServiceType y los flujos de cada uno.
**Respuesta:**

---

### Q2: Estados de Bultos/Entregas — Lista completa
**Pregunta:** Mencionas "cargado, en ruta, entregado, ausencia, etc." — ¿cuál es la lista COMPLETA de estados? ¿Cada bulto tiene su propio estado independiente, o todos los bultos de un envío comparten estado?
**Impacto:** Define el modelo de estados y las transiciones válidas.
**Propuesta:**
```
PENDING → LOADED → IN_ROUTE → OUT_FOR_DELIVERY → DELIVERED
                                                 → ABSENT (reintento)
                                                 → REFUSED
                                                 → DAMAGED
                                                 → RETURNED
```
**Respuesta:**

---

### Q3: Modelo de Costes
**Pregunta:** Mencionas €/ruta y €/bulto. ¿Cómo se calcula el coste?
- ¿Es un coste fijo por ruta + variable por bulto?
- ¿Incluye coste por km?
- ¿Coste por hora del transportista?
- ¿Se factura al cliente o es un coste interno?
**Impacto:** Define las métricas del dashboard y el módulo ERP/Costes.
**Respuesta:**

---

### Q4: Integración del Agente IA
**Pregunta:** ¿Quién/qué es el "agente" en el contexto de MXO-Track?
- **Opción A:** Un LLM (Claude/GPT) que recibe peticiones en lenguaje natural y ejecuta tools
- **Opción B:** Un sistema de reglas automatizado que procesa lógica de negocio
- **Opción C:** Ambos — IA para decisiones complejas, reglas para flujos predecibles
**Impacto:** Define toda la capa agent y la inversión en infraestructura IA.
**Respuesta:**

---

## 🟡 IMPORTANTES (afectan diseño)

### Q5: SGA — Alcance del Módulo de Almacén
**Pregunta:** Mencionas "entrada de mercancía" en el SGA. ¿Esto implica:
- ¿Gestión de inventario (stock)?
- ¿O solo registro de entrada/salida de bultos para entregas?
- ¿Hay almacén(es) físico(s) que gestionar?
**Impacto:** Si es un SGA completo, es un módulo enorme. Si es solo registro de bultos, es mucho más simple.
**Respuesta:**

---

### Q6: RGU e Isócronas — Fuente de Datos
**Pregunta:** Para calcular isócronas necesitamos una API de routing. ¿Usamos:
- **OpenRouteService** (ya integrado para mapas) — tiene endpoint de isócronas
- **Google Maps** (más preciso, con coste)
- **OSRM** (open source, self-hosted)
**Impacto:** Coste, precisión, dependencia de terceros.
**Propuesta:** Empezar con OpenRouteService (ya integrado) y migrar si es necesario.
**Respuesta:**

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

---

### Q9: Generación de Albarán
**Pregunta:** ¿Qué información debe llevar el albarán?
- ¿Hay un formato legal específico?
- ¿Necesita firma digital?
- ¿Se genera PDF?
- ¿Tiene numeración secuencial?
**Impacto:** Define el módulo de documentos.
**Respuesta:**

---

### Q10: Productividad del Transportista
**Pregunta:** ¿Qué métricas definen el "caso de éxito" del transportista?
- ¿Entregas completadas vs planificadas?
- ¿Tiempo medio por entrega?
- ¿Cumplimiento de ventanas horarias (RGU)?
- ¿Ratio de excepciones?
**Impacto:** Define el dashboard de productividad.
**Respuesta:**

---

## 🟢 DESEABLES (pueden decidirse durante desarrollo)

### Q11: Multi-almacén
**Pregunta:** ¿Los clientes tienen un solo punto de origen o varios almacenes?
**Nota:** Ya existe `CustomerLocation` que soporta múltiples ubicaciones.
**Respuesta:**

---

### Q12: Optimizador — Estrategia Preferida
**Pregunta:** Mencionas "empezar desde el punto más alejado". ¿Es una preferencia firme o podemos ofrecer varias estrategias?
- Nearest-neighbor (ya implementado)
- Farthest-first (empezar desde el más alejado)
- Time-window based (priorizar ventanas horarias)
- Hybrid (isócronas + distancia)
**Respuesta:**

---

### Q13: Escalabilidad — Volumen Esperado
**Pregunta:** ¿Cuántos pedidos/rutas/vehículos manejamos?
- ¿800 pedidos de Raúl es el caso típico o excepcional?
- ¿Cuántos clientes B2B simultáneos?
- ¿Cuántos vehículos en total?
**Impacto:** Arquitectura (monolito vs microservicios), infra, colas.
**Respuesta:**

---

### Q14: Página Web de Servicios
**Pregunta:** Mencionas que "en la página web hay ejemplos de servicios". ¿Puedes compartir la URL o capturas? Necesito entender todos los tipos de servicio.
**Respuesta:**

---

### Q15: SMS/Correo para Crear Servicio
**Pregunta:** ¿El cliente puede crear un servicio enviando un SMS o correo? ¿Cómo funciona esto?
- ¿Email a una dirección específica que se parsea automáticamente?
- ¿SMS a un número que se procesa?
- ¿Esto es un requisito de fase 1 o futuro?
**Respuesta:**

---

## Historial de Preguntas y Respuestas

| Fecha | Pregunta | Respuesta |
|-------|----------|-----------|
| 2026-02-27 | Documento inicial creado | Pendiente de revisión |
| | | |
