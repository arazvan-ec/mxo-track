# Customer Locations + Route Origin Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow customers to have multiple named locations (warehouses/depots) and use one as the origin point when creating delivery routes, auto-generating a sequence=0 RouteStop.

**Architecture:** New `CustomerLocation` entity (multi-tenant via `CustomerScopedEntityInterface`). `Route` gains an `originLocation` FK. `RouteStop` gains `isOrigin` bool. Admin CRUD for locations nested under customer. Geocoding via client-side Nominatim calls.

**Tech Stack:** Symfony 7.4, Doctrine ORM 3.x, Twig, Tailwind CSS, vanilla JS (Nominatim API)

---

### Task 1: Create `CustomerLocation` Entity

**Files:**
- Create: `backend/src/Entity/CustomerLocation.php`

**Step 1: Create the entity file**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_customer_location_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class CustomerLocation implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(Customer $customer, string $name, string $address)
    {
        $this->customer = $customer;
        $this->name = $name;
        $this->address = $address;
    }

    public function getCustomer(): Customer { return $this->customer; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): void { $this->address = $address; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): void { $this->latitude = $latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): void { $this->longitude = $longitude; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setDefault(bool $isDefault): void { $this->isDefault = $isDefault; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): void { $this->isActive = $isActive; }
}
```

**Step 2: Commit**

```bash
git add backend/src/Entity/CustomerLocation.php
git commit -m "feat: add CustomerLocation entity with multi-tenant support"
```

---

### Task 2: Add `isOrigin` to `RouteStop` Entity

**Files:**
- Modify: `backend/src/Entity/RouteStop.php`

**Step 1: Add `isOrigin` property and getter/setter**

After the existing `$exceptionNotes` property (line 33), add:

```php
    #[ORM\Column]
    private bool $isOrigin = false;
```

Add getter/setter alongside existing ones:

```php
    public function isOrigin(): bool { return $this->isOrigin; }
    public function setOrigin(bool $isOrigin): void { $this->isOrigin = $isOrigin; }
```

**Step 2: Commit**

```bash
git add backend/src/Entity/RouteStop.php
git commit -m "feat: add isOrigin flag to RouteStop entity"
```

---

### Task 3: Add `originLocation` to `Route` Entity

**Files:**
- Modify: `backend/src/Entity/Route.php`

**Step 1: Add `originLocation` property**

After the existing `$customer` property (line 42), add:

```php
    #[ORM\ManyToOne(targetEntity: CustomerLocation::class)]
    #[ORM\JoinColumn(name: 'origin_location_id', nullable: true, onDelete: 'SET NULL')]
    private ?CustomerLocation $originLocation = null;
```

Add getter/setter alongside existing ones:

```php
    public function getOriginLocation(): ?CustomerLocation { return $this->originLocation; }
    public function setOriginLocation(?CustomerLocation $originLocation): void { $this->originLocation = $originLocation; }
```

**Step 2: Commit**

```bash
git add backend/src/Entity/Route.php
git commit -m "feat: add originLocation relationship to Route entity"
```

---

### Task 4: Create Database Migration

**Files:**
- Create: `backend/migrations/Version20260220010000.php`

**Step 1: Write migration**

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_location table, Route.origin_location_id, RouteStop.is_origin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_location (
            id BIGSERIAL NOT NULL,
            public_id UUID NOT NULL,
            customer_id BIGINT NOT NULL,
            name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            latitude DOUBLE PRECISION DEFAULT NULL,
            longitude DOUBLE PRECISION DEFAULT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            PRIMARY KEY (id),
            CONSTRAINT uniq_customer_location_public_id UNIQUE (public_id),
            CONSTRAINT fk_customer_location_customer FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE
        )');

        $this->addSql('ALTER TABLE route_plan ADD COLUMN origin_location_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE route_plan ADD CONSTRAINT fk_route_origin_location FOREIGN KEY (origin_location_id) REFERENCES customer_location (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE route_stop ADD COLUMN is_origin BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE route_stop DROP COLUMN is_origin');

        $this->addSql('ALTER TABLE route_plan DROP CONSTRAINT IF EXISTS fk_route_origin_location');
        $this->addSql('ALTER TABLE route_plan DROP COLUMN origin_location_id');

        $this->addSql('DROP TABLE customer_location');
    }
}
```

