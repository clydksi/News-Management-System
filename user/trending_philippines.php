<?php
/**
 * Philippines Trending News Aggregator
 * Enhanced version - Minimum 20 trending topics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$debugMode = isset($_GET['debug']) && $_GET['debug'] === 'true';

// Top Philippine news sources with their RSS feeds
$newsSources = [
    'GMA News' => 'https://data.gmanetwork.com/gno/rss/news/feed.xml',
    'Inquirer' => 'https://newsinfo.inquirer.net/feed',
    'Philstar' => 'https://www.philstar.com/rss/headlines',
    'Rappler' => 'https://www.rappler.com/feed',
    'Manila Bulletin' => 'https://mb.com.ph/feed',
    'ABS-CBN News' => 'https://news.abs-cbn.com/rss/news',
    'Manila Times' => 'https://www.manilatimes.net/feed',
];

/**
 * Fetch RSS feed with enhanced error handling
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
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        $debugInfo['http_code'] = $httpCode;
        $debugInfo['effective_url'] = $effectiveUrl;
        $debugInfo['content_length'] = strlen($xmlContent);
        $debugInfo['curl_error'] = $curlError;
        $debugInfo['curl_errno'] = $curlErrno;
        
        curl_close($ch);
        
        if ($curlErrno !== 0) {
            $debugInfo['status'] = 'error';
            $debugInfo['error'] = "CURL Error ($curlErrno): $curlError";
            return ['articles' => [], 'debug' => $debugInfo];
        }
        
        if ($httpCode !== 200) {
            $debugInfo['status'] = 'error';
            $debugInfo['error'] = "HTTP Error: $httpCode";
            return ['articles' => [], 'debug' => $debugInfo];
        }
        
        if (empty($xmlContent)) {
            $debugInfo['status'] = 'error';
            $debugInfo['error'] = "Empty response";
            return ['articles' => [], 'debug' => $debugInfo];
        }
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $debugInfo['status'] = 'error';
            $debugInfo['error'] = 'XML Parse Error';
            $debugInfo['xml_errors'] = array_map(function($e) {
                return $e->message;
            }, $errors);
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
            $title = '';
            if (isset($item->title)) {
                $title = (string)$item->title;
            }
            
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
            
            // Extract image from RSS feed
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
                    'title' => trim($title),
                    'link' => trim($link),
                    'pubDate' => $pubDate,
                    'description' => substr($description, 0, 500),
                    'source' => $sourceName,
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
 * Fetch and parse RSS feeds from all sources
 */
function fetchAllSources($sources, $debugMode = false) {
    $allArticles = [];
    $debugData = [];
    
    foreach ($sources as $sourceName => $rssUrl) {
        $result = fetchSingleSource($sourceName, $rssUrl, $debugMode);
        $allArticles = array_merge($allArticles, $result['articles']);
        
        if ($debugMode) {
            $debugData[] = $result['debug'];
        }
    }
    
    return ['articles' => $allArticles, 'debug' => $debugData];
}

/**
 * Extract meaningful keywords from a title
 */
function extractKeywords($title) {
    $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'been', 'be', 
        'have', 'has', 'had', 'will', 'can', 'said', 'says', 'after', 'over', 
        'out', 'into', 'his', 'her', 'their', 'this', 'that', 'these', 'those',
        'who', 'what', 'where', 'when', 'why', 'how', 'all', 'each', 'every',
        'some', 'any', 'few', 'more', 'most', 'other', 'such', 'only', 'own',
        'than', 'too', 'very', 'just', 'now', 'then', 'here', 'there',
        'ng', 'sa', 'mga', 'ang', 'na', 'ay', 'si', 'ni', 'kay', 'para',
        'new', 'latest', 'breaking', 'update', 'news', 'today', 'tonight'
    ];
    
    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/[^\w\s\-]/u', ' ', $title);
    $words = preg_split('/\s+/', $title);
    
    $keywords = [];
    foreach ($words as $word) {
        $word = trim($word);
        
        if (mb_strlen($word, 'UTF-8') >= 4 && 
            !in_array($word, $stopWords) && 
            !is_numeric($word)) {
            $keywords[] = $word;
        }
    }
    
    return array_values(array_unique($keywords));
}

/**
 * Enhance articles with images from RSS image search
 */
