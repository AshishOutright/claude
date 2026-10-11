<?php

namespace SmartCore\Gateway;

use InvalidArgumentException;

/**
 * Signed, expiring user tokens, so the browser never holds the gateway secret.
 */
final class TokenIssuer
{
    public function __construct(private readonly string $secret, private readonly int $ttlSeconds = 43200)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('The Smart Gateway secret must be at least 32 characters.');
        }
    }

    public function issue(string $userId, ?int $now = null): string
    {
        $payload = $this->encode(json_encode(['sub' => $userId, 'exp' => ($now ?? time()) + $this->ttlSeconds]));

        return $payload.'.'.$this->sign($payload);
    }

    public function verify(string $token, ?int $now = null): ?string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || ! hash_equals($this->sign($parts[0]), $parts[1])) {
            return null;
        }

        $claims = json_decode($this->decode($parts[0]), true);

        if (! is_array($claims) || ! isset($claims['sub'], $claims['exp']) || $claims['exp'] < ($now ?? time())) {
            return null;
        }

        return (string) $claims['sub'];
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, $this->secret, true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
