# Seguridad

Decisiones fijadas desde Fase 0-1 (detalle operativo se completa en Fase 6-9):

- **Secretos**: certificados y Clave SOL nunca en texto plano — cifrados con `Crypt` (AES-256-GCM sobre `APP_KEY`). La contraseña del P12 solo existe durante la importación y no se conserva. `APP_KEY` es un secreto crítico: respaldo seguro, nunca en el repo (`.env` está en `.gitignore`).
- **Certificados**: nunca en `/public` ni `storage/app/public`. Disco privado (`config/facturacion.php: storage_disk`). Descifrado solo transitorio en memoria/archivo temporal con permisos 0600, `unlink()` inmediato tras usar.
- **Integraciones API**: Laravel Passport, grant `client_credentials` (el único habilitado — no hay usuario humano delegando en una integración POS/ERP/ecommerce). `client_secret` lo hashea Passport internamente (nunca se persiste en texto plano); `access_token` de vida corta (1 hora, `Passport::clientCredentialsTokensExpireIn()`) fuerza reautenticación periódica con `client_id`+`client_secret`. Scopes por cliente restringidos vía la columna propia `oauth_clients.scopes` (extensión sobre la tabla de Passport — sin esa columna, `Client::hasScope()` permitiría cualquier scope registrado). Revocar una integración (`RevocarIntegracionApi`) revoca el cliente **y** todos sus `access_token` ya emitidos — verificado en vivo que un token revocado deja de servir de inmediato, no espera su expiración natural.
- **Multiempresa**: `empresa_id` **siempre** derivado del actor autenticado (cliente OAuth vía `owner_id`, o sesión de usuario en el panel), nunca aceptado como campo de request — evita IDOR entre tenants. El vínculo integración→empresa usa `oauth_clients.owner_type`/`owner_id` nativos de Passport (`nullableUuidMorphs('owner')` en la migración — no el `nullableMorphs()` que Passport trae por defecto, que crea `owner_id` como `bigint`) apuntando a `App\Models\Empresa`, sin tabla propia adicional. Tests explícitos de fuga entre empresas son no negociables (ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) Fase 6).
- **Rate limiting**: `RateLimiter` de Laravel sobre Redis, por integración y por empresa.
- **Logs**: nunca Clave SOL, password de certificado, certificado, `client_secret` ni `access_token` completos. Cada log de request importante incluye `request_id`, `empresa_id`, `comprobante_id`.
- **Mass assignment**: modelos Eloquent con `$fillable` explícito, nunca `$guarded = []`.
- **Headers/CORS**: CORS controlado por origen explícito (no `*`), headers de seguridad estándar (HSTS en producción, `X-Content-Type-Options`, etc.) — se configuran en Fase 9 (endurecimiento producción).

## Altas implementadas (Empresa, Serie, Certificado, Credencial SUNAT, Integración API)

- **Certificado digital**: `CrearCertificadoDigital` acepta PEM o P12/PFX, exige un X.509 vigente con su clave privada correspondiente y normaliza el material a PEM antes de cifrarlo (`AnalizadorCertificadoDigital`, ver [02_DOMINIO.md](02_DOMINIO.md)). Solo puede existir un certificado `ACTIVO` por empresa — índice único parcial en Postgres, reforzado en el caso de uso: si ya hay uno activo, se marca `REEMPLAZADO` (nunca se borra, queda como histórico auditable) dentro de la misma transacción antes de insertar el nuevo. La contraseña de importación no se almacena.
- **Credencial SUNAT**: única por (empresa, entorno) — a diferencia del certificado, aquí la rotación es la operación normal (Clave SOL cambia periódicamente), así que `CrearCredencialSunat` actualiza la fila existente en vez de crear una nueva cada vez que se registra una credencial para un entorno ya configurado.
- **Integración API**: `GestorClientesOAuth` (puerto, implementado por `GestorClientesOAuthPassport` en Infrastructure) crea un `oauth_client` de Passport (`grant_types: ['client_credentials']`, `owner_type/owner_id` → la empresa). `CrearIntegracionApi` valida que los scopes solicitados existan en `ScopeApi::valores()` (rechaza scopes desconocidos, no los ignora en silencio) y devuelve el `client_secret` en texto plano **una única vez**, dentro de `ResultadoCrearIntegracionApi` — ese objeto es transitorio, no se guarda en ningún log ni tabla; Passport ya lo persiste hasheado.
- Este puerto reemplaza la implementación propia de API Keys que existió hasta 2026-08-19 (`GeneradorClaveApi`/`ApiKeyEmpresa`, tabla `api_keys`) — se migró a Passport/OAuth2 antes de tener integraciones reales en producción, ver Fase 1 de [01_ARQUITECTURA.md](01_ARQUITECTURA.md) para el razonamiento completo.
- **Altas administrativas separadas de la API pública**: `CrearEmpresa` y `CrearSerie` ya se invocan desde el panel interno `/admin`; certificados, credenciales e integraciones API siguen disponibles solo mediante comandos/casos de uso hasta contar con formularios que protejan correctamente sus secretos. Ninguna de estas altas se expone en `routes/api_v1.php`.

## Panel interno

- No existe registro público. El primer operador se crea con `php artisan facturacion:crear-admin correo@dominio --name="Nombre"`; la contraseña se solicita dos veces y nunca viaja como argumento ni queda en el historial del shell.
- `Usuario::canAccessPanel()` exige panel `admin`, rol `super_admin` y `empresa_id` nulo. Un usuario asociado a una empresa no obtiene acceso cross-tenant aunque se le asigne el rol por error.
- Las tablas RBAC de Spatie usan UUID para `model_id`, igual que `usuarios.id`.
- Empresas, establecimientos y series no se eliminan desde el panel. RUC, empresa/código de establecimiento e identidad de la serie quedan inmutables después del alta.

Checklist de auditoría, rotación de secretos y runbook de incidentes se documentan en detalle en Fase 6-9, una vez implementados (evitar documentar procesos que todavía no existen).
