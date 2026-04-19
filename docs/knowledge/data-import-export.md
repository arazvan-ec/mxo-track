# Data Import / Export

**Última actualización:** 2026-04-19
**Estado:** Vigente
**Consultar cuando:** Trabajes con la importación CSV de envíos, el analizador de calidad
(`CsvQualityAnalyzer`), el tracking de ejecuciones (`CsvImportRun`), o cualquier endpoint
que produzca CSV (contabilidad, reportes, posiciones de vehículos). Si vas a añadir una
nueva columna al CSV de import, este es el primer archivo a leer.

## Overview

Two bulk pipelines:

- **Import:** CSV → quality analysis → row-by-row persist → `CsvImportRun` tracking
  record → domain event `ShipmentsImported` → redirect with CTA "Crear Rutas".
- **Export:** Streamed CSV responses (`StreamedResponse` + `fputcsv`) for accounting,
  reports (deliveries / drivers / customer) and raw vehicle positions. No export queue
  — all exports are synchronous.

All CSV I/O uses the default PHP `fgetcsv` / `fputcsv` settings: comma delimiter (`,`),
double-quote enclosure (`"`), UTF-8 charset. No BOM is written and no alternate delimiter
is supported.

## Import Flow — Shipments CSV

Entry point: `AdminShipmentController::import()` at `POST /admin/shipments/import`
(CSRF token `import-shipments`, ROLE_ADMIN only).

```
Form POST (multipart/form-data)
   │   customer_id (ULID)  +  csv_file (UploadedFile)
   ▼
ShipmentCsvImporter::import($csvPath, $customer)
   │   1. fopen + fgetcsv, skip header, collect $dataRows
   │   2. CsvQualityAnalyzer::analyze($dataRows)  → CsvQualityReport
   │   3. For each row:
   │        - trim reference → skip if empty (++errors)
   │        - SELECT existing by reference → skip if found (++skipped)
   │        - build Shipment + Parcels + ShipmentEvent(CREATED, source=csv_import)
   │   4. ImportRunTracker::track() → persists CsvImportRun
   │   5. Back-link every created Shipment to the run (setCsvImportRun)
   │   6. EntityManager::flush()  (single transaction)
   ▼
Controller dispatches ShipmentsImported domain event + flash messages
   ▼
Redirect to admin_shipments_import with flash 'import_run_id' → CTA "Crear Rutas"
links to /app/admin/route-planner?import_id={ULID}
```

**Key invariant:** flush runs once at the end. A single fatal exception during
persistence reverts the entire import (Doctrine unit of work not committed). Partial
commit is impossible by design.

## CSV Schema — Shipment Import

Source of truth: `ShipmentCsvImporter::EXPECTED_COLUMNS` (15 columns, positional).
Header row is required but column **names** are ignored — only the **order** matters.
Rows shorter than 15 fields are padded with empty strings (`array_pad`).

| # | Column | Required | Type | Validation / Default |
|---|---|---|---|---|
| 0 | `reference` | **yes** | string | non-empty; duplicates skipped silently |
| 1 | `recipient_name` | optional | string | trimmed |
| 2 | `address` | optional | string | trimmed |
| 3 | `latitude` | optional | float | FILTER_VALIDATE_FLOAT, range `[-90, 90]` |
| 4 | `longitude` | optional | float | FILTER_VALIDATE_FLOAT, range `[-180, 180]` |
| 5 | `phone` | optional | string | — |
| 6 | `notes` | optional | string | — |
| 7 | `service_type` | optional | enum | `ServiceType::tryFrom(strtoupper(...))` |
| 8 | `weight_kg` | optional | float | > 0 to be applied |
| 9 | `volume_m3` | optional | float | > 0 to be applied |
| 10 | `num_parcels` | optional | int | > 0 required, defaults to `1` |
| 11 | `ean` | optional | string | assigned to every parcel |
| 12 | `description` | optional | string | assigned to every parcel |
| 13 | `service_time_seconds` | optional | int | > 0; no default in importer (entity default applies) |
| 14 | `priority` | optional | enum | matched case-insensitively against `ShipmentPriority::cases()` |

**Parcel expansion:** when `num_parcels > 1`, weight and volume are split evenly across
N `Parcel` rows (`weight / n`, `volume / n`). Default fallbacks when weight/volume absent:
`0.1 kg`, `0.001 m³` per parcel.

## CSV Quality Analyzer

`CsvQualityAnalyzer::analyze(array $rows): CsvQualityReport` runs before the row-by-row
persist. It pads each row to 7 columns — it only validates the first seven fields
(reference through phone). Score starts at 100 and deducts per warning:

