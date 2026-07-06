<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmbarqueController;
use App\Http\Controllers\ExcursaoController;
use App\Http\Controllers\FacialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FastPass API - Rotas (MVP)
|--------------------------------------------------------------------------
| Fluxo do TCC:
| 1. Cadastro .................. POST   /api/auth/register
| 2. Login ..................... POST   /api/auth/login
| 3. Dashboard (excursões) ..... GET    /api/excursoes
| 4. Compra efetuada ........... POST   /api/compras
| 5. Registro da facial ........ POST   /api/compras/{compra}/facial
| 6. Validação de facial ....... POST   /api/embarque/facial
|    (alternativa por QR Code) . POST   /api/embarque/qrcode
| 7. Viagem concluída .......... POST   /api/excursoes/{excursao}/concluir
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
        Route::post('/facial', [AuthController::class, 'registrarFacial']); // cadastro da face (por usuário)
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

    // Embarque inteligente
    Route::post('/embarque/facial', [EmbarqueController::class, 'porFacial']);
    Route::post('/embarque/qrcode', [EmbarqueController::class, 'porQrCode']);
    Route::post('/embarque/manual', [EmbarqueController::class, 'porManual']);

    // Gestão da excursão (visão da empresa)
    Route::post('/excursoes', [ExcursaoController::class, 'store']);
    Route::put('/excursoes/{excursao}', [ExcursaoController::class, 'update']);
    Route::post('/excursoes/{excursao}/passageiros', [ExcursaoController::class, 'adicionarPassageiro']);
    Route::get('/excursoes/{excursao}/painel', [ExcursaoController::class, 'painel']);
    Route::post('/excursoes/{excursao}/concluir', [ExcursaoController::class, 'concluir']);

    // Painel da empresa: métricas agregadas (visão geral + relatórios)
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
