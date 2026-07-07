<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\EmbarqueController;
use App\Http\Controllers\ExcursaoController;
use App\Http\Controllers\FacialController;
use App\Http\Controllers\MotoristaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FastPass API - Rotas (MVP)
|--------------------------------------------------------------------------
| Fluxo do passageiro:
| 1. Cadastro .................. POST   /api/auth/register
| 2. Login ..................... POST   /api/auth/login
| 3. Dashboard (excursões) ..... GET    /api/excursoes
| 4. Compra efetuada ........... POST   /api/compras
| 5. Registro da facial ........ POST   /api/compras/{compra}/facial
|
| Fluxo do motorista (embarque):
| 6. Viagens do motorista ...... GET    /api/motorista/excursoes
| 7. Lista de embarque ......... GET    /api/motorista/excursoes/{excursao}/embarque
| 8. Validação de facial ....... POST   /api/embarque/facial
|    (alternativa por QR Code) . POST   /api/embarque/qrcode
|    (conferência manual) ...... POST   /api/embarque/manual
| 9. Encerrar embarque ......... POST   /api/motorista/excursoes/{excursao}/concluir
*/

// ---------- Rotas públicas ----------
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ---------- Rotas autenticadas ----------
Route::middleware('auth:sanctum')->group(function () {

    // Autenticação / sessão
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Dashboard: excursões disponíveis para compra
    Route::get('/excursoes', [ExcursaoController::class, 'index']);
    Route::get('/excursoes/{excursao}', [ExcursaoController::class, 'show']);

    // Compras do passageiro
    Route::get('/compras', [CompraController::class, 'index']);
    Route::post('/compras', [CompraController::class, 'store']);
    Route::get('/compras/{compra}', [CompraController::class, 'show']);

    // Registro da biometria facial vinculada à compra
    Route::post('/compras/{compra}/facial', [FacialController::class, 'registrar']);

    // Gestão da excursão (visão da empresa/administrador)
    Route::middleware('role:administrador')->group(function () {
        Route::get('/excursoes/{excursao}/painel', [ExcursaoController::class, 'painel']);
        Route::post('/excursoes/{excursao}/concluir', [ExcursaoController::class, 'concluir']);
    });

    // ---------- Motorista: viagens atribuídas e embarque ----------
    Route::middleware('role:motorista,administrador')->group(function () {
        Route::prefix('motorista')->group(function () {
            Route::get('/excursoes', [MotoristaController::class, 'excursoes']);
            Route::get('/excursoes/{excursao}/embarque', [MotoristaController::class, 'embarque']);
            Route::post('/excursoes/{excursao}/concluir', [MotoristaController::class, 'concluir']);
        });

        // Embarque inteligente (facial / QR / manual)
        Route::post('/embarque/facial', [EmbarqueController::class, 'porFacial']);
        Route::post('/embarque/qrcode', [EmbarqueController::class, 'porQrCode']);
        Route::post('/embarque/manual', [EmbarqueController::class, 'porManual']);
    });
});
