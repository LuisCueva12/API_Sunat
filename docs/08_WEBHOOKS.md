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

Cada entrega usa timeout de conexión de 5 segundos, timeout total de 10 segundos y backoff de 10, 60, 300 y 900 segundos. Una respuesta HTTP no exitosa se reintenta hasta agotar cinco intentos.

Gestión de endpoints (alta, rotación de secreto, activación y eventos suscritos): panel `/app/webhooks`, siempre restringido al tenant autenticado. Solo acepta HTTPS, vuelve a resolver el destino antes de cada entrega y rechaza IP privadas o reservadas para evitar SSRF.
