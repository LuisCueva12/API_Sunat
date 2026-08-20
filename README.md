# API de Facturación Electrónica

Plataforma SaaS multiempresa para emitir Facturas, Boletas, Notas de Crédito y Notas de Débito electrónicas mediante SUNAT SEE - Del Contribuyente.

Incluye API OAuth2 para integraciones, panel interno `/admin`, panel de empresa `/app`, procesamiento asíncrono, XML UBL firmado, CDR, ticket PDF con QR, trazabilidad y webhooks firmados.

## Requisitos

- PHP 8.3 o superior con `openssl`, `soap`, `zip`, `dom` y `mbstring`.
- PostgreSQL 18.
- Redis 7 para colas y caché.
- Composer 2.

## Instalación local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work --tries=5
php artisan serve
```

Los usuarios de demostración solo se crean cuando `APP_ENV=local`. Consulta [docs/09_DESPLIEGUE.md](docs/09_DESPLIEGUE.md) antes de desplegar.

## API

El contrato ejecutable está en [openapi/openapi.yaml](openapi/openapi.yaml). La API vive bajo `/api/v1` y usa OAuth2 `client_credentials` con estos scopes:

- `comprobantes:crear`
- `comprobantes:leer`
- `comprobantes:reintentar`

Los cuatro endpoints de emisión admiten `Idempotency-Key`, aislada por empresa y endpoint. XML, CDR, PDF, eventos y estado se descargan o consultan siempre dentro del tenant autenticado.

## Verificación

```bash
php artisan test
vendor/bin/phpstan analyse --no-progress
vendor/bin/deptrac analyse --no-progress
vendor/bin/pint --test
composer validate --no-check-publish
```

## Alcance tributario V1

La V1 soporta operaciones gravadas con IGV 18 %, pago al contado y monedas PEN/USD. No pretende ser un ERP: inventario, compras, almacenes y POS están fuera de alcance. La comunicación de baja y otros regímenes tributarios no deben considerarse implementados hasta aparecer expresamente en OpenAPI y en [docs/05_SUNAT.md](docs/05_SUNAT.md).

## Documentación

- [Estado real del proyecto](docs/00_ESTADO_PROYECTO.md)
- [Arquitectura](docs/01_ARQUITECTURA.md)
- [Contrato API](docs/04_API.md)
- [Integración SUNAT](docs/05_SUNAT.md)
- [Seguridad](docs/06_SEGURIDAD.md)
- [Colas](docs/07_COLAS.md)
- [Webhooks](docs/08_WEBHOOKS.md)
- [Despliegue](docs/09_DESPLIEGUE.md)
- [Operación](docs/10_OPERACION.md)

## Licencia

Este proyecto fue creado por [LuisCueva12](https://github.com/LuisCueva12) y se distribuye bajo la [Licencia MIT](LICENSE). Puedes usarlo, modificarlo, distribuirlo y tomarlo como base para otros proyectos, siempre que conserves el aviso de copyright y la licencia original.

Las contribuciones son bienvenidas y se publicarán bajo los mismos términos.
