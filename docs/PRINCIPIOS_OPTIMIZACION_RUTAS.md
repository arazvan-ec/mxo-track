# Principios de Optimización de Rutas de Entrega

> Documento evolutivo que define los principios estratégicos de optimización de rutas en MXO Track. Sirve como referencia para el equipo y guía para futuras mejoras del sistema.

---

## 1. Ruta Circular (Farthest-First + Sweep)

**Concepto**: Empezar siempre por el envío más lejano al origen y construir la ruta de vuelta en forma de círculo/bucle hacia el almacén.

**Por qué funciona**:
- Evita el patrón ineficiente de "viajes cortos primero, tramo largo al final" que maximiza el tiempo del conductor alejado del origen
- Al volver en espiral hacia el almacén, el vehículo se descarga progresivamente → menos peso = menos consumo
- Si hay que abortar la ruta (avería, fin de jornada), el conductor ya está más cerca del origen
- Reduce deadhead miles (kilómetros vacíos de retorno)

**Implementación con VROOM**: VROOM genera rutas circulares cuando se define `start` y `end` en el mismo punto (almacén). El principio se aplica verificando que la ruta generada respeta este patrón y ajustando si es necesario.

**Estado**: ✅ Implementado (`VroomRequestMapper.php` — start=end=origen)

---

## 2. Cluster-First, Route-Second

**Concepto**: Antes de optimizar el orden de paradas, agrupar los envíos por zona geográfica. Cada vehículo atiende un cluster/zona, y dentro de cada zona se optimiza el recorrido.

**Por qué funciona**:
- Evita que dos vehículos crucen sus rutas → menos km totales de flota
- Las zonas se pueden pre-asignar a conductores que las conocen
- Facilita la estimación de tiempos por zona
- Reduce la complejidad computacional del VRP

**Implementación con VROOM**: VROOM internamente ya hace clustering como parte de su heurística. El principio se aplica como validación post-optimización: verificar que no hay "cruces" entre rutas (dos vehículos visitando la misma zona).

**Estado**: ✅ Implementado (heurística interna de VROOM)

---

## 3. Ventanas Horarias como Restricción Dura

**Concepto**: Las ventanas de entrega del cliente son restricciones inviolables, no sugerencias. El optimizador debe respetar la franja horaria preferida incluso si eso aumenta la distancia total.

**Por qué funciona**:
- La satisfacción del cliente depende más de entregar a la hora prometida que de la eficiencia del conductor
- Los fallos de entrega (cliente ausente fuera de ventana) son más caros que los km extra
- Permite al receptor planificar su disponibilidad

**Implementación**: Se usa `time_windows` en los jobs de VROOM. Los envíos sin ventana horaria son flexibles; los que la tienen son restricción dura. VROOM los marca como `unassigned` si no puede cumplir la ventana → el operador decide.

**Estado**: ✅ Implementado (`VroomRequestMapper.php` L80-95)

---

## 4. Capacidad Tridimensional (Peso + Volumen + Bultos)

**Concepto**: Un vehículo no solo tiene límite de peso — el espacio físico y la cantidad de paquetes también son restricciones. Las tres dimensiones deben validarse simultáneamente.

**Por qué funciona**:
- Un paquete ligero pero voluminoso puede llenar un vehículo antes de alcanzar el límite de peso
- Muchos bultos pequeños pueden ser imprácticos de manejar incluso si caben por peso y volumen
- La validación tridimensional previene sobrecargas que causan devoluciones al almacén

**Dimensiones VROOM**: `capacity = [peso_gramos, volumen_cm³, bultos]`

**Conversiones**:
- Peso: kg × 1000 → gramos
- Volumen: m³ × 1,000,000 → cm³
- Bultos: entero directo

**Estado**: ✅ Implementado (`RouteCapacityValidator.php`)

---

## 5. Tiempo de Servicio Variable

**Concepto**: No todas las entregas tardan lo mismo. Una entrega con firma tarda más que un drop-off en portería. El tiempo de servicio por parada debe ser configurable.

**Tipos de entrega propuestos**:

