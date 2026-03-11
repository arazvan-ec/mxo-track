# CSV Import Format

Format for bulk shipment import via `ShipmentCsvImporter`.

## Columns (15)

| # | Column | Required | Type | Example |
|---|--------|----------|------|---------|
| 1 | `reference` | Yes | string (unique) | `SHP-0001` |
| 2 | `recipient_name` | No | string | `María García` |
| 3 | `address` | No | string | `Calle Gran Vía 1, 28013 Madrid` |
| 4 | `latitude` | Yes* | float (-90..90) | `40.4200` |
| 5 | `longitude` | Yes* | float (-180..180) | `-3.7025` |
| 6 | `phone` | No | string | `612345001` |
| 7 | `notes` | No | string | `Entregar en portería` |
| 8 | `service_type` | No | enum | `DELIVERY` |
| 9 | `weight_kg` | No | float | `5.2` |
| 10 | `volume_m3` | No | float | `0.03` |
| 11 | `num_parcels` | No | int (default: 1) | `2` |
| 12 | `ean` | No | string | `8412345000001` |
| 13 | `description` | No | string | `Paquete documentos` |
| 14 | `service_time_seconds` | No | int | `300` |
| 15 | `priority` | No | enum | `normal` |

*Coordinates required for route optimization.

## Enum Values

**service_type:** `DELIVERY`, `DELIVERY_AND_PICKUP`, `RETURN`

**priority:** `low`, `normal`, `high`, `urgent`, `critical`

## Usage

```bash
# Via ShipmentCsvImporter service (programmatic)
$importer->import('/path/to/file.csv', $customer);

# Via smoke test command
php bin/console app:csv:smoke-import --customer-id=<ULID> --file=/path/to/file.csv

# Via demo setup (imports docs/demo/envios-madrid.csv)
php bin/console app:demo:setup --import-csv
```

## Demo CSV

`docs/demo/envios-madrid.csv` — 55 shipments across Madrid with:
- Mixed priorities (10% critical, 15% high, 50% normal, 15% low, 10% urgent)
- Mixed service types (delivery, pickup, return)
- Varied weights (0.8 kg to 180 kg) and volumes
- Realistic addresses with GPS coordinates
- Spanish recipient names and delivery notes
