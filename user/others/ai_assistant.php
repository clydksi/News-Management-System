<?php
require dirname(__DIR__, 2) . '/auth.php';
header('Content-Type: application/json');

// OpenAI API Configuration
$_env = parse_ini_file(dirname(__DIR__, 2) . '/.env') ?: [];
define('OPENAI_API_KEY', $_env['OPENAI_API_KEY'] ?? '');
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

/**
 * Get request data - handles both JSON and multipart/form-data
 */
function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Check if this is a multipart form (with files)
    if (strpos($contentType, 'multipart/form-data') !== false) {
        return [
            'action' => $_POST['action'] ?? '',
            'prompt' => $_POST['prompt'] ?? '',
            'content' => $_POST['content'] ?? '',
            'title' => $_POST['title'] ?? '',
            'tone' => $_POST['tone'] ?? 'professional',
            'length' => $_POST['length'] ?? 'medium',
            'history' => json_decode($_POST['history'] ?? '[]', true),
            'files' => $_FILES['files'] ?? [],
            'has_files' => !empty($_FILES['files']['tmp_name'])
        ];
    } else {
        // Regular JSON request
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            return null;
        }
        
        return [
            'action' => $input['action'] ?? '',
            'prompt' => $input['prompt'] ?? '',
            'content' => $input['content'] ?? '',
            'title' => $input['title'] ?? '',
            'tone' => $input['tone'] ?? 'professional',
            'length' => $input['length'] ?? 'medium',
            'history' => $input['history'] ?? [],
            'files' => [],
            'has_files' => false
        ];
    }
}

/**
 * Process uploaded files for GPT-4 Vision
 */
function processFiles($files) {
    $processedFiles = [];
    
    if (empty($files) || !isset($files['tmp_name'])) {
        return $processedFiles;
    }
    
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $file = [
            'name' => $files['name'][$key],
            'type' => $files['type'][$key],
            'tmp_name' => $tmp_name,
            'size' => $files['size'][$key]
        ];
        
        // Get actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Only process images for GPT-4 Vision
        if (strpos($mimeType, 'image/') === 0) {
            $processedFiles[] = [
                'type' => 'image',
                'name' => $file['name'],
                'data' => fileToBase64($file),
                'mime_type' => $mimeType
            ];
        } elseif ($mimeType === 'application/pdf' || 
                  strpos($mimeType, 'text/') === 0 ||
                  in_array($mimeType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
            // Extract text from documents
            $textContent = extractTextFromFile($file, $mimeType);
            if ($textContent) {
                $processedFiles[] = [
                    'type' => 'document',
                    'name' => $file['name'],
                    'content' => $textContent
                ];
            }
        }
    }
    
    return $processedFiles;
}

/**
 * Convert file to base64 for API
 */
function fileToBase64($file) {
    $content = file_get_contents($file['tmp_name']);
    return base64_encode($content);
}

/**
 * Extract text from various file types
 */