| Tipo | Tiempo estimado | Descripción |
|------|-----------------|-------------|
| Drop-off sin contacto | 2 min | Dejar en portería/buzón |
| Entrega estándar | 5 min | Entrega en mano básica |
| Entrega con firma/POD | 8 min | Requiere identificación y firma |
| Entrega voluminosa | 15 min | Mueble, electrodoméstico, subir escaleras |

**Estado**: ⚠️ Parcial — Actualmente hardcoded a 300 segundos (5 min) en `VroomRequestMapper.php` L21. Evolucionar para que cada `Shipment` defina su `serviceTime`.

---

## 6. Optimizar Duración Total, No Solo Distancia

**Concepto**: El objetivo de optimización es minimizar la duración total del recorrido, no solo los kilómetros.

**Por qué es correcto**:
- Un camino más corto puede ser más lento (centro urbano vs circunvalación)
- El coste real es el tiempo del conductor + combustible, no solo km
- OSRM proporciona tiempos reales por segmento de carretera basados en el tipo de vía

**Nota importante**: VROOM optimiza por defecto la duración total, no la distancia. Este es el comportamiento correcto para logística de última milla.

**Estado**: ✅ Implementado (comportamiento por defecto de VROOM)

---

## 7. Gestión de Envíos No Asignables

**Concepto**: No forzar un envío que no cabe. Si VROOM marca envíos como `unassigned`, el operador debe poder:

1. Ver qué envíos no se asignaron y por qué
2. Añadir más vehículos
3. Relajar restricciones (ampliar ventana horaria)
4. Programarlos para otro día

**Por qué importa**: Forzar un envío en una ruta sobrecargada causa retrasos en cadena que afectan a todos los envíos posteriores.

**Estado**: ✅ Implementado (`VroomResponseMapper.php` — recolecta unassigned; API devuelve la lista)

---

## 8. Re-optimización Incremental

**Concepto**: Las rutas no son estáticas. Durante el día ocurren cambios (cancelaciones, nuevos envíos urgentes, tráfico). Debe ser posible re-optimizar una ruta en curso sin empezar de cero.

**Flujo**:
1. Medir distancia actual via OSRM
2. Enviar paradas restantes a VROOM como TSP de un solo vehículo
3. VROOM re-ordena las paradas
4. Calcular mejora en % y nueva distancia
5. El operador aprueba o descarta

**Estado**: ✅ Implementado (`RouteOptimizationService.optimizeStopOrder()`)

---

## 9. Retorno al Origen

**Concepto**: Toda ruta debe terminar donde empezó (almacén/base).

**Por qué importa**:
- El vehículo está listo para la siguiente jornada
- Se pueden devolver paquetes no entregados al almacén
- Control de flota al final del día
- Facilita el cálculo real de la jornada laboral

**Implementación**: VROOM vehicles con `start` = `end` = coordenadas del almacén/CustomerLocation.

**Estado**: ✅ Implementado (`VroomRequestMapper.php` L43-46)

---

## 10. Máximo de Paradas por Ruta

**Concepto**: Aunque un vehículo tenga capacidad física, un conductor no puede hacer entregas infinitas en una jornada. Se debe limitar a un máximo operativo.

**Valores de referencia**:
- Ruta urbana densa: 25-35 paradas/día
- Ruta suburbana: 15-25 paradas/día
- Ruta rural/voluminosa: 8-15 paradas/día

**Estado**: ⚠️ Parcial — Parámetro `maxStopsPerRoute` existe en `RouteBuilder.php` (default 30) pero no se traslada a VROOM como `max_tasks` por vehículo.

---

## 11. Carga LIFO (Last In, First Out)

**Concepto**: Los paquetes de la primera entrega deben cargarse **al final** (más accesibles). El orden de carga en el vehículo es el inverso al orden de entrega.

**Por qué funciona**:
- El conductor no tiene que descargar y recargar paquetes para acceder a los del fondo
- Reduce el tiempo de servicio por parada (menos manipulación)
- Minimiza riesgo de daño por manipulación repetida
- Es especialmente crítico en vehículos con acceso trasero único

