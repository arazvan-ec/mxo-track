# DECISIONS — FASE 1

1. **Symfony 7.4 LTS**
   - Se fija baseline en rama `^7.4` para estabilidad LTS y actualizaciones menores compatibles.

2. **Login tradicional + sesiones Redis**
   - Se usa `form_login` (sin JWT propio para autenticación de portal).
   - Session handler en Redis con prefijo `sess:transporte:`.

3. **public_id ULID + BIGINT interno**
   - Patrón para User y entidades críticas: PK BIGINT interno, `public_id` ULID único para exposición externa.

4. **SameSite=Lax**
   - Cookies de sesión endurecidas con `HttpOnly`, `Secure=auto` y `SameSite=lax`.

5. **Seguridad base**
   - Rate limiting en login.
   - Security headers: `X-Frame-Options=DENY`, `X-Content-Type-Options=nosniff`, `Referrer-Policy`, `CSP` básica.

6. **Turbo global**
   - Turbo habilitado por defecto en layout base; el mapa realtime podrá excluirse en fases posteriores.

7. **Rutas públicas con `public_id`**
   - Se migran endpoints públicos iniciales de Vehicle y Shipment a `{publicId}`.
   - El `id` interno queda reservado para joins/infra y no se expone en JSON público.

8. **Migración dura de API pública a `public_id`**
   - Se elimina uso de `{id}` en endpoints públicos migrados (Driver/Vehicle/Shipment) y se usa `{publicId}`.

9. **Payload explícito `shipment_public_id`**
   - En APIs DRIVER se renombra `shipment_id` a `shipment_public_id` para evitar ambigüedad con IDs internos.

10. **Eventos internos**
   - Se mantiene `id` interno para joins y procesamiento interno (mejor rendimiento y simplicidad relacional).
   - `public_id` se usa en contratos públicos y payloads expuestos al cliente.
