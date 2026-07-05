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

];
