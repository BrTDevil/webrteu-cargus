<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use WeBRTeu\Cargus\Exceptions\CargusException;
use WeBRTeu\Cargus\Models\CargusPudoPoint;
use WeBRTeu\Cargus\Requests\AwbPayload;
use WeBRTeu\Shipping\Contracts\Carrier;
use WeBRTeu\Shipping\Contracts\HasBulkTracking;
use WeBRTeu\Shipping\Contracts\HasCourierOrder;
use WeBRTeu\Shipping\Contracts\HasPickupPoints;
use WeBRTeu\Shipping\Data\AwbData;
use WeBRTeu\Shipping\Data\PickupPointData;
use WeBRTeu\Shipping\Data\PickupRequestData;
use WeBRTeu\Shipping\Data\QuoteData;
use WeBRTeu\Shipping\Data\ServiceData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Data\TrackingData;
use WeBRTeu\Shipping\Data\TrackingEventData;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

class CargusCarrier implements Carrier, HasBulkTracking, HasCourierOrder, HasPickupPoints
{
    public function __construct(private readonly CargusClient $client) {}

    public function name(): string
    {
        return 'cargus';
    }

    public function client(): CargusClient
    {
        return $this->client;
    }

    /**
     * Lista statică din config (Cargus nu expune un nomenclator de servicii).
     */
    public function services(): Collection
    {
        /** @var array<int|string, string> $services */
        $services = Config::array('cargus.services', []);
        $pudo = Config::get('cargus.pudo_service_id');

        return collect($services)->map(fn (string $name, int|string $id): ServiceData => new ServiceData(
            code: (string) $id,
            name: $name,
            pickupPoint: (string) $id === (string) $pudo || str_contains(strtolower($name), 'ship & go'),
        ))->values();
    }

    public function quote(ShipmentData $shipment): QuoteData
    {
        $payload = $this->payload()->make($shipment);
        $json = $this->array($this->client->post('/ShippingCalculation', [
            'FromLocalityId' => 0,
            'ToCountyName' => $payload['Recipient']['CountyName'],
            'ToLocalityName' => $payload['Recipient']['LocalityName'],
            'Parcels' => $payload['Parcels'],
            'Envelopes' => $payload['Envelopes'],
            'TotalWeight' => $payload['TotalWeight'],
            'DeclaredValue' => $payload['DeclaredValue'],
            'CashRepayment' => $payload['CashRepayment'],
            'BankRepayment' => $payload['BankRepayment'],
            'OpenPackage' => $payload['OpenPackage'],
            'SaturdayDelivery' => $payload['SaturdayDelivery'],
            'ServiceId' => $payload['ServiceId'],
            'PriceTableId' => Config::get('cargus.price_table_id'),
        ])->json());

        return new QuoteData(
            carrier: $this->name(),
            amount: Money::fromDecimal($this->decimal($json['GrandTotal'] ?? $json['Subtotal'] ?? 0)),
            serviceCode: $payload['ServiceId'] !== null ? (string) $payload['ServiceId'] : null,
            raw: $json,
        );
    }

    public function createShipment(ShipmentData $shipment): AwbData
    {
        $payload = $this->payload()->make($shipment);

        try {
            $response = $this->client->post('/Awbs', $payload);
        } catch (ConnectionException $exception) {
            throw new ShipmentStateUnknownException($this->name(), $shipment->reference, $exception);
        } catch (CargusException $exception) {
            if (str_contains($exception->getMessage(), '(HTTP 400)')) {
                throw ShipmentValidationException::withErrors($this->name(), ['general' => [$exception->getMessage()]], $exception);
            }

            throw $exception;
        }

        $awb = $response->json();
        $awb = is_scalar($awb) ? (string) $awb : '';

        if ($awb === '') {
            throw new CargusException('Cargus nu a întors numărul AWB: '.$response->body());
        }

        return new AwbData(awb: $awb, carrier: $this->name(), parcelNumbers: [$awb], raw: ['awb' => $awb, 'request' => $payload]);
    }

    /**
     * AwbDocuments întoarce PDF-ul fie binar, fie ca șir base64 în JSON. ⚠️ de verificat pe API real.
     */
    public function label(string $awb, LabelFormat $format = LabelFormat::A6Pdf): string
    {
        if ($format === LabelFormat::Zpl) {
            throw UnsupportedOperationException::for($this->name(), 'label ZPL');
        }

        $body = $this->client->get('/AwbDocuments', [
            'barCodes' => $awb,
            'type' => 'PDF',
            'format' => $format === LabelFormat::A4Pdf ? 0 : 1,
            'printMainOnce' => 1,
        ])->body();

        if (str_starts_with($body, '%PDF')) {
            return $body;
        }

        $decoded = base64_decode(trim((string) (json_decode($body, true) ?? $body), '"'), true);

        if ($decoded === false || ! str_starts_with($decoded, '%PDF')) {
            throw new CargusException('Cargus nu a întors o etichetă PDF validă pentru AWB-ul '.$awb.'.');
        }

        return $decoded;
    }