function extractTextFromFile($file, $mimeType) {
    $content = '';
    
    if (strpos($mimeType, 'text/') === 0) {
        // Plain text files
        $content = file_get_contents($file['tmp_name']);
    } elseif ($mimeType === 'application/pdf') {
        // PDF files - basic extraction
        // Note: For production, consider using pdf-parser library
        $content = "PDF Document: {$file['name']} (content extraction requires additional library)";
    } elseif (in_array($mimeType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
        // Word documents
        $content = "Word Document: {$file['name']} (content extraction requires additional library)";
    }
    
    // Limit content length
    if (strlen($content) > 3000) {
        $content = substr($content, 0, 3000) . '... [truncated]';
    }
    
    return $content;
}

/**
 * Build messages array for OpenAI API with file support
 */
function buildMessages($data, $processedFiles) {
    $messages = [];
    
    // Add system message
    if ($data['action'] === 'chat') {
        $messages[] = [
            'role' => 'system',
            'content' => getChatSystemPrompt()
        ];
    } else {
        $messages[] = [
            'role' => 'system',
            'content' => getSystemPrompt($data['action'], $data['tone'], $data['length'])
        ];
    }
    
    // Add conversation history (for chat mode)
    if (!empty($data['history'])) {
        foreach ($data['history'] as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                if ($msg['role'] === 'user' || $msg['role'] === 'assistant') {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content']
                    ];
                }
            }
        }
    }
    
    // Build current user message
    $userContent = [];
    
    // Add file content/images first
    if (!empty($processedFiles)) {
        foreach ($processedFiles as $file) {
            if ($file['type'] === 'image') {
                // GPT-4 Vision format
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$file['mime_type']};base64,{$file['data']}"
                    ]
                ];
                
                // Add text describing the image
                $userContent[] = [
                    'type' => 'text',
                    'text' => "[Image attached: {$file['name']}]"
                ];
            } elseif ($file['type'] === 'document') {
                // Document content as text
                $userContent[] = [
                    'type' => 'text',
                    'text' => "[Document: {$file['name']}]\n\n{$file['content']}"
                ];
            }
        }
    }
    
    // Add main text prompt
    if ($data['action'] === 'chat') {
        $textContent = getChatUserPrompt($data['prompt'], $data['content'], $data['title']);
    } else {
        $textContent = getUserPrompt($data['action'], $data['content']);
    }
    
    $userContent[] = [
        'type' => 'text',
        'text' => $textContent
    ];
    
    // Add user message
    $messages[] = [
        'role' => 'user',
        'content' => $userContent
    ];
    
    return $messages;
}

/**
 * Choose appropriate model based on files
 */
function selectModel($hasFiles) {
    if ($hasFiles) {
        // GPT-4 Vision for image analysis
        return 'gpt-4o';  // or 'gpt-4-vision-preview'
    } else {
        // Regular GPT-4o-mini for text only
        return 'gpt-4o-mini';
    }
}

// ==========================================
// Main Execution
// ==========================================

