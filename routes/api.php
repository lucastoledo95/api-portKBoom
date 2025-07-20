<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoriaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        // nega acesso ao refresh tokens
        if ($request->user()->currentAccessToken()->name === 'refresh-token') {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return $request->user();
    });
});



Route::prefix('auth/clientes')->name('auth.clientes.')
->controller(AuthController::class)->group(function () {
    Route::post('cadastro', 'register')->name('cadastro')->middleware('throttle:6,1');
    Route::post('login', 'login')->name('login')->middleware('throttle:5,1');
    Route::post('sair', 'logout')->name('sair')->middleware(['throttle:5,1']);
    Route::post('refresh-token', 'refreshToken')->name('refreshToken');

    // redefinir senha
    // minha conta
});

Route::get('/status', function () {
    return response()->json(['status' => 'online']);
})->name('status');

Route::prefix('categoria')->name('categoria.')
->controller(CategoriaController::class)->group(function () {
    Route::get('', 'index')->name('index');
    Route::get('{slug}', [CategoriaController::class, 'getPorSlug'])->where('slug', '.*');
});
