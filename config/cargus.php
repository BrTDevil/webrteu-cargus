<?php

declare(strict_types=1);

return [

    'base_url' => env('CARGUS_BASE_URL', 'https://urgentcargus.azure-api.net/api'),

    /*
    | Primary key din urgentcargus.portal.azure-api.net, după aprobarea abonamentului UrgentOnlineAPI.
    */
    'subscription_key' => env('CARGUS_SUBSCRIPTION_KEY'),

    /*
    | Utilizatorul de client Cargus, pentru LoginUser (nu cel din WebExpress).
    */
    'username' => env('CARGUS_USERNAME'),
    'password' => env('CARGUS_PASSWORD'),

    'pickup_location_id' => env('CARGUS_PICKUP_LOCATION_ID'),
    'price_table_id' => env('CARGUS_PRICE_TABLE_ID'),
    'service_id' => env('CARGUS_SERVICE_ID'),
    'pudo_service_id' => env('CARGUS_PUDO_SERVICE_ID'),

    /*
    | Ramburs: cash (CashRepayment) | bank (BankRepayment, în cont).
    */
    'cod_type' => env('CARGUS_COD_TYPE', 'cash'),

    'open_package' => (bool) env('CARGUS_OPEN_PACKAGE', false),

    /*
    | Serviciile afișate de services(): id → nume.
    */
    'services' => [
        1 => 'Standard',
        34 => 'Economic Standard',
        38 => 'Ship & Go',
    ],

    'token_cache_ttl' => 60 * 60 * 20,
    'timeout' => (int) env('CARGUS_TIMEOUT', 15),

    'tables' => [
        'localities' => 'cargus_localities',
        'pudo_points' => 'cargus_pudo_points',
    ],

];
