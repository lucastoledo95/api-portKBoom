<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $categorias = Categoria::ativas()->whereNull('parent_id')->get()->map(function ($categoria) {
            return $this->getChildrensParents($categoria);
        });

        if ($categorias->isEmpty()) {
            return response()->json(['ok' => false,'message' => 'Categoria não encontrada.',], 404);
        }
        return response()->json(['ok' => true, 'categorias' => $categorias], 200);

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
    public function show(Categoria $categoria)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Categoria $categoria)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categoria $categoria)
    {
        //
    }


    public function getPorSlug(string $slug)
    {
        $slugs = explode('/', $slug);
        $categoria = null;

        foreach ($slugs as $slugParte) {
            $query = Categoria::ativas()->where('slug', $slugParte);
            if ($categoria) {
                $query->where('parent_id', $categoria->id);
            } else {
                $query->whereNull('parent_id');
            }
            $categoria = $query->first();

            if (!$categoria) {
                return response()->json(['ok' => false,'message' => 'Categoria não encontrada.',], 404);
            }
        }
        return response()->json(['ok' => true, 'categorias' => $categoria], 200);
    }


    private function getChildrensParents($categoria)
    {
        $categoria->children = $categoria->children()->ativas()->get()->map(function ($child) {
            return $this->getChildrensParents($child);
        });

        return $categoria;
    }

}
