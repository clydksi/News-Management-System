<?php
/**
 * Cache Manager
 *
 * Handles all file-based caching operations for NewsAPI data.
 *
 * @package  NewsAggregator
 * @version  2.0.0
 */

class CacheManager
{
    private string $cacheDir;
    private int    $cacheLifetime;

    // ─── Constructor ──────────────────────────────────────────────────────────
    public function __construct(string $cacheDir, int $cacheLifetime)
    {
        $this->cacheDir      = rtrim($cacheDir, '/') . '/';
        $this->cacheLifetime = max(1, $cacheLifetime);
        $this->ensureCacheDirectory();
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PUBLIC GETTERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return the configured cache lifetime in seconds.
     * Required by NewsAPIClient to compute expires_at metadata.
     */
    public function getLifetime(): int
    {
        return $this->cacheLifetime;
    }

    /**
     * Return the cache directory path.
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  CORE CACHE OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Retrieve a valid (non-expired) cache entry.
     *
     * @param  string     $cacheKey  Unique cache identifier
     * @return array|null            Cached data with metadata, or null if missing/expired
     */
    public function get(string $cacheKey): ?array
    {
        $file = $this->getFilePath($cacheKey);

        if (!is_file($file)) {
            return null;
        }

        $mtime = filemtime($file);

        // Expired?
        if ((time() - $mtime) >= $this->cacheLifetime) {
            $this->deleteFile($file); // Auto-purge on read
            return null;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            $this->deleteFile($file); // Remove corrupt cache
            return null;
        }

        // Attach / overwrite metadata so it's always accurate
        $data['from_cache'] = true;
        $data['cached_at']  = date('Y-m-d H:i:s', $mtime);
        $data['expires_at'] = date('Y-m-d H:i:s', $mtime + $this->cacheLifetime);
        $data['ttl_seconds']= ($mtime + $this->cacheLifetime) - time();

        return $data;
    }

    /**
     * Store data in the cache.
     *
     * @param  string $cacheKey  Unique cache identifier
     * @param  array  $data      Data to cache (metadata keys are stripped before writing)
     * @return bool              True on success
     */
    public function set(string $cacheKey, array $data): bool
    {
        $file = $this->getFilePath($cacheKey);

        // Strip transient metadata before persisting — it's re-computed on get()
        $toWrite = array_diff_key($data, array_flip([
            'from_cache', 'cached_at', 'expires_at', 'ttl_seconds',
        ]));

        $encoded = json_encode($toWrite, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return false;
        }

        // Atomic write: write to temp file then rename to avoid partial reads
        $tmp = $file . '.tmp.' . uniqid('', true);

        if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
            return false;
        }

        return rename($tmp, $file);
    }

    /**
     * Check whether a valid (non-expired) cache entry exists.
     *
     * @param  string $cacheKey
     * @return bool
     */
    public function has(string $cacheKey): bool
    {
        return $this->get($cacheKey) !== null;
    }

    /**
     * Explicitly delete a single cache entry.
     *
     * @param  string $cacheKey
     * @return bool
     */
    public function forget(string $cacheKey): bool
    {
        return $this->deleteFile($this->getFilePath($cacheKey));
    }

    /**
     * Clear cache files, optionally matching a pattern.
     *
     * @param  string|null $pattern  Substring match against filenames (e.g. 'hl_technology')
     * @return int                   Number of files deleted
     */
    public function clear(?string $pattern = null): int
    {
        $glob = $pattern
            ? $this->cacheDir . '*' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $pattern) . '*.json'
            : $this->cacheDir . '*.json';

        $deleted = 0;

        foreach (glob($glob) ?: [] as $file) {
            if (is_file($file) && $this->deleteFile($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Remove only expired cache entries (lightweight GC pass).
     *
     * @return int  Number of expired files removed
     */
    public function purgeExpired(): int
    {
        $purged = 0;

        foreach (glob($this->cacheDir . '*.json') ?: [] as $file) {
            if (is_file($file) && (time() - filemtime($file)) >= $this->cacheLifetime) {
                if ($this->deleteFile($file)) {
                    $purged++;
                }
            }
        }

        return $purged;
    }

    /**
     * Run a probabilistic GC purge (call on every request without hurting perf).
     *
     * @param int $probability  Chance in 100 to actually run (default 5 = 5%)
     */
    public function maybePurge(int $probability = 5): void
    {
        if (random_int(1, 100) <= $probability) {
            $this->purgeExpired();
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  STATISTICS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return statistics about the cache directory.
     *
     * @return array{
     *   total_files: int,
     *   valid_files: int,
     *   expired_files: int,
     *   total_size_bytes: int,
     *   total_size_mb: float,
     *   oldest_cache: string|null,
     *   newest_cache: string|null,
     *   lifetime_seconds: int,
     *   cache_dir: string
     * }
     */
    public function getStats(): array
    {
        $files       = glob($this->cacheDir . '*.json') ?: [];
        $totalSize   = 0;
        $validCount  = 0;
        $expiredCount= 0;
        $oldest      = null;
        $newest      = null;

        foreach ($files as $file) {
            $size  = filesize($file);
            $mtime = filemtime($file);
            $totalSize += $size;

            if ((time() - $mtime) >= $this->cacheLifetime) {
                $expiredCount++;
            } else {
                $validCount++;
            }

            if ($oldest === null || $mtime < $oldest) $oldest = $mtime;
            if ($newest === null || $mtime > $newest) $newest = $mtime;
        }

        return [
            'total_files'      => count($files),
            'valid_files'      => $validCount,
            'expired_files'    => $expiredCount,
            'total_size_bytes' => $totalSize,
            'total_size_mb'    => round($totalSize / 1024 / 1024, 2),
            'oldest_cache'     => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
            'newest_cache'     => $newest ? date('Y-m-d H:i:s', $newest) : null,
            'lifetime_seconds' => $this->cacheLifetime,
            'cache_dir'        => $this->cacheDir,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve the full file path for a given cache key.
     * The key is hashed so any string (including long URLs) is safe as a filename.
     */
    private function getFilePath(string $cacheKey): string
    {
        return $this->cacheDir . md5($cacheKey) . '.json';
    }

    /**
     * Safely delete a file, suppressing warnings if it's already gone.
     */
    private function deleteFile(string $path): bool
    {
        return is_file($path) && @unlink($path);
    }

    /**
     * Create the cache directory (recursively) if it doesn't exist.
     *
     * @throws RuntimeException if directory cannot be created
     */
    private function ensureCacheDirectory(): void
    {
        if (is_dir($this->cacheDir)) {
            return;
        }

        if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException(
                "Cache directory could not be created: {$this->cacheDir}"
            );
        }
    }
}