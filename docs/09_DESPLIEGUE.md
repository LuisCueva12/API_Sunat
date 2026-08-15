# Despliegue

Pendiente — se completa en Fase 9 (endurecimiento producción) de [01_ARQUITECTURA.md](01_ARQUITECTURA.md).

Punto de partida ya decidido: 1 servidor Laravel + PostgreSQL + Redis + 2 workers, Nginx + PHP-FPM, sin Kubernetes/microservicios. Producción usa disco S3 (`FACTURACION_STORAGE_DISK=s3`), desarrollo usa disco local. La API debe ser stateless para poder escalar horizontalmente sin reescribir el dominio.
