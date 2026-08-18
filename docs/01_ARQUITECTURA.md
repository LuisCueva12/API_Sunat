# Arquitectura — Plataforma de Facturación Electrónica Perú

Estado: **V1 en construcción**. Este documento es la fuente de verdad de las decisiones de arquitectura. Cualquier cambio a lo aquí definido debe actualizarse en este archivo en el mismo commit que lo implementa.

## 1. Modelo regulatorio

La plataforma opera bajo el modelo **SEE - Del Contribuyente** (Sistema de Emisión Electrónica del propio contribuyente), **no** como OSE (Operador de Servicios Electrónicos).

Esto significa:

- Cada empresa cliente emite comprobantes con **su propio RUC, su propio certificado digital y sus propias credenciales SUNAT (Clave SOL)**.
- La plataforma es software/infraestructura para esa emisión — no es un intermediario autorizado por SUNAT ante terceros.
- No se requiere autorización SUNAT especial para la plataforma en sí. Cada empresa sí debe completar su propio proceso de afiliación/habilitación a facturación electrónica ante SUNAT (proceso de negocio, no técnico) antes de poder emitir en producción.
- **Nunca** se emite usando un certificado o credencial compartido entre empresas.

Si en el futuro se evalúa convertirse en OSE, es un proyecto regulatorio distinto, no una extensión incremental de la V1.

## 2. Capas y regla de dependencia

```text
Interfaces  →  Application  →  Domain
                    ↑
             Infrastructure
```

- **Domain**: entidades, value objects, reglas de negocio, interfaces de puertos. Cero dependencias de Laravel, Eloquent, Redis, Greenter, HTTP.
- **Application**: casos de uso que orquestan Domain. Habla en términos de puertos (interfaces), nunca de implementaciones concretas.
- **Infrastructure**: implementa los puertos del Domain (Eloquent, Redis, Greenter, S3, HTTP saliente para webhooks).
- **Interfaces**: controladores API, FormRequests, Resources, panel (Filament/Livewire), comandos de consola. Delgadas — solo reciben, validan formato, autorizan, invocan un caso de uso y transforman la respuesta.

### Dónde vive el código

```text
app/                    Laravel puro: HTTP, Filament/Livewire, Jobs, Console,
                         Providers, y los modelos Eloquent (persistencia).
modules/Facturacion/     Domain + Application + Infrastructure del núcleo
                         tributario. Namespace Modules\Facturacion.
```

Los modelos Eloquent (`App\Models\Comprobante`, etc.) **no son** la entidad de dominio — son el mecanismo de persistencia y viven en `app/Models` por convención Laravel/Filament. Las entidades de dominio (`Modules\Facturacion\Domain\Comprobante\Comprobante`) son clases independientes. El repositorio de Infrastructure traduce entre ambas.

**Enforcement**: `qossmic/deptrac` valida en CI que nada bajo `Modules\Facturacion\Domain` importe `Illuminate\*` ni `Greenter\*`. La regla no es solo documental.

### Modelado del agregado Comprobante

Una sola entidad `Comprobante` con `TipoComprobante` (enum: FACTURA/BOLETA/NOTA_CREDITO/NOTA_DEBITO), no cuatro jerarquías de clases. El pipeline técnico (numeración → cálculo → XML → firma → envío → CDR) es idéntico entre los 4 tipos. Las reglas específicas de cada tipo viven en validadores dedicados de responsabilidad única:

```text
ValidadorComprobante (interfaz)
├── ValidadorFactura        (exige RUC del receptor)
├── ValidadorBoleta         (admite DNI o sin documento, según monto)
├── ValidadorNotaCredito    (exige comprobante_referencia_id + motivo)
└── ValidadorNotaDebito     (exige comprobante_referencia_id + motivo)
```

seleccionados por factory según `tipo`.

## 3. Módulos

1. **Empresas** — empresa, establecimientos, series.
2. **Credenciales & Certificados** — aislado por sensibilidad; único módulo con permiso de descifrar.
3. **Facturación** (núcleo) — comprobantes, items, tributos, cálculo, numeración, estados, casos de uso `Emitir*`.
4. **Integración SUNAT** — adaptadores Greenter, envío, procesamiento CDR, reintentos.
5. **Documentos** — generación/almacenamiento de XML, CDR, PDF, hashes.
6. **Webhooks** — configuración, firma HMAC, entrega, reintentos.
7. **Identidad/Seguridad** — usuarios panel, API Keys, autenticación/autorización.
8. **Auditoría** — transversal.
9. **Panel Administrativo** (Interfaces) — Filament sobre los mismos casos de uso.
10. **API Pública** (Interfaces) — controladores v1, FormRequests, API Resources, OpenAPI.

## 4. Árbol de carpetas

