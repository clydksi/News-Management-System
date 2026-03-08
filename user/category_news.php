<?php
/**
 * Category News Aggregator
 * Fetches news from Philippine sources filtered by category
 * Similar architecture to trending_philippines.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(60);
ini_set('memory_limit', '128M');

$debugMode = isset($_GET['debug']) && $_GET['debug'] === 'true';
$category = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : 'latest news';
$location = isset($_GET['location']) ? trim($_GET['location']) : 'Philippines';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// General headline feeds (used for "latest news" and as fallback)
$generalSources = [
    'GMA News' => 'https://data.gmanetwork.com/gno/rss/news/feed.xml',
    'Inquirer' => 'https://newsinfo.inquirer.net/feed',
    'Philstar' => 'https://www.philstar.com/rss/headlines',
    'Rappler' => 'https://www.rappler.com/feed',
    'Manila Bulletin' => 'https://mb.com.ph/rss/articles',
    'Manila Times' => 'https://www.manilatimes.net/news/feed/',
];

// Category-specific RSS feeds — dedicated feeds per category for maximum articles
$categorySources = [
    'business' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/money/feed.xml',
        'Inquirer' => 'https://business.inquirer.net/feed',
        'Philstar' => 'https://www.philstar.com/rss/business',
        'Rappler' => 'https://www.rappler.com/business/feed/',
        'Manila Bulletin' => 'https://mb.com.ph/rss/business',
        'Manila Times' => 'https://www.manilatimes.net/business/feed/',
    ],
    'entertainment' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/showbiz/feed.xml',
        'Inquirer' => 'https://entertainment.inquirer.net/feed',
        'Philstar' => 'https://www.philstar.com/rss/entertainment',
        'Rappler' => 'https://www.rappler.com/entertainment/feed/',
        'Manila Bulletin' => 'https://mb.com.ph/rss/entertainment',
        'Manila Times' => 'https://www.manilatimes.net/entertainment/feed/',
    ],
    'health' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/lifestyle/healthandwellness/feed.xml',
        'Inquirer' => 'https://lifestyle.inquirer.net/feed',
        'Rappler' => 'https://www.rappler.com/science/feed/',
        'Manila Bulletin' => 'https://mb.com.ph/rss/lifestyle',
        'Manila Times' => 'https://www.manilatimes.net/lifestyle/feed/',
    ],
    'politics' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/news/nation/feed.xml',
        'Inquirer' => 'https://newsinfo.inquirer.net/feed',
        'Philstar' => 'https://www.philstar.com/rss/nation',
        'Rappler' => 'https://www.rappler.com/nation/feed/',
        'Manila Bulletin' => 'https://mb.com.ph/rss/national',
        'Manila Times' => 'https://www.manilatimes.net/news/feed/',
    ],
    'science' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/scitech/science/feed.xml',
        'Rappler' => 'https://www.rappler.com/science/feed/',
        'Rappler Environment' => 'https://www.rappler.com/environment/feed/',
        'GMA SciTech' => 'https://data.gmanetwork.com/gno/rss/scitech/feed.xml',
    ],
    'sports' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/sports/feed.xml',
        'Inquirer' => 'https://sports.inquirer.net/feed',
        'Philstar' => 'https://www.philstar.com/rss/sports',
        'Rappler' => 'https://www.rappler.com/sports/feed/',
        'Manila Bulletin' => 'https://mb.com.ph/rss/sports',
        'Manila Times' => 'https://www.manilatimes.net/sports/feed/',
    ],
    'technology' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/scitech/technology/feed.xml',
        'Inquirer' => 'https://technology.inquirer.net/feed',
        'Rappler' => 'https://www.rappler.com/technology/feed/',
        'Manila Bulletin' => 'https://mb.com.ph/rss/technology',
        'Manila Times' => 'https://www.manilatimes.net/technology/feed/',
    ],
    'weather' => [
        'GMA News' => 'https://data.gmanetwork.com/gno/rss/weather/feed.xml',
        'Rappler' => 'https://www.rappler.com/philippines/weather/feed/',
        'Inquirer' => 'https://newsinfo.inquirer.net/feed',
        'Philstar' => 'https://www.philstar.com/rss/nation',
    ],
];

// Category keyword mappings for filtering
$categoryKeywords = [
    'business' => ['economy', 'business', 'stock', 'trade', 'investment', 'finance', 'peso', 'gdp', 'market', 'corporate', 'pse', 'bsp', 'inflation', 'revenue', 'profit', 'bank', 'loan', 'economic', 'fiscal', 'budget', 'tax', 'export', 'import', 'industry', 'company'],
    'entertainment' => ['entertainment', 'movie', 'film', 'celebrity', 'music', 'concert', 'showbiz', 'kapamilya', 'kapuso', 'actor', 'actress', 'singer', 'drama', 'teleserye', 'star', 'award', 'festival', 'album', 'viral', 'trending', 'tiktoker', 'vlogger'],
    'health' => ['health', 'medical', 'hospital', 'disease', 'covid', 'vaccine', 'doh', 'healthcare', 'doctor', 'medicine', 'patient', 'virus', 'dengue', 'flu', 'mental', 'wellness', 'nutrition', 'cancer', 'surgery', 'clinic', 'epidemic', 'pandemic', 'diagnosis'],
    'politics' => ['politics', 'government', 'senate', 'congress', 'president', 'marcos', 'election', 'dilg', 'law', 'bill', 'duterte', 'political', 'mayor', 'governor', 'senator', 'representative', 'legislation', 'executive', 'judiciary', 'comelec', 'cabinet', 'administration', 'palace', 'official'],
    'science' => ['science', 'research', 'discovery', 'space', 'environment', 'climate', 'biodiversity', 'nasa', 'scientist', 'study', 'species', 'marine', 'biology', 'chemistry', 'physics', 'geological', 'phivolcs', 'dost', 'laboratory', 'experiment', 'fossil'],
    'sports' => ['sports', 'basketball', 'pba', 'uaap', 'boxing', 'volleyball', 'fifa', 'athlete', 'game', 'tournament', 'gilas', 'pacquiao', 'nba', 'football', 'soccer', 'swimming', 'coach', 'player', 'champion', 'medal', 'olympics', 'sea games', 'league', 'match', 'score'],
    'technology' => ['technology', 'tech', 'software', 'digital', 'startup', 'gadget', 'internet', 'app', 'cyber', 'smartphone', 'computer', 'data', 'cloud', 'programming', 'robot', 'innovation', 'silicon', 'chip', 'network', 'online', 'platform', 'ai', 'artificial intelligence'],
    'weather' => ['weather', 'typhoon', 'storm', 'pagasa', 'flood', 'rain', 'temperature', 'climate', 'bagyo', 'signal', 'landslide', 'earthquake', 'tsunami', 'volcanic', 'eruption', 'monsoon', 'habagat', 'amihan', 'forecast', 'warning', 'evacuation', 'disaster', 'calamity'],
];

// Location keywords for filtering
$locationKeywords = [
    'luzon philippines' => ['luzon', 'manila', 'metro manila', 'quezon city', 'makati', 'taguig', 'pasig', 'mandaluyong', 'caloocan', 'cavite', 'laguna', 'batangas', 'bulacan', 'pampanga', 'pangasinan', 'baguio', 'ilocos', 'bicol', 'bataan', 'zambales', 'nueva ecija', 'tarlac', 'rizal', 'antipolo'],
    'visayas philippines' => ['visayas', 'cebu', 'iloilo', 'bacolod', 'tacloban', 'leyte', 'samar', 'bohol', 'dumaguete', 'negros', 'panay', 'aklan', 'boracay', 'eastern visayas', 'western visayas', 'central visayas'],
    'mindanao philippines' => ['mindanao', 'davao', 'cagayan de oro', 'zamboanga', 'general santos', 'cotabato', 'marawi', 'iligan', 'butuan', 'surigao', 'caraga', 'armm', 'barmm', 'bangsamoro', 'lanao', 'maguindanao', 'sultan kudarat'],
];

/**
 * Fetch RSS feed with error handling (same pattern as trending_philippines.php)
 */
