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

Implementados y wireados en `DomainServiceProvider` (los 3 primeros) o ya escritos pendientes de wiring (los 2 de SUNAT, faltan certificados/credenciales reales):

```php
interface RepositorioComprobante { guardar(Comprobante $c): void; buscarPorId(string $empresaId, string $id): ?Comprobante; }
interface AsignadorCorrelativo   { asignar(string $empresaId, TipoComprobante $t, Serie $s): NumeroComprobante; }
interface GestorTransacciones    { ejecutar(Closure $operacion): mixed; } // Closure, no callable — ver nota abajo
interface GeneradorId            { nuevo(): string; }
interface GeneradorXmlFirmado    { generar(Comprobante $c, DatosEmisor $e, CertificadoDigital $cert): string; }
interface EnviadorComprobanteElectronico { enviar(Comprobante $c, string $xmlFirmado): ResultadoEnvio; }
```

Pendientes (no bloquean Factura, pero están documentados desde el inicio): `AlmacenPrivado`, `GeneradorRepresentacionImpresa`, `NotificadorWebhook`.

**Cambios respecto a la primera versión de este documento**, aprendidos al integrar Greenter de verdad (ver [05_SUNAT.md](05_SUNAT.md)):
- `GeneradorXmlComprobante` + `FirmadorXml` (dos puertos separados) se **fusionaron** en `GeneradorXmlFirmado`: `Greenter\See::getXmlSigned()` genera y firma como una sola operación atómica, y forzar la separación no aportaba nada real.
- `GestorTransacciones::ejecutar()` recibe `Closure`, no `callable` — es lo que `DB::transaction()` espera por debajo y evita perder el tipo genérico de retorno en Larastan.
- Nuevos Value Objects de soporte: `CertificadoDigital` (contenido PEM descifrado, transitorio en memoria), `DatosEmisor` (Domain/Empresa — RUC/razón social/dirección del emisor, separado de Comprobante porque el emisor es responsabilidad de Empresa, no del agregado Comprobante), `TotalesComprobante` (desglose completo: gravada/exonerada/inafecta/gratuita/IGV/descuentos/total, no solo el total), `ResultadoEnvio` (distingue aceptado/aceptado-con-observaciones/rechazado/error-técnico).

Implementaciones en `modules/Facturacion/Infrastructure/*`. El dominio nunca instancia una implementación concreta directamente.