| Severity | Deduction | Examples |
|---|---|---|
| `error` | 5 pts | empty `reference`, empty `recipient_name`, empty `address`, duplicate reference (cites first-seen row), non-numeric lat/lng |
| `warning` | 2 pts | lat outside `[35, 44]` (Spain box), lng outside `[-10, 5]`, phone < 9 digits, address < 10 chars |
| `info` | 0.5 pts | address without any digit (likely missing street number) |

Constants live at the top of `CsvQualityAnalyzer.php`: `LAT_MIN/MAX`, `LNG_MIN/MAX`,
`MIN_PHONE_DIGITS=9`, `MIN_ADDRESS_LENGTH=10`. Statistical outlier constants
(`MIN_ROWS_FOR_OUTLIERS=10`, `OUTLIER_THRESHOLD=3.0`) are declared but **not yet wired**
to any check — reserved for future weight/volume outlier detection.

**Important:** the score is informational only. `ShipmentCsvImporter` does NOT abort on
a low score — it persists whatever rows have a non-empty reference. The score is stored
on `CsvImportRun.qualityScore` for operator review.

## CsvImportRun Entity

`src/Entity/CsvImportRun.php` — implements `CustomerScopedEntityInterface` (subject to
the `CustomerTenantFilter`). Columns: `id`, `public_id` (ULID via `PublicIdTrait`),
`customer_id` (FK, `ON DELETE CASCADE`), `created_count`, `skipped_count`,
`quality_score` (nullable), `created_at`.

One `CsvImportRun` is created per `ShipmentCsvImporter::import()` invocation, **even if
`created_count == 0`** — this preserves audit trail for failed / all-duplicate imports.
Created shipments back-link to their run via `Shipment::setCsvImportRun()`; that FK is
how the route planner finds "envíos recién importados" (via `?import_id=ULID` query param).

The import UI at `/admin/shipments/import` lists the 10 most recent runs with customer
name, created/skipped counts, and timestamp.

## Export Surface

All exports are synchronous `StreamedResponse` + `fputcsv('php://output' | 'php://memory', ...)`.
No queue, no background job. Content-Type is `text/csv; charset=utf-8`.

| Endpoint | Route name | Roles | Rows | Notes |
|---|---|---|---|---|
| `GET /admin/billing/export/customer?customer={ULID}&from=&to=&format=csv` | `admin_accounting_export` | ROLE_OPERATOR (+ tenant check: non-admins must own customer) | one per shipment | Header: `Fecha, Referencia, Destinatario, Tipo Servicio, Estado, Peso (kg)`. Default range: first-of-month → today. Service: `AccountingExportService::exportCsv()` — raw SQL via `Connection::fetchAllAssociative`, status derived from latest `shipment_event` |
| `GET /admin/billing/export.csv?from=&to=` | `admin_billing_export` | per `BillingController` | one per customer | Header: `Cliente, Envios, Entregados, Excepciones, Facturables, Km Ahorrados, Tiempo Ahorrado (min), Ahorro %, Rutas con Metricas`. Aggregates via `BillingService::getCustomerSummary()` |
| `GET /admin/reports/export/deliveries.csv` | `admin_reports_export_deliveries` | admin report | multi-section | Sections: resumen → por transportista → por cliente. Blank `fputcsv([])` separators |
| `GET /admin/reports/export/drivers.csv` | `admin_reports_export_drivers` | admin report | one per driver | Header: `Nombre, Email, Rutas completadas, Entregas, Excepciones, Tasa de exito (%)` |
| `GET /customer/report/export.csv?from=&to=` | customer export | ROLE_CUSTOMER | multi-section | Customer-scoped summary + breakdowns |
| `GET /api/vehicles/{publicId}/positions.csv?from=&to=&limit=` | `api_vehicle_positions_csv` | authenticated + `VisibilityScopeService::canAccessVehicle` | up to 5000 (default 2000) | Header: `device_time, server_time, lat, lng, speed, course, accuracy`. Times formatted with `DATE_ATOM` (ISO 8601) |
| `GET /admin/reports/sla/export` | `admin_reports_sla_export` | admin | HTML page | Not CSV — renders `admin/reports/sla_export.html.twig` for browser printing |

**AccountingExportService** uses `php://memory` + `stream_get_contents()` and returns a
string — the controller then wraps it in a `StreamedResponse` closure. Other exporters
write directly to `php://output` inside the closure (more memory-efficient for large
datasets).

## Error Handling

- **Row-level:** empty `reference` increments `errors` counter, row is skipped (not
  persisted). Invalid lat/lng values fail the range filter and are silently dropped for
  that field only — the shipment is still created without coordinates.
