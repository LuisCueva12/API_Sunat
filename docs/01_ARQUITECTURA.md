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

1. **Empresas** — empresa, establecimientos, series. Alta implementada: `Empresa`, `SerieEmpresa` (ver [02_DOMINIO.md](02_DOMINIO.md)).
2. **Credenciales & Certificados** — aislado por sensibilidad; único módulo con permiso de descifrar. Alta implementada: `CertificadoEmpresa`, `CredencialSunatEmpresa` — cifrado nativo Laravel (`Crypt`), sin exponerse aún por HTTP (ver [06_SEGURIDAD.md](06_SEGURIDAD.md)).
3. **Facturación** (núcleo) — comprobantes, items, tributos, cálculo, numeración, estados, casos de uso `Emitir*`.
4. **Integración SUNAT** — adaptadores Greenter, envío, procesamiento CDR, reintentos.
5. **Documentos** — generación/almacenamiento de XML, CDR, PDF, hashes.
6. **Webhooks** — configuración, firma HMAC, entrega, reintentos.
7. **Identidad/Seguridad** — usuarios panel, integraciones API (Passport/OAuth2), autenticación/autorización.
8. **Auditoría** — transversal.
9. **Paneles Filament** (Interfaces) — `/admin` (interno, staff) y `/app` (self-service por empresa, implementado 2026-08-19 — ver §14), ambos sobre los mismos casos de uso.
10. **API Pública** (Interfaces) — controladores v1, FormRequests, API Resources, OpenAPI.
11. **Clientes** (`modules/Clientes/`, implementado 2026-08-19 — ver §14) — entidad `Cliente` por empresa, CRUD en ambos paneles y resolución opcional del receptor al emitir. No reemplaza el snapshot desnormalizado en `comprobantes.receptor_*`.
12. **Integraciones** — envuelve Passport (implementado 2026-08-19): `IntegracionApi` con metadata de negocio (nombre, scopes, último uso) sobre el `oauth_client` nativo. Ver §14.

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
│       ├── Sunat/Greenter/  Passport/
│       ├── Certificados/  Storage/  Pdf/  Webhooks/
├── modules/Clientes/   (implementado 2026-08-19 — ver §14, mismo
│                        Domain/Application/Infrastructure que Facturacion)
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
  ACEPTADO_CON_OBSERVACIONES | RECHAZADO → CDR + hashes

Comprobante aceptado → GeneradorRepresentacionImpresa → ticket PDF 80 mm + QR SUNAT
  → almacenamiento privado versionado → descarga autenticada desde Filament
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
| `laravel/passport` ^13 | OAuth2 para integraciones máquina-a-máquina (`client_credentials`) — reemplazó la implementación propia de API Keys el 2026-08-19, ver §14 |
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

No se agregan paquetes para UUID v7, rate limiting, cliente HTTP, cifrado — Laravel ya los provee. No se usa Sanctum en ningún punto: para integraciones API se usa Passport (OAuth2 real, ver §14); para el panel, sesión web estándar de Laravel/Filament — ninguno de los dos casos es "una SPA con tokens ligeros", que es el caso de uso que Sanctum resuelve. No se usa `brick/money` — VO `Dinero` propio basado en enteros (centavos), evita depender de la extensión `bcmath` (no disponible en este entorno) y evita float.

## 9. Qué resuelve Greenter vs. qué construimos nosotros

**Greenter**: modelado UBL, generación XML 2.1, firma XML-DSig, comunicación SOAP con SUNAT, parseo de CDR, catálogos SUNAT como constantes.

**Nosotros**: dominio de negocio y validación, mapeo dominio↔Greenter, numeración concurrente, persistencia/estados/eventos/auditoría, cifrado de certificados/credenciales, orquestación async/reintentos, idempotencia, webhooks, **generación de PDF propia con Dompdf**, selección de certificado/credenciales por empresa, panel, API, auth, rate limiting. Greenter dispone de un paquete de reportes, pero depende de `wkhtmltopdf`; no se usa en este proyecto.

## 10. Plan de fases

