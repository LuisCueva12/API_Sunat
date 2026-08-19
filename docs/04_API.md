# API v1

Base: `/api/v1`. Autenticación: OAuth2 `client_credentials` (Laravel Passport) — `Authorization: Bearer <access_token>`, obtenido en `POST /oauth/token` con `client_id`+`client_secret` (ver [06_SEGURIDAD.md](06_SEGURIDAD.md)). Idempotencia: header opcional `Idempotency-Key` en los 4 endpoints de emisión.

Detalle completo (request/response/errores por campo) se agrega en OpenAPI (`openapi/openapi.yaml`) a medida que cada endpoint se implementa — este documento es el contrato de alto nivel, la fuente exacta es el YAML.

## Envoltorio de respuesta

Éxito:

```json
{
  "data": { "id": "uuid", "tipo": "factura", "serie": "F001", "numero": 125, "estado": "REGISTRADO" },
  "meta": { "request_id": "..." }
}
```

Error (nunca stack traces ni excepciones internas):

```json
{
  "error": { "codigo": "DATOS_INVALIDOS", "mensaje": "Los datos enviados contienen errores.", "detalles": [] }
}
```

## Endpoints

| Método | Ruta | Body específico por tipo | Notas |
|---|---|---|---|
| POST | `/facturas` | sí | requiere RUC del receptor |
| POST | `/boletas` | sí | DNI o sin documento según monto |
| POST | `/notas-credito` | sí | requiere `comprobante_referencia_id` + motivo |
| POST | `/notas-debito` | sí | requiere `comprobante_referencia_id` + motivo |
| GET | `/comprobantes` | — | filtros: `tipo`, `estado`, `serie`, `fecha_desde`, `fecha_hasta`, paginado |
| GET | `/comprobantes/{id}` | — | detalle completo |
| GET | `/comprobantes/{id}/estado` | — | polling barato: solo estado + timestamps |
| GET | `/comprobantes/{id}/eventos` | — | trazabilidad completa |
| POST | `/comprobantes/{id}/reintentar` | — | 409 si el estado no es `ERROR` |
| GET | `/comprobantes/{id}/xml` \| `/cdr` \| `/pdf` | — | descarga desde disco privado |
| GET | `/empresas/actual` | — | info de la empresa dueña de la integración autenticada |
| GET | `/up` | — | health check nativo de Laravel |

Emisión específica por tipo (contrato de entrada difiere genuinamente) + consulta/ciclo de vida genérico sobre `/comprobantes` (recurso uniforme una vez creado). Justificación completa en [01_ARQUITECTURA.md](01_ARQUITECTURA.md) §7.

En los cuatro endpoints de emisión, `receptor_razon_social` es opcional. Si se omite o llega vacío, el caso de uso busca un cliente de la misma empresa por `receptor_tipo_documento` + `receptor_numero_documento` y copia su razón social al snapshot del comprobante. Si no existe, responde `422` con código `COMPROBANTE_INVALIDO`. Cuando se envía una razón social no vacía, esta prevalece y no se consulta el maestro de clientes.

Webhooks: gestión solo desde el panel en V1, no expuesta por API todavía (se agrega si un cliente real lo pide — evita superficie sin uso).

## Idempotencia

`Idempotency-Key` + hash de la solicitud, con scope `empresa_id + endpoint`. Mismo key + mismo body → responde la respuesta cacheada tal cual. Mismo key + body distinto → `422`. Ver [03_BASE_DATOS.md](03_BASE_DATOS.md) tabla `idempotency_keys`.

## Autorización

Scopes por integración (catálogo `ScopeApi`): `comprobantes:crear`, `comprobantes:leer`, `comprobantes:reintentar`. `empresa_id` siempre se deriva del cliente OAuth autenticado — **nunca** se acepta como campo del body/query (evita IDOR entre tenants).
