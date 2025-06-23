<?php
namespace App\Services;



class Enc
{
    private string $key;
    private string $cipher = 'AES-256-CBC';

    public function __construct()
    {
       $this->key = env('KEY_CRIPTOGRAFIA');
        if (!$this->key || strlen($this->key) !== 32) {
            throw new \Exception('Chave invalida');
        }
    }

    public function encriptar(string $data): string
    {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            return false;
        }

        $payload = $iv . $encrypted;
        $hmac = hash_hmac('sha256', $payload, $this->key, true); 

        return $this->base64url_encode($hmac . $payload);
    }

    public function desencriptar(string $data): string|false
    {
        $data = $this->base64url_decode($data);

        $hmacLength = 32;
        $hmac = substr($data, 0, $hmacLength);
        $payload = substr($data, $hmacLength);

        $calculatedHmac = hash_hmac('sha256', $payload, $this->key, true);

        if (!hash_equals($hmac, $calculatedHmac)) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($payload, 0, $ivLength);
        $encrypted = substr($payload, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);

        return $decrypted === false ? false : $decrypted;
    }

    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($data);
    }
}