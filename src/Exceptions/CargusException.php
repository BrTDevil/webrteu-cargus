<?php

declare(strict_types=1);

namespace WeBRTeu\Cargus\Exceptions;

use WeBRTeu\Shipping\Exceptions\ShippingException;

class CargusException extends ShippingException
{
    public static function missingSubscriptionKey(): self
    {
        return new self('Lipsește CARGUS_SUBSCRIPTION_KEY: abonează-te la UrgentOnlineAPI pe urgentcargus.portal.azure-api.net și copiază Primary key din profil.');
    }
}
