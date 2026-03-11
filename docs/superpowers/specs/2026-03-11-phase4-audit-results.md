# Resultados de Auditoría — Fase 4

**Fecha:** 2026-03-11

---

## 1. Auditoría AI/ML

| Módulo | Estado | Evidencia |
|--------|--------|-----------|
| `ClaudeLlmClient` | **Scaffolded** | `NullLlmClient` es el default en services.yaml. `CLAUDE_API_KEY` vacío en .env |
| `OpenAiEmbeddingClient` | **Scaffolded** | `NullEmbeddingClient` es el default. `OPENAI_API_KEY` vacío |
| `HttpMlServiceClient` | **Scaffolded** | Null predictors son default. `ML_SERVICE_URL` vacío |
| `AiAssistantService` | **Scaffolded** | Controller existe, usa LlmClientInterface → apunta a Null |
| `EmbeddingService` | **Scaffolded** | Usado en ShipmentEmbeddingListener, pero con NullEmbeddingClient |
| `ExceptionClassifierService` | **Scaffolded** | Usado en NlpClassificationHandler, pero LLM es Null |
| `ml-service/` (Python FastAPI) | **Scaffolded** | Dockerfile existe, servicio en docker-compose, pero predictors apuntan a Null |

**Conclusión:** Todo el módulo AI/ML está correctamente scaffolded con el patrón Null Object. La infraestructura está lista para activarse cuando se configuren las API keys. **No es código muerto** — es funcionalidad preparada para producción.

**Recomendación:** No eliminar. Documentar como "disponible, requiere configuración de API keys para activar".

---

## 2. Auditoría Canales de Notificación

| Canal | Provider Default | Estado |
|-------|-----------------|--------|
| SMS | `NullSmsProvider` | Scaffolded — Twilio ready, needs API keys |
| WhatsApp | `NullWhatsAppProvider` | Scaffolded — Twilio ready, needs API keys |
| Log | `LogChannel` | **Activo** — siempre funciona |
| WebPush | `WebPushService` | **Activo** — usa push subscriptions del browser |

**Uso real:** `RecipientNotificationService` es usado activamente en 6 locations:
1. `RouteActivatedNotificationSubscriber` — notifica inicio de ruta
2. `ApproachingNotificationSubscriber` — notifica cuando conductor está cerca
3. `PreDeliveryNotificationHandler` — mensaje async pre-entrega
4. `PublicTrackingController` — notifica entrega completada
5. `WebPushService` — push notifications
6. `PushNotifyReoptimizeSubscriber` — notifica re-optimización

**Problema encontrado:** `APP_BASE_URL` se usa en services.yaml pero **no está definido** en ningún archivo .env → URLs de tracking y rating se generarán vacías.

**Recomendación:** Añadir `APP_BASE_URL=http://localhost:8000` a `.env`.

---

## 3. Auditoría Controladores Duplicados

### ShipmentApiController (2 versiones)

| Archivo | Ruta | Propósito |
|---------|------|-----------|
| `Controller/ShipmentApiController.php` | `/api/shipments` | Legacy — GET list, GET detail |
| `Controller/Api/V1/ShipmentApiController.php` | `/api/v1/shipments` | V1 API — POST create, GET list (paginado), GET detail, GET tracking |

**Conclusión:** No hay conflicto funcional (rutas diferentes). El controller raíz parece legacy. Considerar eliminación si no hay clientes usándolo.

### RouteAnalysisController (2 versiones)

| Archivo | Ruta | Respuesta | Rol |
|---------|------|-----------|-----|
| `Controller/Api/RouteAnalysisController.php` | `/api/routes/{id}/analysis` | JSON | ROLE_OPERATOR |
| `Controller/Admin/RouteAnalysisController.php` | `/admin/routes/{id}/analysis` | HTML (Twig) | ROLE_ADMIN |

**Conclusión:** Diseño intencional — API (JSON) vs Web UI (HTML). Usan diferentes servicios internos. **No es duplicación.**

---

## 4. Acciones Recomendadas (Prioridad)

1. **Alta:** Añadir `APP_BASE_URL` a `.env` — URLs de notificación rotas sin esto
2. **Media:** Evaluar si eliminar `Controller/ShipmentApiController.php` (legacy)
3. **Baja:** Documentar módulo AI/ML como "disponible, requiere configuración"
