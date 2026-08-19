# SUNAT

Modelo: **SEE - Del Contribuyente** (ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) §1). Cada empresa con su propio RUC, certificado y Clave SOL.

## Entornos

`beta` y `producción`, configurados por `credenciales_sunat.entorno` (por empresa) — nunca hardcodeados en casos de uso, nunca mezclados. Configuración central en `config/facturacion.php`.

### Preparación de BETA

El manual oficial de SUNAT indica que el servicio BETA no exige tener el certificado registrado en SUNAT. Para este entorno se usa un certificado autofirmado y las credenciales públicas `[RUC]MODDATOS` / `moddatos`; no se deben reutilizar en producción.

Después de migrar la base de datos:

```bash
php artisan facturacion:preparar-beta
```

El comando crea o reutiliza la empresa de pruebas con RUC `20100066603`, la serie `F001`, un certificado autofirmado cifrado, las credenciales BETA y una API Key. Está bloqueado cuando `APP_ENV=production`. Puede indicarse otro RUC válido con `--ruc=`.

Con el servidor HTTP y el worker activos, una factura mínima puede enviarse así:

```bash
php artisan queue:work --tries=5

curl -X POST http://localhost:8000/api/v1/facturas \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: beta-factura-001" \
  -d '{
    "serie": "F001",
    "receptor_tipo_documento": "6",
    "receptor_numero_documento": "20100070970",
    "receptor_razon_social": "CLIENTE DE PRUEBA SAC",
    "items": [{
      "descripcion": "SERVICIO DE PRUEBA BETA",
      "unidad_medida": "NIU",
      "cantidad": 1,
      "valor_unitario": "100.00",
      "tipo_afectacion_igv": "10"
    }]
  }'
```

`$API_KEY` es el valor mostrado una sola vez por `facturacion:preparar-beta`. El endpoint responde `202`; el resultado tributario se consulta en `GET /api/v1/comprobantes/{id}/estado`. BETA solo valida pruebas y nunca debe mezclarse con credenciales o documentos de producción.

Fuente: [Manual del programador SEE - Sistemas del Contribuyente](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), sección Servicio Beta.

### Catálogos 09 y 10 — Motivos de Nota de Crédito y Nota de Débito

