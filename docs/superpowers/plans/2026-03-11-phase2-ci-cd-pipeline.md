# Plan Fase 2: CI/CD Pipeline

**Goal:** GitHub Actions que ejecute tests y lint en cada PR, bloqueando merge si fallan
**Estado actual:** Solo existe `deploy.yml` que hace lint + Symfony boot, pero NO ejecuta PHPUnit

---

## Análisis Previo

### Problemas detectados en deploy.yml actual:
1. **No ejecuta PHPUnit** — solo hace lint y `php bin/console about`
2. **Database name mismatch:** Workflow usa `mxo_track_test`, `.env.test` usa `transporte_test`
3. **User credentials mismatch:** Workflow usa `test/test`, `.env.test` usa `app:test`
4. **NelmioApiDocBundle falla** al hacer cache:clear

---

## Tareas

### Task 1: Corregir `.env.test`

**Archivo:** `backend/.env.test`

- [ ] 1.1 Alinear DATABASE_URL con lo que espera el workflow de CI: `postgresql://test:test@localhost:5432/mxo_track_test`
- [ ] 1.2 Verificar que REDIS_URL usa localhost (correcto para CI)
- [ ] 1.3 Commit: "fix: align .env.test with CI database credentials"

### Task 2: Fix NelmioApiDocBundle routing error

**Problema:** `@NelmioApiDocBundle/Resources/config/routing.yaml` no se encuentra.

- [ ] 2.1 Verificar versión instalada de NelmioApiDocBundle
- [ ] 2.2 Actualizar `config/routes/nelmio_api_doc.yaml` al formato correcto para la versión actual
- [ ] 2.3 Verificar que `php bin/console cache:clear` funciona sin errores
- [ ] 2.4 Verificar que `php bin/console about` funciona
- [ ] 2.5 Commit: "fix: update NelmioApiDoc routing for bundle version"

### Task 3: Añadir PHPUnit al workflow de CI

**Archivo:** `.github/workflows/deploy.yml`

- [ ] 3.1 Añadir step de "Run migrations" después de instalar dependencias:
  ```yaml
  - name: Run migrations
    run: cd backend && php bin/console doctrine:migrations:migrate -n
    env:
      DATABASE_URL: postgresql://test:test@localhost:5432/mxo_track_test
  ```
- [ ] 3.2 Añadir step de "Run tests":
  ```yaml
  - name: Run PHPUnit
    run: cd backend && php vendor/bin/phpunit
  ```
- [ ] 3.3 Verificar que los services de PostgreSQL y Redis están configurados correctamente
- [ ] 3.4 Commit: "feat: add PHPUnit to CI pipeline"

### Task 4: Configurar branch protection (documentación)

- [ ] 4.1 Documentar en README o CLAUDE.md: configurar branch protection en GitHub para `main`
  - Require status checks: `lint` job must pass
  - Require PR reviews (opcional)
- [ ] 4.2 Commit: "docs: add branch protection recommendations"

### Task 5: Verificación

- [ ] 5.1 Push a branch de feature
- [ ] 5.2 Verificar que el workflow se ejecuta
- [ ] 5.3 Verificar que lint + tests pasan en CI
