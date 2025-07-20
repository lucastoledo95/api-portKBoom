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
     * Configurações de expiração por tipo de token
     */
    private const TOKEN_EXPIRATION = [
        'access-token' => 15,      // 15 minutos
        'refresh-token' => 10080,  // 7 dias // pensando em colcoar um dia
        //'api-token' => 60,         // 1 hora // não tem mais
    ];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $chaveCache = 'ultima_execucao_limpeza_tokens';
            $cacheClearToken = (int) env('CACHE_CLEAR_TOKEN', 15);
            //dd(Cache::get($chaveCache));
            if (!Cache::has($chaveCache) ||
                Carbon::parse(Cache::get($chaveCache))->addMinutes($cacheClearToken)->isPast()) {
                foreach (self::TOKEN_EXPIRATION as $tokenName => $timeoutMinutes) {
                    $this->clearExpiredTokens($tokenName, $timeoutMinutes);
                }

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

    /**
     * Limpa tokens expirados de um tipo específico
     */
    private function clearExpiredTokens(string $tokenName, int $timeoutMinutes): void
    {
        PersonalAccessToken::query()
            ->where('name', $tokenName)
            ->where(function ($query) use ($timeoutMinutes) {
                $query->where(function ($q) use ($timeoutMinutes) {
                    $q->whereNull('last_used_at')
                      ->where('created_at', '<', now()->subMinutes($timeoutMinutes));
                })->orWhere(function ($q) use ($timeoutMinutes) {
                    $q->whereNotNull('last_used_at')
                      ->where('last_used_at', '<', now()->subMinutes($timeoutMinutes));
                });
            })
            ->delete();
    }
}
