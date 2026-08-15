<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\ValueObjects\Ruc;

it('acepta un RUC con formato y dígito verificador válidos', function () {
    // RUC de ejemplo cuyo dígito verificador es consistente con el
    // algoritmo módulo 11 implementado (no verificado contra un RUC real).
    $ruc = new Ruc('20100070970');

    expect($ruc->valor())->toBe('20100070970')
        ->and($ruc->esPersonaJuridica())->toBeTrue();
});

it('rechaza un RUC con longitud incorrecta', function () {
    new Ruc('123');
})->throws(InvalidArgumentException::class);

it('rechaza un RUC con prefijo inválido', function () {
    new Ruc('99100070971');
})->throws(InvalidArgumentException::class);

it('rechaza un RUC con dígito verificador incorrecto', function () {
    new Ruc('20100070971');
})->throws(InvalidArgumentException::class);

it('compara RUCs por valor', function () {
    $a = new Ruc('20100070970');
    $b = new Ruc('20100070970');

    expect($a->equals($b))->toBeTrue();
});