**Implementación**: Tras generar la ruta optimizada, generar un "manifiesto de carga" con el orden inverso de paradas. La primera parada se carga última, la última parada se carga primera.

**Estado**: 🔲 No implementado — Requiere generar listado de carga ordenado inversamente al itinerario

---

## 12. Aprender de Rutas Históricas

**Concepto**: Las rutas ejecutadas por conductores experimentados contienen conocimiento implícito que los algoritmos no capturan: calles cortadas, dificultad de aparcamiento, accesos complicados, horarios de carga/descarga de comercios.

**Referencia**: Amazon desarrolló un sistema (MIT partnership) que aprende de rutas reales ejecutadas por conductores para ajustar la optimización algorítmica.

**Aplicación práctica**:
- Registrar la secuencia real de entregas (ya se hace via RouteStop + ShipmentEvent)
- Comparar ruta planificada vs ruta ejecutada
- Identificar desviaciones recurrentes → posibles mejoras al modelo
- Ajustar tiempos de servicio por zona/dirección basándose en datos históricos

**Estado**: 🔲 No implementado — Los datos base ya se capturan, falta el análisis

---

## Principios Avanzados (Fase Futura)

### A. Zonificación Pre-asignada
Dividir el área de cobertura en zonas fijas. Cada zona tiene vehículos/conductores asignados. La optimización opera dentro de cada zona. Útil cuando la flota crece y los conductores se especializan por barrio/municipio. Se puede implementar con clustering previo (DBSCAN/HDBSCAN) que ha demostrado 30-40% reducción en tiempo de planificación.

### B. Peso de Prioridad
Envíos urgentes o de alto valor tienen prioridad en la secuencia. VROOM soporta `priority` en jobs (1-100). Implementar mapeando el nivel de prioridad del `Shipment`.

### C. Skills/Restricciones de Vehículo
No todo vehículo puede entregar todo (frigorífico, carga pesada, acceso restringido a zonas peatonales). VROOM soporta `skills` matching entre vehicles y jobs.

### D. Multi-Depot
Cuando hay múltiples almacenes, cada vehículo parte del más cercano a su zona. VROOM soporta diferentes `start`/`end` por vehículo, lo que permite rutas multi-depot.

### E. Predicción de Tráfico
Integrar datos históricos de tráfico para ajustar los tiempos OSRM según hora del día. OSRM soporta perfiles custom con speed profiles temporales.

### F. Ordenación de Dimensiones de Capacidad
La dimensión más restrictiva debe ir primero en el vector de capacidad VROOM. Si el peso es el factor limitante más frecuente, poner peso como `capacity[0]`. Esto mejora la eficiencia del solver. Actualmente: `[peso, volumen, bultos]` — validar con datos reales cuál es el más restrictivo.

---

## Resumen: Estado Actual del Código

| # | Principio | Estado | Archivo clave |
|---|-----------|--------|---------------|
| 1 | Ruta circular (farthest-first) | ✅ | `VroomRequestMapper.php` |
| 2 | Cluster-first, route-second | ✅ | Heurística interna VROOM |
| 3 | Ventanas horarias | ✅ | `VroomRequestMapper.php` |
| 4 | Capacidad 3D | ✅ | `RouteCapacityValidator.php` |
| 5 | Servicio variable | ⚠️ Hardcoded 300s | `VroomRequestMapper.php` |
| 6 | Optimizar duración | ✅ | VROOM default |
| 7 | Unassigned handling | ✅ | `VroomResponseMapper.php` |
| 8 | Re-optimización | ✅ | `RouteOptimizationService.php` |
| 9 | Retorno al origen | ✅ | `VroomRequestMapper.php` |
| 10 | Max paradas | ⚠️ Parcial | `RouteBuilder.php` |
| 11 | Carga LIFO | 🔲 No implementado | — |
| 12 | Aprender de rutas históricas | 🔲 No implementado | ShipmentEvent (datos base) |

**Stack tecnológico**: VROOM (VRP solver) + OSRM (routing engine) + PostgreSQL + Symfony 7.4
