# Tests — Conventions and Patterns

<!-- GENERIC-START -->
## Philosophy

Tests are the executable specification. If a test passes without you having 
implemented anything, it doesn't test the right thing. Every test must be 
watched failing before writing the implementation.

## Structure

Organize tests to mirror the code they validate:
- **Unit tests** — Pure logic, no database, no framework. Fast.
- **Functional tests** — HTTP requests through the framework. Slow but comprehensive.
- **Domain tests** — Domain model behavior with minimal infrastructure.
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
### This Project's Test Layout
- `tests/Unit/` — Unit tests for services, value objects, domain logic
- `tests/Functional/` — Controller tests with WebTestCase
- `tests/Domain/` — Domain model tests (Route, RouteStop, events)
- `tests/Factory/` — Factory pattern tests

### Fixtures
- Fixtures loaded via `doctrine:fixtures:load`
- Admin user created by default fixtures
- Tests that need specific data should create it in setUp()

### Running Tests
```bash
cd backend && php vendor/bin/phpunit          # Full suite
php vendor/bin/phpunit --filter=ClassName     # Single class
php vendor/bin/phpunit tests/Unit/            # By directory
```
<!-- PROJECT-SPECIFIC-END -->

<!-- GENERIC-START -->
## Verification Checklist
- [ ] Every new function/method has a test
- [ ] Watched each test fail before implementing
- [ ] Wrote minimal code to pass each test
- [ ] All tests pass (full suite, not just new tests)
- [ ] Output is pristine (no errors, warnings, deprecations)
<!-- GENERIC-END -->
