<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Shipping\Exceptions\AuthenticationException;
use WeBRTeu\Shipping\Facades\Shipping;

function cargusLogins(): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/LoginUser'))->count();
}

it('re-logins only once on 401', function (): void {
    fakeCargus([
        CARGUS.'/LoginUser' => Http::sequence()->push('"old"')->push('"new"'),
        CARGUS.'/AwbTrace*' => fn (Request $request) => $request->hasHeader('Authorization', 'Bearer old')
            ? Http::response('', 401)
            : Http::response(fixtureFile('awb-trace.json')),
    ]);

    expect(Shipping::carrier('cargus')->track('1234567890')->awb)->toBe('1234567890')
        ->and(cargusLogins())->toBe(2);
});

it('gives up after the second 401', function (): void {
    fakeCargus([CARGUS.'/AwbTrace*' => Http::response('', 401)]);

    expect(fn () => Shipping::carrier('cargus')->track('1'))->toThrow(AuthenticationException::class);
    expect(cargusLogins())->toBe(2);
});

it('reports a failed login and a login timeout', function (): void {
    Http::fake([CARGUS.'/LoginUser' => Http::response(['Message' => 'Invalid'], 400)]);
    expect(fn () => Shipping::carrier('cargus')->track('1'))->toThrow(AuthenticationException::class, 'CARGUS_USERNAME');

    Http::fake([CARGUS.'/LoginUser' => fn () => throw new ConnectionException('timeout')]);
    expect(fn () => Shipping::carrier('cargus')->track('1'))->toThrow(CargusException::class, 'LoginUser');
});

it('reports API errors and never logs secrets', function (mixed $body, string $message): void {
    Log::spy();
    fakeCargus([CARGUS.'/AwbTrace*' => Http::response($body, 500)]);

    expect(fn () => Shipping::carrier('cargus')->track('1'))->toThrow(CargusException::class, $message);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $m, array $context): bool => ! str_contains((string) json_encode($context), 'secret-pass'));
})->with([
    ['"Eroare internă"', 'Eroare internă'],
    [['Message' => 'Server down'], 'Server down'],
    ['', 'eroare necunoscută'],
    ['<html>', '<html>'],
]);
