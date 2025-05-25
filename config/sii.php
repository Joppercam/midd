<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SII Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Chilean Servicio de Impuestos Internos (SII)
    | electronic invoicing integration.
    |
    */

    'environment' => env('SII_ENVIRONMENT', 'certification'),

    'endpoints' => [
        'certification' => [
            'auth_seed' => 'https://maullin.sii.cl/cgi_AUT2000/CAutInicio.cgi',
            'auth_token' => 'https://maullin.sii.cl/cgi_AUT2000/CAutAvanzada.cgi',
            'dte_upload' => 'https://maullin.sii.cl/cgi_dte/UPL/DTEUpload',
            'dte_status' => 'https://maullin.sii.cl/cgi_dte/CJO_QueryEstUp.cgi',
        ],
        'production' => [
            'auth_seed' => 'https://palena.sii.cl/cgi_AUT2000/CAutInicio.cgi',
            'auth_token' => 'https://palena.sii.cl/cgi_AUT2000/CAutAvanzada.cgi',
            'dte_upload' => 'https://palena.sii.cl/cgi_dte/UPL/DTEUpload',
            'dte_status' => 'https://palena.sii.cl/cgi_dte/CJO_QueryEstUp.cgi',
        ],
    ],

    'document_types' => [
        33 => 'Factura Electrónica',
        34 => 'Factura No Afecta o Exenta Electrónica',
        39 => 'Boleta Electrónica',
        41 => 'Boleta No Afecta o Exenta Electrónica',
        56 => 'Nota de Débito Electrónica',
        61 => 'Nota de Crédito Electrónica',
    ],

    'xsd_schemas' => [
        'dte' => storage_path('app/sii/schemas/DTE_v10.xsd'),
        'envio' => storage_path('app/sii/schemas/EnvioDTE_v10.xsd'),
        'signature' => storage_path('app/sii/schemas/xmldsignature_v10.xsd'),
    ],

    'certificates_path' => storage_path('app/sii/certificates'),

    'cache' => [
        'token_ttl' => 60, // minutes
        'token_key' => 'sii_token:',
    ],

    'timeout' => 30, // seconds

    'retry' => [
        'times' => 3,
        'sleep' => 1000, // milliseconds
    ],
];