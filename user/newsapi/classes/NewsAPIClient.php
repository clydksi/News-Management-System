<?php
/**
 * NewsAPI Client
 *
 * Handles all interactions with NewsAPI.org, routing requests
 * to the correct endpoint based on supplied parameters.
 *
 * @package  NewsAggregator
 * @version  2.0.0
 */

class NewsAPIClient
{
    // ─── NewsAPI Endpoints ────────────────────────────────────────────────────
    private const ENDPOINT_HEADLINES  = 'https://newsapi.org/v2/top-headlines';
    private const ENDPOINT_EVERYTHING = 'https://newsapi.org/v2/everything';
    private const ENDPOINT_SOURCES    = 'https://newsapi.org/v2/top-headlines/sources';

    // ─── Parameters allowed per endpoint ─────────────────────────────────────
    private const HEADLINES_PARAMS  = ['q', 'sources', 'category', 'country', 'language', 'pageSize', 'page'];
    private const EVERYTHING_PARAMS = ['q', 'searchIn', 'sources', 'domains', 'excludeDomains',
                                       'from', 'to', 'language', 'sortBy', 'pageSize', 'page'];

    private string       $apiKey;
    private int          $timeout;
    private int          $connectTimeout;
    private int          $retryAttempts;
    private int          $retryDelayMs;
    private string       $userAgent;
    private CacheManager $cache;

