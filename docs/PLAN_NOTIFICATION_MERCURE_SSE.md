# Plan: Reemplazar polling de notificaciones con Mercure SSE

## Context

El `notificationBell()` en `base.html.twig` hace polling HTTP cada 30 segundos a `/api/notifications/unread-count`. Esto genera muchísimas peticiones y errores (ej: sesión expirada, red lenta). Además hay un **bug**: cada llamada a `fetchCount()` crea un nuevo `setInterval` sin limpiar el anterior, causando polling exponencial.

**Lo bueno:** El backend ya publica actualizaciones Mercure en `NotificationService::publishMercureUpdate()` al topic `/users/{id}/notifications` cada vez que se crea o lee una notificación. Solo falta conectar el frontend.

## Cambios

### 1. Añadir `mercure_public_url` como Twig global
**Archivo:** `backend/config/packages/twig.yaml`

Añadir la variable como global para que esté disponible en `base.html.twig` sin tener que pasarla desde cada controller:

```yaml
globals:
    app_name: 'transporte-tracking'
    mercure_public_url: '%env(MERCURE_PUBLIC_URL)%'
```

### 2. Añadir topic de notificaciones en TopicResolver
**Archivo:** `backend/src/Security/TopicResolver.php`

El topic `/users/{id}/notifications` no está incluido en los topics autorizados para CUSTOMER ni DRIVER. Añadir para todos los roles:

- ADMIN: ya tiene `['*']` (cubre todo)
- CUSTOMER: añadir `/users/{userId}/notifications` al array de topics
- DRIVER: añadir `/users/{userId}/notifications` al array de topics

### 3. Reescribir `notificationBell()` para usar Mercure SSE
**Archivo:** `backend/templates/base.html.twig` (líneas 344-363)

Nuevo flujo:
1. Fetch inicial del count via HTTP (una sola vez para obtener el valor actual)
2. Obtener Mercure token via `/api/mercure-token`
3. Suscribirse al topic `/users/{userId}/notifications` via EventSource
4. Actualizar `unreadCount` en tiempo real cuando llega un evento Mercure
5. **Eliminar el `setInterval` por completo** — no más polling

Necesitamos pasar `app.user.id` al JavaScript. Usaremos un `data-` attribute en el HTML.

### 4. Mantener el endpoint `/api/notifications/unread-count`
El endpoint se conserva — sigue siendo útil para:
- El fetch inicial al cargar la página
- Fallback si Mercure no conecta

## Archivos a modificar

1. `backend/config/packages/twig.yaml` — añadir global
2. `backend/src/Security/TopicResolver.php` — añadir notification topics
3. `backend/templates/base.html.twig` — reescribir notificationBell()

## Verificación

1. Abrir la app en el navegador con DevTools → Network
2. Confirmar que NO hay peticiones repetidas a `/api/notifications/unread-count` (solo 1 al cargar)
3. Crear una notificación (ej: vía fixtures o acción en la app)
4. Confirmar que el badge del bell se actualiza sin recargar la página
5. Confirmar que EventSource está conectado al topic de Mercure en Network → EventSource
