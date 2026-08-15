# Seguridad

Decisiones fijadas desde Fase 0-1 (detalle operativo se completa en Fase 6-9):

- **Secretos**: certificados, password de certificado, Clave SOL nunca en texto plano — cifrados con `Crypt` (AES-256-GCM sobre `APP_KEY`). `APP_KEY` es un secreto crítico: respaldo seguro, nunca en el repo (`.env` está en `.gitignore`).
- **Certificados**: nunca en `/public` ni `storage/app/public`. Disco privado (`config/facturacion.php: storage_disk`). Descifrado solo transitorio en memoria/archivo temporal con permisos 0600, `unlink()` inmediato tras usar.
- **API Keys**: implementación propia (no Sanctum) — prefijo visible + hash de la key completa (nunca se persiste la key completa), scopes, expiración, revocación. Lookup por prefijo indexado antes de comparar hash.
- **Multiempresa**: `empresa_id` **siempre** derivado del actor autenticado (API Key o sesión de usuario), nunca aceptado como campo de request — evita IDOR entre tenants. Tests explícitos de fuga entre empresas son no negociables (ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) Fase 6).
- **Rate limiting**: `RateLimiter` de Laravel sobre Redis, por API Key y por empresa.
- **Logs**: nunca Clave SOL, password de certificado, certificado, API Key completa ni tokens. Cada log de request importante incluye `request_id`, `empresa_id`, `comprobante_id`.
- **Mass assignment**: modelos Eloquent con `$fillable` explícito, nunca `$guarded = []`.
- **Headers/CORS**: CORS controlado por origen explícito (no `*`), headers de seguridad estándar (HSTS en producción, `X-Content-Type-Options`, etc.) — se configuran en Fase 9 (endurecimiento producción).

Checklist de auditoría, rotación de secretos y runbook de incidentes se documentan en detalle en Fase 6-9, una vez implementados (evitar documentar procesos que todavía no existen).