    // ─── Constructor ─────────────────────────────────────────────────────────
    public function __construct(array $config, CacheManager $cache)
    {
        if (empty($config['key'])) {
            throw new InvalidArgumentException('NewsAPI key is required.');
        }

        $this->apiKey         = $config['key'];
        $this->timeout        = $config['timeout']         ?? 30;
        $this->connectTimeout = $config['connect_timeout'] ?? 10;
        $this->retryAttempts  = $config['retry_attempts']  ?? 3;
        $this->retryDelayMs   = $config['retry_delay_ms']  ?? 500;
        $this->userAgent      = $config['user_agent']      ?? 'NewsAggregator/2.0';
        $this->cache          = $cache;
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PUBLIC API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Fetch news articles, routing to the correct endpoint automatically.
     *
     * @param  array $params      category, country, q, sortBy, language, pageSize, page
     * @param  bool  $forceRefresh Skip cache and call API directly
     * @return array              Normalised response with metadata
     * @throws RuntimeException   On API or network errors
     */
    public function fetchNews(array $params, bool $forceRefresh = false): array
    {
        [$endpoint, $cleanParams] = $this->resolveEndpoint($params);

        $cacheKey = $this->generateCacheKey($cleanParams, $endpoint);

        // ── Serve from cache ──────────────────────────────────────────────
        if (!$forceRefresh) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        // ── Call API with retry ───────────────────────────────────────────
        $rawResponse = $this->requestWithRetry($endpoint, $cleanParams);

        // ── Validate response body ────────────────────────────────────────
        if (($rawResponse['status'] ?? '') !== 'ok') {
            throw new RuntimeException(
                'NewsAPI Error: ' . ($rawResponse['message'] ?? 'Unknown error from API')
            );
        }

        // ── Build normalised result ───────────────────────────────────────
        $result = [
            'articles'     => $rawResponse['articles']      ?? [],
            'totalResults' => $rawResponse['totalResults']  ?? 0,
            'from_cache'   => false,
            'cached_at'    => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', time() + $this->cache->getLifetime()),
            'endpoint'     => $endpoint,
        ];

        $this->cache->set($cacheKey, $result);

        return $result;
    }

    /**
     * Fetch available sources (optionally filtered by category / country).
     *
     * @param  array $filters  category, country, language
     * @return array           List of source objects
     * @throws RuntimeException
     */
    public function fetchSources(array $filters = []): array
    {
        $allowed = ['category', 'country', 'language'];
        $params  = array_intersect_key($filters, array_flip($allowed));

        $raw = $this->requestWithRetry(self::ENDPOINT_SOURCES, $params);

        if (($raw['status'] ?? '') !== 'ok') {
            throw new RuntimeException('NewsAPI Sources Error: ' . ($raw['message'] ?? 'Unknown'));
        }

        return $raw['sources'] ?? [];
    }

    /**
     * Clear cached responses.
     *
     * @param  string|null $prefix  Optional prefix filter
     * @return int                  Number of cache entries removed
     */
    public function clearCache(?string $prefix = null): int
    {
        return $this->cache->clear($prefix);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  ENDPOINT ROUTING
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Decide which endpoint to use and strip unsupported parameters.
     *
     * Rules:
     *  - category OR country present  → top-headlines
     *  - keyword search only          → everything
     *  - neither                      → top-headlines (general/us fallback)
     *
     * @param  array $params  Raw input params from the controller
     * @return array          [endpoint URL, cleaned param array]
     */
    private function resolveEndpoint(array $params): array
    {
        // ── Normalise search key to 'q' ───────────────────────────────────────
        if (!empty($params['search']) && empty($params['q'])) {
            $params['q'] = $params['search'];
        }
        unset($params['search']);

        $category = $params['category'] ?? 'general';
        $country  = $params['country']  ?? 'us';
        $hasSearch = !empty($params['q']);

        // Countries that natively work on /v2/top-headlines (free tier)
        $nativeCountries = ['ae','ar','at','au','be','bg','br','ca','ch','cn',
                            'co','cu','cz','de','eg','fr','gb','gr','hk','hu',
                            'id','ie','il','in','it','jp','kr','lt','lv','ma',
                            'mx','my','ng','nl','no','nz','ph','pl','pt','ro',
                            'rs','ru','sa','se','sg','si','sk','th','tr','tw',
                            'ua','us','ve','za'];

        // NOTE: The list above is the full NewsAPI-documented list.
        // On FREE tier, only 'us' reliably returns data for top-headlines.
        // All others fall back to /everything with a country-specific query.
        $freeTierCountries = ['us', 'gb', 'ca', 'au', 'in'];

        $isFreeTierSupported = in_array($country, $freeTierCountries, true);

        // ── PATH 1: Native top-headlines (free tier supported country) ────────
        if ($isFreeTierSupported && !$hasSearch) {
            $clean = $this->filterParams($params, self::HEADLINES_PARAMS);
            unset($clean['sources'], $clean['sortBy']);

            $clean['country']  = $country;
            $clean['category'] = $category;
            $clean['pageSize'] = $params['pageSize'] ?? 100;

            return [self::ENDPOINT_HEADLINES, $clean];
        }

        // ── PATH 2: /everything fallback for unsupported countries ───────────
        // Build a country-specific query so each country gets unique results.
        $clean = $this->filterParams($params, self::EVERYTHING_PARAMS);
        unset($clean['country'], $clean['category']); // Not accepted by /everything

        // Map country → localised search term + language
        $countryProfiles = [
            'ph' => ['term' => 'Philippines',    'lang' => 'en'],
            'sg' => ['term' => 'Singapore',      'lang' => 'en'],
            'jp' => ['term' => 'Japan',          'lang' => 'ja'],
            'fr' => ['term' => 'France',         'lang' => 'fr'],
            'de' => ['term' => 'Germany',        'lang' => 'de'],
            'it' => ['term' => 'Italy',          'lang' => 'it'],
            'br' => ['term' => 'Brazil',         'lang' => 'pt'],
            'cn' => ['term' => 'China',          'lang' => 'zh'],
            'kr' => ['term' => 'South Korea',    'lang' => 'ko'],
            'mx' => ['term' => 'Mexico',         'lang' => 'es'],
            'za' => ['term' => 'South Africa',   'lang' => 'en'],
            'ng' => ['term' => 'Nigeria',        'lang' => 'en'],
            'ru' => ['term' => 'Russia',         'lang' => 'ru'],
            'tr' => ['term' => 'Turkey',         'lang' => 'tr'],
            'in' => ['term' => 'India',          'lang' => 'en'],
            'gb' => ['term' => 'United Kingdom', 'lang' => 'en'],
            'ca' => ['term' => 'Canada',         'lang' => 'en'],
            'au' => ['term' => 'Australia',      'lang' => 'en'],
            'us' => ['term' => 'United States',  'lang' => 'en'],
        ];

        // Map category → search keyword to keep results topical
        $categoryKeywords = [
            'general'       => 'news',
            'business'      => 'business economy',
            'entertainment' => 'entertainment celebrity',
            'health'        => 'health medical',
            'science'       => 'science technology',
            'sports'        => 'sports',
            'technology'    => 'technology innovation',
        ];

        $profile  = $countryProfiles[$country] ?? ['term' => strtoupper($country), 'lang' => 'en'];
        $catWord  = $categoryKeywords[$category] ?? 'news';

        // Final query: e.g. "Philippines sports" or "Singapore business economy"
        if (!$hasSearch) {
            $clean['q'] = $profile['term'] . ' ' . $catWord;
        } else {
            // User-typed search: prepend country for geographic relevance
            $clean['q'] = $profile['term'] . ' ' . $params['q'];
        }

        $clean['language'] = $params['language'] ?? $profile['lang'];
        $clean['sortBy']   = $params['sortBy']   ?? 'publishedAt';
        $clean['pageSize'] = $params['pageSize'] ?? 100;

        return [self::ENDPOINT_EVERYTHING, $clean];
    }

    /**
     * Strip params not accepted by the target endpoint.
     */
    private function filterParams(array $params, array $allowed): array
    {
        return array_filter(
            array_intersect_key($params, array_flip($allowed)),
            fn($v) => $v !== '' && $v !== null
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  HTTP LAYER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Make request with automatic retry on transient failures.
     */
    private function requestWithRetry(string $endpoint, array $params): array
    {
        $params['apiKey'] = $this->apiKey;
        $url = $endpoint . '?' . http_build_query($params);

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $this->makeRequest($url);
            } catch (RuntimeException $e) {
                $lastException = $e;

                // Don't retry on auth / bad-request errors
                if ($this->isFatalError($e)) {
                    throw $e;
                }

                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelayMs * 1000 * $attempt); // Exponential back-off
                }
            }
        }

        throw $lastException;
    }

