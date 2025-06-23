<?php

namespace App\Http\Controllers\API;

use App\Services\Enc;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;


class ProdutoController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
       //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function encriptado(string $id, Enc $enc)
    {
        $idencriptado =  $enc->encriptar($id);

        return response()->json(['id_encriptado' => $idencriptado]);
    }


    public function desencriptado(string $id, Enc $enc)
    {
        $iddesencriptado =  $enc->desencriptar($id);

        return response()->json(['id_desencriptado' => $iddesencriptado]);
    }


}
