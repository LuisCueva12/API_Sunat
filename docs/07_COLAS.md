# Colas

Driver: Redis. La API nunca espera el pipeline SUNAT — responde `202` tras `REGISTRADO` y encola.

## Jobs

```text
ProcesarComprobante        genera XML, firma, comprime ZIP
EnviarComprobanteSunat     envía a SUNAT, guarda envios_sunat
ProcesarRespuestaSunat     interpreta CDR, actualiza estado, genera PDF
EnviarWebhook              entrega firmada HMAC a endpoints suscritos
```

Deliberadamente no se fragmenta más — cuatro jobs con responsabilidad clara, no docenas de jobs diminutos sin beneficio real.

## Reintentos

Diferenciados por tipo de fallo:

| Tipo de fallo | Comportamiento |
|---|---|
| Red / timeout / SUNAT no disponible | Reintenta con backoff exponencial + jitter, tope `facturacion.reintentos.maximo_intentos` (default 5) |
| Rechazo tributario definitivo de SUNAT | **Nunca reintenta** — estado `RECHAZADO`, terminal |
| Bug/excepción no esperada | Reintenta igual que error temporal, pero debe alertar (no debe pasar desapercibido) |

Job idempotente: verifica el estado actual del comprobante antes de actuar (las colas garantizan entrega *at-least-once*, un mismo job puede ejecutarse dos veces).

Cola de fallos inspeccionable vía `php artisan queue:failed` + comando propio `InspeccionarComprobantesFallidosCommand` que cruza `jobs_failed` con comprobantes en estado `ERROR`.

No se implementa circuit breaker formal en V1 — el backoff + tope de intentos + cola de fallos inspeccionable es resiliencia suficiente sin la complejidad de un breaker dedicado. Se reevalúa con datos reales de fallos de SUNAT en producción.

## request_id a través de async

El `request_id` originado en el request HTTP se propaga como parte del payload del Job y se registra en cada evento/log generado por los workers — sin esto se pierde trazabilidad entre el log síncrono y el asíncrono, que es exactamente lo que la plataforma no puede permitirse perder.
