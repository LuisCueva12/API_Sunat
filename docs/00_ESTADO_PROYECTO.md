# Estado del proyecto

Este archivo existe para que cualquier agente (o persona) que retome el trabajo sepa, sin tener que reconstruir contexto de una conversación anterior, **qué existe, qué falta y por qué se tomó cada decisión no obvia**. Se actualiza en el mismo commit que el cambio que describe — nunca después, nunca "ya lo actualizo luego".

Formato: la sección **Snapshot actual** siempre refleja el estado real del repo en este commit (se sobrescribe). La sección **Registro** es histórica y solo crece (se antepone una entrada nueva, nunca se edita una vieja).

## Snapshot actual

**Arquitectura**: hexagonal/Clean/DDD pragmático en monolito modular Laravel. `modules/Facturacion/{Domain,Application,Infrastructure}` + `app/` (Interfaces + modelos Eloquent). Reglas de capas verificadas por `deptrac` en cada commit (0 violaciones).

**Funciona (código completo, con tests unitarios en verde)**:
- Dominio de `Comprobante` (Factura/Boleta/NotaCredito/NotaDebito, agregado único con discriminador) — máquina de estados, validadores por tipo, cálculo de tributos (IGV 18%, solo gravado '10').
- Alta de tenant completa: `Empresa`, `SerieEmpresa`, `CertificadoEmpresa`, `CredencialSunatEmpresa`, `ApiKeyEmpresa` — casos de uso `Crear*` con sus repositorios Eloquent, cifrado nativo (`Crypt`/`encrypted`), sin endpoints HTTP expuestos todavía (ver más abajo).
- Integración Greenter (generación+firma XML UBL, envío SOAP, parseo CDR) — generación y firma verificadas localmente con `ext-soap`; todavía no se envió contra SUNAT BETA real.
- Pipeline asíncrono (`ProcesarComprobante` Job + `ProcesarEnvioComprobante`), API HTTP v1 (8 endpoints, auth por API Key, idempotencia, rate limiting y reintento explícito de errores).
- Suite completa verificada con PostgreSQL 18, Redis 7 y `ext-soap`: **104 tests, 223 assertions, 0 fallos, 0 omitidos**. Incluye Unit, Integration, Feature y concurrencia real de correlativos.

**Entorno local**:
- El bloqueo de validación quedó resuelto mediante servicios y extensiones cargados en un runtime aislado: PostgreSQL en `127.0.0.1:55432`, Redis en `127.0.0.1:56379` y módulos PHP extraídos en `/tmp`. Es evidencia de compatibilidad, no configuración persistente del host.
- Para dejarlo permanente tras reiniciar hace falta privilegio administrativo: instalar `php8.5-soap`, `php8.5-redis` y `redis-server`, y configurar el rol/base PostgreSQL según `.env.testing`.

**Gaps documentados explícitamente (no resueltos, no inventados)**:
- `AnalizadorCertificadoDigital` no verifica que el RUC del certificado coincida con el RUC de la empresa (falta la fuente oficial del campo/OID exacto) — ver `docs/05_SUNAT.md`.
- Las altas de empresa/certificado/credencial/API Key no tienen endpoint HTTP: exponerlas sin autenticación de administrador sería un agujero de seguridad. Pendiente de Fase 8 (panel administrativo).

**Qué sigue (próximos pasos concretos, en orden razonable)**:
1. Certificado de pruebas real para SUNAT BETA (el autofirmado usado en tests locales no sirve ante SUNAT real).
2. Fase 3 (Hito Obligatorio): Factura completa API → XML → firma → BETA → CDR aceptado.
3. Panel administrativo (Fase 8) — primer lugar razonable para exponer las altas de empresa/certificado/credencial/API Key por HTTP.

## Registro

