<?php
class JwtHelper {
    private static $secret = 'HealthApi_S3cr3t_K3y_2025!';

    public static function generate($payload) {
        $header  = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body    = self::b64(json_encode($payload));
        $sig     = self::b64(hash_hmac('sha256', "$header.$body", self::$secret, true));
        return "$header.$body.$sig";
    }

    public static function verify($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$h.$p", self::$secret, true));
        if (!hash_equals($expected, $s)) return null;
        $data = json_decode(self::b64d($p), true);
        if (isset($data['exp']) && $data['exp'] < time()) return null;
        return $data;
    }

    private static function b64($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function b64d($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