function fetchSingleSource($sourceName, $rssUrl, $debugMode = false) {
    $debugInfo = [];

    try {
        $debugInfo['source'] = $sourceName;
        $debugInfo['url'] = $rssUrl;
        $debugInfo['start_time'] = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $rssUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/xml, text/xml, */*',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
            ],
        ]);

        $xmlContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        $debugInfo['http_code'] = $httpCode;
        $debugInfo['content_length'] = strlen($xmlContent ?: '');
        $debugInfo['curl_error'] = $curlError;

        curl_close($ch);

        if ($curlErrno !== 0 || $httpCode !== 200 || empty($xmlContent)) {
            $debugInfo['status'] = 'error';
            $debugInfo['error'] = $curlErrno !== 0 ? "CURL Error ($curlErrno): $curlError" : "HTTP $httpCode";
            return ['articles' => [], 'debug' => $debugInfo];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $debugInfo['status'] = 'error';
            $debugInfo['error'] = 'XML Parse Error';
            libxml_clear_errors();
            return ['articles' => [], 'debug' => $debugInfo];
        }

        // Extract items from various RSS formats
        $items = [];
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->item)) {
            $items = $xml->item;
        } elseif (isset($xml->entry)) {
            $items = $xml->entry;
        }

        $articles = [];
        foreach ($items as $item) {
            $title = isset($item->title) ? (string)$item->title : '';

            $link = '';
            if (isset($item->link)) {
                if (is_object($item->link) && isset($item->link['href'])) {
                    $link = (string)$item->link['href'];
                } else {
                    $link = (string)$item->link;
                }
            } elseif (isset($item->guid)) {
                $link = (string)$item->guid;
            }

            $pubDate = '';
            if (isset($item->pubDate)) {
                $pubDate = (string)$item->pubDate;
            } elseif (isset($item->published)) {
                $pubDate = (string)$item->published;
            } elseif (isset($item->updated)) {
                $pubDate = (string)$item->updated;
            } else {
                $pubDate = date('r');
            }

            $description = '';
            if (isset($item->description)) {
                $description = (string)$item->description;
            } elseif (isset($item->summary)) {
                $description = (string)$item->summary;
            } elseif (isset($item->content)) {
                $description = (string)$item->content;
            }

            // Extract image
            $imageUrl = '';

            // Method 1: media:content
            if (isset($item->children('http://search.yahoo.com/mrss/')->content)) {
                $media = $item->children('http://search.yahoo.com/mrss/')->content;
                if (isset($media->attributes()->url)) {
                    $imageUrl = (string)$media->attributes()->url;
                }
            }

            // Method 2: media:thumbnail
            if (empty($imageUrl) && isset($item->children('http://search.yahoo.com/mrss/')->thumbnail)) {
                $media = $item->children('http://search.yahoo.com/mrss/')->thumbnail;
                if (isset($media->attributes()->url)) {
                    $imageUrl = (string)$media->attributes()->url;
                }
            }

            // Method 3: enclosure
            if (empty($imageUrl) && isset($item->enclosure['url'])) {
                $enclosureUrl = (string)$item->enclosure['url'];
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $enclosureUrl)) {
                    $imageUrl = $enclosureUrl;
                }
            }

            // Method 4: Extract from description HTML
            if (empty($imageUrl) && !empty($description)) {
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $description, $matches)) {
                    $imageUrl = $matches[1];
                }
            }

            $description = strip_tags($description);
            $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
            $description = trim($description);

            if (!empty($title) && !empty($link)) {
                $articles[] = [
                    'title' => mb_convert_encoding(trim($title), 'UTF-8', 'UTF-8'),
                    'link' => trim($link),
                    'pubDate' => $pubDate,
                    'description' => mb_convert_encoding(mb_substr($description, 0, 500, 'UTF-8'), 'UTF-8', 'UTF-8'),
                    'author' => $sourceName,
                    'imageUrl' => $imageUrl
                ];
            }
        }

        $debugInfo['status'] = 'success';
        $debugInfo['article_count'] = count($articles);
        $debugInfo['end_time'] = microtime(true);
        $debugInfo['duration'] = round($debugInfo['end_time'] - $debugInfo['start_time'], 2) . 's';

        return ['articles' => $articles, 'debug' => $debugInfo];

    } catch (Exception $e) {
        $debugInfo['status'] = 'exception';
        $debugInfo['error'] = $e->getMessage();
        return ['articles' => [], 'debug' => $debugInfo];
    }
}

/**
 * Filter articles by category keywords
 */
function filterByCategory($articles, $category, $categoryKeywords) {
    if ($category === 'latest news' || !isset($categoryKeywords[$category])) {
        return $articles;
    }

    $keywords = $categoryKeywords[$category];
    $filtered = [];

    // Build a single regex pattern with word boundaries for all keywords
    $escapedKeywords = array_map(function($kw) { return preg_quote($kw, '/'); }, $keywords);
    $pattern = '/\b(' . implode('|', $escapedKeywords) . ')\b/iu';

    foreach ($articles as $article) {
        $text = $article['title'] . ' ' . $article['description'];

        if (preg_match($pattern, $text)) {
            $filtered[] = $article;
        }
    }

    return $filtered;
}

/**
 * Filter articles by location keywords
 */
function filterByLocation($articles, $location, $locationKeywords) {
    $locationLower = strtolower($location);

    if ($locationLower === 'worldwide' || $locationLower === 'philippines') {
        return $articles;
    }

    if (!isset($locationKeywords[$locationLower])) {
        return $articles;
    }

    $keywords = $locationKeywords[$locationLower];
    $filtered = [];

    foreach ($articles as $article) {
        $text = mb_strtolower($article['title'] . ' ' . $article['description'], 'UTF-8');

        foreach ($keywords as $keyword) {
            if (mb_stripos($text, $keyword) !== false) {
                $filtered[] = $article;
                break;
            }
        }
    }

    return $filtered;
}

/**
 * Filter articles by search term
 */
function filterBySearch($articles, $search) {
    if (empty($search)) {
        return $articles;
    }

    $searchLower = mb_strtolower($search, 'UTF-8');
    $searchTerms = preg_split('/\s+/', $searchLower);
    $filtered = [];

    foreach ($articles as $article) {
        $text = mb_strtolower($article['title'] . ' ' . $article['description'], 'UTF-8');
        $matchCount = 0;

        foreach ($searchTerms as $term) {
            if (mb_stripos($text, $term) !== false) {
                $matchCount++;
            }
        }

        // Require at least half the search terms to match
        if ($matchCount >= max(1, ceil(count($searchTerms) / 2))) {
            $filtered[] = $article;
        }
    }

    return $filtered;
}

// Main execution
try {
    $startTime = microtime(true);

    $allArticles = [];
    $debugData = [];
    $usedSources = [];
    $usedCategoryFeeds = false;

    // Step 1: Use category-specific feeds if available
    if (isset($categorySources[$category])) {
        $usedCategoryFeeds = true;
        foreach ($categorySources[$category] as $sourceName => $rssUrl) {
            $result = fetchSingleSource($sourceName, $rssUrl, $debugMode);
            $allArticles = array_merge($allArticles, $result['articles']);
            $usedSources[] = $sourceName;
            if ($debugMode) {
                $debugData[] = $result['debug'];
            }
        }
    }

    // Step 2: For "latest news" or if no category feeds, use general feeds
    if (!$usedCategoryFeeds) {
        foreach ($generalSources as $sourceName => $rssUrl) {
            $result = fetchSingleSource($sourceName, $rssUrl, $debugMode);
            $allArticles = array_merge($allArticles, $result['articles']);
            $usedSources[] = $sourceName;
            if ($debugMode) {
                $debugData[] = $result['debug'];
            }
        }
    }

    if (empty($allArticles)) {
        echo json_encode([
            'success' => false,
            'message' => 'No articles could be fetched from any source.',
            'articles' => [],
            'totalArticles' => 0,
            'category' => $category,
            'debug' => $debugMode ? $debugData : null
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Step 3: Apply keyword filter only for categories that use general feeds
    // or for weather/health/politics where some feeds are general (not category-specific)
    $filtered = $allArticles;
    if (!$usedCategoryFeeds) {
        $filtered = filterByCategory($allArticles, $category, $categoryKeywords);
    }

    // Deduplicate by title similarity
    $seen = [];
    $unique = [];
    foreach ($filtered as $article) {
        $key = mb_strtolower(preg_replace('/[^a-z0-9]/i', '', $article['title']), 'UTF-8');
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $article;
        }
    }
    $filtered = $unique;

    // Apply location and search filters
    $filtered = filterByLocation($filtered, $location, $locationKeywords);
    $filtered = filterBySearch($filtered, $search);

    // Sort by date (newest first)
    usort($filtered, function($a, $b) {
        return strtotime($b['pubDate']) - strtotime($a['pubDate']);
    });

    // Limit to 100 articles
    $filtered = array_slice($filtered, 0, 100);

    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime, 2);

    $response = [
        'success' => true,
        'articles' => $filtered,
        'totalArticles' => count($filtered),
        'totalFetched' => count($allArticles),
        'category' => $category,
        'location' => $location,
        'search' => $search,
        'processingTime' => $totalTime . 's',
        'sources' => array_unique($usedSources),
        'message' => "Found " . count($filtered) . " articles for '$category'" . (!empty($search) ? " matching '$search'" : "")
    ];

    if ($debugMode) {
        $response['debug'] = $debugData;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'articles' => [],
        'totalArticles' => 0,
        'category' => $category
    ]);
}
?>
