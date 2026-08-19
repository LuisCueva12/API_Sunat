# Seguridad

Decisiones fijadas desde Fase 0-1 (detalle operativo se completa en Fase 6-9):

- **Secretos**: certificados y Clave SOL nunca en texto plano — cifrados con `Crypt` (AES-256-GCM sobre `APP_KEY`). La contraseña del P12 solo existe durante la importación y no se conserva. `APP_KEY` es un secreto crítico: respaldo seguro, nunca en el repo (`.env` está en `.gitignore`).
- **Certificados**: nunca en `/public` ni `storage/app/public`. Disco privado (`config/facturacion.php: storage_disk`). Descifrado solo transitorio en memoria/archivo temporal con permisos 0600, `unlink()` inmediato tras usar.
- **API Keys**: implementación propia (no Sanctum) — prefijo visible + hash de la key completa (nunca se persiste la key completa), scopes, expiración, revocación. Lookup por prefijo indexado antes de comparar hash.
- **Multiempresa**: `empresa_id` **siempre** derivado del actor autenticado (API Key o sesión de usuario), nunca aceptado como campo de request — evita IDOR entre tenants. Tests explícitos de fuga entre empresas son no negociables (ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) Fase 6).
- **Rate limiting**: `RateLimiter` de Laravel sobre Redis, por API Key y por empresa.
- **Logs**: nunca Clave SOL, password de certificado, certificado, API Key completa ni tokens. Cada log de request importante incluye `request_id`, `empresa_id`, `comprobante_id`.
- **Mass assignment**: modelos Eloquent con `$fillable` explícito, nunca `$guarded = []`.
- **Headers/CORS**: CORS controlado por origen explícito (no `*`), headers de seguridad estándar (HSTS en producción, `X-Content-Type-Options`, etc.) — se configuran en Fase 9 (endurecimiento producción).

## Altas implementadas (Empresa, Serie, Certificado, Credencial SUNAT, API Key)

- **Certificado digital**: `CrearCertificadoDigital` acepta PEM o P12/PFX, exige un X.509 vigente con su clave privada correspondiente y normaliza el material a PEM antes de cifrarlo (`AnalizadorCertificadoDigital`, ver [02_DOMINIO.md](02_DOMINIO.md)). Solo puede existir un certificado `ACTIVO` por empresa — índice único parcial en Postgres, reforzado en el caso de uso: si ya hay uno activo, se marca `REEMPLAZADO` (nunca se borra, queda como histórico auditable) dentro de la misma transacción antes de insertar el nuevo. La contraseña de importación no se almacena.
- **Credencial SUNAT**: única por (empresa, entorno) — a diferencia del certificado, aquí la rotación es la operación normal (Clave SOL cambia periódicamente), así que `CrearCredencialSunat` actualiza la fila existente en vez de crear una nueva cada vez que se registra una credencial para un entorno ya configurado.
- **API Key**: `GeneradorClaveApi` (puerto, implementado por `GeneradorClaveApiSegura` en Infrastructure) genera 32 caracteres vía `random_bytes` con prefijo `fe_live_`. `CrearApiKey` valida que los scopes solicitados existan en `ApiKeyEmpresa::ESCOPOS_VALIDOS` (rechaza scopes desconocidos, no los ignora en silencio) y devuelve la clave completa en texto plano **una única vez**, dentro de `ResultadoCrearApiKey` — ese objeto es transitorio, no se guarda en ningún log ni tabla; solo el hash SHA-256 llega a persistirse.
- Esta lógica de generación de clave vivía antes en `app/Services/ApiKeys` (capa `Interfaces`, sin contrato); se migró a un puerto de Domain (`GeneradorClaveApi`) + adaptador de Infrastructure para que `CrearApiKey` (Application) pueda depender de él sin romper la regla de capas de `deptrac` — Application solo puede depender de Domain.
- **Sin endpoints HTTP públicos todavía**: estas 5 altas (`CrearEmpresa`, `CrearSerie`, `CrearCertificadoDigital`, `CrearCredencialSunat`, `CrearApiKey`) existen como casos de uso completos con tests, pero deliberadamente no están expuestas en `routes/api_v1.php`. Exponer "crear tu primera empresa/API Key" sin autenticación sería un endpoint de alta sin control de acceso — el lugar correcto es un área administrativa autenticada (Fase 8, panel), todavía no construida.

Checklist de auditoría, rotación de secretos y runbook de incidentes se documentan en detalle en Fase 6-9, una vez implementados (evitar documentar procesos que todavía no existen).
