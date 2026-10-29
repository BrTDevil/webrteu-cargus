<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Requests;

use WeBRTeu\Cargus\Models\CargusLocality;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

/**
 * ShipmentData → corpul POST /Awbs. ⚠️ Câmpurile urmează „Documentatie API V3” și se confirmă pe portal.
 */
final readonly class AwbPayload
{
    public function __construct(
        private ?int $locationId,
        private ?int $serviceId,
        private ?int $pudoServiceId,
        private string $codType = 'cash',
        private bool $openPackageByDefault = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function make(ShipmentData $shipment): array
    {
        if ($shipment->sender instanceof AddressData) {
            throw UnsupportedOperationException::for('cargus', 'sender (expeditorul vine din CARGUS_PICKUP_LOCATION_ID)');
        }

        if ($this->locationId === null) {
            throw new ShipmentValidationException('Lipsește CARGUS_PICKUP_LOCATION_ID (vezi php artisan cargus:pickup-locations).');
        }

        $cod = Money::toFloat($shipment->codAmount);
        $payload = [
            'Sender' => ['LocationId' => $this->locationId],
            'Recipient' => $this->recipient($shipment),
            'Parcels' => $shipment->parcelCount(),
            'Envelopes' => $shipment->envelopes,
            'TotalWeight' => max(1, (int) ceil($shipment->totalWeightKg())),
            'DeclaredValue' => Money::toFloat($shipment->declaredValue),
            'CashRepayment' => $this->codType === 'bank' ? 0 : $cod,
            'BankRepayment' => $this->codType === 'bank' ? $cod : 0,
            'OpenPackage' => $shipment->openPackage || $this->openPackageByDefault,
            'SaturdayDelivery' => $shipment->saturdayDelivery,
            'PackageContent' => $shipment->contents,
            'Observations' => (string) $shipment->observations,
            'CustomString' => $shipment->reference,
            'ServiceId' => $this->serviceId($shipment),
        ];

        if ($shipment->isPickupPointDelivery()) {
            $payload['DeliveryPudoPoint'] = (int) $shipment->pickupPointId;
        }

        return $payload;
    }

    public function serviceId(ShipmentData $shipment): ?int
    {
        if ($shipment->serviceCode !== null && $shipment->serviceCode !== '') {
            return (int) $shipment->serviceCode;
        }

        return $shipment->isPickupPointDelivery() ? ($this->pudoServiceId ?? $this->serviceId) : $this->serviceId;
    }

    /**
     * @return array<string, mixed>
     */
    private function recipient(ShipmentData $shipment): array
    {
        $address = $shipment->recipient;
        $locality = CargusLocality::query()
            ->where('county_key', Normalizer::key($address->county))
            ->where('name_key', Normalizer::key($address->city))
            ->first();

        $recipient = [
            'Name' => $address->company ?? $address->name,
            'CountyName' => $locality instanceof CargusLocality ? $locality->county : $address->county,
            'LocalityName' => $locality instanceof CargusLocality ? $locality->name : $address->city,
            'AddressText' => trim($address->street.' nr. '.$address->number.' '.implode(' ', array_filter([
                $address->block !== null ? 'bl. '.$address->block : null,
                $address->staircase !== null ? 'sc. '.$address->staircase : null,
                $address->floor !== null ? 'et. '.$address->floor : null,
                $address->apartment !== null ? 'ap. '.$address->apartment : null,
            ]))),
            'ContactPerson' => $address->contactPerson ?? $address->name,
            'PhoneNumber' => Normalizer::phone($address->phone),
            'Email' => (string) $address->email,
            'CodPostal' => (string) $address->postalCode,
        ];

        if ($locality instanceof CargusLocality) {
            $recipient['CountyId'] = $locality->county_id;
            $recipient['LocalityId'] = $locality->locality_id;
        }

        return $recipient;
    }
}