**Important:** The `public_id` column type for ULID in PostgreSQL with Doctrine uses `UUID` type. Check existing tables to confirm — if existing entities use a different column type for ULID, match that. Run `\d customer` inside `psql` to check the `public_id` column type on the `customer` table and use the same type.

**Step 2: Run migration inside Docker**

```bash
docker compose -f docker-compose.local.yml exec app php bin/console doctrine:migrations:migrate -n
```

Expected: Migration runs successfully, creating the `customer_location` table and adding columns.

**Step 3: Commit**

```bash
git add backend/migrations/Version20260220010000.php
git commit -m "feat: migration for customer_location table and route origin fields"
```

---

### Task 5: Create `CustomerLocationType` Form

**Files:**
- Create: `backend/src/Form/CustomerLocationType.php`

**Step 1: Create the form type**

```php
<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerLocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'required' => true,
                'attr' => [
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Ej: Almacen Madrid',
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Direccion',
                'required' => true,
                'attr' => [
                    'id' => 'location-address',
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => 'Direccion completa',
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitud',
                'required' => false,
                'scale' => 6,
                'attr' => [
                    'id' => 'location-latitude',
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => '40.416775',
                    'step' => '0.000001',
                ],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitud',
                'required' => false,
                'scale' => 6,
                'attr' => [
                    'id' => 'location-longitude',
                    'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
                    'placeholder' => '-3.703790',
                    'step' => '0.000001',
                ],
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Ubicacion por defecto',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Activa',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add backend/src/Form/CustomerLocationType.php
git commit -m "feat: add CustomerLocationType form"
```

---

### Task 6: Create Customer Location Admin Controller

**Files:**
- Create: `backend/src/Controller/Admin/CustomerLocationAdminController.php`

