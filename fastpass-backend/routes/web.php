<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'app'    => 'FastPass API',
    'versao' => '1.0.0 (MVP)',
    'status' => 'online',
]));