try {
    // Get request data
    $data = getRequestData();
    
    if (!$data) {
        throw new Exception('Invalid request format');
    }
    
    // Validate action
    $allowedActions = ['generate', 'improve', 'summarize', 'expand', 'paraphrase', 'headline', 'grammar', 'chat', 'tone'];
    if (!in_array($data['action'], $allowedActions)) {
        throw new Exception('Invalid action');
    }
    
    // Process any uploaded files
    $processedFiles = processFiles($data['files']);
    
    // Build messages for API
    $messages = buildMessages($data, $processedFiles);
    
    // Select appropriate model
    $model = selectModel($data['has_files']);
    
    // Prepare API request
    $apiData = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => getTemperature($data['action']),
        'max_tokens' => getMaxTokens($data['action'], $data['length'])
    ];
    
    // Make API call
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for vision
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle errors
    if ($curlError) {
        throw new Exception('Connection failed: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        $errorResponse = json_decode($response, true);
        $errorMessage = $errorResponse['error']['message'] ?? 'API request failed';
        
        if ($httpCode === 401) {
            $errorMessage = 'Invalid API key';
        } elseif ($httpCode === 429) {
            $errorMessage = 'Rate limit exceeded. Please try again in a moment.';
        } elseif ($httpCode === 500) {
            $errorMessage = 'OpenAI service temporarily unavailable';
        }
        
        throw new Exception($errorMessage);
    }
    
    // Parse response
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from API');
    }
    
    $generatedText = trim($result['choices'][0]['message']['content']);
    
    // Post-process
    $generatedText = postProcessResponse($generatedText, $data['action']);
    
    // Log usage
    logUsage($data['action'], $result['usage'] ?? null, count($processedFiles));
    
    // Return success
    echo json_encode([
        'success' => true,
        'content' => $generatedText,
        'action' => $data['action'],
        'files_processed' => count($processedFiles),
        'model_used' => $model,
        'usage' => $result['usage'] ?? null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ==========================================
// Helper Functions (from original)
// ==========================================

function getChatSystemPrompt() {
    return "You are an expert AI writing assistant for news articles. You help writers with brainstorming, outlining, drafting, and rewriting content. 

Your role is to:
- Help generate creative ideas and angles for news stories
- Create structured outlines for articles
- Write clear, engaging, and well-structured drafts
- Improve existing content with better flow and clarity
- Adapt tone based on the context (professional, casual, formal, etc.)
- Analyze images and documents when provided to enhance your assistance

Be helpful, concise, and actionable. When providing suggestions, be specific. When generating content, ensure it's well-structured and publication-ready. Always maintain journalistic standards and accuracy.

When images or documents are attached:
- Analyze them thoroughly
- Reference specific details from the attachments
- Incorporate insights into your suggestions";
}

function getChatUserPrompt($prompt, $content, $title) {
    $context = '';
    
    if (!empty($title)) {
        $context .= "Article Title: {$title}\n\n";
    }
    
    if (!empty($content) && strlen($content) > 10) {
        // Limit content length to avoid token issues
        $contentPreview = strlen($content) > 1000 ? substr($content, 0, 1000) . '...' : $content;
        $context .= "Current Content:\n{$contentPreview}\n\n";
    }
    
    return $context . "User Request: {$prompt}";
}

function getSystemPrompt($action, $tone, $length) {
    $toneDescriptions = [
        'professional' => 'formal and professional',
        'casual' => 'conversational and friendly',
        'formal' => 'strictly formal and academic',
        'neutral' => 'balanced and objective'
    ];
    
    $toneDesc = $toneDescriptions[$tone] ?? 'professional';
    $basePrompt = "You are an expert news writing assistant. Your responses should be clear, concise, and ready to use. ";
    
    switch ($action) {
        case 'generate':
            return $basePrompt . "Generate a complete, well-structured news article based on the given topic or outline. 

Requirements:
- Use a {$toneDesc} tone
- Include a compelling introduction that hooks the reader
- Organize content with clear paragraphs
- Add relevant details and context
- End with a strong conclusion
- Maintain journalistic standards and objectivity
- Make it publication-ready

The article should flow naturally and be engaging throughout.";
        
        case 'improve':
            return $basePrompt . "Improve the given text by enhancing clarity, flow, and impact while maintaining the original message. 

Focus on:
- Enhancing sentence structure and flow
- Improving word choice and clarity
- Making it more engaging and impactful
- Maintaining {$toneDesc} tone
- Fixing awkward phrasing
- Preserving the core message and key points

Return only the improved version without explanations.";
        
        case 'summarize':
            return $basePrompt . "Create a concise, informative summary of the given text. 

Requirements:
- Focus on main points and key information
- Maintain {$toneDesc} tone
- Keep it clear and well-organized
- Preserve important details
- Make it standalone readable

Return only the summary without introductory phrases.";
        
        case 'expand':
            return $basePrompt . "Expand the given text with more details, examples, and explanations. 

Requirements:
- Add depth and context
- Include relevant examples or data points
- Maintain {$toneDesc} tone
- Keep the core message intact
- Make transitions smooth
- Ensure new content adds value

Return only the expanded version.";
        
        case 'paraphrase':
            return $basePrompt . "Rewrite the given text using different words and sentence structures while preserving the exact meaning. 

Requirements:
- Use varied vocabulary and syntax
- Maintain {$toneDesc} tone
- Keep the same information and meaning
- Improve flow if possible
- Maintain the same length approximately

Return only the paraphrased version.";
        
        case 'headline':
            return $basePrompt . "Generate compelling, accurate news headlines for the given content. 

Requirements:
- Create exactly 5 different headline options
- Make them attention-grabbing yet accurate
- Use {$toneDesc} tone
- Keep each under 100 characters
- Number them 1-5
- Make each one unique in approach
- Ensure they capture the essence of the article

Format: One headline per line, numbered 1-5.";
        
        case 'grammar':
            return $basePrompt . "Check and correct all grammar, spelling, punctuation, and syntax errors in the given text. 

Requirements:
- Fix all grammatical errors
- Correct spelling mistakes
- Improve punctuation
- Maintain {$toneDesc} tone
- Preserve the original style and voice
- Keep the meaning unchanged
- Make minimal changes to structure

Return only the corrected version without markup or explanations.";
        
        case 'tone':
            return $basePrompt . "Adjust the tone of the given text. 

Requirements:
- Rewrite to match the {$toneDesc} tone
- Maintain all key information
- Preserve the core message
- Adjust language and style appropriately
- Keep the same structure

Return only the adjusted version.";
        
        default:
            return $basePrompt . "Process the given text according to best practices for news writing.";
    }
}

function getUserPrompt($action, $content) {
    if (empty($content)) {
        return "Please provide content to work with.";
    }
    
    // Trim content if too long
    $maxContentLength = 6000;
    if (strlen($content) > $maxContentLength) {
        $content = substr($content, 0, $maxContentLength) . '...';
    }
    
    switch ($action) {
        case 'generate':
            return "Topic/Outline:\n\n{$content}\n\nGenerate a complete news article based on this.";
        default:
            return $content;
    }
}

function getMaxTokens($action, $length) {
    $lengthTokens = [
        'short' => 400,
        'medium' => 1000,
        'long' => 2000
    ];
    
    $baseTokens = $lengthTokens[$length] ?? 1000;
    
    switch ($action) {
        case 'generate':
            return $baseTokens;
        case 'expand':
            return min($baseTokens * 1.5, 2000);
        case 'summarize':
            return min($baseTokens * 0.5, 600);
        case 'headline':
            return 250;
        case 'chat':
            return 2000; // Increased for file analysis
        default:
            return $baseTokens;
    }
}

function getTemperature($action) {
    switch ($action) {
        case 'generate':
        case 'expand':
        case 'headline':
        case 'chat':
            return 0.8;
        case 'grammar':
        case 'summarize':
            return 0.3;
        default:
            return 0.6;
    }
}

function postProcessResponse($text, $action) {
    $prefixes = [
        'Here is the ',
        'Here\'s the ',
        'Here are ',
        'Here\'s ',
        'Here is ',
        'Sure! Here is ',
        'Sure! Here\'s ',
        'Certainly! Here is ',
        'Certainly! Here\'s ',
        'Of course! Here is ',
        'Of course! Here\'s '
    ];
    
    foreach ($prefixes as $prefix) {
        if (stripos($text, $prefix) === 0) {
            $text = substr($text, strlen($prefix));
            if (($colonPos = strpos($text, ':')) !== false && $colonPos < 50) {
                $text = trim(substr($text, $colonPos + 1));
            }
            break;
        }
    }
    
    if ($action === 'headline') {
        $lines = explode("\n", $text);
        $cleanedLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (!preg_match('/^\d+\./', $line)) {
                $line = (count($cleanedLines) + 1) . '. ' . $line;
            }
            
            $cleanedLines[] = $line;
        }
        
        return implode("\n", array_slice($cleanedLines, 0, 5));
    }
    
    return trim($text);
}

function logUsage($action, $usage, $fileCount) {
    if (!$usage) return;
    
    $logFile = dirname(__DIR__, 2) . '/logs/ai_usage.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] Action: %s | Files: %d | Tokens: %d (prompt: %d, completion: %d) | User: %s\n",
        date('Y-m-d H:i:s'),
        $action,
        $fileCount,
        $usage['total_tokens'] ?? 0,
        $usage['prompt_tokens'] ?? 0,
        $usage['completion_tokens'] ?? 0,
        $_SESSION['username'] ?? 'unknown'
    );
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}