| Fase | Objetivo | Criterio de salida |
|---|---|---|
| 0 | Bootstrap, estructura, tooling | `php artisan serve` funcional, CI verde |
| 1 | Dominio + BD core | Correlativo correcto bajo concurrencia simulada, sin SUNAT |
| 2 | Greenter offline | XML firmado válido generado localmente |
| 3 | SUNAT BETA (**Hito Obligatorio**) | Factura completa API→XML→firma→BETA→CDR aceptado, trazabilidad completa |
| 4 | Async real | Código completo y validado con PostgreSQL/Redis/`ext-soap`: `ProcesarComprobante`, `ProcesarEnvioComprobante`, certificados/credenciales cifrados y `AlmacenPrivado` |
| 5 | Boleta, NC, ND | Los 4 tipos emiten correctamente en BETA — código de dominio/aplicación completo desde antes de Fase 3 (`EmitirComprobanteBase` compartido), pendiente de probar en BETA junto con Factura |
| 6 | Multiempresa real | Suite de tests de fuga entre tenants en verde |
| 7 | Webhooks + Auditoría + Observabilidad | Eventos disparan webhooks firmados; toda acción sensible auditada |
| 8 | Panel | Alta de empresa y emisión sin tocar la API directamente |
| 9 | Endurecimiento producción | Checklist de lanzamiento cumplido |

## 11. Explícitamente fuera de alcance V1

Guías de remisión, detracciones, retenciones, percepciones, documentos especiales, comunicación de baja SUNAT (se cubre con Nota de Crédito hasta que se implemente — ver riesgo abierto en §13), modelo OSE, circuit breaker formal, CQRS/Event Sourcing completo, microservicios/Kubernetes/Kafka, multi-región, PLE, carga masiva, app móvil nativa.

**Ya no está fuera de alcance** (decisión tomada 2026-08-19, ver §14): portal self-service por empresa (`/app`, panel Filament separado del interno `/admin`) — pasó de "fuera de alcance V1" a "parte del MVP", pendiente de construir.

## 12. Decisiones abiertas / asunciones activas

| # | Asunción | Impacto si cambia |
|---|---|---|
| 1 | Boletas se envían individualmente (`sendBill`), no por resumen diario consolidado | Pipeline de Boleta en Fase 5 |
| 2 | Modelo SEE - Del Contribuyente confirmado (no OSE) | Todo el diseño de credenciales/certificados |

**Resuelta e implementada 2026-08-19** (ver §14): el panel V1 deja de ser exclusivamente interno — segundo panel self-service por empresa (`/app`) construido y verificado en vivo (aislamiento multiempresa real, no solo visual), además del interno (`/admin`). Los roles granulares dentro de una empresa (`empresa_admin`/`facturador`/`contador`/`empleado`) siguen pendientes — hoy cualquier usuario con `empresa_id` accede a todo lo de su empresa, ver §14 para el roadmap.

## 13. Riesgos abiertos a verificar antes de cada fase relevante

- Formato vigente de series para NC/ND (Fase 5).
- Regla de redondeo tributario exacta esperada por SUNAT.
- Certificado de producción emitido para el RUC del contribuyente — BETA admite el autofirmado generado por `facturacion:preparar-beta`, según el manual oficial de SUNAT.
- `ext-soap`, `ext-redis` e `igbinary` están instalados permanentemente en el entorno de desarrollo; PostgreSQL y Redis operan en los puertos estándar `5432` y `6379`.

Resueltos y ya no son riesgos abiertos (ver [05_SUNAT.md](05_SUNAT.md) para el detalle): nombre/versión de `greenter/greenter`, si requiere `ext-soap`, rangos de código CDR (código `0` = aceptado, con notas = observaciones, ≠0 = rechazado — confirmado por la forma de `CdrResponse`), endpoints beta/producción reales y contenido normativo del QR de la representación impresa.

## 14. Evolución a plataforma SaaS multiempresa (2026-08-19)

Decisión explícita del usuario: el producto deja de ser solo una API interna y se convierte progresivamente en una plataforma SaaS con dos formas de uso — panel web self-service por empresa y API pública para integraciones (POS/ERP/ecommerce). Sigue siendo **monolito modular** (`modules/Facturacion/{Domain,Application,Infrastructure}` + `app/`), no microservicios — la filosofía arquitectónica de este documento no cambia, solo se amplía el alcance del producto.

