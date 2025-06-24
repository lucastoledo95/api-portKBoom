<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\EncTrait;

class EncDescriptograrToken
{
    use EncTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7); // remover o 'Bearer '
            try {
                $token = $this->desencriptado($token);
                $request->headers->set('Authorization', 'Bearer ' . $token);

            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => 'Token inválido'], 401);
            }
        }

        return $next($request);
    }
}
