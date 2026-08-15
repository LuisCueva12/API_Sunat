# Webhooks

## Eventos

```text
comprobante.aceptado
comprobante.aceptado_con_observaciones
comprobante.rechazado
comprobante.error
```

## Payload

```json
{ "evento": "comprobante.aceptado", "id": "...", "comprobante_id": "...", "estado": "ACEPTADO" }
```

## Firma

HMAC-SHA256 con secreto propio por endpoint (`webhooks.secreto_cifrado`, cifrado en BD). Headers:

```text
X-Facturacion-Signature: sha256=<hmac>
X-Facturacion-Timestamp: <unix ts>
```

La firma cubre `timestamp + "." + body` para permitir al receptor rechazar reintentos de replay fuera de una ventana razonable.

## Entrega

Job `EnviarWebhook`, reintentos con backoff, historial completo en `entregas_webhook` (estado `PENDIENTE`/`ENTREGADO`/`FALLIDO`/`AGOTADO`). Un fallo de webhook **nunca** afecta el estado del comprobante ni bloquea la emisión — son sistemas desacoplados a propósito.

Gestión de endpoints (alta/baja/eventos suscritos): solo panel en V1, ver [01_ARQUITECTURA.md](01_ARQUITECTURA.md) §11.
