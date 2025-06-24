<?php


namespace App\Traits;

use App\Services\Enc;

trait EncTrait
{
    public function encriptado(string $value): string
    {
        return app(Enc::class)->encriptar($value);
    }

    public function desencriptado(string $value): string
    {
        return app(Enc::class)->desencriptar($value);
    }
}