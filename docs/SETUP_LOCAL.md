# Setup local — Fase 1

## Requisitos
- PHP 8.2+
- Composer
- PostgreSQL 16+
- Redis 7+

## Redis local para sesiones

Opción rápida con Docker:

```bash
docker run --name transporte-redis -p 6379:6379 -d redis:7-alpine
```

Verificar prefijo de sesiones:

```bash
redis-cli -h 127.0.0.1 -p 6379 KEYS 'sess:transporte:*'
```

## Variables de entorno
Asegura en `.env.local`:

```env
REDIS_URL=redis://127.0.0.1:6379
REDIS_SESSION_PREFIX=sess:transporte:
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
```

## Trusted proxies (si usas Nginx reverse proxy)
Configura `TRUSTED_PROXIES` y `TRUSTED_HEADERS` en entorno para que Symfony calcule correctamente IP/protocolo.

En esta fase sólo validamos arranque de aplicación. Dejamos tests y CI para fases posteriores. Mercure se considera opcional por ahora.
