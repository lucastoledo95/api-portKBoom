<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearInactiveTokens
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $chaveCache = 'ultima_execucao_limpeza_tokens';
            $timeout = (int) env('EXPIRAR_TOKEN',60); 
            $cacheClearToken = (int) env('CACHE_CLEAR_TOKEN',5);  // time para verificar, assim não derruba desempenho em grande escala
            // a cada acesso na função que chama no grupo da api, salva no cache e verifica clear token
   
            if (!Cache::has($chaveCache) || 
            Carbon::parse(Cache::get($chaveCache))->addMinutes($cacheClearToken)->isPast()) 
            {
                PersonalAccessToken::query()->where(function ($query) use ($timeout) {
                    $query->where(function ($q) use ($timeout) {
                        $q->whereNull('last_used_at')
                        ->where('created_at', '<', now()->subMinutes($timeout));
                    })->orWhere(function ($q) use ($timeout) {
                        $q->whereNotNull('last_used_at')
                        ->where('last_used_at', '<', now()->subMinutes($timeout));
                    });
                })->delete();

                Cache::put($chaveCache, now(), now()->addMinutes($cacheClearToken));
            }
                
        } catch (\Throwable $e) {
            if (app()->environment('local')) {
                throw $e;
            }

            return response()->json([
                'ok' => false,
                'msg' => 'Erro ao limpar tokens inativos DB.'
            ], 500);
        }

                return $next($request);
    }
}
