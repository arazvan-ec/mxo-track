# Plan: Feature 1.1 — Demo Setup Command

**Date:** 2026-03-11
**Spec:** `docs/superpowers/specs/2026-03-11-demo-setup-design.md`
**Goal:** Comando `app:demo:setup` que crea escenario demo completo con datos realistas
**Architecture:** Symfony Command → DemoRouteFixture (mejorada) → RoutePlanningService
**Tech Stack:** PHP 8.4, Symfony 7.4, Doctrine ORM 3.x, PHPUnit

## File Structure

```
backend/
├── src/
│   ├── Command/
│   │   └── DemoSetupCommand.php          # NEW - Symfony command wrapper
│   ├── DataFixtures/
│   │   └── DemoRouteFixture.php          # MODIFY - Extend with 3 vehicles, 2 drivers
│   └── Service/
│       └── DemoScenarioBuilder.php       # NEW - Extracted demo logic (testable)
├── tests/
│   └── Unit/
│       └── Service/
│           └── DemoScenarioBuilderTest.php  # NEW - Unit tests
```

## Tasks

### Task 1: Extract DemoScenarioBuilder service (test-first)

**Why:** DemoRouteFixture depends on Doctrine's ObjectManager which is hard to unit test. Extract the entity creation logic into a testable service.

