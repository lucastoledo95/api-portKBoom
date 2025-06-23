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
            $timeout = 60; // tempo de expirar token
            $cacheClearToken = 5; // a cada minutos apos primeiro acesso a funcao, salva no cache e fica executando conforme chama a funcao

            if (
                !Cache::has($chaveCache) ||
                Carbon::parse(Cache::get($chaveCache))
                    ->addMinutes($cacheClearToken)
                    ->isPast()
            ) {
                PersonalAccessToken::query()
                    ->where(function ($query) use ($timeout) {
                        $query->where(function ($q) use ($timeout) {
                            $q->whereNull('last_used_at')
                            ->where('created_at', '<', now()->subMinutes($timeout));
                        })->orWhere(function ($q) use ($timeout) {
                            $q->whereNotNull('last_used_at')
                            ->where('last_used_at', '<', now()->subMinutes($timeout));
                        });
                    })
                    ->delete();

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