```text
api-facturacion/
├── app/
│   ├── Console/Commands/
│   ├── Http/{Controllers/Api/V1, Requests/Api/V1, Resources/Api/V1, Middleware}/
│   ├── Filament/{Resources, Pages}/
│   ├── Jobs/  (ProcesarComprobante, EnviarComprobanteSunat,
│   │           ProcesarRespuestaSunat, EnviarWebhook)
│   ├── Models/
│   ├── Observers/
│   ├── Policies/
│   └── Providers/
├── modules/Facturacion/
│   ├── Domain/
│   │   ├── Comprobante/  Empresa/  Numeracion/  ValueObjects/
│   │   ├── Validacion/   Tributario/  Excepciones/  Puertos/
│   ├── Application/
│   │   ├── CasosDeUso/  DTO/  Idempotencia/
│   └── Infrastructure/
│       ├── Persistencia/{Eloquent, Redis}/
│       ├── Sunat/Greenter/
│       ├── Certificados/  Storage/  Pdf/  Webhooks/
├── config/facturacion.php
├── database/{migrations, factories, seeders}/
├── docs/
├── routes/{api.php, api_v1.php, web.php}
├── openapi/openapi.yaml
├── tests/{Unit, Integration, Feature}/
└── composer.json  (psr-4: "Modules\\Facturacion\\": "modules/Facturacion/")
```

## 5. Estados y eventos del comprobante

Ver [02_DOMINIO.md](02_DOMINIO.md) para el detalle de la máquina de estados.

Resumen: 6 estados persistidos (`REGISTRADO`, `PROCESANDO`, `ACEPTADO`, `ACEPTADO_CON_OBSERVACIONES`, `RECHAZADO`, `ERROR`) + un log append-only de eventos técnicos de grano fino (`eventos_comprobante`). Los pasos intermedios del pipeline (XML generado, firmado, enviado) son **eventos**, no estados.

## 6. Flujo end-to-end

```text
SÍNCRONO (API, objetivo <500ms):
POST /api/v1/facturas + Idempotency-Key
  → AutenticarApiKey → RequestId → Idempotencia → FormRequest
  → Caso de uso EmitirFactura (transacción corta):
       ValidadorFactura → CalculadorTributos → AsignadorCorrelativo
       (SELECT...FOR UPDATE) → persiste → REGISTRADO
  → encola ProcesarComprobante → 202 Accepted

ASÍNCRONO (worker):
ProcesarComprobante → PROCESANDO → Mapeador → GeneradorXmlGreenter
  → FirmadorXmlGreenter → ZIP
EnviarComprobanteSunat → ClienteSunatGreenter → SUNAT (beta/producción)
ProcesarRespuestaSunat → ParserCdrGreenter → ACEPTADO |
  ACEPTADO_CON_OBSERVACIONES | RECHAZADO → CDR + PDF + hashes
EnviarWebhook → payload firmado HMAC → reintentos con backoff
  (nunca bloquea ni afecta el estado del comprobante)
```

Error técnico → `ERROR`, reintento con backoff, tope de intentos. Rechazo tributario de SUNAT → `RECHAZADO`, terminal, nunca se reintenta automáticamente (el correlativo queda quemado).

## 7. Endpoints V1

Creación específica por tipo (el contrato de entrada difiere genuinamente); consulta y ciclo de vida genéricos sobre `/comprobantes`. Detalle completo en [04_API.md](04_API.md).

```text
POST /api/v1/facturas | boletas | notas-credito | notas-debito
GET  /api/v1/comprobantes[?tipo=&estado=&serie=&fecha_desde=&fecha_hasta=]
GET  /api/v1/comprobantes/{id}
GET  /api/v1/comprobantes/{id}/estado
GET  /api/v1/comprobantes/{id}/eventos
POST /api/v1/comprobantes/{id}/reintentar
GET  /api/v1/comprobantes/{id}/xml | /cdr | /pdf
GET  /api/v1/empresas/actual
GET  /up  (health check nativo de Laravel)
```

## 8. Paquetes Composer

| Paquete | Para qué |
|---|---|
| `laravel/framework` ^13 | |
| `greenter/greenter` | UBL, firma, envío SOAP, CDR — confirmado v5.3.0, requiere `ext-soap` (ver [05_SUNAT.md](05_SUNAT.md)) |
| `luecano/numero-a-letras` | Monto en letras (Legend obligatorio SUNAT) — Greenter no lo resuelve |
| `filament/filament` ^5.7 | Panel admin |
| `spatie/laravel-permission` | Roles/permisos del panel |
| `barryvdh/laravel-dompdf` | PDF de representación impresa |
| `pestphp/pest` + `pest-plugin-laravel` | Tests |
| `larastan/larastan` | Análisis estático |
| `laravel/pint` | Estilo de código |
| `qossmic/deptrac` | Enforcement de límites de capa |
| `league/flysystem-aws-s3-v3` | Storage S3 (solo producción) |

