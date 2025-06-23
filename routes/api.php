<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/clientes')
    ->name('auth.clientes.')
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('cadastro', 'register')->name('cadastro')->middleware('throttle:6,1');
        Route::post('login', 'login')->name('login')->middleware('throttle:5,1');
        Route::post('sair', 'logout')->name('sair')->middleware(['throttle:5,1', 'auth:sanctum']);
        // redefinir senha e email
    });


Route::middleware(['auth:sanctum',])
    ->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });