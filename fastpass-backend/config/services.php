<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de Reconhecimento Facial (FastAPI + DeepFace)
    |--------------------------------------------------------------------------
    */

    'facial' => [
        'url'  => env('FACIAL_API_URL'),
        'fake' => env('FACIAL_API_FAKE', false),
    ],

];
