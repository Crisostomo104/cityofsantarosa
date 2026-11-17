<?php
/**
 * Lightweight cache abstraction.
 * Supports Redis/APCu when configured and falls back to filesystem cache.
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function get_cache_driver(): string
{
    static $driver = null;
    if ($driver !== null) {
        return $driver;
    }

    $redisHost = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST');
    if ($redisHost) {
        if (extension_loaded('redis')) {
            $driver = 'redis';
            return $driver;
        }

        if (class_exists('Predis\Client')) {
            $driver = 'predis';
            return $driver;
        }
    }

    if (extension_loaded('apcu') && (bool) ini_get('apc.enabled')) {
        $driver = 'apcu';
        return $driver;
    }

    $driver = 'file';
    return $driver;
}

function get_cache_client(): ?object
{
    static $client = null;
    if ($client !== null) {
        return $client;
    }

    $driver = get_cache_driver();
    $redisHost = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST');
    $redisPort = $_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379;
    $redisDb   = $_ENV['REDIS_DB'] ?? getenv('REDIS_DB') ?: 0;
    $redisPass = $_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD');

    if ($driver === 'redis') {
        $redis = new Redis();
        try {
            $redis->connect($redisHost, (int) $redisPort, 1.0);
            if ($redisPass !== null && $redisPass !== '') {
                $redis->auth($redisPass);
            }
            $redis->select((int) $redisDb);
            $client = $redis;
        } catch (Throwable $e) {
            error_log('[Cache] Redis connection failed: ' . $e->getMessage());
            $client = (object) ['type' => 'file'];
        }
        return $client;
    }

    if ($driver === 'predis') {
        try {
            $predis = new Predis\Client([
                'scheme'   => 'tcp',
                'host'     => $redisHost,
                'port'     => (int)$redisPort,
                'database' => (int)$redisDb,
                'password' => $redisPass ?: null,
            ]);
            $predis->connect();
            $client = $predis;
        } catch (Throwable $e) {
            error_log('[Cache] Predis connection failed: ' . $e->getMessage());
            $client = (object) ['type' => 'file'];
        }
        return $client;
    }

    $client = (object) ['type' => $driver];
    return $client;
}

function cache_get(string $key, ?int $ttl_seconds = null): mixed
{
    $driver = get_cache_driver();

    if ($driver === 'redis') {
        $client = get_cache_client();
        $value = $client->get($key);
        return $value === false ? null : @unserialize($value);
    }

    if ($driver === 'predis') {
        $client = get_cache_client();
        $value = $client->get($key);
        return $value === null ? null : @unserialize($value);
    }

    if ($driver === 'apcu') {
        $success = false;
        $value = apcu_fetch($key, $success);
        return $success ? $value : null;
    }

    $cache_dir = __DIR__ . '/../cache';
    if (!is_dir($cache_dir) && !mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
        return null;
    }

    $cache_file = $cache_dir . '/' . md5($key) . '.cache';
    if (!is_file($cache_file)) {
        return null;
    }

    if ($ttl_seconds !== null && filemtime($cache_file) < time() - $ttl_seconds) {
        @unlink($cache_file);
        return null;
    }

    $contents = @file_get_contents($cache_file);
    if ($contents === false) {
        return null;
    }

    return @unserialize($contents);
}

function cache_set(string $key, mixed $value, int $ttl_seconds): void
{
    $driver = get_cache_driver();

    if ($driver === 'redis') {
        $client = get_cache_client();
        $client->setex($key, $ttl_seconds, serialize($value));
        return;
    }

    if ($driver === 'predis') {
        $client = get_cache_client();
        $client->setex($key, $ttl_seconds, serialize($value));
        return;
    }

    if ($driver === 'apcu') {
        apcu_store($key, $value, $ttl_seconds);
        return;
    }

    $cache_dir = __DIR__ . '/../cache';
    if (!is_dir($cache_dir) && !mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
        return;
    }

    $cache_file = $cache_dir . '/' . md5($key) . '.cache';
    @file_put_contents($cache_file, serialize($value));
}

function cache_delete(string $key): void
{
    $driver = get_cache_driver();

    if ($driver === 'redis' || $driver === 'predis') {
        $client = get_cache_client();
        try {
            $client->del([$key]);
        } catch (Throwable $e) {
            error_log('[Cache] Failed to delete key: ' . $e->getMessage());
        }
        return;
    }

    if ($driver === 'apcu') {
        apcu_delete($key);
        return;
    }

    $cache_dir = __DIR__ . '/../cache';
    $cache_file = $cache_dir . '/' . md5($key) . '.cache';
    if (is_file($cache_file)) {
        @unlink($cache_file);
    }
}

function get_cached_data(string $key, int $ttl_seconds, callable $callback): mixed
{
    $cache_dir = __DIR__ . '/../cache';
    $cache_file = $cache_dir . '/' . md5($key) . '.cache';
    $lock_file = $cache_dir . '/' . md5($key) . '.lock';

    // 1. Try to get valid cache
    $cached = cache_get($key, $ttl_seconds);
    if ($cached !== null) {
        return $cached;
    }

    // 2. Cache is missing or expired.
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }

    // 3. Try to get a non-blocking exclusive lock
    $fp = @fopen($lock_file, 'c+');
    if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
        try {
            $value = $callback();
            cache_set($key, $value, $ttl_seconds);
            return $value;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    // 4. Another process is recalculating; serve stale cache if it exists
    if ($fp) fclose($fp);

    if (is_file($cache_file)) {
        $contents = @file_get_contents($cache_file);
        if ($contents !== false) {
            $stale_value = @unserialize($contents);
            if ($stale_value !== false || $contents === 'b:0;') {
                return $stale_value;
            }
        }
    }

    // 5. No stale cache at all: wait for the other process
    $fp = @fopen($lock_file, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $cached = cache_get($key, $ttl_seconds);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($cached !== null) return $cached;
    }

    // Absolute fallback
    $value = $callback();
    cache_set($key, $value, $ttl_seconds);
    return $value;
}

function clear_cached_data(string $key): void
{
    cache_delete($key);
}