**Implementado:**
- **Passport/OAuth2** reemplaza por completo el sistema de API Keys propio (`api_keys`, `ApiKeyEmpresa`, `GeneradorClaveApi` — eliminados, no coexisten). Grant `client_credentials` (único habilitado, es el correcto para integraciones máquina-a-máquina sin usuario delegando). `IntegracionApi` (Domain) es metadata de negocio sobre el `oauth_client` nativo de Passport (`owner_type`/`owner_id` → `Empresa`, columna propia `scopes` para restringir por integración, columna propia `ultimo_uso_at`). Ver [02_DOMINIO.md](02_DOMINIO.md), [03_BASE_DATOS.md](03_BASE_DATOS.md) y [06_SEGURIDAD.md](06_SEGURIDAD.md) para el detalle. Verificado en vivo contra SUNAT BETA: token real vía `POST /oauth/token`, factura `ACEPTADO`, y revocación inmediata confirmada (token previamente válido devuelve 401 antes de su expiración natural).
- **Panel `/app` self-service por empresa**: `EmpresaPanelProvider` (Filament, panel id `empresa`), guard `web` compartido con `/admin` pero autorización distinta — `Usuario::canAccessPanel()` exige `empresa_id !== null` para `/app` (vs. `empresa_id === null` + rol `super_admin` para `/admin`; nunca los dos panels a la vez para un mismo usuario). Sin roles granulares todavía (`facturador`/`contador`/etc. quedan para cuando haya una necesidad real de diferenciarlos — no antes). Recursos: `Comprobantes` y `Clientes` (ver más abajo), sin columna/filtro de Empresa — no tiene sentido para un tenant que solo ve lo suyo. `Comprobantes` permite emitir Factura/Boleta sin pasar primero por el maestro: cliente guardado opcional, receptor manual, Boleta sin documento por defecto, ítems dinámicos y totales estimados; la creación invoca los mismos casos de uso de Application que la API. El aislamiento **no es un filtro visual**: cada `Resource::getEloquentQuery()` fuerza `where('empresa_id', $usuario->empresa_id)` en cada consulta — verificado que una empresa no ve los datos de otra ni en el listado ni por URL directa (404, no solo oculto). Lógica de formato/acciones de Comprobantes (`ComprobanteFormato`, `ComprobanteAcciones`, `ComprobanteInfolist` en `app/Filament/Support/`) compartida entre `/admin` y `/app` sin duplicar código, tal como pide la filosofía de este documento.
- **Módulo Clientes** (`modules/Clientes/{Domain,Application,Infrastructure}`, primer módulo top-level genuinamente independiente de `Facturacion` — a diferencia de `Empresa`, que se queda dentro de `Facturacion` por estar acoplado a configuración tributaria específica). `Cliente` (Domain, agregado ligero sin máquina de estados), `TipoDocumentoCliente` (catálogo SUNAT duplicado a propósito de `Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad` — deptrac impide que el Domain de un módulo dependa del Domain de otro, cada bounded context define su propio vocabulario). `CrearCliente`/`ActualizarCliente` (Application) sí reutilizan `RepositorioEmpresa`/`GeneradorId`/`EmpresaInvalidaException` de `Facturacion\Domain` — permitido porque la regla de deptrac es por capa (Application→Domain), no por módulo; sería sobreingeniería reimplementar la validación de empresa activa o la generación de IDs por cada módulo nuevo. CRUD completo en ambos paneles (`/admin` con selector de empresa y columna visible; `/app` con `empresa_id` resuelto del usuario autenticado, nunca de un campo del formulario). `deptrac.yaml` se generalizó (regex `Modules\\[A-Za-z]+\\Domain\\.*` en vez de solo `Facturacion`) para que cualquier módulo nuevo quede cubierto por la misma disciplina de capas sin tocar el archivo de nuevo. El flujo de emisión permite omitir `receptor_razon_social`: la resuelve por empresa, tipo y número de documento desde Clientes; si no encuentra coincidencia devuelve `422 COMPROBANTE_INVALIDO`. Si el emisor envía la razón social explícitamente, esta prevalece. En ambos casos se persiste el snapshot en `comprobantes.receptor_*`, sin FK al maestro.
- **Conceptos frecuentes**: ayuda de captura opcional dentro del formulario Filament, persistida en `conceptos_frecuentes` y siempre acotada por empresa. Reutiliza descripción, valor unitario, unidad e IGV; no representa un producto de dominio y no participa en el comprobante después de copiar esos valores al ítem fiscal.

**Pendiente (roadmap; no forma parte del núcleo actual):**
1. Roles granulares dentro de una empresa cuando exista una necesidad comercial concreta.
2. Escritor general de auditoría para acciones administrativas; la trazabilidad fiscal de comprobantes y envíos sí está activa.
3. Rate limiting diferenciado por tipo de endpoint.
4. Ampliación tributaria más allá de operaciones gravadas al contado.

El envelope estable V1 continúa siendo `{"data"/"error", "meta"}`; no se agrega un booleano redundante.

**Explícitamente descartado incluso en este alcance ampliado:** módulo de productos/inventario, stock, almacenes, kardex, compras, POS, microservicios, Kubernetes, Kafka, múltiples repositorios y CQRS/Event Sourcing completo. La plataforma es un facturador electrónico, no un ERP. Ver también §11.
