# SUNAT

Modelo: **SEE - Del Contribuyente** (ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) §1). Cada empresa con su propio RUC, certificado y Clave SOL.

## Entornos

`beta` y `producción`, configurados por `credenciales_sunat.entorno` (por empresa) — nunca hardcodeados en casos de uso, nunca mezclados. Configuración central en `config/facturacion.php`.

## Greenter

Encapsulado íntegramente en `modules/Facturacion/Infrastructure/Sunat/Greenter/` — `GeneradorXmlGreenter`, `FirmadorXmlGreenter`, `ClienteSunatGreenter`, `ParserCdrGreenter`. Nada fuera de esa carpeta instancia clases de Greenter directamente.

## Pendiente de verificar antes de Fase 2/3 (no asumir — documentar aquí la fuente oficial en cuanto se confirme)

- [ ] Versión exacta de `greenter/greenter` compatible con PHP 8.5, y si requiere `ext-soap` (no instalado en este entorno todavía).
- [ ] Envío individual de boleta (`sendBill`) vs. resumen diario (`sendSummary`) — vigencia normativa actual.
- [ ] Formato de series para NC/ND vigente.
- [ ] Rangos de código en el CDR que distinguen "aceptado con observaciones" de "rechazado".
- [ ] Contenido exacto requerido del QR en la representación impresa.
- [ ] Regla de redondeo tributario esperada por SUNAT.

Este archivo se actualiza con la respuesta y la fuente (documentación oficial SUNAT / especificación UBL / docs de Greenter) en cuanto cada punto se resuelva — nunca se inventa una regla tributaria.
