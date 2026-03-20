# Plan: Session B — Security + Cleanup

**Spec:** `docs/superpowers/specs/2026-03-20-session-b-security-cleanup-design.md`

## Task 1: RED — CredentialEncryptor tests

- Create `tests/Unit/Infrastructure/Security/CredentialEncryptorTest.php`
- Tests: round-trip, not-plaintext, different-keys-fail, empty-array, different-each-time
- Run → RED
- Commit: `test: add CredentialEncryptor tests`

## Task 2: GREEN — CredentialEncryptor

- Create `src/Infrastructure/Security/CredentialEncryptor.php`
- sodium_crypto_secretbox with key from APP_SECRET
- Run → GREEN
- Commit: `feat: add CredentialEncryptor with sodium encryption`

## Task 3: EncryptedJsonType + Initializer + tests

- Create `src/Doctrine/Types/EncryptedJsonType.php`
- Create `src/Doctrine/Types/EncryptedJsonTypeInitializer.php`
- Create `tests/Unit/Infrastructure/Security/EncryptedJsonTypeTest.php`
- Register type in `config/packages/doctrine.yaml`
- Run → GREEN
- Commit: `feat: add encrypted_json Doctrine custom type`

## Task 4: Integrate with CustomerIntegration + Migration

- Modify `src/Entity/CustomerIntegration.php`: `Types::JSON` → `'encrypted_json'`
- Create migration to encrypt existing data
- Commit: `feat: integrate credential encryption with CustomerIntegration`

## Task 5: SLA PDF Export

- `composer require dompdf/dompdf`
- Modify `SlaReportController::export()` to use DomPDF
- Commit: `feat: implement SLA report PDF export with DomPDF`

## Task 6: Documentation entries

- Add User.php SRP decision to `docs/decisions/log.md`
- Add codegen trigger evaluation to `docs/decisions/log.md`
- Commit: `docs: document User.php SRP exclusion and codegen trigger evaluation`
