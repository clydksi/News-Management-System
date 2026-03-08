<?php
// MediaStack-themed News Aggregator
// Clean, professional design with purple accents

if (isset($_GET['debug']) && $_GET['debug'] === 'view-html') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: text/html; charset=utf-8');
    
    $googleNewsUrl = isset($_GET['url']) ? $_GET['url'] : '';
    
    if (empty($googleNewsUrl)) {
        echo "No URL provided. Usage: ?debug=view-html&url=GOOGLE_NEWS_URL";
        exit();
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $googleNewsUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    
    $html = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    echo "<h1>Debug: Google News HTML</h1>";
    echo "<p><strong>Original URL:</strong> " . htmlspecialchars($googleNewsUrl) . "</p>";
    echo "<p><strong>Final URL:</strong> " . htmlspecialchars($finalUrl) . "</p>";
    echo "<hr>";
    echo "<h2>Raw HTML:</h2>";
    echo "<pre>" . htmlspecialchars($html) . "</pre>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Data - Live Data</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7f8fc;
            color: #1e293b;
            line-height: 1.6;
        }
        
        /* Header */
        header {
            background: white;
            padding: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .logo-text h1 {
            font-size: 1.5em;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .logo-text p {
            font-size: 0.75em;
            color: #64748b;
            font-weight: 500;
        }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
        }
        
        .live-dot {
            width: 6px;
            height: 6px;
            background: #f59e0b;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875em;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }
        
        .btn-secondary {
            background: #e0e7ff;
            color: #6366f1;
        }
        
        .btn-secondary:hover {
            background: #c7d2fe;
        }
        
        .btn-refresh {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .btn-refresh:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        
        /* Search Bar */
        .search-section {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 30px;
        }
        
        .search-bar {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            gap: 12px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95em;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: #8b5cf6;
        }
        
        .search-bar input::placeholder {
            color: #94a3b8;
        }
        
        /* Categories */
        .categories-section {
            max-width: 1400px;
            margin: 0 auto 20px;
            padding: 0 30px;
        }
        
        .category-tabs {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .category-tab {
            padding: 10px 20px;
            background: #f1f5f9;
            color: #475569;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875em;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-tab:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .category-tab.active {
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }
        
        /* Special styling for Philippines Trending button */
        .category-tab.trending-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        .category-tab.trending-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: scale(1.05);
        }
        
        .category-tab.trending-btn.active {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.5);
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3); }
            50% { box-shadow: 0 4px 16px rgba(239, 68, 68, 0.6); }
        }
        
        /* Location Filters */
        .location-section {
            max-width: 1400px;
            margin: 0 auto 20px;
            padding: 0 30px;
        }
        
        .location-filters {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .location-label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.875em;
            margin-right: 10px;
        }
        
        .location-tab {
            padding: 8px 18px;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875em;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .location-tab:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .location-tab.active {
            background: #dbeafe;
            color: #1e40af;
            border-color: #3b82f6;
        }
        
        .location-tab.disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            pointer-events: none;
            background: #f8fafc !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
        }
        
        .location-tab.disabled:hover {
            transform: none !important;
            background: #f8fafc !important;
        }
        
        /* Stats Bar */
        .stats-bar {
            max-width: 1400px;
            margin: 0 auto 20px;
            padding: 0 30px;
        }
        
        .stats-content {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-info {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.875em;
            color: #64748b;
        }
        
        .stats-info span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        #filterDisplay {
            background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 100%);
            color: #4f46e5;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85em;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.1);
            white-space: nowrap;
        }
        
        #filterDisplay #categoryName {
            font-weight: 700;
        }
        
        #filterDisplay #locationName {
            opacity: 0.9;
        }
        
        .page-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-controls label {
            font-size: 0.875em;
            color: #64748b;
            font-weight: 500;
        }
        
        .page-controls select {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.875em;
            font-family: inherit;
            background: white;
            cursor: pointer;
        }
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto 40px;
            padding: 0 30px;
        }
        
        .loading {
            text-align: center;
            padding: 60px 20px;
        }
        
        .loading-spinner-large {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        .loading-text {
            font-size: 1.1em;
            color: #64748b;
            font-weight: 500;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }
        
        .news-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            transition: all 0.3s;
            cursor: pointer;
            border: 1px solid #f1f5f9;
        }
        
        .news-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
            border-color: #e2e8f0;
        }
        
        /* Badge for developing news articles in dashboard */
        .developing-news-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 700;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.4);
        }
        
        /* Blinking source reference badge for developing news */
        .source-reference-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            right: 12px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.7em;
            font-weight: 600;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5);
            animation: blink-badge 1.5s ease-in-out infinite;
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
        }
        
        .source-reference-badge .source-icon {
            flex-shrink: 0;
            animation: point-arrow 1s ease-in-out infinite;
        }
        
        .source-reference-badge .source-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        
        @keyframes blink-badge {
            0%, 100% { 
                opacity: 1; 
                box-shadow: 0 2px 8px rgba(245, 158, 11, 0.5);
            }
            50% { 
                opacity: 0.7; 
                box-shadow: 0 4px 16px rgba(245, 158, 11, 0.8);
            }
        }
        
        @keyframes point-arrow {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-3px); }
        }
        
        .news-card-image {
            width: 100%;
            height: 220px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .news-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .news-card-image.has-image {
            background: #f0f0f0;
        }
        
        .image-placeholder {
            font-size: 4em;
            opacity: 0.3;
        }
        
        .loading-spinner {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 28px;
            height: 28px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            z-index: 10;
        }
        
        .news-card-content {
            padding: 24px;
        }
        
        .news-title {
            font-size: 1.15em;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1e293b;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .news-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.85em;
            color: #64748b;
        }
        
        .news-meta-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background-color: white;
            margin: 40px auto;
            padding: 0;
            border-radius: 16px;
            max-width: 1100px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            position: sticky;
            top: 0;
            background: white;
            padding: 24px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }
        
        .modal-header h2 {
            font-size: 1.5em;
            color: #1e293b;
            font-weight: 700;
        }
        
        .close {
            color: #94a3b8;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .close:hover {
            color: #1e293b;
            background: #f1f5f9;
        }
        
        .modal-body {
            padding: 32px;
        }
        
        .main-article {
            margin-bottom: 32px;
            padding-bottom: 32px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .main-article-image {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .main-article-title {
            font-size: 2.2em;
            margin-bottom: 16px;
            color: #1e293b;
            font-weight: 700;
            line-height: 1.3;
        }
        
        .main-article-meta {
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.8;
            font-size: 0.95em;
        }
        
        .article-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .read-more-btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }
        
        .read-more-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(139, 92, 246, 0.4);
        }
        
        .import-article-btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }
        
        .import-article-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.4);
        }
        
        /* Developing News Section */
        .developing-news-section {
            margin-top: 32px;
        }
        
        .developing-news-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
        }
        
        .developing-news-header h3 {
            font-size: 1.5em;
            color: #1e293b;
            font-weight: 700;
            margin: 0;
        }
        
        .developing-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 700;
            animation: pulse-badge 2s ease-in-out infinite;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .developing-news-loading {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .related-articles-grid {
            display: grid;
            gap: 16px;
        }
        
        .related-article {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            border-left: 4px solid #8b5cf6;
            transition: all 0.2s;
        }
        
        .related-article:hover {
            background: #f1f5f9;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .related-article-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .related-article-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 0.85em;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .related-article-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Clickable source link styling */
        .source-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
            padding: 4px 12px;
            background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 100%);
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .source-link:hover {
            background: linear-gradient(135deg, #c7d2fe 0%, #bfdbfe 100%);
            color: #3730a3;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
        }
        
        /* Main article source links (inline style) */
        .main-source-link {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
            padding: 4px 10px;
            background: linear-gradient(135deg, #e0e7ff 0%, #dbeafe 100%);
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-block;
            margin: 2px 0;
        }
        
        .main-source-link:hover {
            background: linear-gradient(135deg, #c7d2fe 0%, #bfdbfe 100%);
            color: #3730a3;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
        }
        
        .related-article-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .related-read-btn {
            padding: 10px 24px;
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875em;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .related-read-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(139, 92, 246, 0.4);
        }
        
        .related-import-btn {
            padding: 10px 24px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875em;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .related-import-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
        }
        
        .no-related {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        
        /* Footer */
        footer {
            background: #1e293b;
            color: #cbd5e1;
            text-align: center;
            padding: 32px 20px;
            margin-top: 60px;
        }
        
        footer p {
            font-size: 0.95em;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .category-tabs, .location-filters {
                justify-content: flex-start;
            }
            
            .modal-content {
                margin: 20px;
                max-width: calc(100% - 40px);
            }
            
            .main-article-title {
                font-size: 1.8em;
            }
            
            .stats-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .article-actions {
                width: 100%;
            }
            
            .read-more-btn, .import-article-btn {
                flex: 1;
                text-align: center;
            }
            
            .related-article-actions {
                width: 100%;
            }
            
            .related-read-btn, .related-import-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo-section">
                <div class="logo-icon">📰</div>
                <div class="logo-text">
                    <h1>AI Generated News</h1>
                    <p>📅 News for <strong id="currentDate">--</strong> • <span class="live-badge"><span class="live-dot"></span>Live Data</span></p>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="importAllNews()">
                    <span>💾</span> Import All (<span id="importCount">0</span>)
                </button>
                <button class="btn btn-secondary" onclick="loadTestData()">
                    <span>🧪</span> Test
                </button>
                <button class="btn btn-primary" onclick="showDashboard()">
                    <span>📊</span> Dashboard
                </button>
                <button class="btn btn-refresh" onclick="refreshNews()">
                    <span>🔄</span> Refresh
                </button>
            </div>
        </div>
    </header>

    <div class="search-section">
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search today's news by keyword...">
            <button class="btn btn-primary" onclick="searchNews()">Search</button>
        </div>
    </div>

    <div class="categories-section">
        <div class="category-tabs">
            <button class="category-tab trending-btn active" onclick="selectPhilippinesTrending(this)">
                <span>🔥</span> Philippines Trending
            </button>
            <button class="category-tab" onclick="selectCategory('business', this)">
                <span>💼</span> Business
            </button>
            <button class="category-tab" onclick="selectCategory('entertainment', this)">
                <span>🎬</span> Entertainment
            </button>
            <button class="category-tab" onclick="selectCategory('health', this)">
                <span>❤️</span> Health
            </button>
            <button class="category-tab" onclick="selectCategory('politics', this)">
                <span>🏛️</span> Politics
            </button>
            <button class="category-tab" onclick="selectCategory('science', this)">
                <span>🧬</span> Science
            </button>
            <button class="category-tab" onclick="selectCategory('sports', this)">
                <span>⚽</span> Sports
            </button>
            <button class="category-tab" onclick="selectCategory('technology', this)">
                <span>💻</span> Technology
            </button>
            <button class="category-tab" onclick="selectCategory('weather', this)">
                <span>🌤️</span> Weather
            </button>
        </div>
    </div>

    <div class="location-section">
        <div class="location-filters">
            <span class="location-label">📍 Location:</span>
            <button class="location-tab disabled" onclick="selectLocation('worldwide', this)">🌐 Worldwide</button>
            <button class="location-tab active disabled" onclick="selectLocation('Philippines', this)">🇵🇭 Philippines</button>
            <button class="location-tab disabled" onclick="selectLocation('Luzon Philippines', this)">📍 Luzon</button>
            <button class="location-tab disabled" onclick="selectLocation('Visayas Philippines', this)">📍 Visayas</button>
            <button class="location-tab disabled" onclick="selectLocation('Mindanao Philippines', this)">📍 Mindanao</button>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stats-content">
            <div class="stats-info">
                <span>
                    <span class="live-dot"></span>
                    Fresh news loaded at <strong id="loadTime">--:--</strong>
                </span>
                <span id="filterDisplay">
                    📂 <span id="categoryName" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 700;">🔥 Philippines Trending</span> • <span id="locationName">🇵🇭 Top Media</span>
                </span>
                <span>
                    ℹ️ Showing <strong id="newsCount">0</strong> of <strong id="totalNews">100</strong>
                </span>
            </div>
            <div class="page-controls">
                <label>Show:</label>
                <select id="perPage" onchange="updatePerPage()">
                    <option value="12">12</option>
                    <option value="24">24</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <label>per page</label>
            </div>
        </div>
    </div>

    <div class="container">
        <div id="loading" class="loading">
            <div class="loading-spinner-large"></div>
            <div class="loading-text">Loading fresh news...</div>
        </div>
        <div id="newsGrid" class="news-grid"></div>
    </div>

    <div id="articleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📰 Article Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 AI generated News | Live Data</p>
    </footer>

    <script>
        let currentNews = [];
        let currentCategory = 'Philippines Trending';
        let currentLocation = 'Philippines';
        let displayedCount = 12;
        let currentFetchController = null;
        let fetchTimeout = null;
        let isTrendingActive = true;
        let developingNewsArticles = [];
        let showDevelopingInDashboard = false;
        let currentMainArticle = null; // Track the main article for developing news reference

        // Fix timezone: Philippine RSS feeds output PHT times but may label them as GMT/UTC.
        // Strip timezone so JavaScript treats the date as local time (PHT for PH users).
        function parsePubDate(dateStr) {
            if (!dateStr) return new Date();
            const cleaned = String(dateStr)
                .replace(/\s*(GMT|UTC|PHT|PST|EST|CST|[A-Z]{2,5})\s*$/i, '')
                .replace(/Z$/, '')
                .replace(/[+-]\d{2}:?\d{2}\s*$/, '')
                .trim();
            const parsed = new Date(cleaned);
            return isNaN(parsed.getTime()) ? new Date(dateStr) : parsed;
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const existingToasts = document.querySelectorAll('.toast-notification');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            
            const colors = {
                success: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                error: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                warning: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                info: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
            };
            
            const icons = {
                success: '✓',
                error: '✗',
                warning: '⚠',
                info: 'ℹ'
            };
            
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type]};
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 300px;
                max-width: 500px;
                animation: slideInRight 0.3s ease-out;
                font-weight: 500;
            `;
            
            toast.innerHTML = `
                <span style="font-size: 20px; font-weight: bold;">${icons[type]}</span>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.parentElement.remove()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">×</button>
            `;
            
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
        
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);

        // Enable/disable location buttons
        function toggleLocationButtons(disable) {
            const locationBtns = document.querySelectorAll('.location-tab');
            locationBtns.forEach(btn => {
                if (disable) {
                    btn.classList.add('disabled');
                } else {
                    btn.classList.remove('disabled');
                }
            });
        }

        // Toggle developing news display in dashboard
        function toggleDevelopingNewsInDashboard() {
            const checkbox = document.getElementById('showDevelopingCheckbox');
            showDevelopingInDashboard = checkbox.checked;
            
            if (showDevelopingInDashboard) {
                addDevelopingNewsToDashboard();
                showToast(`Added ${developingNewsArticles.length} related articles to dashboard`, 'success');
            } else {
                removeDevelopingNewsFromDashboard();
                showToast('Removed related articles from dashboard', 'info');
            }
        }

        // Add developing news to dashboard
        function addDevelopingNewsToDashboard() {
            if (developingNewsArticles.length === 0) return;
            
            // Mark developing news articles with a flag and reference to main article
            const markedArticles = developingNewsArticles.map(article => ({
                ...article,
                isDevelopingNews: true,
                mainArticleTitle: currentMainArticle ? currentMainArticle.title : 'Unknown Article',
                mainArticleIndex: currentMainArticle ? currentMainArticle.originalIndex : -1
            }));
            
            // Remove any existing developing news first (to avoid duplicates)
            currentNews = currentNews.filter(article => !article.isDevelopingNews);
            
            // Add new developing news to the top
            currentNews = [...markedArticles, ...currentNews];
            
            // COMPLETE RE-RENDER with proper onclick handlers
            if (isTrendingActive) {
                displayTrendingNews(currentNews.slice(0, displayedCount));
            } else {
                displayNews(currentNews.slice(0, displayedCount));
            }
            updateStats();
            setupInstantImageLoading();
        }

        // Remove developing news from dashboard
        function removeDevelopingNewsFromDashboard() {
            // Filter out all developing news articles
            currentNews = currentNews.filter(article => !article.isDevelopingNews);
            
            // COMPLETE RE-RENDER
            if (isTrendingActive) {
                displayTrendingNews(currentNews.slice(0, displayedCount));
            } else {
                displayNews(currentNews.slice(0, displayedCount));
            }
            updateStats();
            setupInstantImageLoading();
        }

        // NEW: Smart view function that handles both regular and developing news articles
        function viewArticleByIndex(index) {
            const article = currentNews[index];
            
            // Check if this is a developing news article that was added to dashboard
            if (article.isDevelopingNews) {
                // It's a developing news article - view it as a simple article
                viewSimpleDevelopingArticle(index);
            } else if (article.relatedArticles && article.relatedArticles.length > 0) {
                // It's a trending article with related articles
                viewTrendingArticle(index);
            } else {
                // It's a regular article - fetch developing news for it
                viewArticleWithDevelopingNews(index);
            }
        }

        // NEW: View developing news article that was added to dashboard (simple view, no recursion)
        function viewSimpleDevelopingArticle(index) {
            const article = currentNews[index];
            const modal = document.getElementById('articleModal');
            const modalBody = document.getElementById('modalBody');
            
            const pubDate = parsePubDate(article.pubDate);
            const formattedDate = pubDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const imgElement = document.getElementById(`img-${index}`);
            const imgSrc = imgElement?.querySelector('img')?.src;
            
            modalBody.innerHTML = `
                <div class="main-article">
                    ${imgSrc ? `<img src="${imgSrc}" class="main-article-image" alt="${article.title}">` : ''}
                    <div style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
                        <span style="background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); color: white; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.9em;">
                            🔗 Related Article
                        </span>
                    </div>
                    <h2 class="main-article-title">${article.title}</h2>
                    <div class="main-article-meta">
                        <strong>📰 Source:</strong> ${article.author || 'Unknown'}<br>
                        <strong>📅 Published:</strong> ${formattedDate}
                    </div>
                    <div class="article-actions">
                        <a href="${article.link}" target="_blank" class="read-more-btn">
                            📖 Read Full Article
                        </a>
                        <button class="import-article-btn" onclick='importSingleArticle(${JSON.stringify(article).replace(/'/g, "&apos;")})'>
                            💾 Import Article
                        </button>
                    </div>
                </div>
                
                <div style="padding: 24px; background: #f8fafc; border-radius: 12px; border-left: 4px solid #8b5cf6;">
                    <p style="color: #64748b; font-size: 0.95em; line-height: 1.6;">
                        💡 <strong>Tip:</strong> This article was added from the "Developing News" or "Related Coverage" section. 
                        You can remove it from the dashboard by unchecking the "Show in Dashboard" option in the original article's modal.
                    </p>
                </div>
            `;
            
            modal.style.display = 'block';
        }

        // Save single article to database
        function saveArticle(article, btnElement) {
            if (!article || !btnElement) {
                showToast('Invalid article data', 'error');
                return;
            }
            
            const originalContent = btnElement.innerHTML;
            
            btnElement.disabled = true;
            btnElement.innerHTML = '⏳ Importing...';
            btnElement.style.opacity = '0.7';
            
            const articleData = {
                title: article.title,
                content: article.description || article.title,
                category: currentCategory,
                source: article.author || article.source || 'Unknown',
                author: article.author || article.source || '',
                published_at: article.pubDate,
                url: article.link,
                image: '',
                description: article.description || ''
            };
            
            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(articleData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message || '✓ Article imported successfully!', 'success');
                    
                    btnElement.innerHTML = '✓ Imported';
                    btnElement.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    btnElement.style.color = 'white';
                    btnElement.style.opacity = '1';
                    btnElement.style.cursor = 'not-allowed';
                    btnElement.disabled = true;
                } else {
                    if (data.message && data.message.includes('already exists')) {
                        showToast('Article already imported', 'warning');
                        btnElement.innerHTML = 'ℹ Already Imported';
                        btnElement.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                        btnElement.style.color = 'white';
                        btnElement.style.opacity = '1';
                        btnElement.disabled = true;
                    } else {
                        showToast(data.message || 'Failed to import article', 'error');
                        btnElement.disabled = false;
                        btnElement.innerHTML = originalContent;
                        btnElement.style.opacity = '1';
                    }
                }
            })
            .catch(error => {
                console.error('Import Error:', error);
                showToast('Failed to import article: ' + error.message, 'error');
                btnElement.disabled = false;
                btnElement.innerHTML = originalContent;
                btnElement.style.opacity = '1';
            });
        }

        // Save all visible articles
        function saveAllVisibleArticles() {
            const allArticles = currentNews.slice(0, displayedCount);
            
            if (allArticles.length === 0) {
                showToast('No articles to import', 'warning');
                return;
            }

            if (!confirm(`Import all ${allArticles.length} visible articles to your database?`)) {
                return;
            }

            let saved = 0, failed = 0, exists = 0;
            const total = allArticles.length;
            
            showToast(`Starting import of ${total} articles...`, 'info');
            
            const allButtons = document.querySelectorAll('.import-article-btn, .related-import-btn');
            allButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            });

            allArticles.forEach((article, index) => {
                setTimeout(() => {
                    const articleData = {
                        title: article.title,
                        content: article.description || article.title,
                        category: currentCategory,
                        source: article.author || article.source || 'Unknown',
                        author: article.author || article.source || '',
                        published_at: article.pubDate,
                        url: article.link,
                        description: article.description || ''
                    };

                    fetch('save.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(articleData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            saved++;
                        } else {
                            if (data.message && data.message.includes('already exists')) {
                                exists++;
                            } else {
                                failed++;
                            }
                        }

                        if (saved + failed + exists === total) {
                            let message = '';
                            if (saved > 0) message += `✓ ${saved} imported`;
                            if (exists > 0) message += `${message ? ', ' : ''}${exists} already existed`;
                            if (failed > 0) message += `${message ? ', ' : ''}${failed} failed`;
                            
                            const toastType = failed === 0 ? 'success' : (saved > 0 ? 'warning' : 'error');
                            showToast(message, toastType);
                            
                            allButtons.forEach(btn => {
                                if (!btn.innerHTML.includes('Imported') && !btn.innerHTML.includes('Already')) {
                                    btn.disabled = false;
                                    btn.style.opacity = '1';
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Batch Import Error:', error);
                        failed++;
                        
                        if (saved + failed + exists === total) {
                            showToast(`Import completed with errors: ${failed} failed`, 'error');
                            allButtons.forEach(btn => {
                                btn.disabled = false;
                                btn.style.opacity = '1';
                            });
                        }
                    });
                }, index * 300);
            });
        }

        function updateStats() {
            const now = new Date();
            
            // Update current date in header
            const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);
            
            // Update load time
            document.getElementById('loadTime').textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            
            // Calculate actual displayed count (minimum of displayedCount and total articles)
            const actualDisplayed = Math.min(displayedCount, currentNews.length);
            document.getElementById('newsCount').textContent = actualDisplayed;
            document.getElementById('totalNews').textContent = currentNews.length;
            document.getElementById('importCount').textContent = actualDisplayed;
            
            // Update pagination options based on total articles
            updatePaginationOptions(currentNews.length);
            
            const categoryDisplay = currentCategory.charAt(0).toUpperCase() + currentCategory.slice(1);
            const locationEmoji = currentLocation === 'worldwide' ? '🌐' : 
                                  currentLocation === 'Philippines' ? '🇵🇭' : '📍';
            
            console.log(`📊 Stats Updated - Showing ${actualDisplayed} of ${currentNews.length} | ${categoryDisplay} | ${locationEmoji} ${currentLocation}`);
        }
        
        function updatePaginationOptions(totalArticles) {
            const perPageSelect = document.getElementById('perPage');
            const allOptions = [12, 24, 50, 100];
            
            // Clear existing options
            perPageSelect.innerHTML = '';
            
            // Add only relevant options
            allOptions.forEach(optionValue => {
                if (optionValue <= totalArticles || optionValue === allOptions[0]) {
                    const option = document.createElement('option');
                    option.value = optionValue;
                    option.textContent = optionValue;
                    if (optionValue === displayedCount) {
                        option.selected = true;
                    }
                    perPageSelect.appendChild(option);
                }
            });
            
            // Add "All" option if there are more than 12 articles
            if (totalArticles > 12) {
                const allOption = document.createElement('option');
                allOption.value = totalArticles;
                allOption.textContent = `All (${totalArticles})`;
                if (displayedCount >= totalArticles) {
                    allOption.selected = true;
                }
                perPageSelect.appendChild(allOption);
            }
            
            // If current displayedCount is higher than totalArticles, adjust it
            if (displayedCount > totalArticles) {
                displayedCount = totalArticles;
            }
        }

        function updatePerPage() {
            displayedCount = parseInt(document.getElementById('perPage').value);
            if (isTrendingActive) {
                displayTrendingNews(currentNews.slice(0, displayedCount));
            } else {
                displayNews(currentNews.slice(0, displayedCount));
            }
            updateStats();
        }

        function selectLocation(location, button) {
            if (isTrendingActive) {
                return;
            }

            if (button.classList.contains('disabled')) {
                return;
            }
            
            document.querySelectorAll('.location-tab').forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            currentLocation = location;
            
            const locationDisplay = button.textContent.trim();
            
            const activeCategoryBtn = document.querySelector('.category-tab.active');
            let categoryDisplay = '📰 General';
            if (activeCategoryBtn) {
                const buttonIcon = activeCategoryBtn.querySelector('span')?.textContent || '📰';
                const categoryText = currentCategory.charAt(0).toUpperCase() + currentCategory.slice(1);
                categoryDisplay = `${buttonIcon} ${categoryText}`;
            }
            
            const filterDisplay = document.getElementById('filterDisplay');
            filterDisplay.innerHTML = `📂 <span id="categoryName">${categoryDisplay}</span> • <span id="locationName">${locationDisplay}</span>`;
            
            console.log('📍 Location changed to:', location);
            console.log('📂 Current category:', currentCategory);
            
            if (currentFetchController) {
                currentFetchController.abort();
            }
            
            fetchNews(buildQuery());
        }

        function buildQuery() {
            const categoryKeywords = {
                'politics': 'politics government',
                'weather': 'weather typhoon storm',
                'technology': 'technology tech',
                'business': 'business economy',
                'sports': 'sports',
                'entertainment': 'entertainment',
                'science': 'science',
                'health': 'health medical',
                'world news': 'world news',
                'latest news': 'news'
            };
            
            let query = categoryKeywords[currentCategory.toLowerCase()] || currentCategory;
            
            if (currentLocation === 'worldwide') {
                // No location filter for worldwide
                console.log('🔨 Built query (Worldwide):', query);
            } else if (currentLocation === 'Philippines') {
                // General Philippines search
                query = `Philippines ${query}`;
                console.log('🔨 Built query (Philippines):', query);
            } else {
                // Regional search (Luzon, Visayas, Mindanao) - put location FIRST for priority
                // Extract just the region name (remove "Philippines" suffix if present)
                let region = currentLocation.replace(' Philippines', '');
                query = `${region} Philippines ${query}`;
                console.log(`🔨 Built query (${region}):`, query);
            }
            
            return query;
        }

        function selectCategory(category, button) {
            isTrendingActive = false;
            
            toggleLocationButtons(false);
            
            document.querySelectorAll('.category-tab').forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            currentCategory = category;
            document.getElementById('searchInput').value = '';
            
            const buttonIcon = button.querySelector('span')?.textContent || '📰';
            const categoryDisplay = category.charAt(0).toUpperCase() + category.slice(1);
            
            const activeLocationBtn = document.querySelector('.location-tab.active');
            let locationDisplay = '🇵🇭 Philippines';
            if (activeLocationBtn) {
                locationDisplay = activeLocationBtn.textContent.trim();
            }
            
            const filterDisplay = document.getElementById('filterDisplay');
            filterDisplay.innerHTML = `📂 <span id="categoryName">${buttonIcon} ${categoryDisplay}</span> • <span id="locationName">${locationDisplay}</span>`;
            
            console.log('📂 Category changed to:', category);
            console.log('📍 Current location:', currentLocation);
            
            if (currentFetchController) {
                currentFetchController.abort();
            }
            
            fetchNews(buildQuery());
        }

        async function selectPhilippinesTrending(button) {
            isTrendingActive = true;
            
            toggleLocationButtons(true);
            
            document.querySelectorAll('.category-tab').forEach(b => b.classList.remove('active'));
            button.classList.add('active');
            currentCategory = 'Philippines Trending';
            
            const filterDisplay = document.getElementById('filterDisplay');
            filterDisplay.innerHTML = `📂 <span id="categoryName" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 700;">🔥 Philippines Trending</span> • <span id="locationName">🇵🇭 Top Media</span>`;
            
            const loading = document.getElementById('loading');
            const newsGrid = document.getElementById('newsGrid');
            
            loading.style.display = 'block';
            loading.innerHTML = `
                <div class="loading-spinner-large"></div>
                <div class="loading-text">Analyzing trending news from GMA, Inquirer, Philstar, Rappler...</div>
            `;
            newsGrid.innerHTML = '';
            
            try {
                const response = await fetch('trending_philippines.php');
                const data = await response.json();
                
                if (data.success && data.trendingTopics.length > 0) {
                    currentNews = data.trendingTopics;
                    displayTrendingNews(currentNews.slice(0, displayedCount));
                    loading.style.display = 'none';
                    updateStats();
                    
                    showToast(`Found ${data.trendingTopics.length} trending topics from ${data.totalArticles} articles`, 'success');
                } else {
                    throw new Error(data.message || 'No trending topics found');
                }
            } catch (error) {
                console.error('Error fetching trending news:', error);
                loading.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <div style="font-size: 3em; margin-bottom: 20px;">❌</div>
                        <div style="font-size: 1.2em; color: #ef4444; font-weight: 600;">Failed to load trending news</div>
                        <div style="color: #64748b; margin-top: 10px;">${error.message}</div>
                    </div>
                `;
                showToast('Failed to load trending news: ' + error.message, 'error');
            }
        }

        // Helper function to truncate title for badge display
        function truncateTitle(title, maxLength = 40) {
            if (title.length <= maxLength) return title;
            return title.substring(0, maxLength) + '...';
        }

        function displayTrendingNews(articles) {
            const newsGrid = document.getElementById('newsGrid');
            newsGrid.innerHTML = '';
            
            articles.forEach((article, index) => {
                const card = document.createElement('div');
                card.className = 'news-card';
                // IMPORTANT: Use viewArticleByIndex for smart routing
                card.onclick = () => viewArticleByIndex(index);
                
                const pubDate = parsePubDate(article.pubDate);
                const formattedDate = pubDate.toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric'
                });
                const formattedTime = pubDate.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit'
                });
                
                let trendingBadge = '';
                if (article.sourceCount >= 4) {
                    trendingBadge = '<span style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 4px 12px; border-radius: 6px; font-size: 0.75em; font-weight: 700;">🔥 HOT</span>';
                } else if (article.sourceCount >= 3) {
                    trendingBadge = '<span style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 4px 12px; border-radius: 6px; font-size: 0.75em; font-weight: 700;">📈 TRENDING</span>';
                } else {
                    trendingBadge = '<span style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 4px 12px; border-radius: 6px; font-size: 0.75em; font-weight: 700;">📰 RISING</span>';
                }
                
                // Add badge for developing news articles with source reference
                let developingBadge = '';
                let sourceReferenceBadge = '';
                
                if (article.isDevelopingNews) {
                    developingBadge = '<div class="developing-news-badge">🔗 Related</div>';
                    // Add the blinking source reference badge
                    const truncatedMainTitle = truncateTitle(article.mainArticleTitle || 'Main Article');
                    sourceReferenceBadge = `
                        <div class="source-reference-badge">
                            <span class="source-icon">👈</span>
                            <span class="source-text">From: ${truncatedMainTitle}</span>
                        </div>
                    `;
                }
                
                card.innerHTML = `
                    <div class="news-card-image" id="img-${index}" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);" data-index="${index}">
                        ${developingBadge}
                        ${sourceReferenceBadge}
                        <span class="image-placeholder">🔥</span>
                        <div class="loading-spinner"></div>
                    </div>
                    <div class="news-card-content">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            ${trendingBadge}
                            <span style="background: #e0e7ff; color: #4f46e5; padding: 4px 10px; border-radius: 6px; font-size: 0.75em; font-weight: 600;">
                                ${article.sourceCount || 1} sources
                            </span>
                        </div>
                        <h2 class="news-title">${article.title}</h2>
                        <div class="news-meta">
                            <div class="news-meta-row">📰 ${article.sources || article.author}</div>
                            <div class="news-meta-row">📅 ${formattedDate} • 🕒 ${formattedTime}</div>
                        </div>
                    </div>
                `;
                newsGrid.appendChild(card);
            });
            
            setupInstantImageLoading();
        }

        function viewTrendingArticle(index) {
            const article = currentNews[index];
            const modal = document.getElementById('articleModal');
            const modalBody = document.getElementById('modalBody');
            
            // Store the main article reference for developing news
            currentMainArticle = {
                title: article.title,
                originalIndex: index,
                link: article.link,
                author: article.sources || article.author
            };
            
            const pubDate = parsePubDate(article.pubDate);
            const formattedDate = pubDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const imgElement = document.getElementById(`img-${index}`);
            const imgSrc = imgElement?.querySelector('img')?.src;
            
            developingNewsArticles = article.relatedArticles || [];
            
            // Generate unique clickable source links from relatedArticles (no duplicates)
            const uniqueSources = [];
            const seenSources = new Set();
            article.relatedArticles.forEach(related => {
                const sourceName = related.source.toLowerCase().trim();
                if (!seenSources.has(sourceName)) {
                    seenSources.add(sourceName);
                    uniqueSources.push({
                        name: related.source,
                        link: related.link
                    });
                }
            });
            
            const sourceLinks = uniqueSources.map(src => {
                return `<a href="${src.link}" target="_blank" class="main-source-link" onclick="event.stopPropagation();">${src.name}</a>`;
            }).join(', ');
            
            modalBody.innerHTML = `
                <div class="main-article">
                    ${imgSrc ? `<img src="${imgSrc}" class="main-article-image" alt="${article.title}">` : ''}
                    <div style="display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap;">
                        <span style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 0.9em;">
                            🔥 TRENDING
                        </span>
                        <span style="background: #e0e7ff; color: #4f46e5; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.9em;">
                            ${article.sourceCount} media outlets covering this
                        </span>
                    </div>
                    <h2 class="main-article-title">${article.title}</h2>
                    <div class="main-article-meta">
                        <strong>📰 Sources:</strong> ${sourceLinks}<br>
                        <strong>📅 Published:</strong> ${formattedDate}
                    </div>
                    <div class="article-actions">
                        <button class="import-article-btn" onclick='importSingleArticle(${JSON.stringify(article).replace(/'/g, "&apos;")})'>
                            💾 Import Article
                        </button>
                    </div>
                </div>
                
                <div class="developing-news-section">
                    <div class="developing-news-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <h3>📰 Coverage from Different Sources</h3>
                            <span class="developing-badge">${article.sourceCount} SOURCES</span>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.95em; color: #1e293b; font-weight: 500;">
                            <input type="checkbox" id="showDevelopingCheckbox" 
                                   onchange="toggleDevelopingNewsInDashboard()" 
                                   ${showDevelopingInDashboard ? 'checked' : ''}
                                   style="width: 18px; height: 18px; cursor: pointer; accent-color: #8b5cf6;">
                            <span>Show in Dashboard</span>
                        </label>
                    </div>
                    <div class="related-articles-grid">
                        ${article.relatedArticles.map(related => {
                            const relatedDate = parsePubDate(related.pubDate);
                            const relatedFormattedDate = relatedDate.toLocaleDateString('en-US', {
                                month: 'short', day: 'numeric'
                            });
                            const relatedFormattedTime = relatedDate.toLocaleTimeString('en-US', {
                                hour: '2-digit', minute: '2-digit'
                            });
                            
                            return `
                                <div class="related-article">
                                    <h4 class="related-article-title">${related.title}</h4>
                                    <div class="related-article-meta">
                                        <a href="${related.link}" target="_blank" class="source-link" onclick="event.stopPropagation();">📰 ${related.source}</a>
                                        <span>📅 ${relatedFormattedDate}</span>
                                        <span>🕒 ${relatedFormattedTime}</span>
                                    </div>
                                    <div class="related-article-actions">
                                        <button class="related-import-btn" 
                                            data-title="${related.title.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}"
                                            data-link="${related.link}"
                                            data-author="${related.source || ''}"
                                            data-pubdate="${related.pubDate}"
                                            onclick="event.stopPropagation(); importFromButton(this)">
                                            💾 Import
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
            
            if (showDevelopingInDashboard) {
                addDevelopingNewsToDashboard();
            }
        }

        async function fetchNews(query = 'latest news') {
            const loading = document.getElementById('loading');
            const newsGrid = document.getElementById('newsGrid');
            
            if (currentFetchController) {
                currentFetchController.abort();
            }
            
            if (fetchTimeout) {
                clearTimeout(fetchTimeout);
            }
            
            loading.style.display = 'block';
            loading.innerHTML = `
                <div class="loading-spinner-large"></div>
                <div class="loading-text">Loading fresh news...</div>
            `;
            newsGrid.innerHTML = '';
            
            console.log('🔍 Fetching news:', query);
            console.log('📍 Category:', currentCategory);
            console.log('🌍 Location:', currentLocation);
            
            currentFetchController = new AbortController();
            const signal = currentFetchController.signal;
            
            const proxyUrl = `rss_proxy_v3.php?q=${encodeURIComponent(query)}`;
            console.log('📡 Using server-side proxy:', proxyUrl);
            
            try {
                loading.innerHTML = `
                    <div class="loading-spinner-large"></div>
                    <div class="loading-text">Fetching from AI Generated News...</div>
                `;
                
                fetchTimeout = setTimeout(() => {
                    console.warn(`⏱️ Timeout after 15 seconds`);
                    if (!signal.aborted) {
                        currentFetchController.abort();
                    }
                }, 15000);
                
                const fetchStart = performance.now();
                const response = await fetch(proxyUrl, { 
                    signal,
                    headers: {
                        'Accept': 'application/xml, text/xml, */*'
                    }
                });
                const fetchEnd = performance.now();
                
                clearTimeout(fetchTimeout);
                
                console.log(`⏱️ Response time: ${((fetchEnd - fetchStart) / 1000).toFixed(2)}s`);
                console.log(`📊 Status: ${response.status} ${response.statusText}`);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('❌ Server error:', errorText);
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const xmlText = await response.text();
                console.log(`📥 Received ${xmlText.length} bytes`);
                console.log(`📄 First 200 chars: ${xmlText.substring(0, 200)}`);
                
                const articles = parseXMLFeed(xmlText);
                console.log(`✅ Parsed ${articles.length} articles`);
                
                if (articles.length > 0) {
                    currentNews = articles.sort((a, b) => parsePubDate(b.pubDate) - parsePubDate(a.pubDate));
                    displayNews(currentNews.slice(0, displayedCount));
                    loading.style.display = 'none';
                    updateStats();
                    console.log('🎉 SUCCESS! Articles loaded and displayed');
                    return;
                } else {
                    console.warn(`⚠️ No articles found in feed`);
                    throw new Error('No articles found in RSS feed');
                }
                
            } catch (error) {
                if (error.name === 'AbortError') {
                    console.log(`🚫 Request cancelled/timeout`);
                } else {
                    console.error(`❌ Error:`, error);
                }
                
                console.log('🧪 Auto-loading test data...');
                
                loading.innerHTML = `
                    <div style="text-align: center; padding: 40px; max-width: 600px; margin: 0 auto;">
                        <div class="loading-spinner-large"></div>
                        <div class="loading-text" style="margin-top: 20px;">
                            RSS feed unavailable<br>
                            <span style="color: #8b5cf6; font-weight: 600;">Loading sample data...</span>
                        </div>
                    </div>
                `;
                
                setTimeout(() => {
                    loadTestData();
                }, 1000);
            }
        }

        function parseXMLFeed(xmlString) {
            try {
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(xmlString, "text/xml");
                
                const parserError = xmlDoc.querySelector('parsererror');
                if (parserError) {
                    console.error('XML Parser Error:', parserError.textContent);
                    throw new Error('Invalid XML format');
                }
                
                const items = xmlDoc.querySelectorAll('item');
                console.log(`📦 Found ${items.length} items in RSS feed`);
                
                if (items.length === 0) {
                    console.warn('⚠️ No items found in RSS feed');
                    return [];
                }
                
                const articles = [];
                items.forEach((item, idx) => {
                    try {
                        let title = item.querySelector('title')?.textContent || '';
                        let link = item.querySelector('link')?.textContent || '#';
                        const pubDate = item.querySelector('pubDate')?.textContent || '';
                        
                        if (!title || !link) {
                            console.warn(`⚠️ Skipping item ${idx + 1}: missing title or link`);
                            return;
                        }
                        
                        let source = 'Unknown';
                        const lastDash = title.lastIndexOf(' - ');
                        if (lastDash > 0) {
                            source = title.substring(lastDash + 3).trim();
                            title = title.substring(0, lastDash).trim();
                        }
                        
                        articles.push({ 
                            title, 
                            link, 
                            pubDate, 
                            author: source 
                        });
                    } catch (itemError) {
                        console.warn(`⚠️ Error parsing item ${idx + 1}:`, itemError.message);
                    }
                });
                
                console.log(`✅ Successfully parsed ${articles.length} articles`);
                return articles;
                
            } catch (error) {
                console.error('❌ XML parsing failed:', error);
                throw new Error(`Failed to parse RSS feed: ${error.message}`);
            }
        }

        function createGradientPlaceholder(category) {
            const gradients = {
                'technology': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'business': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                'sports': 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                'entertainment': 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                'science': 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
                'health': 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
                'politics': 'linear-gradient(135deg, #f43b47 0%, #453a94 100%)',
                'weather': 'linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%)'
            };
            
            const emojis = {
                'technology': '💻', 'business': '💼', 'sports': '⚽',
                'entertainment': '🎬', 'science': '🔬', 'health': '❤️',
                'politics': '🏛️', 'weather': '🌤️'
            };
            
            let gradient = gradients[currentCategory.toLowerCase()] || 'linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%)';
            let emoji = emojis[currentCategory.toLowerCase()] || '📰';
            
            return { gradient, emoji };
        }

        async function displayNews(articles) {
            const newsGrid = document.getElementById('newsGrid');
            newsGrid.innerHTML = '';
            
            articles.forEach((article, index) => {
                const card = document.createElement('div');
                card.className = 'news-card';
                // IMPORTANT: Use viewArticleByIndex for smart routing
                card.onclick = () => viewArticleByIndex(index);
                
                const pubDate = parsePubDate(article.pubDate);
                const formattedDate = pubDate.toLocaleDateString('en-US', {
                    month: 'short', day: 'numeric'
                });
                const formattedTime = pubDate.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit'
                });
                
                const placeholder = createGradientPlaceholder(currentCategory);
                
                // Add badge for developing news articles with source reference
                let developingBadge = '';
                let sourceReferenceBadge = '';
                
                if (article.isDevelopingNews) {
                    developingBadge = '<div class="developing-news-badge">🔗 Related</div>';
                    // Add the blinking source reference badge
                    const truncatedMainTitle = truncateTitle(article.mainArticleTitle || 'Main Article');
                    sourceReferenceBadge = `
                        <div class="source-reference-badge">
                            <span class="source-icon">👈</span>
                            <span class="source-text">From: ${truncatedMainTitle}</span>
                        </div>
                    `;
                }
                
                card.innerHTML = `
                    <div class="news-card-image" id="img-${index}" style="background:${placeholder.gradient};" data-index="${index}">
                        ${developingBadge}
                        ${sourceReferenceBadge}
                        <span class="image-placeholder">${placeholder.emoji}</span>
                        <div class="loading-spinner"></div>
                    </div>
                    <div class="news-card-content">
                        <h2 class="news-title">${article.title}</h2>
                        <div class="news-meta">
                            <div class="news-meta-row">📰 ${article.author}</div>
                            <div class="news-meta-row">📅 ${formattedDate} • 🕒 ${formattedTime}</div>
                        </div>
                    </div>
                `;
                newsGrid.appendChild(card);
            });
            
            setupInstantImageLoading();
        }

        function setupInstantImageLoading() {
            const displayedArticles = document.querySelectorAll('.news-card-image');
            const displayedCount = displayedArticles.length;
            
            console.log(`⚡ Starting INSTANT image loading for ${displayedCount} displayed articles...`);
            
            const loadPromises = Array.from(displayedArticles).map((container) => {
                const index = parseInt(container.dataset.index);
                return loadImageForArticle(index);
            });
            
            Promise.allSettled(loadPromises).then(results => {
                const successful = results.filter(r => r.status === 'fulfilled').length;
                const failed = results.filter(r => r.status === 'rejected').length;
                console.log(`⚡ INSTANT loading complete: ${successful} succeeded, ${failed} failed in ${displayedCount} articles`);
            });
        }
        
        async function loadImageForArticle(index) {
            const article = currentNews[index];
            const imgContainer = document.getElementById(`img-${index}`);
            const spinner = imgContainer?.querySelector('.loading-spinner');
            const placeholder = createGradientPlaceholder(currentCategory);
            
            if (!imgContainer || !article) return;
            
            try {
                let imageUrl = null;
                
                if (article.imageUrl && article.imageUrl.trim() !== '') {
                    imageUrl = article.imageUrl;
                    console.log(`✓ Image from RSS for article ${index}`);
                }
                else if (currentCategory === 'Philippines Trending') {
                    try {
                        const searchQuery = `${article.title} ${article.source || ''} Philippines`;
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 5000);
                        
                        const response = await fetch(
                            `rss_images.php?action=get-rss-images&query=${encodeURIComponent(searchQuery)}&limit=3`,
                            { signal: controller.signal }
                        );
                        clearTimeout(timeoutId);
                        
                        if (response.ok) {
                            const data = await response.json();
                            if (data.success && data.articles && data.articles.length > 0) {
                                for (const rssArticle of data.articles) {
                                    if (rssArticle.imageUrl) {
                                        imageUrl = rssArticle.imageUrl;
                                        console.log(`✓ Image from RSS search for article ${index}`);
                                        break;
                                    }
                                }
                            }
                        }
                    } catch (e) {
                        console.log(`⚠ RSS image fetch failed for article ${index}, trying fallback`);
                    }
                }
                
                if (!imageUrl) {
                    const searchQuery = article.source 
                        ? `${article.title} ${article.source} Philippines news`
                        : article.title;
                    
                    const searchUrl = `image_search.php?action=search-image&title=${encodeURIComponent(searchQuery)}`;
                    
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 5000);
                    
                    const response = await fetch(searchUrl, { signal: controller.signal });
                    clearTimeout(timeoutId);
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.imageUrl) {
                            imageUrl = data.imageUrl;
                            console.log(`✓ Image from generic search for article ${index}`);
                        }
                    }
                }
                
                if (spinner) spinner.remove();
                
                if (imageUrl) {
                    const img = document.createElement('img');
                    img.src = imageUrl;
                    
                    img.onerror = () => {
                        console.log(`✗ Image load failed for article ${index}`);
                        if (imgContainer) imgContainer.innerHTML = `<span class="image-placeholder">${placeholder.emoji}</span>`;
                    };
                    
                    img.onload = () => {
                        if (imgContainer) imgContainer.classList.add('has-image');
                    };
                    
                    if (imgContainer) {
                        // Preserve badges when updating image
                        const developingBadge = imgContainer.querySelector('.developing-news-badge');
                        const sourceRefBadge = imgContainer.querySelector('.source-reference-badge');
                        
                        imgContainer.innerHTML = '';
                        if (developingBadge) imgContainer.appendChild(developingBadge.cloneNode(true));
                        if (sourceRefBadge) imgContainer.appendChild(sourceRefBadge.cloneNode(true));
                        imgContainer.appendChild(img);
                    }
                } else {
                    console.log(`⚠ No image found for article ${index}`);
                    // Preserve badges when showing placeholder
                    const developingBadge = imgContainer.querySelector('.developing-news-badge');
                    const sourceRefBadge = imgContainer.querySelector('.source-reference-badge');
                    
                    if (imgContainer) {
                        const badgesHTML = (developingBadge ? developingBadge.outerHTML : '') + 
                                          (sourceRefBadge ? sourceRefBadge.outerHTML : '');
                        imgContainer.innerHTML = `${badgesHTML}<span class="image-placeholder">${placeholder.emoji}</span>`;
                    }
                }
            } catch (error) {
                console.error(`✗ Error loading image for article ${index}:`, error);
                if (spinner) spinner.remove();
                // Preserve badges on error
                const developingBadge = imgContainer?.querySelector('.developing-news-badge');
                const sourceRefBadge = imgContainer?.querySelector('.source-reference-badge');
                
                if (imgContainer) {
                    const badgesHTML = (developingBadge ? developingBadge.outerHTML : '') + 
                                      (sourceRefBadge ? sourceRefBadge.outerHTML : '');
                    imgContainer.innerHTML = `${badgesHTML}<span class="image-placeholder">${placeholder.emoji}</span>`;
                }
            }
        }

        function searchNews() {
            const query = document.getElementById('searchInput').value.trim() || 'latest news';
            
            isTrendingActive = false;
            
            toggleLocationButtons(false);
            
            document.querySelectorAll('.category-tab, .location-tab').forEach(b => b.classList.remove('active'));
            
            currentCategory = query;
            currentLocation = 'worldwide';
            
            const filterDisplay = document.getElementById('filterDisplay');
            filterDisplay.innerHTML = `📂 <span id="categoryName">🔍 Search: ${query}</span> • <span id="locationName">🌐 Worldwide</span>`;
            
            console.log('🔍 Custom search:', query);
            
            if (currentFetchController) {
                currentFetchController.abort();
            }
            
            fetchNews(query);
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function extractKeywords(title) {
            const genericPrefixes = ['live updates:', 'breaking:', 'latest:', 'update:', 'news:', 'watch:', 'photos:', 'video:'];
            let cleanTitle = title.toLowerCase();
            
            genericPrefixes.forEach(prefix => {
                cleanTitle = cleanTitle.replace(prefix, '');
            });
            
            const stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'been', 'be', 'have', 'has', 'had', 'will', 'can', 'said', 'says', 'after', 'over', 'out', 'into'];
            
            const words = cleanTitle
                .replace(/[^\w\s-]/g, ' ')
                .split(/\s+/)
                .filter(word => word.length > 2 && !stopWords.includes(word));
            
            const originalWords = title.split(/\s+/);
            const properNouns = originalWords.filter(word => 
                word.length > 2 && 
                word[0] === word[0].toUpperCase() && 
                !genericPrefixes.some(prefix => word.toLowerCase().includes(prefix))
            );
            
            if (properNouns.length > 0) {
                return properNouns.slice(0, 4).join(' ');
            }
            
            return words.slice(0, 4).join(' ');
        }

        async function fetchDevelopingNews(mainArticle) {
            try {
                const keywords = extractKeywords(mainArticle.title);
                
                const proxyUrl = `rss_proxy_v3.php?q=${encodeURIComponent(keywords)}`;
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 8000);
                
                const response = await fetch(proxyUrl, { 
                    signal: controller.signal 
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                
                const xmlText = await response.text();
                const articles = parseXMLFeed(xmlText);
                
                const relatedArticles = articles
                    .filter(article => article.title !== mainArticle.title)
                    .slice(0, 5);
                
                return relatedArticles;
            } catch (error) {
                console.warn('Failed to fetch developing news:', error.message);
                return [];
            }
        }

        function importSingleArticle(article) {
            const tempBtn = document.createElement('button');
            tempBtn.style.display = 'none';
            document.body.appendChild(tempBtn);
            
            saveArticle(article, tempBtn);
            
            setTimeout(() => {
                if (tempBtn.parentNode) {
                    tempBtn.remove();
                }
            }, 100);
        }

        function importFromButton(btnElement) {
            const article = {
                title: btnElement.dataset.title,
                link: btnElement.dataset.link,
                author: btnElement.dataset.author,
                pubDate: btnElement.dataset.pubdate,
                description: ''
            };
            saveArticle(article, btnElement);
        }

        async function viewArticleWithDevelopingNews(index) {
            const article = currentNews[index];
            const modal = document.getElementById('articleModal');
            const modalBody = document.getElementById('modalBody');
            
            // Store the main article reference for developing news
            currentMainArticle = {
                title: article.title,
                originalIndex: index,
                link: article.link,
                author: article.author
            };
            
            const pubDate = parsePubDate(article.pubDate);
            const formattedDate = pubDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const imgElement = document.getElementById(`img-${index}`);
            const imgSrc = imgElement?.querySelector('img')?.src;
            
            modalBody.innerHTML = `
                <div class="main-article">
                    ${imgSrc ? `<img src="${imgSrc}" class="main-article-image" alt="${article.title}">` : ''}
                    <h2 class="main-article-title">${article.title}</h2>
                    <div class="main-article-meta">
                        <strong>📰 Source:</strong> ${article.author || 'Unknown'}<br>
                        <strong>📅 Published:</strong> ${formattedDate}
                    </div>
                    <div class="article-actions">
                        <a href="${article.link}" target="_blank" class="read-more-btn">
                            📖 Read
                        </a>
                        <button class="import-article-btn" onclick='importSingleArticle(${JSON.stringify(article).replace(/'/g, "&apos;")})'>
                            💾 Import
                        </button>
                    </div>
                </div>
                
                <div class="developing-news-section">
                    <div class="developing-news-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <h3>🔴 Developing News</h3>
                            <span class="developing-badge">LIVE</span>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.95em; color: #1e293b; font-weight: 500;">
                            <input type="checkbox" id="showDevelopingCheckbox" 
                                   onchange="toggleDevelopingNewsInDashboard()" 
                                   ${showDevelopingInDashboard ? 'checked' : ''}
                                   style="width: 18px; height: 18px; cursor: pointer; accent-color: #8b5cf6;">
                            <span>Show in Dashboard</span>
                        </label>
                    </div>
                    <div class="developing-news-loading">
                        <div class="loading-spinner-large" style="width: 40px; height: 40px;"></div>
                        <div style="margin-top: 16px;">Loading related articles...</div>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
            
            const relatedArticles = await fetchDevelopingNews(article);
            
            developingNewsArticles = relatedArticles;
            
            const developingSection = modalBody.querySelector('.developing-news-section');
            
            if (relatedArticles.length > 0) {
                let relatedHTML = '<div class="related-articles-grid">';
                relatedArticles.forEach(related => {
                    const relatedDate = parsePubDate(related.pubDate);
                    const relatedFormattedDate = relatedDate.toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric'
                    });
                    const relatedFormattedTime = relatedDate.toLocaleTimeString('en-US', {
                        hour: '2-digit', minute: '2-digit'
                    });
                    
                    relatedHTML += `
                        <div class="related-article">
                            <h4 class="related-article-title">${related.title}</h4>
                            <div class="related-article-meta">
                                <span>📰 ${related.author}</span>
                                <span>📅 ${relatedFormattedDate}</span>
                                <span>🕒 ${relatedFormattedTime}</span>
                            </div>
                            <div class="related-article-actions">
                                <button class="related-read-btn" onclick="event.stopPropagation(); window.open('${related.link}', '_blank')">
                                    📖 Read
                                </button>
                                <button class="related-import-btn" 
                                    data-title="${related.title.replace(/"/g, '&quot;').replace(/'/g, '&#39;')}"
                                    data-link="${related.link}"
                                    data-author="${related.author || ''}"
                                    data-pubdate="${related.pubDate}"
                                    onclick="event.stopPropagation(); importFromButton(this)">
                                    💾 Import
                                </button>
                            </div>
                        </div>
                    `;
                });
                relatedHTML += '</div>';
                
                developingSection.innerHTML = `
                    <div class="developing-news-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <h3>🔴 Developing News</h3>
                            <span class="developing-badge">LIVE</span>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.95em; color: #1e293b; font-weight: 500;">
                            <input type="checkbox" id="showDevelopingCheckbox" 
                                   onchange="toggleDevelopingNewsInDashboard()" 
                                   ${showDevelopingInDashboard ? 'checked' : ''}
                                   style="width: 18px; height: 18px; cursor: pointer; accent-color: #8b5cf6;">
                            <span>Show in Dashboard</span>
                        </label>
                    </div>
                    ${relatedHTML}
                `;
                
                if (showDevelopingInDashboard) {
                    addDevelopingNewsToDashboard();
                }
            } else {
                developingSection.innerHTML = `
                    <div class="developing-news-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <h3>🔴 Developing News</h3>
                            <span class="developing-badge">LIVE</span>
                        </div>
                    </div>
                    <div class="no-related">
                        No related articles found at this time.
                    </div>
                `;
            }
        }

        function closeModal() {
            document.getElementById('articleModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('articleModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        function importAllNews() {
            saveAllVisibleArticles();
        }

        function showDashboard() {
            window.location.href = 'http://newsnetwork.mbcradio.net/crud/user/user_dashboard.php';
        }

        function refreshNews() {
            if (isTrendingActive) {
                const trendingBtn = document.querySelector('.category-tab.trending-btn');
                if (trendingBtn) {
                    selectPhilippinesTrending(trendingBtn);
                }
            } else {
                fetchNews(buildQuery());
            }
        }
        
        function loadTestData() {
            console.log('🧪 Loading test data...');
            
            isTrendingActive = false;
            toggleLocationButtons(false);
            
            const testArticles = [
                {
                    title: "Philippines Economy Shows Strong Growth in Q4 2025",
                    link: "https://example.com/economy-growth",
                    pubDate: new Date().toUTCString(),
                    author: "Philippine Daily Inquirer"
                },
                {
                    title: "New AI Technology Revolutionizes Healthcare in Manila",
                    link: "https://example.com/ai-healthcare",
                    pubDate: new Date(Date.now() - 3600000).toUTCString(),
                    author: "TechCrunch Philippines"
                },
                {
                    title: "Typhoon Pepito Weakens, Moving Away from Luzon",
                    link: "https://example.com/typhoon-update",
                    pubDate: new Date(Date.now() - 7200000).toUTCString(),
                    author: "ABS-CBN News"
                },
                {
                    title: "Philippine Basketball Team Wins Regional Championship",
                    link: "https://example.com/basketball-win",
                    pubDate: new Date(Date.now() - 10800000).toUTCString(),
                    author: "Rappler Sports"
                },
                {
                    title: "New Infrastructure Projects Announced for Metro Manila",
                    link: "https://example.com/infrastructure",
                    pubDate: new Date(Date.now() - 14400000).toUTCString(),
                    author: "Business World"
                },
                {
                    title: "Local Scientists Discover New Marine Species in Palawan",
                    link: "https://example.com/marine-discovery",
                    pubDate: new Date(Date.now() - 18000000).toUTCString(),
                    author: "Science Daily PH"
                },
                {
                    title: "Filipino Startup Raises $10M in Series A Funding",
                    link: "https://example.com/startup-funding",
                    pubDate: new Date(Date.now() - 21600000).toUTCString(),
                    author: "Tech in Asia"
                },
                {
                    title: "Department of Health Launches New Vaccination Program",
                    link: "https://example.com/vaccination",
                    pubDate: new Date(Date.now() - 25200000).toUTCString(),
                    author: "Manila Bulletin"
                },
                {
                    title: "Cebu Tourism Industry Reports Record Numbers",
                    link: "https://example.com/cebu-tourism",
                    pubDate: new Date(Date.now() - 28800000).toUTCString(),
                    author: "SunStar Cebu"
                },
                {
                    title: "New Film Festival Showcases Filipino Cinema Excellence",
                    link: "https://example.com/film-festival",
                    pubDate: new Date(Date.now() - 32400000).toUTCString(),
                    author: "Variety Asia"
                },
                {
                    title: "Philippine Stock Exchange Reaches All-Time High",
                    link: "https://example.com/stock-market",
                    pubDate: new Date(Date.now() - 36000000).toUTCString(),
                    author: "Bloomberg Philippines"
                },
                {
                    title: "Innovative Education Platform Launches in Mindanao",
                    link: "https://example.com/education-tech",
                    pubDate: new Date(Date.now() - 39600000).toUTCString(),
                    author: "EdTech Magazine"
                }
            ];
            
            const loading = document.getElementById('loading');
            if (loading) {
                loading.innerHTML = `
                    <div id="successBanner" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; max-width: 700px; margin: 0 auto 30px; position: relative; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                        <button onclick="document.getElementById('successBanner').remove()" style="position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.2); border: none; color: white; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">×</button>
                        <div style="font-size: 2.5em; margin-bottom: 12px;">✅</div>
                        <div style="font-size: 1.3em; font-weight: 600; margin-bottom: 10px;">Test Data Loaded Successfully</div>
                        <div style="font-size: 0.95em; opacity: 0.95; line-height: 1.6;">
                            Showing 12 sample Philippine news articles below.<br>
                            <small style="opacity: 0.8;">The Google News RSS feed is currently unavailable (CORS proxy 503 errors).</small>
                        </div>
                    </div>
                `;
                loading.style.display = 'block';
            }
            
            const newsGrid = document.getElementById('newsGrid');
            if (newsGrid) {
                newsGrid.style.display = 'grid';
            }
            
            currentNews = testArticles;
            displayNews(currentNews.slice(0, displayedCount));
            updateStats();
            
            console.log('✅ Test data loaded successfully');
            
            setTimeout(() => {
                const newsGrid = document.getElementById('newsGrid');
                if (newsGrid && newsGrid.children.length > 0) {
                    newsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 300);
        }

        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchNews();
        });

        console.log('⚡ MediaStack News initialized - INSTANT LOADING MODE');
        console.log('🔥 Philippines Trending feature enabled!');
        console.log('📊 Performance Settings:');
        console.log('  - RSS Fetching: Server-side PHP proxy');
        console.log('  - Image loading: INSTANT (all parallel)');
        console.log('  - Trending: Multi-source aggregation (GMA, Inquirer, Philstar, Rappler, Manila Bulletin)');
        console.log('  - Location buttons: Disabled when Philippines Trending is active');
        console.log('  - Developing News Dashboard: Toggle with checkbox (works for both regular and trending)');
        console.log('  - Smart Article Viewing: Automatically routes to correct view function');
        
        function initializeDisplayBadges() {
            const now = new Date();
            
            // Set current date in header
            const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);
            
            // Set initial time
            document.getElementById('loadTime').textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            
            const activeCategoryBtn = document.querySelector('.category-tab.active');
            const activeLocationBtn = document.querySelector('.location-tab.active');
            
            let categoryDisplay = '🔥 Philippines Trending';
            if (activeCategoryBtn) {
                const buttonIcon = activeCategoryBtn.querySelector('span')?.textContent || '🔥';
                const categoryText = currentCategory.charAt(0).toUpperCase() + currentCategory.slice(1);
                categoryDisplay = `${buttonIcon} ${categoryText}`;
            }
            
            let locationDisplay = '🇵🇭 Philippines';
            if (activeLocationBtn) {
                locationDisplay = activeLocationBtn.textContent.trim();
            }
            
            const filterDisplay = document.getElementById('filterDisplay');
            filterDisplay.innerHTML = `📂 <span id="categoryName" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 700;">${categoryDisplay}</span> • <span id="locationName">🇵🇭 Top Media</span>`;
        }
        
        initializeDisplayBadges();
        
        window.onload = () => {
            console.log('📰 Loading initial news feed...');
            console.log('📍 Category:', currentCategory);
            console.log('🌍 Location:', currentLocation);
            
            const startTime = performance.now();
            
            // Default to Philippines Trending
            const trendingBtn = document.querySelector('.category-tab.trending-btn');
            if (trendingBtn) {
                selectPhilippinesTrending(trendingBtn).then(() => {
                    const endTime = performance.now();
                    console.log(`✅ Initial load completed in ${((endTime - startTime) / 1000).toFixed(2)}s`);
                }).catch(err => {
                    console.error('❌ Initial load failed:', err);
                    console.log('💡 Test data will auto-load...');
                });
            }
        };
    </script>
</body>
</html>