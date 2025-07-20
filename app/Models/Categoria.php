<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use SoftDeletes;

    protected $fillable = [ // campos que precisam ser preenchidos para salvos e visualizados
        'nome',
        'slug',
        'parent_id',
        'ativo',
    ];

    protected $hidden = [
        'id',
        'parent_id',
        'deleted_at',
         'ativo',
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'parent_id' => 'integer',
        'deleted_at' => 'datetime',
    ];


    // slug automatico ao salvar - testar
    public static function booted()
    {
        static::saving(function ($categoria) {
            if (empty($categoria->slug) && !empty($categoria->nome)) {
                $categoria->slug = Str::slug($categoria->nome);
            }
        });
    }

    // Categoria pai
    public function parent()
    {
        return $this->belongsTo(Categoria::class, 'parent_id')->where('ativo', true); // 1 pra 1
    }

    // Categorias filhas
    public function children()
    {
        return $this->hasMany(Categoria::class, 'parent_id')->where('ativo', true); // 1 pra n
    }

 
    public function scopeAtivas($query)
    { // forço que sempre deve ser ativo a categoria PAI para visualizar a categoria Filha.
        return $query->where('ativo', true)->where(function($subquery) {
                        $subquery->whereNull('parent_id')->orWhereHas('parent', function($subquery2) {
                            $subquery2->where('ativo', true);
                        });
                    });
    }


}
