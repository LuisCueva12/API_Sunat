# SUNAT

Modelo: **SEE - Del Contribuyente** (ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) §1). Cada empresa con su propio RUC, certificado y Clave SOL.

## Entornos

`beta` y `producción`, configurados por `credenciales_sunat.entorno` (por empresa) — nunca hardcodeados en casos de uso, nunca mezclados. Configuración central en `config/facturacion.php`.

## Greenter

Encapsulado íntegramente en `modules/Facturacion/Infrastructure/Sunat/Greenter/` — `GeneradorXmlGreenter`, `FirmadorXmlGreenter`, `ClienteSunatGreenter`, `ParserCdrGreenter`. Nada fuera de esa carpeta instancia clases de Greenter directamente.

## Greenter — confirmado por código fuente (2026-08-15)

Verificado leyendo `vendor/greenter/greenter` directamente, no asumido:

- Paquete correcto: `composer require greenter/greenter` (v5.3.0 al momento de instalar). Es un meta-paquete que agrupa `greenter/core`, `greenter/ws`, `greenter/xmldsig`, `greenter/xml`, etc.
- **`ext-soap` es un requisito duro** (`greenter/ws`'s `composer.json` lo declara explícitamente) y no solo para enviar: `Greenter\See::__construct()` instancia `Greenter\Ws\Services\SoapClient extends \SoapClient` **incondicionalmente**, así que sin `ext-soap` ni siquiera `getXmlSigned()` (que no envía nada a SUNAT) funciona. Confirmado con un test real: `MapeadorFacturaGreenter` (no toca `See`) pasa sin `ext-soap`; `GeneradorXmlFirmadoGreenter` (usa `See`) falla con `Class "SoapClient" not found` hasta instalar la extensión.
- **Greenter sí genera PDF** (representación impresa) vía `packages/htmltopdf` + `packages/report` (usa `twig/twig` + `mikehaertl/phpwkhtmltopdf`, que a su vez necesita el binario `wkhtmltopdf` instalado en el sistema). Esto corrige lo que dije en la primera respuesta de este proyecto ("Greenter no genera PDF") — sí puede, con una dependencia de sistema adicional a evaluar cuando se llegue a esa pieza.
- Endpoints SUNAT reales (`Greenter\Ws\Services\SunatEndpoints`):
  - `FE_BETA` = `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`
  - `FE_PRODUCCION` = `https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`
  - `FE_HOMOLOGACION` = `https://www.sunat.gob.pe/ol-ti-itcpgem-sqa/billService` (un tercer entorno de certificación, distinto de beta — evaluar si V1 lo necesita)
  - `FE_CONSULTA_CDR` para consultar CDR por separado.
- `See::send()`/`sendXml()`/`sendXmlFile()` devuelven `?BillResult` con `isSuccess()`, `getError()` (fallo técnico), `getCdrZip()` (ZIP crudo) y `getCdrResponse()` → `CdrResponse::getCode()/getDescription()/getNotes()/getReference()`. Confirma la lógica de interpretación ya documentada: código `0` sin notas = ACEPTADO, código `0` con notas = ACEPTADO_CON_OBSERVACIONES, código≠0 = RECHAZADO.
- El Legend obligatorio (monto en letras, code `1000`) no lo resuelve Greenter — se usa `luecano/numero-a-letras` (`NumeroALetras::toInvoice()`), ya instalado.
- Arquitectura ajustada: los puertos `GeneradorXmlComprobante` y `FirmadorXml` documentados originalmente se fusionaron en uno solo, `GeneradorXmlFirmado`, porque `See::getXmlSigned()` resuelve generación+firma como una sola operación atómica — forzar la separación no aportaba nada real.

## Pendiente de verificar antes de Fase 3 (no asumir — documentar aquí la fuente oficial en cuanto se confirme)

- [ ] Envío individual de boleta (`sendBill`) vs. resumen diario (`sendSummary`) — vigencia normativa actual.
- [ ] Formato de series para NC/ND vigente.
- [ ] Contenido exacto requerido del QR en la representación impresa.
- [ ] Regla de redondeo tributario esperada por SUNAT.
- [ ] Certificado de pruebas real para SUNAT BETA (el usado en los tests locales es autofirmado y solo prueba que el código no truena — SUNAT lo rechazaría).
- [ ] **Verificación de titularidad del certificado**: SUNAT exige que el certificado digital corresponda al RUC del emisor, pero el campo/OID exacto del Subject donde SUNAT espera encontrar ese RUC (y si además exige que la entidad emisora del certificado esté acreditada) no está confirmado con la especificación oficial. `AnalizadorCertificadoDigital` (`modules/Facturacion/Domain/Certificados/`) hoy solo valida que el certificado sea X.509 válido y no esté vencido — **no** compara el RUC del titular contra el de la empresa. Implementar ese chequeo sin la fuente oficial confirmada sería adivinar una regla tributaria, así que se deja pendiente explícitamente en vez de fingir una validación completa (ver [02_DOMINIO.md](02_DOMINIO.md)).

Este archivo se actualiza con la respuesta y la fuente (documentación oficial SUNAT / especificación UBL / docs de Greenter) en cuanto cada punto se resuelva — nunca se inventa una regla tributaria.
