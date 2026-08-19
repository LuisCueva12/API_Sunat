# Colas

Driver: Redis (bloqueado en desarrollo hasta instalar `ext-redis`/servidor — ver `docs/01_ARQUITECTURA.md` §12). La API nunca espera el pipeline SUNAT — responde `202` tras `REGISTRADO` y encola.

## Jobs

Implementado, un solo Job cubriendo generación→firma→envío→interpretación:

```text
ProcesarComprobante (app/Jobs)
  → resuelve ProcesarEnvioComprobante (Application) del contenedor y lo invoca
  → GeneradorXmlFirmado: genera + firma XML (Greenter)
  → AlmacenPrivado: guarda el XML firmado antes de arriesgar la llamada de red
  → FabricaEnviadorComprobante + EnviadorComprobanteElectronico: envía a SUNAT
  → guarda CDR, transiciona el estado (ACEPTADO/ACEPTADO_CON_OBSERVACIONES/
    RECHAZADO/ERROR) según ResultadoEnvio
```

Se consolidó en un solo Job lo que `docs/01_ARQUITECTURA.md` original planteaba como 3 (ProcesarComprobante/EnviarComprobanteSunat/ProcesarRespuestaSunat): la orquestación real vive en el caso de uso `ProcesarEnvioComprobante` (Application), no en el Job — dividirlo en más Jobs solo movería código, no simplificaría nada, y el propio principio de "no crear docenas de jobs diminutos" ya pedía evitar la fragmentación excesiva. `EnviarWebhook` sigue pendiente (Fase 7, cuando exista el módulo de webhooks).

Despachado desde `EmitirComprobanteBase` (los 4 casos de uso `Emitir*`) vía el puerto `DespachadorProcesamiento` — nunca `Bus::dispatch()` directo desde Application, que no depende de Illuminate.

## Reintentos

`ProcesarComprobante::$tries = 5`, backoff `[10, 30, 60, 300]` segundos.

El Job implementa unicidad por `empresa_id + comprobante_id` durante una hora. Varias solicitudes de reintento concurrentes pueden responder que el reintento fue programado, pero Laravel solo conserva un Job activo para ese comprobante.

| Tipo de fallo | Comportamiento |
|---|---|
| Red / timeout / SUNAT no disponible / certificado o credenciales faltantes | `ProcesarEnvioComprobante` marca el comprobante en `ERROR` (reintentable) y deja que Laravel reintente el Job según `$tries`/`backoff()` |
| Rechazo tributario definitivo de SUNAT | **Nunca reintenta** — estado `RECHAZADO`, terminal, el Job termina exitosamente (no es un fallo del Job, es un resultado de negocio) |

El Job es idempotente por diseño: `ProcesarEnvioComprobante::iniciarProcesamiento()` verifica el estado actual antes de actuar — si el comprobante ya está en un estado terminal (ACEPTADO/ACEPTADO_CON_OBSERVACIONES/RECHAZADO), no hace nada. Cubre tanto reintentos legítimos (estado `ERROR`) como la entrega *at-least-once* de las colas (un mismo Job ejecutado dos veces).

Pendiente (Fase 9): comando propio para inspeccionar comprobantes en `ERROR` cruzado con `queue:failed`.

No se implementa circuit breaker formal en V1 — el backoff + tope de intentos es resiliencia suficiente sin la complejidad de un breaker dedicado. Se reevalúa con datos reales de fallos de SUNAT en producción.

## request_id a través de async

El middleware HTTP asigna un `request_id`, los controladores lo incluyen en `EmitirComprobanteInput` y `EmitirComprobanteBase` lo entrega al despachador. `ProcesarComprobante` conserva el valor serializado y, al ejecutarse, comparte con el logger el contexto `request_id`, `empresa_id` y `comprobante_id`. Laravel limpia ese contexto entre Jobs para impedir que un worker reutilizado mezcle la trazabilidad de comprobantes distintos.
