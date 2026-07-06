<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de Reconhecimento Facial (FastAPI + DeepFace)
    |--------------------------------------------------------------------------
    */

    'facial' => [
        'url'  => env('FACIAL_API_URL'),
        'key'  => env('FACIAL_API_KEY'),
        'fake' => env('FACIAL_API_FAKE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagamento Pix (Mercado Pago)
    |--------------------------------------------------------------------------
    | Sem access token (ou com MERCADOPAGO_FAKE=true) o checkout opera em modo
    | simulado. Use o token de TESTE (sandbox) para validar com QR real.
    */
    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'fake'         => env('MERCADOPAGO_FAKE', false),
    ],

];
