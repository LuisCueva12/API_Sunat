# Estado del proyecto

Este archivo existe para que cualquier agente (o persona) que retome el trabajo sepa, sin tener que reconstruir contexto de una conversación anterior, **qué existe, qué falta y por qué se tomó cada decisión no obvia**. Se actualiza en el mismo commit que el cambio que describe — nunca después, nunca "ya lo actualizo luego".

Formato: la sección **Snapshot actual** siempre refleja el estado real del repo en este commit (se sobrescribe). La sección **Registro** es histórica y solo crece (se antepone una entrada nueva, nunca se edita una vieja).

## Snapshot actual

**Arquitectura**: hexagonal/Clean/DDD pragmático en monolito modular Laravel. `modules/Facturacion/{Domain,Application,Infrastructure}` + `app/` (Interfaces + modelos Eloquent). Reglas de capas verificadas por `deptrac` en cada commit (0 violaciones).

**Funciona (código completo, con tests unitarios en verde)**:
- Dominio de `Comprobante` (Factura/Boleta/NotaCredito/NotaDebito, agregado único con discriminador) — máquina de estados, validadores por tipo, cálculo de tributos (IGV 18%, solo gravado '10').
- Alta de tenant completa: `Empresa`, `SerieEmpresa`, `CertificadoEmpresa`, `CredencialSunatEmpresa`, `ApiKeyEmpresa` — casos de uso `Crear*` con sus repositorios Eloquent, cifrado nativo (`Crypt`/`encrypted`), sin endpoints HTTP expuestos todavía (ver más abajo).
- Integración Greenter (generación+firma XML UBL, envío SOAP, parseo CDR) — código completo, nunca ejecutado de verdad contra Postgres/SUNAT real en este entorno.
- Pipeline asíncrono (`ProcesarComprobante` Job + `ProcesarEnvioComprobante`), API HTTP v1 (7 endpoints, auth por API Key, idempotencia, rate limiting).

**Bloqueado (sin resolver en ningún momento de este proyecto)**:
- Postgres: el usuario `facturacion` no autentica con la password esperada — bloquea **todos** los tests de Integración/Feature. Solo corren los tests Unit (sin BD).
- `ext-soap` no instalado — bloquea cualquier uso real de `Greenter\See` (ni siquiera generar XML firmado sin enviar funciona sin esta extensión).
- Redis no instalado.

**Gaps documentados explícitamente (no resueltos, no inventados)**:
- `AnalizadorCertificadoDigital` no verifica que el RUC del certificado coincida con el RUC de la empresa (falta la fuente oficial del campo/OID exacto) — ver `docs/05_SUNAT.md`.
- `request_id` no viaja del HTTP request al Job async — ver `docs/07_COLAS.md`.
- Las altas de empresa/certificado/credencial/API Key no tienen endpoint HTTP: exponerlas sin autenticación de administrador sería un agujero de seguridad. Pendiente de Fase 8 (panel administrativo).

**Qué sigue (próximos pasos concretos, en orden razonable)**:
1. Resolver el bloqueador de entorno (Postgres/Redis/`ext-soap`) para poder correr por primera vez los tests de Integración/Feature ya escritos y validar todo el flujo end-to-end.
2. Certificado de pruebas real para SUNAT BETA (el autofirmado usado en tests locales no sirve ante SUNAT real).
3. Fase 3 (Hito Obligatorio): Factura completa API → XML → firma → BETA → CDR aceptado.
4. Panel administrativo (Fase 8) — primer lugar razonable para exponer las altas de empresa/certificado/credencial/API Key por HTTP.
5. Cerrar el gap de `request_id` en el pipeline async.

## Registro

### 2026-08-18 — Alta de certificados, credenciales SUNAT y API Keys
**Hecho**: `CertificadoEmpresa` (+ `AnalizadorCertificadoDigital`, parseo X.509 real vía `ext-openssl`), `CredencialSunatEmpresa` (rota en vez de duplicar por entorno), `ApiKeyEmpresa` (+ puerto `GeneradorClaveApi`, migrado desde `app/Services/ApiKeys` porque Application no puede depender de esa capa). Casos de uso `CrearCertificadoDigital`/`CrearCredencialSunat`/`CrearApiKey`, adaptadores Eloquent, wiring en `DomainServiceProvider`. 26 tests nuevos (Domain+Application con dobles; Integration escritos, sin ejecutar por el bloqueador de Postgres).
**Sigue**: ver "Qué sigue" arriba — nada específico de esta pieza quedó a medias, salvo la verificación de titularidad del certificado (documentada como gap, no como pendiente de código).

### 2026-08-18 — Alta de empresa y series
**Hecho**: `Empresa`, `SerieEmpresa` (entidad distinta del VO `Serie`, que solo valida formato), puertos + adaptadores Eloquent, casos de uso `CrearEmpresa`/`CrearSerie`. Limpieza de comentarios explicativos en todo el código existente (pedido explícito del usuario: código sin comentarios salvo excepción justificada).
**Sigue**: certificados/credenciales/API Keys (resuelto en la entrada de arriba).

### Anterior a 2026-08-18 (fases 0-2 + boleta/NC/ND + API + async)
Bootstrap Laravel 13, dominio core de `Comprobante`, integración Greenter (XML+firma), Boleta/NotaCredito/NotaDebito reutilizando el pipeline de Factura, capa API HTTP v1 completa (auth, idempotencia, 7 endpoints), revisión de correctitud (rate limiting faltante + bugs de tipos reales encontrados en revisión), pipeline de procesamiento asíncrono (`ProcesarComprobante` Job). Detalle completo en el resto de `docs/*.md` y en `git log`.
