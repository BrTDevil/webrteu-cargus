<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Facades\Shipping;

it('sends both headers on every request', function (): void {
    fakeCargus([CARGUS.'/AwbTrace*' => Http::response(fixtureFile('awb-trace.json'))]);

    Shipping::carrier('cargus')->track('1234567890');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/LoginUser')
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'sub-key')
        && $request['UserName'] === 'test-user');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/AwbTrace')
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'sub-key')
        && $request->hasHeader('Authorization', 'Bearer cargus-token-1'));
});
