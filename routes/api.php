<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// No crear v2 hasta que exista una incompatibilidad real — ver
// docs/01_ARQUITECTURA.md. Cambios compatibles permanecen dentro de v1.
Route::prefix('v1')->group(__DIR__.'/api_v1.php');
