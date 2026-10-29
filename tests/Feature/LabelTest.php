<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Facades\Shipping;

it('returns the PDF whether it comes binary or as base64', function (string $body, LabelFormat $format): void {
    fakeCargus([CARGUS.'/AwbDocuments*' => Http::response($body)]);

    expect(Shipping::carrier('cargus')->label('1234567890', $format))->toBe(fixtureFile('awb-documents.pdf'));
})->with([
    'binar A6' => [fn (): string => fixtureFile('awb-documents.pdf'), LabelFormat::A6Pdf],
    'base64 în JSON' => [fn (): string => json_encode(base64_encode(fixtureFile('awb-documents.pdf'))), LabelFormat::A4Pdf],
    'base64 simplu' => [fn (): string => base64_encode(fixtureFile('awb-documents.pdf')), LabelFormat::A6Pdf],
]);

it('rejects something that is not a PDF', function (): void {
    fakeCargus([CARGUS.'/AwbDocuments*' => Http::response('"bm90IGEgcGRm"')]);

    Shipping::carrier('cargus')->label('1');
})->throws(CargusException::class, 'etichetă PDF');

it('does not support ZPL', function (): void {
    Shipping::carrier('cargus')->label('1', LabelFormat::Zpl);
})->throws(UnsupportedOperationException::class);

it('cancels an AWB', function (): void {
    fakeCargus([CARGUS.'/Awbs?*' => Http::response('true')]);

    expect(Shipping::carrier('cargus')->cancel('1234567890'))->toBeTrue();
});
