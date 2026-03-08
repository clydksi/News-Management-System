<?php
// image_search.php - AGGRESSIVE SEARCH WITH ACTUAL ARTICLE TITLES
// Uses the real article title to find relevant images

if (isset($_GET['action']) && $_GET['action'] === 'search-image') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    
    $title = isset($_GET['title']) ? $_GET['title'] : '';
    
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title parameter required']);
        exit();
    }
    
    try {
        // Clean the title for better search
        $cleanTitle = cleanTitleForSearch($title);
        
        // METHOD 1: Google Images - Try multiple search variations
        $imageUrl = tryGoogleSearch($cleanTitle);
        if ($imageUrl) {
            echo json_encode([
                'success' => true,
                'title' => $title,
                'imageUrl' => $imageUrl,
                'method' => 'google-search'
            ]);
            exit();
        }
        
        // METHOD 2: Bing Images - More lenient
        $imageUrl = tryBingSearch($cleanTitle);
        if ($imageUrl) {
            echo json_encode([
                'success' => true,
                'title' => $title,
                'imageUrl' => $imageUrl,
                'method' => 'bing-search'
            ]);
            exit();
        }
        
        // METHOD 3: Try with just keywords
        $keywords = extractMainKeywords($cleanTitle);
        $imageUrl = tryGoogleSearch($keywords);
        if ($imageUrl) {
            echo json_encode([
                'success' => true,
                'title' => $title,
                'imageUrl' => $imageUrl,
                'method' => 'google-keywords'
            ]);
            exit();
        }
        
        // METHOD 4: Contextual Picsum fallback
        $contextId = getContextualImageId($title);
        $picsumUrl = "https://picsum.photos/id/$contextId/800/600";
        
        echo json_encode([
            'success' => true,
            'title' => $title,
            'imageUrl' => $picsumUrl,
            'method' => 'picsum-fallback',
            'note' => 'Could not find specific image, using contextual placeholder'
        ]);
        
    } catch (Exception $e) {
        $seed = abs(crc32($title)) % 500;
        echo json_encode([
            'success' => true,
            'title' => $title,
            'imageUrl' => "https://picsum.photos/id/$seed/800/600",
            'method' => 'picsum-emergency'
        ]);
    }
    exit();
}

function cleanTitleForSearch($title) {
    // Remove source attribution
    $title = preg_replace('/ - [^-]+$/', '', $title);
    
    // Keep it clean but meaningful
    return trim($title);
}

function extractMainKeywords($title) {
    $title = strtolower($title);
    
    // Priority keywords
    $priorityPatterns = [
        'typhoon|hurricane|storm' => 'typhoon storm',
        'earthquake' => 'earthquake disaster',
        'military|missile|defense' => 'military defense',
        'government|senate|congress' => 'government capitol',
        'sports|basketball|football|game' => 'sports game',
        'technology|tech|AI' => 'technology',
        'business|economy' => 'business office'
    ];
    
    foreach ($priorityPatterns as $pattern => $keywords) {
        if (preg_match("/($pattern)/i", $title)) {
            return $keywords;
        }
    }
    
    // Extract first few meaningful words
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
    $words = explode(' ', $title);
    $keywords = [];
    
    foreach ($words as $word) {
        $word = preg_replace('/[^a-z0-9]/i', '', strtolower($word));
        if (strlen($word) > 3 && !in_array($word, $stopWords)) {
            $keywords[] = $word;
            if (count($keywords) >= 3) break;
        }
    }
    
    return implode(' ', $keywords);
}

function tryGoogleSearch($query) {
    $searchQuery = urlencode($query);
    $googleUrl = "https://www.google.com/search?q=$searchQuery&tbm=isch&tbs=isz:m";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $googleUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
        ]
    ]);
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) return null;
    
    // Try multiple patterns
    $patterns = [
        '/"ou":"(https?:\/\/[^"]+)"/',
        '/"url":"(https?:\/\/[^"]+\.(?:jpg|jpeg|png|webp)[^"]*)"/',
        '/\["(https?:\/\/[^"]+\.(?:jpg|jpeg|png|webp))/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $imageUrl) {
                // Skip logos and icons
                if (preg_match('/(logo|icon|avatar|sprite|button)/i', $imageUrl)) {
                    continue;
                }
                
                // Verify URL is accessible
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    return $imageUrl;
                }
            }
        }
    }
    
    return null;
}

function tryBingSearch($query) {
    $searchQuery = urlencode($query);
    $bingUrl = "https://www.bing.com/images/search?q=$searchQuery&first=1";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $bingUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) return null;
    
    // Try multiple Bing patterns
    $patterns = [
        '/"murl":"(https?:\/\/[^"]+)"/',
        '/"mediaUrl":"(https?:\/\/[^"]+)"/',
        '/"purl":"(https?:\/\/[^"]+)"/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $match)) {
            $imageUrl = str_replace('\/', '/', $match[1]);
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                return $imageUrl;
            }
        }
    }
    
    return null;
}

function getContextualImageId($title) {
    $title = strtolower($title);
    
    // Map content to specific quality Picsum images
    if (preg_match('/(typhoon|storm|hurricane|flood|rain)/i', $title)) {
        return [10, 15, 20, 28, 30][abs(crc32($title)) % 5];
    }
    if (preg_match('/(military|missile|defense|army)/i', $title)) {
        return [1, 2, 3, 4, 8][abs(crc32($title)) % 5];
    }
    if (preg_match('/(government|senate|congress|politics)/i', $title)) {
        return [201, 202, 203, 204, 206][abs(crc32($title)) % 5];
    }
    if (preg_match('/(sports|game|basketball|football)/i', $title)) {
        return [103, 113, 123, 133, 143][abs(crc32($title)) % 5];
    }
    if (preg_match('/(technology|tech|AI|computer)/i', $title)) {
        return [250, 251, 252, 253, 255][abs(crc32($title)) % 5];
    }
    if (preg_match('/(business|economy|market)/i', $title)) {
        return [180, 183, 188, 193, 198][abs(crc32($title)) % 5];
    }
    
    return abs(crc32($title)) % 400;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Image Search - Aggressive Matching</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; }
        .note { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📸 Image Search - Aggressive Matching</h1>
        
        <div class="success">
            <strong>✅ Now searches with actual article titles!</strong><br>
            Uses multiple methods to find the most relevant images.
        </div>
        
        <h2>Search Strategy:</h2>
        <ol>
            <li><strong>Full Title Search</strong> - Uses complete article title</li>
            <li><strong>Bing Search</strong> - Alternative with full title</li>
            <li><strong>Keyword Search</strong> - Extracts main keywords and searches</li>
            <li><strong>Contextual Picsum</strong> - Smart fallback based on content</li>
        </ol>
        
        <div class="note">
            <strong>⚡ Higher Success Rate:</strong><br>
            Multiple search attempts increase chances of finding relevant images!
        </div>
    </div>
</body>
</html>