    public function track(string $awb): TrackingData
    {
        return $this->trackMany([$awb])->get($awb) ?? new TrackingData($awb, ShipmentStatus::Unknown, 'AWB negăsit la Cargus');
    }

    public function trackMany(array $awbs): Collection
    {
        $json = $this->array($this->client->get('/AwbTrace', ['barCode' => '['.implode(',', array_map(fn (string $awb): string => '"'.$awb.'"', $awbs)).']'])->json());

        /** @var Collection<string, TrackingData> $results */
        $results = collect();

        foreach ($this->list($json) as $row) {
            $awb = self::string($row, 'Code');

            if ($awb !== '') {
                $events = array_map($this->event(...), $this->list($row['Event'] ?? null));
                $results->put($awb, TrackingData::fromEvents($awb, $events, $row));
            }
        }

        return $results;
    }

    public function cancel(string $awb): bool
    {
        $this->client->delete('/Awbs', ['barCode' => $awb]);

        return true;
    }

    public function pickupPoints(?string $county = null, ?string $city = null): Collection
    {
        $points = CargusPudoPoint::query()->where('active', true)->exists()
            ? CargusPudoPoint::query()->where('active', true)->get()->map(fn (CargusPudoPoint $point): PickupPointData => self::pickupPoint($point->raw ?? []))
            : $this->remotePickupPoints();

        return $points
            ->filter(fn (PickupPointData $point): bool => ($county === null || Normalizer::same($point->county, $county))
                && ($city === null || Normalizer::same($point->city, $city)))
            ->values();
    }

    /**
     * @return Collection<int, PickupPointData>
     */
    public function remotePickupPoints(): Collection
    {
        return collect($this->list($this->array($this->client->get('/PudoPoints')->json())))
            ->map(fn (array $point): PickupPointData => self::pickupPoint($point))
            ->values();
    }

    /**
     * ⚠️ Structura exactă a comenzii de ridicare se confirmă în documentația V3.
     */
    public function orderPickup(PickupRequestData $request): string
    {
        $id = $this->client->post('/Orders', [
            'LocationId' => (int) Config::get('cargus.pickup_location_id'),
            'PickupStartDate' => $request->date->setTimeFromTimeString($request->fromHour)->format('Y-m-d\TH:i:s'),
            'PickupEndDate' => $request->date->setTimeFromTimeString($request->toHour)->format('Y-m-d\TH:i:s'),
            'Parcels' => $request->parcels,
            'TotalWeight' => max(1, (int) ceil($request->totalWeightKg)),
            'Observations' => (string) $request->observations,
        ], ['action' => 1])->json();

        return is_scalar($id) ? (string) $id : '';
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function rawPickupLocations(): array
    {
        return $this->list($this->array($this->client->get('/PickupLocations')->json()));
    }

    private function payload(): AwbPayload
    {
        return new AwbPayload(
            locationId: is_numeric($id = Config::get('cargus.pickup_location_id')) ? (int) $id : null,
            serviceId: is_numeric($service = Config::get('cargus.service_id')) ? (int) $service : null,
            pudoServiceId: is_numeric($pudo = Config::get('cargus.pudo_service_id')) ? (int) $pudo : null,
            codType: Config::string('cargus.cod_type', 'cash'),
            openPackageByDefault: Config::boolean('cargus.open_package', false),
        );
    }

    /**
     * @param  array<array-key, mixed>  $point
     */
    public static function pickupPoint(array $point): PickupPointData
    {
        $payment = $point['AcceptedPaymentType'] ?? null;

        return new PickupPointData(
            id: self::string($point, 'Id'),
            carrier: 'cargus',
            type: PickupPointType::Pudo,
            name: self::string($point, 'Name'),
            county: self::string($point, 'County'),
            city: self::string($point, 'City'),
            address: self::string($point, 'Address'),
            lat: is_numeric($point['Latitude'] ?? null) ? (float) $point['Latitude'] : null,
            lng: is_numeric($point['Longitude'] ?? null) ? (float) $point['Longitude'] : null,
            postalCode: self::string($point, 'PostalCode') !== '' ? self::string($point, 'PostalCode') : null,
            supportsCod: is_array($payment) && ((bool) ($payment['Cash'] ?? false) || (bool) ($payment['Card'] ?? false)),
            raw: $point,
        );
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function event(array $event): TrackingEventData
    {
        $code = self::string($event, 'EventId');
        $label = self::string($event, 'Description');
        $date = self::string($event, 'Date');

        return new TrackingEventData(
            status: CargusStatusMap::resolve($code, $label),
            code: $code,
            label: $label,
            occurredAt: $date !== '' ? CarbonImmutable::parse($date, 'Europe/Bucharest') : null,
            location: self::string($event, 'LocalityName') !== '' ? self::string($event, 'LocalityName') : null,
            raw: $event,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function array(mixed $json): array
    {
        return is_array($json) ? $json : [];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        return is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';
    }

    private function decimal(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '0';
    }
}