**Step 1: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Form\CustomerLocationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/customers/{customerPublicId}/locations')]
#[IsGranted('ROLE_ADMIN')]
class CustomerLocationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_customer_locations_index', methods: ['GET'])]
    public function index(string $customerPublicId): Response
    {
        $customer = $this->findCustomer($customerPublicId);

        $locations = $this->em->createQueryBuilder()
            ->select('l')
            ->from(CustomerLocation::class, 'l')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('l.isDefault', 'DESC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/customer/locations/index.html.twig', [
            'customer' => $customer,
            'locations' => $locations,
        ]);
    }

    #[Route('/new', name: 'admin_customer_locations_new', methods: ['GET', 'POST'])]
    public function new(string $customerPublicId, Request $request): Response
    {
        $customer = $this->findCustomer($customerPublicId);

        $form = $this->createForm(CustomerLocationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $location = new CustomerLocation($customer, $data['name'], $data['address']);

            if ($data['latitude'] !== null) {
                $location->setLatitude((float) $data['latitude']);
            }
            if ($data['longitude'] !== null) {
                $location->setLongitude((float) $data['longitude']);
            }
            if ($data['isDefault'] ?? false) {
                $this->clearDefaultForCustomer($customer);
                $location->setDefault(true);
            }
            if (isset($data['isActive'])) {
                $location->setActive((bool) $data['isActive']);
            }

            $this->em->persist($location);
            $this->em->flush();

            $this->addFlash('success', 'Ubicacion creada correctamente.');

            return $this->redirectToRoute('admin_customer_locations_index', [
                'customerPublicId' => $customerPublicId,
            ]);
        }

        return $this->render('admin/customer/locations/form.html.twig', [
            'customer' => $customer,
            'form' => $form,
            'location' => null,
        ]);
    }

    #[Route('/{publicId}/edit', name: 'admin_customer_locations_edit', methods: ['GET', 'POST'])]
    public function edit(string $customerPublicId, string $publicId, Request $request): Response
    {
        $customer = $this->findCustomer($customerPublicId);
        $location = $this->findLocation($publicId, $customer);

        $form = $this->createForm(CustomerLocationType::class, [
            'name' => $location->getName(),
            'address' => $location->getAddress(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'isDefault' => $location->isDefault(),
            'isActive' => $location->isActive(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $location->setName($data['name']);
            $location->setAddress($data['address']);
            $location->setLatitude($data['latitude'] !== null ? (float) $data['latitude'] : null);
            $location->setLongitude($data['longitude'] !== null ? (float) $data['longitude'] : null);
            $location->setActive((bool) ($data['isActive'] ?? true));

            if ($data['isDefault'] ?? false) {
                $this->clearDefaultForCustomer($customer);
                $location->setDefault(true);
            } else {
                $location->setDefault(false);
            }

            $this->em->flush();

            $this->addFlash('success', 'Ubicacion actualizada correctamente.');

            return $this->redirectToRoute('admin_customer_locations_index', [
                'customerPublicId' => $customerPublicId,
            ]);
        }

        return $this->render('admin/customer/locations/form.html.twig', [
            'customer' => $customer,
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route('/{publicId}/delete', name: 'admin_customer_locations_delete', methods: ['POST'])]
    public function delete(string $customerPublicId, string $publicId, Request $request): Response
    {
        $customer = $this->findCustomer($customerPublicId);
        $location = $this->findLocation($publicId, $customer);

        if (!$this->isCsrfTokenValid('delete-location-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_customer_locations_index', [
                'customerPublicId' => $customerPublicId,
            ]);
        }

        $this->em->remove($location);
        $this->em->flush();

        $this->addFlash('success', 'Ubicacion eliminada correctamente.');

        return $this->redirectToRoute('admin_customer_locations_index', [
            'customerPublicId' => $customerPublicId,
        ]);
    }

    private function findCustomer(string $publicId): Customer
    {
        try {
            $customer = $this->em->getRepository(Customer::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
            ]);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Cliente no encontrado.');
        }

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Cliente no encontrado.');
        }

        return $customer;
    }

    private function findLocation(string $publicId, Customer $customer): CustomerLocation
    {
        try {
            $location = $this->em->getRepository(CustomerLocation::class)->findOneBy([
                'publicId' => Ulid::fromString($publicId),
                'customer' => $customer,
            ]);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Ubicacion no encontrada.');
        }

        if (!$location instanceof CustomerLocation) {
            throw $this->createNotFoundException('Ubicacion no encontrada.');
        }

        return $location;
    }

    private function clearDefaultForCustomer(Customer $customer): void
    {
        $this->em->createQueryBuilder()
            ->update(CustomerLocation::class, 'l')
            ->set('l.isDefault', 'false')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->execute();
    }
}
```

**Step 2: Commit**

```bash
git add backend/src/Controller/Admin/CustomerLocationAdminController.php
git commit -m "feat: add CustomerLocation admin controller with CRUD"
```

---

### Task 7: Create Customer Location Templates

**Files:**
- Create: `backend/templates/admin/customer/locations/index.html.twig`
- Create: `backend/templates/admin/customer/locations/form.html.twig`

**Step 1: Create locations index template**

```twig
{% extends 'base.html.twig' %}

{% block title %}Ubicaciones de {{ customer.name }} - Admin{% endblock %}

{% block content %}
    <div class="mb-6">
      <a href="{{ path('admin_customers_index') }}" class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
        &larr; Volver a clientes
      </a>
    </div>

    <div class="sm:flex sm:items-center sm:justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Ubicaciones de {{ customer.name }}</h1>
      <a href="{{ path('admin_customer_locations_new', {customerPublicId: customer.publicIdString}) }}"
         class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
        Nueva ubicacion
      </a>
    </div>

    {% if locations|length > 0 %}
      <div class="overflow-hidden bg-white shadow ring-1 ring-gray-200 sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Direccion</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Coordenadas</th>
              <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Estado</th>
              <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 bg-white">
            {% for location in locations %}
              <tr>
                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                  {{ location.name }}
                  {% if location.default %}
                    <span class="ml-1 inline-flex rounded-full bg-blue-100 px-2 text-xs font-semibold leading-5 text-blue-800">Por defecto</span>
                  {% endif %}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ location.address }}">
                  {{ location.address }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                  {% if location.latitude and location.longitude %}
                    {{ location.latitude|number_format(4) }}, {{ location.longitude|number_format(4) }}
                  {% else %}
                    <span class="text-gray-400">Sin coordenadas</span>
                  {% endif %}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm">
                  {% if location.active %}
                    <span class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-800">Activa</span>
                  {% else %}
                    <span class="inline-flex rounded-full bg-red-100 px-2 text-xs font-semibold leading-5 text-red-800">Inactiva</span>
                  {% endif %}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                  <a href="{{ path('admin_customer_locations_edit', {customerPublicId: customer.publicIdString, publicId: location.publicIdString}) }}"
                     class="text-blue-600 hover:text-blue-800 mr-3">Editar</a>
                  <form method="post"
                        action="{{ path('admin_customer_locations_delete', {customerPublicId: customer.publicIdString, publicId: location.publicIdString}) }}"
                        class="inline"
                        onsubmit="return confirm('Eliminar esta ubicacion?');">
                    <input type="hidden" name="_token" value="{{ csrf_token('delete-location-' ~ location.publicIdString) }}">
                    <button type="submit" class="text-red-600 hover:text-red-800">Eliminar</button>
                  </form>
                </td>
              </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    {% else %}
      <div class="rounded-md bg-gray-100 p-6 text-center text-sm text-gray-500">
        No hay ubicaciones registradas para este cliente.
      </div>
    {% endif %}
{% endblock %}
```

**Step 2: Create locations form template with geocoding button**

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ location ? 'Editar ubicacion' : 'Nueva ubicacion' }} - {{ customer.name }} - Admin{% endblock %}

{% block content %}
    <div class="mb-6">
      <a href="{{ path('admin_customer_locations_index', {customerPublicId: customer.publicIdString}) }}"
         class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
        &larr; Volver a ubicaciones
      </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">
      {{ location ? 'Editar ubicacion' : 'Nueva ubicacion' }} — {{ customer.name }}
    </h1>

    <div class="bg-white shadow sm:rounded-lg">
      {{ form_start(form, {attr: {class: 'space-y-6 p-6', novalidate: 'novalidate'}}) }}

        <div>
          {{ form_label(form.name, null, {label_attr: {class: 'block text-sm font-medium text-gray-700 mb-1'}}) }}
          {{ form_widget(form.name) }}
          {{ form_errors(form.name) }}
        </div>

        <div>
          {{ form_label(form.address, null, {label_attr: {class: 'block text-sm font-medium text-gray-700 mb-1'}}) }}
          <div class="flex gap-2">
            <div class="flex-1">
              {{ form_widget(form.address) }}
            </div>
            <button type="button"
                    id="geocode-btn"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
              Geocodificar
            </button>
          </div>
          {{ form_errors(form.address) }}
          <p id="geocode-status" class="mt-1 text-xs text-gray-500"></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            {{ form_label(form.latitude, null, {label_attr: {class: 'block text-sm font-medium text-gray-700 mb-1'}}) }}
            {{ form_widget(form.latitude) }}
            {{ form_errors(form.latitude) }}
          </div>
          <div>
            {{ form_label(form.longitude, null, {label_attr: {class: 'block text-sm font-medium text-gray-700 mb-1'}}) }}
            {{ form_widget(form.longitude) }}
            {{ form_errors(form.longitude) }}
          </div>
        </div>

        <div class="flex items-center gap-6">
          <div class="flex items-center">
            {{ form_widget(form.isDefault, {attr: {class: 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500'}}) }}
            {{ form_label(form.isDefault, null, {label_attr: {class: 'ml-2 block text-sm text-gray-700'}}) }}
          </div>
          <div class="flex items-center">
            {{ form_widget(form.isActive, {attr: {class: 'h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500'}}) }}
            {{ form_label(form.isActive, null, {label_attr: {class: 'ml-2 block text-sm text-gray-700'}}) }}
          </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
          <a href="{{ path('admin_customer_locations_index', {customerPublicId: customer.publicIdString}) }}"
             class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancelar
          </a>
          <button type="submit"
                  class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
            {{ location ? 'Guardar cambios' : 'Crear ubicacion' }}
          </button>
        </div>

      {{ form_end(form) }}
    </div>

    <script>
    document.getElementById('geocode-btn').addEventListener('click', async function() {
        const addressInput = document.getElementById('location-address');
        const latInput = document.getElementById('location-latitude');
        const lngInput = document.getElementById('location-longitude');
        const status = document.getElementById('geocode-status');
        const address = addressInput.value.trim();

        if (!address) {
            status.textContent = 'Introduce una direccion primero.';
            status.className = 'mt-1 text-xs text-red-600';
            return;
        }

        status.textContent = 'Buscando coordenadas...';
        status.className = 'mt-1 text-xs text-blue-600';

        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
                    q: address,
                    format: 'json',
                    limit: '1',
                }),
                { headers: { 'Accept': 'application/json' } }
            );

            const results = await response.json();

            if (results.length > 0) {
                latInput.value = parseFloat(results[0].lat).toFixed(6);
                lngInput.value = parseFloat(results[0].lon).toFixed(6);
                status.textContent = 'Coordenadas encontradas: ' + results[0].display_name;
                status.className = 'mt-1 text-xs text-green-600';
            } else {
                status.textContent = 'No se encontraron resultados. Introduce las coordenadas manualmente.';
                status.className = 'mt-1 text-xs text-yellow-600';
            }
        } catch (error) {
            status.textContent = 'Error al geocodificar. Introduce las coordenadas manualmente.';
            status.className = 'mt-1 text-xs text-red-600';
        }
    });
    </script>
{% endblock %}
```

**Step 3: Commit**

```bash
git add backend/templates/admin/customer/locations/
git commit -m "feat: add customer location templates with Nominatim geocoding"
```

---

### Task 8: Add "Ubicaciones" Link to Customer Index

**Files:**
- Modify: `backend/templates/admin/customer/index.html.twig`

**Step 1: Add a "Ubicaciones" column and link**

In the table header row, add a column after "Vehiculos":

```html
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Ubicaciones</th>
```

In the table body row, add a cell after the vehiculos cell:

```twig
<td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
  <a href="{{ path('admin_customer_locations_index', {customerPublicId: customer.publicIdString}) }}"
     class="text-blue-600 hover:text-blue-800 hover:underline">
    {{ locationCounts[customer.id] is defined ? locationCounts[customer.id] : 0 }} ubicacion(es)
  </a>
</td>
```

Also add a "Ubicaciones" link in the actions cell:

```twig
<a href="{{ path('admin_customer_locations_index', {customerPublicId: customer.publicIdString}) }}"
   class="text-purple-600 hover:text-purple-800 mr-3">Ubicaciones</a>
```

Update the empty-row `colspan` from `8` to `9`.

**Step 2: Update `CustomerAdminController::index()` to count locations**

In `backend/src/Controller/Admin/CustomerAdminController.php`, add a location count query similar to the vehicle count query. After the existing `$userCounts` block, add:

```php
// Count locations per customer
$locationCounts = [];
if (\count($customers) > 0) {
    $locationCountRows = $this->em->createQueryBuilder()
        ->select('IDENTITY(l.customer) AS customer_id, COUNT(l.id) AS location_count')
        ->from(\App\Entity\CustomerLocation::class, 'l')
        ->where('l.customer IN (:ids)')
        ->setParameter('ids', $customerIds)
        ->groupBy('l.customer')
        ->getQuery()
        ->getArrayResult();

    foreach ($locationCountRows as $row) {
        $locationCounts[$row['customer_id']] = (int) $row['location_count'];
    }
}
```

Add `'locationCounts' => $locationCounts` to the `render()` call.

**Step 3: Commit**

```bash
git add backend/templates/admin/customer/index.html.twig backend/src/Controller/Admin/CustomerAdminController.php
git commit -m "feat: show location counts and links in customer admin index"
```

---

### Task 9: Add `originLocation` Selector to Route Form

**Files:**
- Modify: `backend/src/Form/RouteType.php`
- Modify: `backend/templates/admin/route/form.html.twig`

**Step 1: Add `originLocation` field to RouteType**

Add this import at the top:

```php
use App\Entity\CustomerLocation;
```

Add this field after the `customer` field in `buildForm()`:

```php
->add('originLocation', EntityType::class, [
    'class' => CustomerLocation::class,
    'choice_label' => 'name',
    'required' => false,
    'label' => 'Ubicacion de origen',
    'placeholder' => 'Seleccionar origen...',
    'query_builder' => function (EntityRepository $er): QueryBuilder {
        return $er->createQueryBuilder('l')
            ->where('l.isActive = true')
            ->orderBy('l.name', 'ASC');
    },
    'attr' => [
        'class' => 'w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm',
    ],
])
```

**Note:** This initially loads all active locations. Filtering by customer dynamically (when customer dropdown changes) would require JS — skip for now, all locations are shown with their names which include the customer context.

**Step 2: Add the field to the route form template**

In `backend/templates/admin/route/form.html.twig`, inside the grid after the customer field, add:

```twig
<div>
  {{ form_label(form.originLocation, null, {'label_attr': {'class': 'block text-sm font-medium text-gray-700 mb-1'}}) }}
  {{ form_widget(form.originLocation) }}
  {{ form_errors(form.originLocation) }}
</div>
```

**Step 3: Commit**

```bash
git add backend/src/Form/RouteType.php backend/templates/admin/route/form.html.twig
git commit -m "feat: add originLocation selector to route form"
```

---

### Task 10: Auto-Create Origin RouteStop on Route Save

**Files:**
- Modify: `backend/src/Controller/Admin/RouteAdminController.php`

**Step 1: Add origin stop creation logic**

In the `new()` method, after `$this->em->persist($route)` and before `$this->em->flush()`, add:

```php
$this->createOriginStopIfNeeded($route);
```

In the `edit()` method, after the `$form->isValid()` check and before `$this->em->flush()`, add:

```php
$this->syncOriginStop($route);
```

**Step 2: Add private helper methods at the bottom of the controller**

```php
private function createOriginStopIfNeeded(Route $route): void
{
    $origin = $route->getOriginLocation();
    if ($origin === null) {
        return;
    }

    $stop = new RouteStop($route, 0, $origin->getAddress());
    $stop->setLatitude($origin->getLatitude());
    $stop->setLongitude($origin->getLongitude());
    $stop->setOrigin(true);
    $this->em->persist($stop);
}

private function syncOriginStop(Route $route): void
{
    // Remove existing origin stop
    $existingOrigin = $this->em->createQueryBuilder()
        ->select('s')
        ->from(RouteStop::class, 's')
        ->where('s.route = :route')
        ->andWhere('s.isOrigin = true')
        ->setParameter('route', $route)
        ->getQuery()
        ->getOneOrNullResult();

    if ($existingOrigin !== null) {
        $this->em->remove($existingOrigin);
    }

    // Create new one if origin location is set
    $this->createOriginStopIfNeeded($route);
}
```

**Step 3: Commit**

```bash
git add backend/src/Controller/Admin/RouteAdminController.php
git commit -m "feat: auto-create origin RouteStop when route has originLocation"
```

---

### Task 11: Update Route Stop Table UI to Show Origin Differently

**Files:**
- Modify: `backend/templates/admin/route/form.html.twig`

**Step 1: Update the stops table to highlight origin stops**

In the stops table body loop, wrap the sequence cell to show a depot icon for origin stops:

Replace the sequence cell with:

```twig
<td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
  {% if stop.origin %}
    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 text-xs font-semibold leading-5 text-purple-800">Origen</span>
  {% else %}
    {{ stop.sequence }}
  {% endif %}
</td>
```

Also, hide the delete button for origin stops (they are managed automatically):

Replace the delete form in the actions cell with:

```twig
<td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium">
  {% if not stop.origin %}
    <form method="post"
          action="{{ path('admin_routes_stop_delete', {publicId: route.publicIdString, stopPublicId: stop.publicIdString}) }}"
          class="inline"
          onsubmit="return confirm('Eliminar esta parada?');">
      <input type="hidden" name="_token" value="{{ csrf_token('delete-stop-' ~ stop.publicIdString) }}">
      <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
    </form>
  {% else %}
    <span class="text-xs text-gray-400">Automatico</span>
  {% endif %}
</td>
```

**Step 2: Commit**

```bash
git add backend/templates/admin/route/form.html.twig
git commit -m "feat: display origin stops differently in route admin"
```

---

### Task 12: Update Fixtures

**Files:**
- Modify: `backend/src/DataFixtures/DemoRouteFixture.php`

**Step 1: Add CustomerLocation import and creation**

Add import at top:

```php
use App\Entity\CustomerLocation;
```

After creating the customer and before creating the route, add:

```php
$warehouse = new CustomerLocation($customer, 'Almacen Villaverde', 'Poligono Industrial de Villaverde, Madrid');
$warehouse->setLatitude(40.3460);
$warehouse->setLongitude(-3.6970);
$warehouse->setDefault(true);
$manager->persist($warehouse);
```

After creating the route (after `$route->setCustomer($customer)`), add:

```php
$route->setOriginLocation($warehouse);
```

After persisting the route, add the origin stop:

```php
$originStop = new RouteStop($route, 0, $warehouse->getAddress());
$originStop->setLatitude($warehouse->getLatitude());
$originStop->setLongitude($warehouse->getLongitude());
$originStop->setOrigin(true);
$manager->persist($originStop);
```

**Step 2: Commit**

```bash
git add backend/src/DataFixtures/DemoRouteFixture.php
git commit -m "feat: add warehouse location and origin stop to demo fixtures"
```

---

### Task 13: Verify End-to-End

**Step 1: Run migration and reload fixtures inside Docker**

```bash
docker compose -f docker-compose.local.yml exec app bash -c \
  "php bin/console doctrine:migrations:migrate -n && php bin/console doctrine:fixtures:load -n"
```

Expected: No errors. Database has `customer_location` table with one row, route has origin stop at sequence 0.

**Step 2: Start the PHP server and verify in browser**

```bash
docker compose -f docker-compose.local.yml exec app php -S 0.0.0.0:8000 -t public
```

Verify:
1. Go to `/admin/customers` — should see "Ubicaciones" column with count "1"
2. Click "Ubicaciones" — should see "Almacen Villaverde" with coords
3. Click "Editar" on the location — form should show fields, geocode button should work
4. Go to `/admin/routes` — edit the demo route, should see "Ubicacion de origen" dropdown with "Almacen Villaverde" selected
5. In the stops table, first row should show purple "Origen" badge instead of sequence number

**Step 3: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: adjustments from end-to-end verification"
```
