<?php

namespace SmartCore\Gateway;

/**
 * Fixed-window request counter per key, kept in small files.
 */
final class RateLimiter
{
    public function __construct(private readonly string $directory, private readonly int $perMinute = 120)
    {
    }

    public function hit(string $key, ?int $now = null): bool
    {
        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0770, true);
        }

        $window = intdiv($now ?? time(), 60);
        $file = $this->directory.'/'.hash('sha256', $key).'.json';
        $handle = fopen($file, 'c+');

        if ($handle === false) {
            return true;
        }

        flock($handle, LOCK_EX);
        $state = json_decode((string) stream_get_contents($handle), true);
        $count = (is_array($state) && ($state['window'] ?? null) === $window) ? $state['count'] + 1 : 1;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(['window' => $window, 'count' => $count]));
        flock($handle, LOCK_UN);
        fclose($handle);

        return $count <= $this->perMinute;
    }
}
