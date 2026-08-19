# Estado del proyecto

Este archivo existe para que cualquier agente (o persona) que retome el trabajo sepa, sin tener que reconstruir contexto de una conversación anterior, **qué existe, qué falta y por qué se tomó cada decisión no obvia**. Se actualiza en el mismo commit que el cambio que describe — nunca después, nunca "ya lo actualizo luego".

Formato: la sección **Snapshot actual** siempre refleja el estado real del repo en este commit (se sobrescribe). La sección **Registro** es histórica y solo crece (se antepone una entrada nueva, nunca se edita una vieja).

## Snapshot actual

**Arquitectura**: hexagonal/Clean/DDD pragmático en monolito modular Laravel. `modules/Facturacion/{Domain,Application,Infrastructure}` + `app/` (Interfaces + modelos Eloquent). Reglas de capas verificadas por `deptrac` en cada commit (0 violaciones).

**Funciona (código completo, con tests unitarios en verde)**:
- Dominio de `Comprobante` (Factura/Boleta/NotaCredito/NotaDebito, agregado único con discriminador) — máquina de estados, validadores por tipo, cálculo de tributos (IGV 18%, solo gravado '10').
- Alta de tenant completa: `Empresa`, `SerieEmpresa`, `CertificadoEmpresa`, `CredencialSunatEmpresa`, `ApiKeyEmpresa` — casos de uso `Crear*` con sus repositorios Eloquent, cifrado nativo (`Crypt`/`encrypted`), importación de certificados PEM/P12 y comandos seguros de aprovisionamiento, sin endpoints HTTP expuestos todavía (ver más abajo).
- Integración Greenter (generación+firma XML UBL, envío SOAP, parseo CDR) — flujo Factura API → XML firmado → SUNAT BETA → CDR confirmado con una factura `ACEPTADO` sin observaciones.
- Pipeline asíncrono (`ProcesarComprobante` Job + `ProcesarEnvioComprobante`), API HTTP v1 (8 endpoints, auth por API Key, idempotencia, rate limiting y reintento explícito de errores).
- Panel interno Filament 5 en `/admin`, cerrado por rol `super_admin` y `empresa_id = null`, con alta/edición de empresas, establecimientos y series. El alta de empresa/serie reutiliza los casos de uso de Application; no hay borrado fiscal desde el panel.
- Suite completa verificada con PostgreSQL 18, Redis 7 y `ext-soap`: **124 tests, 291 assertions, 0 fallos, 0 omitidos**. Incluye Unit, Integration, Feature, seguridad del panel y concurrencia real de correlativos.

**Entorno local**:
- PostgreSQL 18 y Redis 7 operan como servicios permanentes en `127.0.0.1:5432` y `127.0.0.1:6379`. PHP carga permanentemente `ext-soap`, `ext-redis` e `igbinary`.
- Las bases `facturacion` y `facturacion_test` están configuradas mediante `.env` y `.env.testing`, ambos ignorados por Git. El runtime aislado de `/tmp` fue migrado, verificado y eliminado.

**Gaps documentados explícitamente (no resueltos, no inventados)**:
- `AnalizadorCertificadoDigital` no verifica que el RUC del certificado coincida con el RUC de la empresa (falta la fuente oficial del campo/OID exacto) — ver `docs/05_SUNAT.md`.
- Certificados, credenciales SUNAT y API Keys todavía se gestionan por comandos/casos de uso; falta incorporarlos al panel mediante acciones que no expongan secretos persistidos.

**Qué sigue (próximos pasos concretos, en orden razonable)**:
1. Completar Fase 8 en el panel: importación/rotación segura de certificado, credenciales SUNAT y API Keys; después, emisión y consulta de comprobantes.
2. Resolver las reglas tributarias pendientes de `docs/05_SUNAT.md` antes de promover cada tipo de comprobante a producción.

## Registro

### 2026-08-19 — Primera vertical del panel administrativo interno
**Hecho**: se habilitó Filament 5.7 en `/admin` sin registro público. El acceso requiere simultáneamente usuario interno (`empresa_id` nulo) y rol Spatie `super_admin`; se añadieron tablas RBAC compatibles con UUID. `facturacion:crear-admin` crea el primer operador con contraseña oculta y política fuerte. El panel gestiona empresas, establecimientos y series, mantiene un solo establecimiento principal por empresa, impide editar RUC/identidad de series y no ofrece borrado. Las altas de empresa y serie invocan los casos de uso existentes. Pruebas Feature cubren invitado, rol ausente, aislamiento de tenant, acceso autorizado, comando y creación Livewire real. Suite completa: 124 tests y 291 assertions.
**Sigue**: añadir acciones seguras para certificados, credenciales y API Keys sin mostrar material cifrado ni claves completas después de su creación.

### 2026-08-19 — Entorno local permanente sin runtime temporal
**Hecho**: se instalaron Redis y las extensiones PHP SOAP/Redis/igbinary como paquetes del sistema. La base BETA completa se migró desde PostgreSQL temporal al servicio permanente, preservando las dos facturas y sus CDR. PostgreSQL y Redis temporales se apagaron y `/tmp/api_facturacion_beta_runtime` se eliminó. PHPUnit dejó de fijar un usuario de base versionado y toma usuario/contraseña desde `.env.testing`. La suite pasó nuevamente con servicios permanentes: 117 tests y 268 assertions.
**Sigue**: no queda infraestructura temporal de este hito; usar los puertos estándar definidos en los archivos de entorno locales.

### 2026-08-19 — Hito SUNAT BETA completado con CDR aceptado
**Hecho**: se ejecutó el flujo real API → Redis → worker → XML UBL 2.1 firmado → SOAP SUNAT BETA → CDR. La factura `F001-2` fue `ACEPTADO` sin observaciones y se persistieron los hashes SHA-256 del XML y CDR. Las respuestas previas de BETA revelaron requisitos que faltaban en el XML: código de local, tipo de operación, forma de pago `Contado` y nombres de departamento/provincia/distrito. El generador ahora incorpora `ProfileID` oficial sin depender de `Greenter\See` para la firma offline; el envío conserva `ext-soap`. Un reintento exitoso también limpia `ultimo_error`. Suite final: 117 tests, 268 assertions, PHPStan sin errores, Pint aprobado y Deptrac con 0 violaciones.
**Sigue**: panel administrativo y cierre de las reglas normativas listadas en `docs/05_SUNAT.md` antes de producción.

### 2026-08-19 — Aprovisionamiento BETA e importación P12 para producción
**Hecho**: `facturacion:preparar-beta` provisiona datos de prueba oficiales (`[RUC]MODDATOS`/`moddatos`), certificado autofirmado, serie F001 y API Key, y se bloquea en producción. El alta de certificados ahora acepta PEM y P12/PFX, comprueba la clave privada, normaliza a PEM cifrado y descarta la contraseña. `facturacion:importar-certificado` permite cargar el CDT oficial solicitando la contraseña de forma oculta. Se corrigió la premisa anterior: SUNAT BETA no exige registrar el certificado, según su manual oficial.
**Sigue**: ejecutar el envío BETA real cuando el host vuelva a tener PostgreSQL, Redis y `ext-soap` disponibles.

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