- [ ] **1a. Write failing test** — `DemoScenarioBuilderTest::testCreatesExpectedEntities`

  **File:** `backend/tests/Unit/Service/DemoScenarioBuilderTest.php`

  Test that `DemoScenarioBuilder::buildScenario()` returns the correct structure:
  - Customer with name "Logística Express Madrid"
  - 3 Vehicles with correct names, capacities, and skills
  - 2 Driver users with correct roles
  - N Shipments (configurable) with valid Madrid coordinates
  - CustomerLocation (warehouse)

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Tests\Unit\Service;

  use App\Service\DemoScenarioBuilder;
  use App\Entity\Customer;
  use App\Entity\Vehicle;
  use App\Entity\User;
  use App\Entity\Shipment;
  use App\Entity\CustomerLocation;
  use App\Enum\VehicleSkill;
  use App\Enum\UserRole;
  use PHPUnit\Framework\TestCase;
  use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

  final class DemoScenarioBuilderTest extends TestCase
  {
      private DemoScenarioBuilder $builder;

      protected function setUp(): void
      {
          $hasher = $this->createMock(UserPasswordHasherInterface::class);
          $hasher->method('hashPassword')->willReturn('hashed');
          $this->builder = new DemoScenarioBuilder($hasher);
      }

      public function testCreatesExpectedEntities(): void
      {
          $result = $this->builder->buildScenario(shipmentCount: 20);

          self::assertInstanceOf(Customer::class, $result->customer);
          self::assertSame('Logística Express Madrid', $result->customer->getName());

          self::assertInstanceOf(CustomerLocation::class, $result->warehouse);
          self::assertNotNull($result->warehouse->getLatitude());

          self::assertCount(3, $result->vehicles);
          self::assertCount(2, $result->drivers);
          self::assertCount(20, $result->shipments);
      }

      public function testVehiclesHaveCorrectSkills(): void
      {
          $result = $this->builder->buildScenario();

          $skills = array_map(fn (Vehicle $v) => $v->getSkills(), $result->vehicles);
          // Furgoneta has FRAGILE
          self::assertContains(VehicleSkill::FRAGILE, $skills[0]);
          // Camión refrigerado has REFRIGERATED + HEAVY_LOAD
          self::assertContains(VehicleSkill::REFRIGERATED, $skills[1]);
          self::assertContains(VehicleSkill::HEAVY_LOAD, $skills[1]);
          // Moto has PEDESTRIAN_ACCESS
          self::assertContains(VehicleSkill::PEDESTRIAN_ACCESS, $skills[2]);
      }

      public function testVehiclesHaveCapacity(): void
      {
          $result = $this->builder->buildScenario();

          // Furgoneta
          self::assertSame(1000.0, $result->vehicles[0]->getMaxWeightKg());
          // Camión refrigerado
          self::assertSame(3000.0, $result->vehicles[1]->getMaxWeightKg());
          // Moto
          self::assertSame(30.0, $result->vehicles[2]->getMaxWeightKg());
      }

      public function testDriversHaveDriverRole(): void
      {
          $result = $this->builder->buildScenario();

          foreach ($result->drivers as $driver) {
              self::assertContains('ROLE_DRIVER', $driver->getRoles());
          }
      }

      public function testShipmentsHaveValidCoordinates(): void
      {
          $result = $this->builder->buildScenario(shipmentCount: 40);

          foreach ($result->shipments as $shipment) {
              self::assertNotNull($shipment->getLatitude());
              self::assertNotNull($shipment->getLongitude());
              self::assertGreaterThan(40.0, $shipment->getLatitude());
              self::assertLessThan(41.0, $shipment->getLatitude());
              self::assertGreaterThan(-4.0, $shipment->getLongitude());
              self::assertLessThan(-3.0, $shipment->getLongitude());
          }
      }

      public function testShipmentsHaveMixedPriorities(): void
      {
          $result = $this->builder->buildScenario(shipmentCount: 40);

          $priorities = array_map(fn (Shipment $s) => $s->getPriority(), $result->shipments);
          $uniquePriorities = array_unique($priorities);
          self::assertGreaterThan(1, count($uniquePriorities), 'Shipments should have mixed priorities');
      }

      public function testConfigurableShipmentCount(): void
      {
          $result10 = $this->builder->buildScenario(shipmentCount: 10);
          $result30 = $this->builder->buildScenario(shipmentCount: 30);

          self::assertCount(10, $result10->shipments);
          self::assertCount(30, $result30->shipments);
      }
  }
  ```

  **Run:** `cd backend && php vendor/bin/phpunit tests/Unit/Service/DemoScenarioBuilderTest.php`
  **Expected:** FAIL — class `DemoScenarioBuilder` does not exist

- [ ] **1b. Implement DemoScenarioBuilder**

  **File:** `backend/src/Service/DemoScenarioBuilder.php`

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Customer;
  use App\Entity\CustomerLocation;
  use App\Entity\Shipment;
  use App\Entity\User;
  use App\Entity\Vehicle;
  use App\Enum\ShipmentPriority;
  use App\Enum\UserRole;
  use App\Enum\VehicleSkill;
  use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

  final class DemoScenarioBuilder
  {
      // Same 40 Madrid stops as DemoRouteFixture
      private const STOPS = [/* ... copy from DemoRouteFixture ... */];

      private const VEHICLES = [
          ['Furgoneta Madrid #1', 1000.0, 8.0, 50, [VehicleSkill::FRAGILE], 1002],
          ['Camión Refrigerado #1', 3000.0, 20.0, 100, [VehicleSkill::REFRIGERATED, VehicleSkill::HEAVY_LOAD], 1003],
          ['Moto Express #1', 30.0, 0.5, 5, [VehicleSkill::PEDESTRIAN_ACCESS], 1004],
      ];

      private const PRIORITIES = [
          ShipmentPriority::CRITICAL,   // 10%
          ShipmentPriority::HIGH,       // 20%
          ShipmentPriority::NORMAL,     // 50%
          ShipmentPriority::LOW,        // 20%
      ];

      public function __construct(
          private readonly UserPasswordHasherInterface $passwordHasher,
      ) {}

      public function buildScenario(int $shipmentCount = 40): DemoScenarioResult
      {
          $customer = $this->createCustomer();
          $warehouse = $this->createWarehouse($customer);
          $vehicles = $this->createVehicles();
          $drivers = $this->createDrivers($customer);
          $shipments = $this->createShipments($customer, $shipmentCount);

          return new DemoScenarioResult($customer, $warehouse, $vehicles, $drivers, $shipments);
      }

      // Private methods for each entity type...
  }
  ```

  **File:** `backend/src/Service/DemoScenarioResult.php`

  ```php
  <?php
  declare(strict_types=1);

  namespace App\Service;

  use App\Entity\Customer;
  use App\Entity\CustomerLocation;
  use App\Entity\Shipment;
  use App\Entity\User;
  use App\Entity\Vehicle;

  final readonly class DemoScenarioResult
  {
      public function __construct(
          public Customer $customer,
          public CustomerLocation $warehouse,
          /** @var Vehicle[] */
          public array $vehicles,
          /** @var User[] */
          public array $drivers,
          /** @var Shipment[] */
          public array $shipments,
      ) {}
  }
  ```

  **Run:** `cd backend && php vendor/bin/phpunit tests/Unit/Service/DemoScenarioBuilderTest.php`
  **Expected:** All tests GREEN

