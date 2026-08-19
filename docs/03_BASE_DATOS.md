# Base de datos

PostgreSQL. Convenciones generales:

- Identificadores públicos (empresas, comprobantes, series, api_keys, certificados, credenciales, webhooks): `uuid` v7 (`Str::uuid7()` / trait `HasUuids` de Laravel).
- Logs append-only sin referencia externa (`eventos_comprobante`, `entregas_webhook`, `auditorias`): `bigserial` — más barato, el orden de inserción ya es su propósito.
- Dinero: `NUMERIC(12,2)`, nunca `float`/`double`.
- Nada de `SoftDeletes` genérico de Laravel — cada tabla que lo necesita tiene su propio `estado` explícito (`ACTIVA`/`INACTIVA`/`REVOCADA`/etc.), más preciso que un `deleted_at` indiferenciado.
- `empresa_id` es columna obligatoria e indexada en toda tabla que cuelgue de una empresa — aislamiento multiempresa a nivel de aplicación desde el día uno; PostgreSQL Row Level Security se evalúa más adelante como capa adicional, no sustituto.

## Constraints tributarios críticos (no negociables)

```sql
-- Nunca dos comprobantes con el mismo número dentro de una empresa
ALTER TABLE comprobantes ADD CONSTRAINT comprobantes_numero_unico
    UNIQUE (empresa_id, tipo, serie, correlativo);

-- Idempotencia: misma empresa + misma clave = una sola operación
ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_pk
    PRIMARY KEY (empresa_id, clave);

-- Un solo certificado activo por empresa a la vez
CREATE UNIQUE INDEX certificados_un_activo_por_empresa
    ON certificados_digitales (empresa_id) WHERE estado = 'ACTIVO';

-- Series únicas por empresa+tipo
ALTER TABLE series ADD CONSTRAINT series_unicas
    UNIQUE (empresa_id, tipo_comprobante, serie);
```

## Tablas

### empresas

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | PK |
| ruc | char(11) | UNIQUE. Formato + dígito verificador validados en Domain, no solo en BD |
| razon_social | varchar(255) | |
| nombre_comercial | varchar(255) | nullable |
| estado | varchar | ACTIVA / INACTIVA / SUSPENDIDA |
| configuracion | jsonb | solo config no crítica/no consultada (ej. logo para PDF) |
| created_at, updated_at | timestamptz | |

### establecimientos

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| empresa_id | uuid FK | |
| codigo | varchar(4) | código de anexo SUNAT, default '0000' |
| denominacion, ubigeo, direccion | | |
| es_principal | boolean | |

### series

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| empresa_id | uuid FK | |
| establecimiento_id | uuid FK | nullable |
| tipo_comprobante | varchar | FACTURA / BOLETA / NOTA_CREDITO / NOTA_DEBITO |
| serie | varchar(4) | F001, B001, etc. |
| correlativo_actual | bigint | default 0, actualizado vía `SELECT ... FOR UPDATE` |
| activa | boolean | |

### certificados_digitales

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| empresa_id | uuid FK | |
| alias | varchar | |
| contenido_cifrado | text | .pfx cifrado con `Crypt` (AES-256-GCM sobre APP_KEY) |
| password_cifrado | text nullable | Compatibilidad de esquema; las nuevas importaciones normalizan a PEM y no conservan la contraseña |
| huella_sha256 | varchar(64) | fingerprint, permite identificar sin descifrar |
| fecha_emision, fecha_expiracion | date | job diario alerta 30/15/5 días antes de expirar |
| estado | varchar | ACTIVO / VENCIDO / REVOCADO / REEMPLAZADO |

### credenciales_sunat

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| empresa_id | uuid FK | |
| entorno | varchar | BETA / PRODUCCION — UNIQUE (empresa_id, entorno) |
| usuario_sol_cifrado, clave_sol_cifrada | text | cifrados |
| estado | varchar | ACTIVA / INACTIVA |

### api_keys

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| empresa_id | uuid FK | |
| nombre | varchar | |
| prefijo | varchar(12) | parte visible, ej. `sf_live_ab12`, indexado para lookup rápido |
| hash | varchar(255) | UNIQUE, hash de la key completa — la key completa nunca se persiste |
| scopes | jsonb | ej. `["comprobantes:crear","comprobantes:leer"]` |
| ultimo_uso_at, expira_at | timestamptz | nullable |
| estado | varchar | ACTIVA / REVOCADA |

