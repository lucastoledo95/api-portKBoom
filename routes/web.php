<?php

use App\Http\Controllers\API\ProdutoController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return redirect('404');

});

// para teste 
Route::get('produto/encriptar/{id_user}', [ProdutoController::class, 'encriptado']);

// para teste 
Route::get('produto/desencriptar/{id_user}', [ProdutoController::class, 'desencriptado']);