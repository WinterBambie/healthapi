<?php
class JwtHelper {

    // ✅ Lee desde variable de entorno — no hardcodeado
    private static function getSecret(): string {
        $s = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        return $s ?: 'HealthApi_S3cr3t_K3y_2025!';
    }

    public static function generate(array $payload): string {
        $secret = self::getSecret();
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body   = self::b64(json_encode($payload));
        $sig    = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        return "$header.$body.$sig";
    }

    public static function verify(string $token): ?array {
        $secret = self::getSecret();
        $parts  = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$h.$p", $secret, true));
        if (!hash_equals($expected, $s)) return null;
        $data = json_decode(self::b64d($p), true);
        if (!is_array($data)) return null;
        if (isset($data['exp']) && $data['exp'] < time()) return null;
        return $data;
    }

    private static function b64(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64d(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}