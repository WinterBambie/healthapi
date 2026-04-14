<?php

class JwtHelper {

    // Cambia esto por una clave larga y secreta en producción
    private static $secret = 'HealthApi_S3cr3t_K3y_2025!';

    // ── Generar token ─────────────────────────────────────────────────────────

    public static function generate($payload) {
        $header  = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64url(json_encode($payload));
        $sig     = self::base64url(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        return "$header.$payload.$sig";
    }

    // ── Verificar y decodificar token ─────────────────────────────────────────

    public static function verify($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;

        $expectedSig = self::base64url(hash_hmac('sha256', "$header.$payload", self::$secret, true));
        if (!hash_equals($expectedSig, $sig)) return null;

        $data = json_decode(self::base64urlDecode($payload), true);

        // Verificar expiración
        if (isset($data['exp']) && $data['exp'] < time()) return null;

        return $data;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}