### comprobantes

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | PK, identificador público |
| empresa_id | uuid FK | |
| tipo | varchar | FACTURA / BOLETA / NOTA_CREDITO / NOTA_DEBITO |
| serie, correlativo | varchar(4), bigint | UNIQUE compuesto con empresa_id |
| estado | varchar | ver [02_DOMINIO.md](02_DOMINIO.md) |
| moneda, tipo_cambio | char(3), numeric(10,3) | tipo_cambio solo si moneda ≠ PEN |
| receptor_tipo_documento, receptor_numero_documento, receptor_razon_social, receptor_direccion, receptor_email | | **snapshot** al momento de emitir, no FK a maestro de clientes |
| op_gravada, op_exonerada, op_inafecta, op_gratuita, total_igv, total_descuentos, total | numeric(12,2) | |
| comprobante_referencia_id | uuid FK → comprobantes.id | nullable, solo NC/ND |
| tipo_nota, motivo_nota | varchar | catálogo 09/10 SUNAT |
| snapshot_emisor | jsonb | razón social/dirección de la empresa al momento de emitir |
| idempotency_key, xml_sha256, cdr_sha256 | | |
| intentos_envio, ultimo_error | int, text | |
| api_key_id, creado_por | uuid FK | nullable — quién lo originó |
| created_at, updated_at | timestamptz | |

### comprobante_items

| Campo | Tipo | Notas |
|---|---|---|
| comprobante_id | uuid FK | |
| numero_orden | smallint | UNIQUE con comprobante_id |
| descripcion, unidad_medida | | catálogo 03 SUNAT |
| cantidad, valor_unitario, precio_unitario | numeric(12,3) | |
| tipo_afectacion_igv | varchar(2) | catálogo 07 |
| monto_igv, monto_valor_venta, descuento | numeric(12,2) | |

### comprobante_tributos

Agregado por tipo de tributo a nivel de comprobante (no por línea — eso ya está en `comprobante_items`).

| Campo | Tipo |
|---|---|
| comprobante_id | uuid FK |
| tipo_tributo | varchar (IGV, ICBPER, ISC...) |
| codigo | varchar |
| base_imponible, monto | numeric(12,2) |

### envios_sunat

| Campo | Tipo | Notas |
|---|---|---|
| comprobante_id | uuid FK | |
| intento | int | |
| entorno | varchar | BETA / PRODUCCION |
| codigo_respuesta_sunat, descripcion_respuesta_sunat | varchar, text | |
| notas_sunat | jsonb | observaciones (array) |
| xml_path, cdr_path | varchar | rutas en disco privado |
| duracion_ms | int | |
| error_tecnico | text | |

### eventos_comprobante

| Campo | Tipo | Notas |
|---|---|---|
| id | bigserial | log append-only |
| comprobante_id, empresa_id | uuid FK | empresa_id denormalizado para queries eficientes |
| tipo_evento | varchar | ver [02_DOMINIO.md](02_DOMINIO.md) |
| actor | varchar | `api_key:{id}` / `usuario:{id}` / `system` |
| request_id | varchar | |
| datos | jsonb | nunca secretos |
| created_at | timestamptz | sin updated_at — es inmutable |

### webhooks

| Campo | Tipo |
|---|---|
| id | uuid |
| empresa_id | uuid FK |
| url | varchar(500) |
| secreto_cifrado | text |
| eventos | jsonb |
| estado | ACTIVO / INACTIVO |

### entregas_webhook

| Campo | Tipo |
|---|---|
| id | bigserial |
| webhook_id | FK |
| comprobante_id | FK |
| payload | jsonb |
| intento | int |
| estado | PENDIENTE / ENTREGADO / FALLIDO / AGOTADO |
| http_status | int |
| proximo_intento_at | timestamptz |

### auditorias

| Campo | Tipo |
|---|---|
| id | bigserial |
| empresa_id, usuario_id, api_key_id | FK, nullable |
| accion | varchar (ej. `empresa.actualizada`, `certificado.rotado`) |
| entidad_tipo, entidad_id | varchar |
| ip | inet |
| request_id | varchar |
| datos_previos, datos_nuevos | jsonb |

### idempotency_keys

| Campo | Tipo | Notas |
|---|---|---|
| empresa_id, clave | | **PK compuesta** |
| endpoint, hash_solicitud | varchar | mismo `clave` + distinto `hash_solicitud` → 409 |
| estado | PROCESANDO / COMPLETADO | |
| respuesta_cache | jsonb | se re-sirve tal cual en reintentos del cliente |
| expira_at | timestamptz | |

Postgres es la fuente de verdad (constraint real); Redis es cache delante para el camino feliz, nunca el único lugar donde vive el dedupe.

### usuarios (panel)

| Campo | Tipo | Notas |
|---|---|---|
| id | uuid | |
| empresa_id | uuid FK | **nullable** — asunción activa: panel V1 es interno (staff con visibilidad cross-tenant vía roles), no portal por empresa. Ver docs/01_ARQUITECTURA.md §12 |
| nombre, email | | |
| password | | hash bcrypt estándar de Laravel |

Roles/permisos vía `spatie/laravel-permission`, no tabla propia.
