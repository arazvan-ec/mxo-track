# DECISIONS — FASE 1

1. **Symfony 7.4 LTS estricta**
   - Se congela Symfony en `7.4.*` para todas las fases (sin componentes `8.x`).
   - Se fuerza con `extra.symfony.require=7.4.*`, conflicto explícito para `symfony/symfony >=8.0` y guardia de lockfile (`scripts/check_symfony_74_lock.sh`) para bloquear propuestas con componentes 8.x.

2. **Symfony Flex + recetas como estándar**
   - Se adopta Flex/recipes como forma oficial de bootstrap y mantenimiento de configuración del backend.

3. **Login tradicional + sesiones Redis**
   - Se usa `form_login` (sin JWT propio para autenticación de portal).
   - Session handler en Redis con prefijo `sess:transporte:`.

4. **public_id ULID + BIGINT interno**
   - Patrón para User y entidades críticas: PK BIGINT interno, `public_id` ULID único para exposición externa.

5. **SameSite=Lax**
   - Cookies de sesión endurecidas con `HttpOnly`, `Secure=auto` y `SameSite=lax`.

6. **Seguridad base**
   - Rate limiting en login.
   - Security headers: `X-Frame-Options=DENY`, `X-Content-Type-Options=nosniff`, `Referrer-Policy`, `CSP` básica.

7. **Turbo global**
   - Turbo habilitado por defecto en layout base; el mapa realtime podrá excluirse en fases posteriores.

8. **Rutas públicas con `public_id`**
   - Se migran endpoints públicos iniciales de Vehicle y Shipment a `{publicId}`.
   - El `id` interno queda reservado para joins/infra y no se expone en JSON público.

9. **Migración dura de API pública a `public_id`**
   - Se elimina uso de `{id}` en endpoints públicos migrados (Driver/Vehicle/Shipment) y se usa `{publicId}`.

10. **Payload explícito `shipment_public_id`**
   - En APIs DRIVER se renombra `shipment_id` a `shipment_public_id` para evitar ambigüedad con IDs internos.

11. **Eventos internos**
   - Se mantiene `id` interno para joins y procesamiento interno (mejor rendimiento y simplicidad relacional).
   - `public_id` se usa en contratos públicos y payloads expuestos al cliente.


12. **Criterio operativo de “Symfony funcionando”**
   - Se valida de forma manual local con `bash scripts/symfony_e2e_boot_check.sh` levantando `db/redis/mercure` en Docker Compose.
   - Por ahora no es gate obligatorio de CI.


13. **Enfoque de fase actual**
   - Prioridad: aplicación Symfony arrancando y operativa.
   - Se pospone definición de estrategia de tests y CI para fases posteriores.


14. **Mercure opcional en fase actual**
   - Mercure no bloquea la validación base de arranque de la aplicación en esta etapa.

15. **Regla rígida de identidad en entidades**
   - Arquitectura obligatoria: PK interna `BIGINT` + `public_id` ULID para exposición pública.
   - Queda prohibido introducir nuevas entidades con PK UUID interna salvo justificación excepcional y decisión explícita documentada.

16. **Topics Mercure por `public_id`**
   - Se estandarizan los topics de vehículo en Mercure usando `public_id` (`/vehicles/{public_id}/position`) tanto para publicación backend como para suscripción.

17. **Tablas técnicas 1:1: consistencia rígida también aplicada**
   - Se prioriza uniformidad del modelo: PK interna BIGINT + `public_id` ULID también en tablas técnicas 1:1.
   - En `vehicle_last_position` se acepta la desviación frente al prompt literal: `id` BIGSERIAL como PK y `vehicle_id` con UNIQUE.
