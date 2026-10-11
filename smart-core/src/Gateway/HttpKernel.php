<?php

namespace SmartCore\Gateway;

/**
 * Turns an HTTP request into a gateway call: JSON in, JSON out, CORS for the React app,
 * and a per-IP request limit against endpoint targeting (misuse detection).
 */
final class HttpKernel
{
    /** @param list<string> $allowedOrigins */
    public static function run(Gateway $gateway, array $allowedOrigins, ?RateLimiter $limiter = null): never
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::send(405, ['ok' => false, 'error' => ['code' => 'post_only', 'message' => 'Send a POST request with a JSON body.']]);
        }

        if ($limiter && ! $limiter->hit($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
            self::send(429, ['ok' => false, 'error' => ['code' => 'too_many_requests', 'message' => 'Too many requests. Try again in a minute.']]);
        }

        $request = json_decode((string) file_get_contents('php://input'), true);

        if (! is_array($request)) {
            self::send(400, ['ok' => false, 'error' => ['code' => 'bad_json', 'message' => 'The body must be a JSON object.']]);
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
        $response = $gateway->handle($request, $token);

        self::send($response['status'], $response['body']);
    }

    private static function send(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($body);
        exit;
    }
}