### 2026-08-19 — Reintento operativo de comprobantes con error
**Hecho**: endpoint `POST /api/v1/comprobantes/{id}/reintentar`, protegido por tenant y scope `comprobantes:reintentar`. Solo acepta comprobantes en `ERROR`, responde `409` para cualquier otro estado y vuelve a usar el pipeline asíncrono conservando `request_id`. `ProcesarComprobante` es único por empresa+comprobante durante una hora para evitar envíos simultáneos duplicados.
**Sigue**: obtener certificado y credenciales de pruebas para completar el hito contra SUNAT BETA.

### 2026-08-19 — Trazabilidad HTTP hasta el worker asíncrono
**Hecho**: `request_id` viaja desde el middleware HTTP por `EmitirComprobanteInput` y `DespachadorProcesamiento` hasta `ProcesarComprobante`. El Job registra como contexto compartido `request_id`, `empresa_id` y `comprobante_id`, que Laravel limpia entre ejecuciones del worker. Una prueba Feature verifica el header, la respuesta y el Job encolado.
**Sigue**: obtener certificado y credenciales de pruebas para completar el hito contra SUNAT BETA.

### 2026-08-19 — Primera validación completa con PostgreSQL, Redis y SOAP
**Hecho**: se levantó un runtime aislado con PostgreSQL 18, Redis 7 y `ext-soap`/`ext-redis`, y se ejecutó por primera vez toda la suite: 104 tests, 223 assertions, 0 fallos y 0 omitidos. La corrida real descubrió y permitió corregir incompatibilidades que Unit no podía detectar: FK autorreferente creada antes de que PostgreSQL pudiera resolver la PK, IDs de dominio descartados por mass assignment de Eloquent, longitud insuficiente del prefijo API Key, escala monetaria de BD incompatible con `Dinero`, helper X.509 no compartido entre suites y ejecución accidental del Job SUNAT síncrono en tests de emisión. PHPStan quedó en 0 errores, Pint pasó y Deptrac quedó en 0 violaciones.
**Sigue**: instalar las dependencias de forma persistente en el host cuando haya acceso `sudo`; para el producto, obtener un certificado válido de pruebas y ejecutar el hito contra SUNAT BETA real.

### 2026-08-18 — Alta de certificados, credenciales SUNAT y API Keys
**Hecho**: `CertificadoEmpresa` (+ `AnalizadorCertificadoDigital`, parseo X.509 real vía `ext-openssl`), `CredencialSunatEmpresa` (rota en vez de duplicar por entorno), `ApiKeyEmpresa` (+ puerto `GeneradorClaveApi`, migrado desde `app/Services/ApiKeys` porque Application no puede depender de esa capa). Casos de uso `CrearCertificadoDigital`/`CrearCredencialSunat`/`CrearApiKey`, adaptadores Eloquent, wiring en `DomainServiceProvider`. 26 tests nuevos (Domain+Application con dobles; Integration escritos, sin ejecutar por el bloqueador de Postgres).
**Sigue**: ver "Qué sigue" arriba — nada específico de esta pieza quedó a medias, salvo la verificación de titularidad del certificado (documentada como gap, no como pendiente de código).

### 2026-08-18 — Alta de empresa y series
**Hecho**: `Empresa`, `SerieEmpresa` (entidad distinta del VO `Serie`, que solo valida formato), puertos + adaptadores Eloquent, casos de uso `CrearEmpresa`/`CrearSerie`. Limpieza de comentarios explicativos en todo el código existente (pedido explícito del usuario: código sin comentarios salvo excepción justificada).
**Sigue**: certificados/credenciales/API Keys (resuelto en la entrada de arriba).

### Anterior a 2026-08-18 (fases 0-2 + boleta/NC/ND + API + async)
Bootstrap Laravel 13, dominio core de `Comprobante`, integración Greenter (XML+firma), Boleta/NotaCredito/NotaDebito reutilizando el pipeline de Factura, capa API HTTP v1 completa (auth, idempotencia, 7 endpoints), revisión de correctitud (rate limiting faltante + bugs de tipos reales encontrados en revisión), pipeline de procesamiento asíncrono (`ProcesarComprobante` Job). Detalle completo en el resto de `docs/*.md` y en `git log`.
