<?php

/**
 * JwtHelper
 * Xử lý JWT thuần PHP bằng HMAC-SHA256
 */
class JwtHelper
{
    private static ?string $secretKey = null;

    private static int $defaultTTL = 604800; // 7 ngày

    /**
     * Lấy secret key từ biến môi trường hoặc file .env
     */
    private static function getSecretKey(): string
    {
        if (self::$secretKey !== null) {
            return self::$secretKey;
        }

        $secret = getenv('JWT_SECRET');

        // Nếu chưa có biến môi trường thì đọc file .env
        if (!$secret) {
            $envFile = dirname(__DIR__) . '/.env';

            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    if (str_starts_with($line, 'JWT_SECRET=')) {
                        $secret = trim(substr($line, strlen('JWT_SECRET=')));

                        // Bỏ dấu quote nếu có
                        $secret = trim($secret, "\"'");
                        break;
                    }
                }
            }
        }

        if (!$secret || strlen($secret) < 32) {
            throw new RuntimeException(
                'JWT_SECRET chưa được cấu hình hoặc có độ dài dưới 32 ký tự.'
            );
        }

        self::$secretKey = $secret;

        return self::$secretKey;
    }

    /**
     * Base64 URL-safe encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(
            strtr(base64_encode($data), '+/', '-_'),
            '='
        );
    }

    /**
     * Base64 URL-safe decode
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(
            strtr($data, '-_', '+/'),
            true
        );

        if ($decoded === false) {
            throw new RuntimeException('JWT Base64 không hợp lệ.');
        }

        return $decoded;
    }

    /**
     * Tạo JWT
     */
    public static function encode(array $payload, ?int $ttl = null): string
    {
        $secretKey = self::getSecretKey();

        $header = self::base64UrlEncode(
            json_encode(
                [
                    'alg' => 'HS256',
                    'typ' => 'JWT'
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + ($ttl ?? self::$defaultTTL);

        $payloadEncoded = self::base64UrlEncode(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $signature = self::base64UrlEncode(
            hash_hmac(
                'sha256',
                $header . '.' . $payloadEncoded,
                $secretKey,
                true
            )
        );

        return $header . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Decode và xác thực JWT
     */
    public static function decode(string $token): ?array
    {
        try {
            $secretKey = self::getSecretKey();
        } catch (Throwable $e) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        try {
            $header = json_decode(
                self::base64UrlDecode($headerEncoded),
                true
            );

            $payload = json_decode(
                self::base64UrlDecode($payloadEncoded),
                true
            );
        } catch (Throwable $e) {
            return null;
        }

        if (!is_array($header) || !is_array($payload)) {
            return null;
        }

        // Kiểm tra thuật toán
        if (($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        if (($header['typ'] ?? '') !== 'JWT') {
            return null;
        }

        // Tính lại chữ ký
        $expectedSignature = self::base64UrlEncode(
            hash_hmac(
                'sha256',
                $headerEncoded . '.' . $payloadEncoded,
                $secretKey,
                true
            )
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // JWT phải có exp
        if (!isset($payload['exp'])) {
            return null;
        }

        // Kiểm tra hết hạn
        if ((int)$payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * TTL mặc định
     */
    public static function getDefaultTTL(): int
    {
        return self::$defaultTTL;
    }
}