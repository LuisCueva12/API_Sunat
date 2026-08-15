# Dominio

`modules/Facturacion/Domain` — cero dependencias de Illuminate\*, Greenter\*, HTTP. Enforced por `deptrac` en CI.

## Agregado raíz: Comprobante

Una sola entidad `Comprobante` con discriminador `TipoComprobante`, no cuatro jerarquías paralelas — el 90% del comportamiento (numeración, estados, envío, CDR) es idéntico entre Factura/Boleta/NotaCredito/NotaDebito. Las reglas específicas de cada tipo viven en validadores dedicados (ver §4).

## Value Objects

| VO | Responsabilidad |
|---|---|
| `Ruc` | formato (11 dígitos, prefijo válido) + dígito verificador módulo 11 |
| `DocumentoIdentidad` | tipo (DNI/RUC/CE/pasaporte/sin documento) + número, valida coherencia entre ambos |
| `Dinero` | monto respaldado por **entero (centavos)**, no float ni bcmath (extensión no disponible en este entorno). Redondeo explícito y centralizado en un único punto |
| `Moneda` | PEN / USD, catálogo cerrado |
| `Serie` | 4 caracteres, formato válido según tipo de comprobante |
| `NumeroComprobante` | serie + correlativo como unidad, con su propia representación (`F001-00000125`) |

## Enums

```php
enum TipoComprobante: string
{
    case Factura = 'FACTURA';
    case Boleta = 'BOLETA';
    case NotaCredito = 'NOTA_CREDITO';
    case NotaDebito = 'NOTA_DEBITO';
}

enum EstadoComprobante: string
{
    case Registrado = 'REGISTRADO';
    case Procesando = 'PROCESANDO';
    case Aceptado = 'ACEPTADO';
    case AceptadoConObservaciones = 'ACEPTADO_CON_OBSERVACIONES';
    case Rechazado = 'RECHAZADO';
    case Error = 'ERROR';
}
```

## Máquina de estados

```text
REGISTRADO → PROCESANDO
PROCESANDO → ACEPTADO | ACEPTADO_CON_OBSERVACIONES | RECHAZADO | ERROR
ERROR      → PROCESANDO   (reintento, mismo correlativo, tope de intentos)
```

`ACEPTADO`, `ACEPTADO_CON_OBSERVACIONES`, `RECHAZADO` son terminales. Un reintento (`ReintentarEnvioComprobante`) solo es válido desde `ERROR` — regla dura, no UI: una vez `RECHAZADO`, el correlativo queda quemado, se emite un comprobante nuevo. La transición se valida en un guard de dominio (no `if` sueltos repartidos) y cada transición emite su evento correspondiente automáticamente.

`ANULADO` queda fuera de la V1 (se cubre con Nota de Crédito); el enum admite agregarlo después sin romper lo existente.

## Eventos (`eventos_comprobante`, append-only, fuera del agregado)

```text
comprobante_recibido · comprobante_validado · correlativo_asignado
xml_generado · xml_firmado · envio_iniciado · sunat_respondio
comprobante_aceptado · comprobante_aceptado_con_observaciones
comprobante_rechazado · comprobante_error
reintento_programado · reintento_ejecutado
pdf_generado · webhook_enviado · webhook_fallido
```

Nunca contienen secretos.

## Validadores por tipo

```php
interface ValidadorComprobante
{
    public function validar(Comprobante $comprobante): void; // lanza ComprobanteInvalidoException
}
```

`ValidadorFactura` (exige RUC del receptor) · `ValidadorBoleta` (admite DNI o sin documento según monto) · `ValidadorNotaCredito` / `ValidadorNotaDebito` (exigen `comprobante_referencia_id` + motivo válido del catálogo correspondiente). Seleccionados por factory según `TipoComprobante`.

## Puertos (interfaces hacia Infrastructure)

```php
interface EnviadorComprobanteElectronico { public function enviar(Comprobante $c): ResultadoEnvio; }
interface GeneradorXmlComprobante        { public function generar(Comprobante $c): string; }
interface FirmadorXml                    { public function firmar(string $xml, CertificadoDigital $cert): string; }
interface AlmacenPrivado                 { public function guardar(string $ruta, string $contenido): void; }
interface GeneradorRepresentacionImpresa { public function generar(Comprobante $c): string; }
interface NotificadorWebhook             { public function notificar(Webhook $w, array $payload): void; }
interface AsignadorCorrelativo           { public function asignar(Serie $serie): NumeroComprobante; }
interface RepositorioComprobante         { /* buscar/guardar por id, por empresa+numero */ }
```

Implementaciones en `modules/Facturacion/Infrastructure/*`. El dominio nunca instancia una implementación concreta directamente.
