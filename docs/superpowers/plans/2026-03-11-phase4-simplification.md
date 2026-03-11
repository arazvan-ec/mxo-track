# Plan Fase 4: Simplificación y Limpieza

**Goal:** Reducir complejidad innecesaria, eliminar código muerto, consolidar duplicados
**Prerequisito:** Fases 1-3 (tests verdes con buena cobertura para detectar regresiones)

---

## Auditoría Previa

Antes de eliminar nada, verificar uso real de cada módulo.

---

## Tareas

### Task 1: Auditar módulo AI/ML

**Archivos:** `src/Ai/` (9 files), `src/Prediction/` (9 files), `ml-service/`

- [ ] 1.1 Verificar si `ClaudeLlmClient` se usa en producción o solo `NullLlmClient`
- [ ] 1.2 Verificar si `OpenAiEmbeddingClient` se usa o solo `NullEmbeddingClient`
- [ ] 1.3 Verificar si `HttpMlServiceClient` se usa o solo `NullEtaPredictor`/`NullDemandForecaster`/`NullAnomalyDetector`
- [ ] 1.4 Buscar en `services.yaml` qué implementaciones están configuradas como default
- [ ] 1.5 Si los Null implementations son las activas: documentar el estado y marcar como "pendiente de activación"
- [ ] 1.6 Si hay código muerto: proponer eliminación con justificación
- [ ] 1.7 Commit: "docs: audit AI/ML module usage status"

### Task 2: Auditar canales de notificación

**Archivos:** `src/Notification/` (16 files)

- [ ] 2.1 Verificar configuración de Twilio (SMS/WhatsApp) — ¿hay API keys configuradas?
- [ ] 2.2 Verificar si `SmsChannel` y `WhatsAppChannel` tienen providers reales o solo `NullSmsProvider`/`NullWhatsAppProvider`
- [ ] 2.3 Verificar si `WebPushService` está activo
- [ ] 2.4 Documentar qué canales están activos vs preparados para futuro
- [ ] 2.5 Commit: "docs: audit notification channels status"

### Task 3: Consolidar controladores duplicados

**Problema detectado:** `ShipmentApiController` (2 versiones)
- `src/Controller/ShipmentApiController.php`
- `src/Controller/Api/V1/ShipmentApiController.php`

- [ ] 3.1 Comparar ambos controladores — ¿qué rutas tiene cada uno?
- [ ] 3.2 Verificar si el primero es legacy y el segundo es la versión actual
- [ ] 3.3 Buscar referencias al legacy controller
- [ ] 3.4 Si es legacy: migrar rutas útiles a V1, eliminar legacy
- [ ] 3.5 Verificar que no se rompen tests
- [ ] 3.6 Commit: "refactor: consolidate ShipmentApiController"

### Task 4: Revisar entidades con SoftDelete

**Archivos:** Entidades con `SoftDeleteTrait`

- [ ] 4.1 Listar todas las entidades que usan `SoftDeleteTrait`
- [ ] 4.2 Verificar que el `SoftDeleteFilter` está correctamente configurado
- [ ] 4.3 Verificar que las queries no olvidan aplicar el filtro
- [ ] 4.4 Documentar hallazgos

### Task 5: Limpieza de documentación obsoleta

**Archivos:** `docs/`

- [ ] 5.1 Identificar documentos de fases completadas que ya no son relevantes
- [ ] 5.2 Mover a `docs/archive/` (no eliminar)
- [ ] 5.3 Verificar que `FEATURES.md` está actualizado
- [ ] 5.4 Commit: "docs: archive completed phase documents"

### Task 6: Revisión de composer.json

- [ ] 6.1 Añadir `description` al composer.json
- [ ] 6.2 Añadir `license: proprietary`
- [ ] 6.3 Verificar que no hay dependencias no usadas
- [ ] 6.4 Commit: "chore: add description and license to composer.json"

### Task 7: Revisión de configuración de servicios

- [ ] 7.1 Leer `services.yaml` — identificar servicios sin uso
- [ ] 7.2 Verificar que los aliases de interfaz apuntan a implementaciones correctas
- [ ] 7.3 Verificar que autoconfiguration y autowiring están correctos
- [ ] 7.4 Documentar hallazgos

### Task 8: Verificación Final

- [ ] 8.1 Ejecutar `make lint`
- [ ] 8.2 Ejecutar `php vendor/bin/phpunit` — 0 errores
- [ ] 8.3 Verificar `composer validate`
- [ ] 8.4 Verificar que la app bootea: `php bin/console about`
- [ ] 8.5 Revisión final del estado del repo
