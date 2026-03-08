<?php
// RSS IMAGE EXTRACTOR - Alternative approach
// This extracts images directly from Google News RSS feed instead of article pages
// More reliable because RSS feeds often include image URLs

if (isset($_GET['action']) && $_GET['action'] === 'get-rss-images') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    
    $query = isset($_GET['query']) ? $_GET['query'] : 'latest news';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    
    try {
        // Fetch Google News RSS
        $rssUrl = "https://news.google.com/rss/search?q=" . urlencode($query) . "&hl=en-US&gl=US&ceid=US:en";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $rssUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        
        $xmlContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$xmlContent || $httpCode != 200) {
            throw new Exception("Failed to fetch RSS feed (HTTP $httpCode)");
        }
        
        // Parse XML
        $xml = simplexml_load_string($xmlContent);
        if (!$xml) {
            throw new Exception("Failed to parse RSS XML");
        }
        
        $articles = [];
        $count = 0;
        
        foreach ($xml->channel->item as $item) {
            if ($count >= $limit) break;
            
            $title = (string)$item->title;
            $link = (string)$item->link;
            $pubDate = (string)$item->pubDate;
            $description = (string)$item->description;
            
            // Extract source from title
            $source = 'Unknown';
            $lastDash = strrpos($title, ' - ');
            if ($lastDash !== false) {
                $source = substr($title, $lastDash + 3);
                $title = substr($title, 0, $lastDash);
            }
            
            // Try to extract image from various RSS fields
            $imageUrl = null;
            
            // Method 1: media:content (common in RSS feeds)
            if (isset($item->children('media', true)->content)) {
                $mediaContent = $item->children('media', true)->content;
                if (isset($mediaContent->attributes()->url)) {
                    $imageUrl = (string)$mediaContent->attributes()->url;
                }
            }
            
            // Method 2: media:thumbnail
            if (!$imageUrl && isset($item->children('media', true)->thumbnail)) {
                $mediaThumbnail = $item->children('media', true)->thumbnail;
                if (isset($mediaThumbnail->attributes()->url)) {
                    $imageUrl = (string)$mediaThumbnail->attributes()->url;
                }
            }
            
            // Method 3: enclosure
            if (!$imageUrl && isset($item->enclosure)) {
                $enclosure = $item->enclosure;
                if (isset($enclosure->attributes()->url)) {
                    $type = (string)$enclosure->attributes()->type;
                    if (strpos($type, 'image') !== false) {
                        $imageUrl = (string)$enclosure->attributes()->url;
                    }
                }
            }
            
            // Method 4: Look in description HTML
            if (!$imageUrl && $description) {
                if (preg_match('/<img[^>]+src=["\'](https?:\/\/[^"\']+)["\']/i', $description, $match)) {
                    $imageUrl = html_entity_decode($match[1]);
                }
            }
            
            // Method 5: Try to construct Google News image URL
            if (!$imageUrl) {
                // Extract article ID from link
                if (preg_match('/\/articles\/([A-Za-z0-9_-]+)/', $link, $match)) {
                    $articleId = $match[1];
                    // Try Google's image proxy
                    $testUrl = "https://lh3.googleusercontent.com/proxy/" . $articleId;
                    
                    // Quick check if image exists (HEAD request)
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $testUrl,
                        CURLOPT_NOBODY => true,
                        CURLOPT_TIMEOUT => 3,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_FOLLOWLOCATION => true,
                    ]);
                    curl_exec($ch);
                    $testHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    curl_close($ch);
                    
                    if ($testHttpCode == 200 && strpos($contentType, 'image') !== false) {
                        $imageUrl = $testUrl;
                    }
                }
            }
            
            $articles[] = [
                'title' => $title,
                'link' => $link,
                'pubDate' => $pubDate,
                'source' => $source,
                'imageUrl' => $imageUrl,
                'description' => substr($description, 0, 200),
            ];
            
            $count++;
        }
        
        echo json_encode([
            'success' => true,
            'query' => $query,
            'count' => count($articles),
            'articles' => $articles,
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Return info if accessed directly
echo json_encode([
    'endpoint' => 'RSS Image Extractor',
    'usage' => '?action=get-rss-images&query=technology&limit=20',
    'description' => 'Extracts images directly from Google News RSS feed'
]);
?>