    /**
     * Execute a single cURL request and return decoded JSON.
     *
     * @throws RuntimeException
     */
    private function makeRequest(string $url): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER     => [
                "User-Agent: {$this->userAgent}",
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body      = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        // ── cURL-level errors ─────────────────────────────────────────────
        if ($curlErrNo !== 0) {
            throw new RuntimeException("Network Error [{$curlErrNo}]: {$curlError}");
        }

        // ── HTTP-level errors ─────────────────────────────────────────────
        $this->assertHttpSuccess($httpCode, $body);

        // ── Decode JSON ───────────────────────────────────────────────────
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON decode failed: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Map HTTP status codes to meaningful exceptions.
     *
     * @throws RuntimeException
     */
    private function assertHttpSuccess(int $code, string $body): void
    {
        if ($code === 200) {
            return;
        }

        // Try to extract NewsAPI's own error message from body
        $decoded = json_decode($body, true);
        $apiMsg  = $decoded['message'] ?? null;

        $messages = [
            400 => 'Bad Request: ' . ($apiMsg ?? 'Invalid parameters sent to NewsAPI.'),
            401 => 'Unauthorized: ' . ($apiMsg ?? 'Invalid or missing API key.'),
            403 => 'Forbidden: '    . ($apiMsg ?? 'You do not have access to this resource.'),
            429 => 'Rate Limit Exceeded: ' . ($apiMsg ?? 'Too many requests. Please wait.'),
            500 => 'Server Error: NewsAPI is experiencing issues. Try again later.',
            503 => 'Service Unavailable: NewsAPI is temporarily offline.',
        ];

        $message = $messages[$code]
            ?? "API Error (HTTP {$code}): " . ($apiMsg ?? 'Unexpected response from NewsAPI.');

        throw new RuntimeException($message);
    }

    /**
     * Determine if an error is non-retryable (auth, bad params, etc.)
     */
    private function isFatalError(RuntimeException $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, 'Unauthorized')
            || str_contains($msg, 'Forbidden')
            || str_contains($msg, 'Bad Request')
            || str_contains($msg, 'Invalid parameters');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  CACHE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Generate a stable, unique cache key from endpoint + params.
     */
    private function generateCacheKey(array $params, string $endpoint): string
    {
        // Remove volatile keys that shouldn't affect the cache identity
        $stable = $params;
        unset($stable['apiKey'], $stable['page']);

        ksort($stable); // Ensure key order is consistent

        $hash    = md5($endpoint . json_encode($stable));
        $type    = str_contains($endpoint, 'everything') ? 'ev' : 'hl';
        $cat     = $stable['category'] ?? 'all';
        $country = $stable['country']  ?? 'xx';

        return "newsapi_{$type}_{$cat}_{$country}_{$hash}";
    }
}