- **Duplicate reference:** skipped (`++skipped`); reported as flash warning
  `"%d fila(s) omitida(s) (referencia duplicada)."`
- **Transaction scope:** all persists batch into a single `flush()` at the end. A fatal
  exception (DB constraint violation, OOM) rolls back the **entire** import. There is no
  partial-commit mode and no per-row savepoint.
- **Quality warnings:** collected in `CsvQualityReport.warnings` but **not shown in the
  current UI** — only the integer score is stored on `CsvImportRun`. The DTO is returned
  from `import()` but the controller does not yet render per-row warnings.
- **CSRF:** `import-shipments` token is required on the POST. Invalid token → flash error,
  redirect.
- **Missing file / non-file path:** `ShipmentCsvImporter` returns `{created:0, skipped:0,
  errors:0, quality_report:null, import_run:null}` without raising.

## Key Files

| File | Purpose |
|---|---|
| `backend/src/Service/ShipmentCsvImporter.php` | Row parsing, entity building, run tracking orchestration |
| `backend/src/Service/CsvQualityAnalyzer.php` | Pre-import validation, severity-weighted score |
| `backend/src/Service/ImportRunTracker.php` | Thin helper that instantiates + persists `CsvImportRun` |
| `backend/src/Service/AccountingExportService.php` | Raw-SQL shipment query + CSV string builder for billing export |
| `backend/src/Entity/CsvImportRun.php` | Audit entity; back-linked from `Shipment.csvImportRun` |
| `backend/src/Dto/CsvQualityReport.php` / `CsvQualityWarning.php` | Immutable result DTOs (`SEVERITY_ERROR/WARNING/INFO`) |
| `backend/src/Controller/AdminShipmentController.php` | `/admin/shipments/import` GET+POST endpoint, flash messaging, run history |
| `backend/src/Controller/Admin/AccountingExportController.php` | `/admin/billing/export/customer` — tenant-scoped CSV download |
| `backend/src/Controller/Admin/BillingController.php` | `/admin/billing/export.csv` — aggregate billing per customer |
| `backend/src/Controller/Admin/ReportController.php` | `/admin/reports/export/{deliveries,drivers}.csv` |
| `backend/src/Controller/Customer/CustomerReportController.php` | Customer-scoped CSV report |
| `backend/src/Controller/VehicleApiController.php` | `/api/vehicles/{publicId}/positions.csv` (positions export) |
| `backend/src/Domain/Event/ShipmentsImported.php` | Fired post-import with `importRunId`, `customerId`, counts |
| `backend/templates/admin/shipments_import.html.twig` | Import form UI with Alpine.js drag-drop + schema help + runs table |

## Gotchas

- **No React UI for import.** The import page is Twig + Alpine.js only, not a React SPA
  page. Searching `frontend/src` for "import" will not find it — look in
  `backend/templates/admin/shipments_import.html.twig`. The only React hand-off is the
  post-import CTA that links into `/app/admin/route-planner?import_id=<ULID>`.
- **Header row is ignored by name.** Column order is load-bearing. Renaming a header
  does nothing; reordering columns silently breaks every row. Any schema change MUST
  update `ShipmentCsvImporter::EXPECTED_COLUMNS`, the Twig help table, **and** the
  `data:text/csv` download template in the Twig template (three places).
- **Encoding assumption.** `fgetcsv` reads bytes as-is — if the uploaded file is not UTF-8
  (e.g. Windows-1252 from Excel exports), accented characters land in the DB corrupted.
  There is no current detection or transcoding step.
- **Large files load into memory.** `ShipmentCsvImporter` reads all rows into `$dataRows`
  before processing (needed for the quality pass). For 10k+ row imports, this peaks PHP
  memory; there is no streaming mode and no chunked commit.
- **Concurrent imports are not serialized.** Two simultaneous imports for the same
  customer with overlapping references will race on the `findOneBy(['reference' => ...])`
  check. One will win on the unique constraint and raise; the other's entire flush
  rolls back. There is no row-level lock.
- **`CsvImportRun` respects `CustomerTenantFilter`.** Queries from a non-admin context
  are scoped — don't forget to disable the filter in CLI tools or the run list will be
  empty.
- **`errors` counter is not persisted.** Only `created_count` and `skipped_count` land
  on the run; the error count lives only as a flash message.
- **Quality warnings are discarded.** `CsvQualityReport.warnings` is computed but never
  written to the DB. If you need a per-row error log, that persistence layer does not
  exist yet — only the integer `qualityScore` survives.
- **Accounting export status column reads `shipment_event`.** The latest event type is
  the status ("DELIVERED", "EXCEPTION", etc.); if a shipment has zero events it reports
  `'PENDING'`. Rename an event type and the export silently changes output.