function enhanceArticleImage($article) {
    if (!empty($article['imageUrl'])) {
        return $article['imageUrl'];
    }
    
    try {
        $searchQuery = $article['title'] . ' ' . $article['source'] . ' Philippines';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'rss_images.php?action=get-rss-images&query=' . urlencode($searchQuery) . '&limit=3',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && $data['success'] && !empty($data['articles'])) {
                foreach ($data['articles'] as $rssArticle) {
                    if (!empty($rssArticle['imageUrl'])) {
                        return $rssArticle['imageUrl'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Return empty if failed
    }
    
    return '';
}

/**
 * Find trending topics - ENHANCED to ensure minimum 20 results
 */
function findTrendingTopics($articles, $minTopics = 20) {
    if (empty($articles)) {
        return [];
    }
    
    $topicGroups = [];
    
    // First pass: Group articles by similar keywords
    foreach ($articles as $article) {
        $keywords = extractKeywords($article['title']);
        
        if (count($keywords) < 2) {
            continue;
        }
        
        $matched = false;
        foreach ($topicGroups as $topicKey => &$group) {
            $commonKeywords = array_intersect($keywords, $group['keywords']);
            
            // If 2 or more keywords match, it's the same topic
            if (count($commonKeywords) >= 2) {
                $group['articles'][] = $article;
                $group['sources'][] = $article['source'];
                $group['sources'] = array_unique($group['sources']);
                $group['count']++;
                $group['keywords'] = array_unique(array_merge($group['keywords'], $keywords));
                
                // Keep best image
                if (!empty($article['imageUrl']) && empty($group['imageUrl'])) {
                    $group['imageUrl'] = $article['imageUrl'];
                }
                
                $matched = true;
                break;
            }
        }
        unset($group);
        
        if (!$matched) {
            $topicKey = 'topic_' . count($topicGroups);
            $topicGroups[$topicKey] = [
                'keywords' => $keywords,
                'articles' => [$article],
                'sources' => [$article['source']],
                'count' => 1,
                'imageUrl' => $article['imageUrl'] ?? ''
            ];
        }
    }
    
    // Build trending topics
    $trendingTopics = [];
    $singleSourceTopics = [];
    
    foreach ($topicGroups as $group) {
        $sourceCount = count($group['sources']);
        
        // Sort articles by date
        usort($group['articles'], function($a, $b) {
            return strtotime($b['pubDate']) - strtotime($a['pubDate']);
        });
        
        $mainArticle = $group['articles'][0];
        
        // Use group's imageUrl if available, otherwise use main article's
        $imageUrl = !empty($group['imageUrl']) ? $group['imageUrl'] : ($mainArticle['imageUrl'] ?? '');
        
        $topic = [
            'title' => $mainArticle['title'],
            'link' => $mainArticle['link'],
            'pubDate' => $mainArticle['pubDate'],
            'description' => $mainArticle['description'],
            'sourceCount' => $sourceCount,
            'sources' => implode(', ', $group['sources']),
            'relatedArticles' => array_slice($group['articles'], 0, 5),
            'trending' => true,
            'imageUrl' => $imageUrl,
            'source' => $mainArticle['source']
        ];
        
        // Separate multi-source and single-source topics
        if ($sourceCount >= 2) {
            $trendingTopics[] = $topic;
        } else {
            $singleSourceTopics[] = $topic;
        }
    }
    
    // Sort multi-source topics by source count, then by recency
    usort($trendingTopics, function($a, $b) {
        if ($b['sourceCount'] !== $a['sourceCount']) {
            return $b['sourceCount'] - $a['sourceCount'];
        }
        return strtotime($b['pubDate']) - strtotime($a['pubDate']);
    });
    
    // Sort single-source topics by recency
    usort($singleSourceTopics, function($a, $b) {
        return strtotime($b['pubDate']) - strtotime($a['pubDate']);
    });
    
    // If we don't have enough multi-source topics, add single-source topics
    if (count($trendingTopics) < $minTopics) {
        $needed = $minTopics - count($trendingTopics);
        $additionalTopics = array_slice($singleSourceTopics, 0, $needed);
        $trendingTopics = array_merge($trendingTopics, $additionalTopics);
    }
    
    // Ensure we have at least minTopics (if possible)
    if (count($trendingTopics) < $minTopics && count($articles) >= $minTopics) {
        // Add individual recent articles if needed
        $usedLinks = array_map(function($t) { return $t['link']; }, $trendingTopics);
        
        foreach ($articles as $article) {
            if (count($trendingTopics) >= $minTopics) break;
            
            if (!in_array($article['link'], $usedLinks)) {
                $trendingTopics[] = [
                    'title' => $article['title'],
                    'link' => $article['link'],
                    'pubDate' => $article['pubDate'],
                    'description' => $article['description'],
                    'sourceCount' => 1,
                    'sources' => $article['source'],
                    'relatedArticles' => [$article],
                    'trending' => false,
                    'imageUrl' => $article['imageUrl'] ?? '',
                    'source' => $article['source']
                ];
                
                $usedLinks[] = $article['link'];
            }
        }
    }
    
    return $trendingTopics;
}

// Main execution
try {
    $startTime = microtime(true);
    
    // Fetch articles from all sources
    $fetchResult = fetchAllSources($newsSources, $debugMode);
    $articles = $fetchResult['articles'];
    $debugData = $fetchResult['debug'];
    
    if (empty($articles)) {
        echo json_encode([
            'success' => false,
            'message' => 'No articles could be fetched from any source. Please check RSS feed availability.',
            'totalArticles' => 0,
            'trendingTopics' => [],
            'debug' => $debugMode ? $debugData : null,
            'suggestion' => 'RSS feeds may be temporarily unavailable. Try again in a few minutes.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Find trending topics (minimum 20)
    $trendingTopics = findTrendingTopics($articles, 20);
    
    // Enhance images for topics without images
    $enhanceImages = isset($_GET['enhance_images']) ? $_GET['enhance_images'] === 'true' : true;
    
    if ($enhanceImages) {
        foreach ($trendingTopics as &$topic) {
            if (empty($topic['imageUrl'])) {
                $topic['imageUrl'] = enhanceArticleImage($topic);
            }
        }
        unset($topic);
    }
    
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime, 2);
    
    $response = [
        'success' => true,
        'totalArticles' => count($articles),
        'trendingTopics' => $trendingTopics,
        'message' => "Found " . count($trendingTopics) . " trending topics from " . count($articles) . " articles",
        'processingTime' => $totalTime . 's',
        'sources' => array_keys($newsSources),
        'imagesEnhanced' => $enhanceImages
    ];
    
    if ($debugMode) {
        $response['debug'] = $debugData;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'totalArticles' => 0,
        'trendingTopics' => []
    ]);
}
?>