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

Implementados y wireados en `DomainServiceProvider`:

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

## Empresa y alta de tenant (`Domain/Empresa`)

Entidades independientes del agregado `Comprobante`, cada una con su propio puerto de repositorio y caso de uso `Crear*` en Application:

```text
Empresa                 activa/inactiva/suspendida. RUC único (índice UNIQUE en BD).
SerieEmpresa             empresa + tipo de comprobante + código (VO Serie) + activa.
                         Distinta del VO ValueObjects\Serie, que solo valida el formato
                         de 4 caracteres — SerieEmpresa es la fila configurada real.
CertificadoEmpresa       empresa + PEM normalizado + huella SHA-256 + vigencia + estado
                         (ACTIVO/VENCIDO/REVOCADO/REEMPLAZADO). Solo un ACTIVO por
                         empresa (índice único parcial en BD); al registrar uno nuevo,
                         el anterior se marca REEMPLAZADO en la misma transacción.
CredencialSunatEmpresa   empresa + entorno (BETA/PRODUCCION) + usuario/clave SOL +
                         activa. Única por (empresa, entorno); registrar sobre un
                         entorno ya configurado rota la credencial en vez de duplicar.
IntegracionApi           empresa + nombre + scopes + estado (ACTIVA/REVOCADA). Los
                         scopes válidos están en `ScopeApi` (enum, catálogo propio —
                         no confundir con catálogos SUNAT). El `id` de la entidad ES
                         el `oauth_client_id` de Passport (no hay tabla propia
                         `integraciones_api`; se apoya en `oauth_clients.owner_type`/
                         `owner_id` nativos de Passport apuntando a `Empresa`).
```

Casos de uso: `CrearEmpresa`, `CrearSerie`, `CrearCertificadoDigital`, `CrearCredencialSunat`, `CrearIntegracionApi`, `RevocarIntegracionApi` (`Application/CasosDeUso`). Todos validan que la empresa exista y esté activa antes de operar (mismo patrón, reutilizando `RepositorioEmpresa`).

**`AnalizadorCertificadoDigital`** (`Domain/Certificados`) es un servicio de dominio concreto (sin puerto, igual que `CalculadorTributos`) que acepta PEM o PKCS#12 vía `ext-openssl` — extensión núcleo de PHP, no Illuminate ni Greenter, por lo que vivir en Domain no rompe la regla de dependencia cero. Comprueba la contraseña del P12/PFX, exige una clave privada correspondiente, normaliza certificado+clave a PEM, calcula la huella SHA-256 y la vigencia; `CrearCertificadoDigital` rechaza registrar un certificado ya vencido. El PEM se cifra en BD y la contraseña de importación se descarta.

**Verificación de titularidad pendiente**: `AnalizadorCertificadoDigital` valida que el certificado sea un X.509 bien formado y no esté vencido, pero **no** verifica que el RUC del titular del certificado coincida con el RUC de la empresa que lo registra — SUNAT exige que el certificado corresponda al RUC emisor, pero el formato exacto en que ese RUC aparece dentro del certificado (qué campo del Subject, qué OID) no está confirmado con una fuente oficial todavía. No se implementa un chequeo adivinado: se documenta aquí como riesgo abierto (ver [05_SUNAT.md](05_SUNAT.md)) en vez de dar una falsa sensación de validación completa.

**`GestorClientesOAuth`** (puerto) + `GestorClientesOAuthPassport` (Infrastructure) envuelven `Laravel\Passport\ClientRepository`/`Passport::client()` para crear el `oauth_client` (grant `client_credentials`, `secret` hasheado automáticamente por Passport) y revocarlo. `CrearIntegracionApi` devuelve `ResultadoCrearIntegracionApi { integracion, clientSecret }` — `clientSecret` es la única vez que el valor en texto plano existe fuera de la memoria transitoria del proceso; Passport lo hashea antes de persistirlo (ver [06_SEGURIDAD.md](06_SEGURIDAD.md)). `RevocarIntegracionApi` revoca el cliente **y** todos sus `access_token` ya emitidos — revocar solo el cliente no invalida tokens todavía vigentes (Passport valida revocación a nivel de token, no de cliente).

## Módulo Clientes (`modules/Clientes/`, implementado 2026-08-19)

Primer módulo top-level genuinamente independiente de `Facturacion` (namespace `Modules\Clientes`, no `Modules\Facturacion\Domain\Clientes`). Mismo patrón Domain/Application/Infrastructure, mismas reglas de `deptrac`.

```text
Cliente                  empresa + tipo de documento + número de documento + razón
                          social + dirección (nullable) + email (nullable). Agregado
                          ligero, sin máquina de estados — a diferencia de Comprobante.
                          UNIQUE (empresa_id, tipo_documento, numero_documento) en BD.
TipoDocumentoCliente      Catálogo 1 SUNAT (0/1/4/6/7) — deliberadamente duplicado de
                          Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad,
                          no importado: deptrac impide que el Domain de un módulo
                          dependa del Domain de otro, cada bounded context define su
                          propio vocabulario aunque el catálogo subyacente coincida.
```

Casos de uso: `CrearCliente`, `ActualizarCliente` (`Modules\Clientes\Application\CasosDeUso`). A diferencia de `TipoDocumentoCliente`, **sí** reutilizan `RepositorioEmpresa`, `GeneradorId` y `EmpresaInvalidaException` de `Modules\Facturacion\Domain` — la regla de `deptrac` es por capa (Application puede depender de cualquier Domain), no por módulo; reimplementar la validación de empresa activa o la generación de IDs en cada módulo nuevo sería duplicación real, no la trivial de un enum de 5 valores.

Expuesto en ambos paneles Filament (`/admin` con selector de empresa; `/app` con `empresa_id` resuelto del usuario autenticado, nunca de un campo del formulario — mismo principio que ya aplica en toda la plataforma). Todavía no conectado al flujo de emisión de comprobantes (no autocompleta el receptor); el snapshot desnormalizado en `comprobantes.receptor_*` sigue siendo la fuente de verdad fiscal, `Cliente` es solo un catálogo de conveniencia para uso futuro.

**Sin endpoints públicos de aprovisionamiento**: estas altas permanecen intencionalmente fuera de la API OAuth2 del tenant, porque crear la primera empresa o integración desde una integración todavía inexistente sería un problema circular. El panel administrativo `/admin` ya existe para empresas y series; certificados, credenciales SUNAT e integraciones continúan usando comandos/casos de uso seguros hasta que se diseñen acciones visuales que nunca revelen secretos persistidos.