No se agregan paquetes para UUID v7, rate limiting, cliente HTTP, cifrado — Laravel ya los provee. No se usa Sanctum para API Keys (implementación propia, más simple dado el requisito de prefijo+scopes+empresa_id). No se usa `brick/money` — VO `Dinero` propio basado en enteros (centavos), evita depender de la extensión `bcmath` (no disponible en este entorno) y evita float.

## 9. Qué resuelve Greenter vs. qué construimos nosotros

**Greenter**: modelado UBL, generación XML 2.1, firma XML-DSig, comunicación SOAP con SUNAT, parseo de CDR, catálogos SUNAT como constantes.

**Nosotros**: dominio de negocio y validación, mapeo dominio↔Greenter, numeración concurrente, persistencia/estados/eventos/auditoría, cifrado de certificados/credenciales, orquestación async/reintentos, idempotencia, webhooks, **generación de PDF (Greenter no lo hace)**, selección de certificado/credenciales por empresa, panel, API, auth, rate limiting.

## 10. Plan de fases

| Fase | Objetivo | Criterio de salida |
|---|---|---|
| 0 | Bootstrap, estructura, tooling | `php artisan serve` funcional, CI verde |
| 1 | Dominio + BD core | Correlativo correcto bajo concurrencia simulada, sin SUNAT |
| 2 | Greenter offline | XML firmado válido generado localmente |
| 3 | SUNAT BETA (**Hito Obligatorio**) | Factura completa API→XML→firma→BETA→CDR aceptado, trazabilidad completa |
| 4 | Async real | Código completo (`ProcesarComprobante` Job + `ProcesarEnvioComprobante`, certificados/credenciales cifrados, `AlmacenPrivado`) — pendiente de correr de verdad hasta resolver `ext-soap`/Postgres/Redis |
| 5 | Boleta, NC, ND | Los 4 tipos emiten correctamente en BETA — código de dominio/aplicación completo desde antes de Fase 3 (`EmitirComprobanteBase` compartido), pendiente de probar en BETA junto con Factura |
| 6 | Multiempresa real | Suite de tests de fuga entre tenants en verde |
| 7 | Webhooks + Auditoría + Observabilidad | Eventos disparan webhooks firmados; toda acción sensible auditada |
| 8 | Panel | Alta de empresa y emisión sin tocar la API directamente |
| 9 | Endurecimiento producción | Checklist de lanzamiento cumplido |

## 11. Explícitamente fuera de alcance V1

Guías de remisión, detracciones, retenciones, percepciones, documentos especiales, comunicación de baja SUNAT (se cubre con Nota de Crédito), modelo OSE, portal self-service por empresa, circuit breaker formal, CQRS/Event Sourcing completo, microservicios/Kubernetes/Kafka, multi-región, PLE, carga masiva, app móvil nativa.

## 12. Decisiones abiertas / asunciones activas

| # | Asunción | Impacto si cambia |
|---|---|---|
| 1 | Panel V1 es interno (equipo operador), no portal por empresa | Modelo de autorización de `usuarios` |
| 2 | Boletas se envían individualmente (`sendBill`), no por resumen diario consolidado | Pipeline de Boleta en Fase 5 |
| 3 | Modelo SEE - Del Contribuyente confirmado (no OSE) | Todo el diseño de credenciales/certificados |

## 13. Riesgos abiertos a verificar antes de cada fase relevante

- Formato vigente de series para NC/ND (Fase 5).
- Contenido exacto del QR en representación impresa (antes de construir el PDF, Fase 5+).
- Regla de redondeo tributario exacta esperada por SUNAT.
- Certificado de pruebas real para SUNAT BETA — pendiente de conseguir uno (el certificado autofirmado usado para probar `GeneradorXmlFirmadoGreenter` localmente no sirve ante SUNAT real).
- `ext-soap` sigue sin instalarse en este entorno de desarrollo — bloquea cualquier uso de `Greenter\See` (confirmado: incluso `getXmlSigned()` sin enviar nada falla sin esta extensión, por cómo Greenter instancia su cliente SOAP en el constructor). Comando ya entregado al usuario: `sudo apt install php8.5-soap`.

Resueltos y ya no son riesgos abiertos (ver [05_SUNAT.md](05_SUNAT.md) para el detalle): nombre/versión de `greenter/greenter`, si requiere `ext-soap`, rangos de código CDR (código `0` = aceptado, con notas = observaciones, ≠0 = rechazado — confirmado por la forma de `CdrResponse`), endpoints beta/producción reales.
