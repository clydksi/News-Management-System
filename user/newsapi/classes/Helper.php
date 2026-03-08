<?php
/**
 * Helper Utilities
 * Common utility functions used throughout the application
 */

class Helper {
    /**
     * HTML escape string
     * 
     * @param mixed $string String to escape
     * @return string Escaped string
     */
    public static function e($string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Convert datetime to relative time string
     * 
     * @param string $datetime Datetime string
     * @return string Relative time (e.g., "2 hours ago")
     */
    public static function timeAgo(string $datetime): string {
        $time = strtotime($datetime);
        if ($time === false) {
            return 'Unknown';
        }
        
        $diff = time() - $time;
        
        if ($diff < 60) {
            return 'Just now';
        }
        
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min' . ($mins !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
        }
        
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
        }
        
        return date('M d, Y', $time);
    }
    
    /**
     * Build pagination URL with parameters
     * 
     * @param int $page Page number
     * @param array $params Additional parameters
     * @return string URL string
     */
    public static function buildPaginationUrl(int $page, array $params = []): string {
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
    
    /**
     * Get request parameter with default value
     * 
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @param string $method Request method (GET or POST)
     * @return mixed Parameter value or default
     */
    public static function getParam(string $key, $default = null, string $method = 'GET') {
        $source = $method === 'POST' ? $_POST : $_GET;
        return $source[$key] ?? $default;
    }
    
    /**
     * Sanitize integer input
     * 
     * @param mixed $value Input value
     * @param int $default Default value
     * @param int $min Minimum value
     * @param int|null $max Maximum value
     * @return int Sanitized integer
     */
    public static function sanitizeInt($value, int $default = 0, int $min = 0, ?int $max = null): int {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            return $default;
        }
        
        $int = max($min, $int);
        if ($max !== null) {
            $int = min($max, $int);
        }
        
        return $int;
    }
    
    /**
     * Sanitize string input
     * 
     * @param mixed $value Input value
     * @param string $default Default value
     * @param int $maxLength Maximum length
     * @return string Sanitized string
     */
    public static function sanitizeString($value, string $default = '', int $maxLength = 255): string {
        if (!is_string($value)) {
            return $default;
        }
        
        $string = trim($value);
        if (strlen($string) > $maxLength) {
            $string = substr($string, 0, $maxLength);
        }
        
        return $string;
    }
    
    /**
     * Format file size in human-readable format
     * 
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public static function formatFileSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    public static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Get category icon name
     * 
     * @param string $category Category name
     * @param array $iconMap Icon mapping
     * @return string Icon name
     */
    public static function getCategoryIcon(string $category, array $iconMap): string {
        return $iconMap[$category] ?? 'article';
    }
    
    /**
     * Truncate text to specified length
     * 
     * @param string $text Text to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to add
     * @return string Truncated text
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }
}