- [ ] **1c. Commit**

  ```bash
  git add backend/src/Service/DemoScenarioBuilder.php backend/src/Service/DemoScenarioResult.php backend/tests/Unit/Service/DemoScenarioBuilderTest.php
  git commit -m "feat: add DemoScenarioBuilder with TDD tests"
  ```

### Task 2: Create DemoSetupCommand (test-first)

- [ ] **2a. Write failing test** — `DemoSetupCommandTest`

  **File:** `backend/tests/Functional/Command/DemoSetupCommandTest.php`

  Test the command via `CommandTester`:
  - Verify command creates entities in DB
  - Verify `--fresh` purges existing demo data
  - Verify output messages

  This is a functional test that needs kernel + database.

  **Run:** `cd backend && php vendor/bin/phpunit tests/Functional/Command/DemoSetupCommandTest.php`
  **Expected:** FAIL — class `DemoSetupCommand` does not exist

- [ ] **2b. Implement DemoSetupCommand**

  **File:** `backend/src/Command/DemoSetupCommand.php`

  Symfony Console command `app:demo:setup` that:
  1. Accepts `--fresh`, `--shipments=40`, `--simulate-gps` options
  2. If `--fresh`: delete demo entities (Customer by name "Logística Express Madrid" + cascade)
  3. Create entities via DemoScenarioBuilder
  4. Persist all entities to DB
  5. Call RoutePlanningService::buildRoutes() with all shipments + vehicles + warehouse
  6. If VROOM unavailable: fallback to round-robin assignment with warning
  7. Mark first route as ACTIVE
  8. If `--simulate-gps`: run SimulateGpsCommand as sub-command
  9. Output summary table

  **Run:** `cd backend && php vendor/bin/phpunit tests/Functional/Command/DemoSetupCommandTest.php`
  **Expected:** GREEN

- [ ] **2c. Commit**

  ```bash
  git add backend/src/Command/DemoSetupCommand.php backend/tests/Functional/Command/DemoSetupCommandTest.php
  git commit -m "feat: add app:demo:setup command with --fresh and --shipments options"
  ```

### Task 3: Update DemoRouteFixture to use DemoScenarioBuilder

- [ ] **3a. Write test** — verify fixture still works

  **Run:** `cd backend && php vendor/bin/phpunit` (full suite, verify no regressions)

- [ ] **3b. Refactor DemoRouteFixture**

  Replace hardcoded entity creation with `DemoScenarioBuilder::buildScenario()` + persist.
  Keep the fixture working for `doctrine:fixtures:load`.

- [ ] **3c. Run full test suite, commit**

  ```bash
  cd backend && php vendor/bin/phpunit
  git add backend/src/DataFixtures/DemoRouteFixture.php
  git commit -m "refactor: DemoRouteFixture uses DemoScenarioBuilder"
  ```

### Task 4: Verify full flow and push

- [ ] **4a. Run full test suite**

  ```bash
  cd backend && php vendor/bin/phpunit
  make lint
  ```

- [ ] **4b. Push to branch**

  ```bash
  git push -u origin claude/deploy-providers-railway-sTixQ
  ```