`Modules\Facturacion\Domain\Comprobante\MotivoNotaCredito` (13 códigos, `01`–`13`) y `MotivoNotaDebito` (5 códigos: `01`, `02`, `03`, `11`, `12`) implementan los catálogos oficiales. Fuente: Resolución de Superintendencia N.° 193-2020/SUNAT, [anexo 3](https://www.sunat.gob.pe/legislacion/superin/2020/anexo3-193-2020.pdf), confirmada además contra [Factpro — Catálogo 09](https://docs.factpro.la/catalogos-sunat/catalogo-09-codigos-de-tipo-de-nota-de-credito-electronica) y [Factpro — Catálogo 10](https://docs.factpro.la/catalogos-sunat/catalogo-10-codigos-de-tipo-de-nota-de-debito.md). Los códigos `02` y `03` del catálogo 09 tienen una restricción adicional (no aplican a notas que referencian comprobantes impresos/de imprenta autorizada) que no se implementó: como este proyecto es 100% SEE - Del Contribuyente (comprobantes electrónicos desde su origen, nunca convertidos desde papel), esa restricción no aplica a ningún comprobante que este sistema pueda emitir.

### Resultado BETA verificado

El 19 de agosto de 2026 se completó un envío real con el RUC y credenciales públicas de prueba. SUNAT devolvió código `0`, descripción de factura aceptada y un CDR sin observaciones para `F001-2`. El XML aceptado incluyó:

- `cbc:ProfileID` `0101` con los atributos del catálogo SUNAT.
- `cbc:InvoiceTypeCode` `01` con `listID="0101"`.
- `cac:PaymentTerms` con `FormaPago` y `Contado`.
- domicilio fiscal con código de local `0000`, ubigeo, departamento, provincia y distrito.

Los errores BETA `3030`, `3205` y `3244` permitieron completar esos campos. En particular, la [matriz oficial de reglas de validación CPE](https://cpe.sunat.gob.pe/guias-y-manuales) confirma que `3244` exige el bloque de forma de pago, no el tipo de operación. El comando `facturacion:preparar-beta` provisiona todos los datos geográficos requeridos para repetir la prueba.

### Resultado BETA verificado — Boleta

El 19 de agosto de 2026 se emitió una boleta (`B001-1`, receptor con DNI) con el mismo flujo API → cola → SUNAT BETA y quedó `ACEPTADO`, con XML y CDR reales. Confirma que `MapeadorFacturaBoletaGreenter` (`Invoice` de Greenter con `tipoDoc=03`, catálogo 1 SUNAT) no necesita nada adicional a lo que ya exige Factura — comparten `InvoiceBuilder`, forma de pago y domicilio fiscal. Antes de este mapeo, cualquier intento de emitir una Boleta fallaba antes de llegar a SUNAT (ver `docs/00_ESTADO_PROYECTO.md`, registro del mismo día).

### Certificado de producción

El alta acepta PEM o el archivo P12/PFX entregado por SUNAT o por una entidad acreditada. Verifica la contraseña, la vigencia y que la clave privada corresponda al certificado; luego normaliza el contenido a PEM y lo cifra en la base de datos. La contraseña de importación no se conserva.

```bash
php artisan facturacion:importar-certificado EMPRESA_UUID /ruta/certificado.p12
```

La contraseña se solicita de forma oculta. El Certificado Digital Tributario gratuito puede solicitarse desde SOL siguiendo `Empresas → Comprobantes de Pago → Certificado Digital Tributario - CDT`. Fuente: [Certificado Digital Tributario de SUNAT](https://cpe.sunat.gob.pe/certificado-digital).

## Greenter

Encapsulado íntegramente en `modules/Facturacion/Infrastructure/Sunat/Greenter/`. El generador usa `InvoiceBuilder` y `SignedXml` para producir y firmar el XML sin inicializar el cliente SOAP; el cliente de envío y el parser CDR permanecen separados. Nada fuera de esa carpeta instancia clases de Greenter directamente.

## Greenter — confirmado por código fuente (2026-08-15)

Verificado leyendo `vendor/greenter/greenter` directamente, no asumido:

- Paquete correcto: `composer require greenter/greenter` (v5.3.0 al momento de instalar). Es un meta-paquete que agrupa `greenter/core`, `greenter/ws`, `greenter/xmldsig`, `greenter/xml`, etc.
- **`ext-soap` es requisito duro para el envío** (`greenter/ws` lo declara y su cliente extiende `\SoapClient`). La generación y firma local ya no dependen de `Greenter\See`: usan `InvoiceBuilder` y `SignedXml`, por lo que sus pruebas offline funcionan sin cargar SOAP.
- **Greenter sí genera PDF** (representación impresa) vía `packages/htmltopdf` + `packages/report` (usa `twig/twig` + `mikehaertl/phpwkhtmltopdf`, que a su vez necesita el binario `wkhtmltopdf` instalado en el sistema). Esto corrige lo que dije en la primera respuesta de este proyecto ("Greenter no genera PDF") — sí puede, con una dependencia de sistema adicional a evaluar cuando se llegue a esa pieza.
- Endpoints SUNAT reales (`Greenter\Ws\Services\SunatEndpoints`):
  - `FE_BETA` = `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`
  - `FE_PRODUCCION` = `https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`
  - `FE_HOMOLOGACION` = `https://www.sunat.gob.pe/ol-ti-itcpgem-sqa/billService` (un tercer entorno de certificación, distinto de beta — evaluar si V1 lo necesita)
  - `FE_CONSULTA_CDR` para consultar CDR por separado.
- `See::send()`/`sendXml()`/`sendXmlFile()` devuelven `?BillResult` con `isSuccess()`, `getError()` (fallo técnico), `getCdrZip()` (ZIP crudo) y `getCdrResponse()` → `CdrResponse::getCode()/getDescription()/getNotes()/getReference()`. Confirma la lógica de interpretación ya documentada: código `0` sin notas = ACEPTADO, código `0` con notas = ACEPTADO_CON_OBSERVACIONES, código≠0 = RECHAZADO.
- El Legend obligatorio (monto en letras, code `1000`) no lo resuelve Greenter — se usa `luecano/numero-a-letras` (`NumeroALetras::toInvoice()`), ya instalado.
- Arquitectura ajustada: los puertos `GeneradorXmlComprobante` y `FirmadorXml` documentados originalmente se fusionaron en uno solo, `GeneradorXmlFirmado`; la infraestructura mantiene generación y firma como una operación atómica para el caso de uso.

## Pendiente de verificar antes de Fase 3 (no asumir — documentar aquí la fuente oficial en cuanto se confirme)

- [ ] Envío individual de boleta (`sendBill`) vs. resumen diario (`sendSummary`) — vigencia normativa actual. Parcialmente investigado el 2026-08-19: a nivel técnico Greenter sí soporta `sendSummary` (documento `Greenter\Model\Summary\Summary`, `SummaryBuilder`) y la "Comunicación de Baja" para anular una boleta ya emitida sin Nota de Crédito (documento `Greenter\Model\Voided\Voided`, `VoidedBuilder`, enviado con `See::send()` igual que una factura), con resultado asíncrono consultable vía `See::getStatus($ticket)`. Falta confirmar con la fuente oficial (manual SEE - Del Contribuyente) la regla exacta de plazo para presentar la baja — no implementado todavía, no se debe adivinar el plazo.
- [ ] Formato de series para NC/ND vigente.
- [ ] Contenido exacto requerido del QR en la representación impresa.
- [ ] Regla de redondeo tributario esperada por SUNAT.
- [ ] **Verificación de titularidad del certificado**: SUNAT exige que el certificado digital corresponda al RUC del emisor, pero el campo/OID exacto del Subject donde SUNAT espera encontrar ese RUC (y si además exige que la entidad emisora del certificado esté acreditada) no está confirmado con la especificación oficial. `AnalizadorCertificadoDigital` (`modules/Facturacion/Domain/Certificados/`) hoy solo valida que el certificado sea X.509 válido y no esté vencido — **no** compara el RUC del titular contra el de la empresa. Implementar ese chequeo sin la fuente oficial confirmada sería adivinar una regla tributaria, así que se deja pendiente explícitamente en vez de fingir una validación completa (ver [02_DOMINIO.md](02_DOMINIO.md)).

Este archivo se actualiza con la respuesta y la fuente (documentación oficial SUNAT / especificación UBL / docs de Greenter) en cuanto cada punto se resuelva — nunca se inventa una regla tributaria.
