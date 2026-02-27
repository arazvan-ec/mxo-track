# Requisitos del Cliente — MXO-Track Agent-Native

> Fecha: 2026-02-27
> Estado: Borrador — Pendiente de validación con cliente

---

## 1. Optimización de Rutas

### 1.0 Optimizador de ruta
- Dados unos puntos A, B, C... calcular la ruta óptima
- Estrategia inicial: empezar desde el punto más alejado (punto del RECORRIDO)

### 1.1 Generación de albarán
- Cada entrega debe generar un albarán

### 1.2 Configuración de entrega
- Cada entrega debe tener configuración de **volumen** y **peso**
- Cada vehículo debe tener configuración de **volumen** y **peso** máximo

### 1.3 Cálculos del optimizador
- **Tiempo del Recorrido/Conducción**: la distancia entre cada punto la da la estimación de la ruta
- **Tiempo de la Entrega**: estimación de tiempo por entrega
- **Cálculo de PESO + VOLUMEN**: validar que los productos caben en el camión ANTES de empezar la ruta

---

## 2. Demo para Cliente

### 2.1 Importación CSV
- CSV para importar pedidos/entregas
- Con ese CSV crear X rutas automáticamente
- Cada vehículo puede hacer X entregas
- Poder configurar/ajustar antes de aceptar la ruta

---

## 3. Integración del Cliente con MXO-Track

### 3.1 Conexión API
- El cliente se conecta vía API REST
- El cliente puede entregar un CSV

---

## 4. Inicio de Servicio

### 4.1 Cómo inicia un cliente un servicio
- Llamada telefónica / envío directo
- CSV
- SMS / correo para crear un servicio (ruta de punto A a punto B)

### 4.2 Tipos de servicio
- Paquetería entrega
- Paquetería entrega y recogida
- Paquetería de devolución
- (ver página web para más ejemplos de servicios)

### 4.3 Configuración del servicio
- Número total de bultos (ej: 1/5, 2/5, etc.)
- Cada bulto requiere:
  - **Peso** (obligatorio)
  - **Volumen** (obligatorio)
  - **EAN** (código de barras)
  - **Descripción**

### 4.4 Estados de bultos y entregas
Cada bulto y entrega debe mantener un estado actualizado:
- Cargado
- En ruta
- Entregado
- Ausencia
- (otros estados a definir)

---

## 5. Módulos del Sistema

### 5.1 SGA (Sistema de Gestión de Almacén)
- Entrada de mercancía
- Fecha de entrega estimada para el cliente
- Franja horaria de preferencias de entrega

### 5.2 TMS (Transport Management System)
- Gestión completa de transporte

### 5.3 Costes / ERP
- Métricas de costes:
  - **€/ruta**
  - **€/bulto**

---

## 6. Visibilidad del Cliente B2B

### 6.1 Visualización de estados
- El cliente B2B necesita visualizar los estados de las entregas

### 6.2 Sistema de notificaciones
- Notificación para cada cambio de estado
- El cliente debe estar informado en todo momento
- Proponer fecha y franja horaria de entrega

---

## 7. Optimizador de Clientes

Según la frecuencia de los clientes, optimizar y corregir sus entregas:

| Categoría | Descripción |
|-----------|-------------|
| No frecuentes | Entregas esporádicas |
| Frecuentes | Entregas regulares |
| Muy frecuentes | Entregas diarias o casi diarias |
| Super frecuentes | Múltiples entregas diarias |

- Optimización por franja horaria (mañana/tarde) según frecuencia

---

## 8. Vehículos

- Configuración de volumen y peso máximo por vehículo
- Validación pre-ruta: verificar que los productos caben en el camión

---

## 9. RGU e Isócronas

### 9.1 Conceptos
- **RGU**: Ruta Geográfica Unitaria
- **Isócrona**: Área geográfica accesible en un tiempo determinado

### 9.2 Uso para optimización
- Las isócronas se calculan desde el punto de partida
- Ejemplo: isocrona de 30 minutos → ver qué puntos de entrega están dentro
- Permite agrupar entregas por ventanas de tiempo realistas

---

## 10. Transportistas (Productividad)

- Sistema de tracking de productividad
- Caso de éxito del transportista en cada RGU (isócrona)
- Métricas de rendimiento por zona/tiempo

---

## 11. Dashboard

```
Dashboard
    |
Optimizador
    |
€/ruta
€/bulto
```

---

## 12. Caso de Uso: Cliente Raúl

- El cliente crea **800 pedidos**
- Nosotros tenemos que:
  1. Crear las rutas automáticamente
  2. Informar al cliente del plan de entrega
