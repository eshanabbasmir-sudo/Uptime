<?php
// index.php - 24/7 PHP Web Browser
// Real browser proxy with cookie support, form handling, and JavaScript execution

session_start();

// Configuration
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
define('TIMEOUT', 30);
define('MAX_REDIRECTS', 5);

// Cookie handling
$cookieFile = sys_get_temp_dir() . '/php_browser_cookies_' . session_id() . '.txt';

// Initialize cURL handle
function initCurl($url) {
    global $cookieFile;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, MAX_REDIRECTS);
    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
    curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Handle gzip/deflate
    curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER'] ?? '');
    
    return $ch;
}

// Get the URL to fetch
$url = isset($_GET['url']) ? $_GET['url'] : '';
$baseUrl = '';

// Handle POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_browser_url'])) {
    $url = $_POST['_browser_url'];
    $postFields = [];
    foreach ($_POST as $key => $value) {
        if ($key !== '_browser_url') {
            $postFields[$key] = $value;
        }
    }
} else {
    $postFields = [];
}

// Clean URL
if (!empty($url)) {
    // Add http:// if no protocol specified
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'http://' . $url;
    }
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "Invalid URL format. Please enter a valid URL.";
        $url = '';
    }
}

$content = '';
$headers = [];
$statusCode = 0;
$error = '';

// Fetch the URL
if (!empty($url)) {
    try {
        $ch = initCurl($url);
        
        // Handle POST data
        if (!empty($postFields)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }
        
        // Handle custom headers from the browser
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: ' . $_SERVER['HTTP_ACCEPT'],
                'Accept-Language: ' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US,en;q=0.9'),
                'Cache-Control: no-cache'
            ]);
        }
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $statusCode = $info['http_code'];
        
        // Extract headers and body
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerStr = substr($response, 0, $headerSize);
        $content = substr($response, $headerSize);
        
        // Parse headers
        $headers = [];
        $headerLines = explode("\r\n", $headerStr);
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        // Check for errors
        if (curl_errno($ch)) {
            $error = "CURL Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
        // Rewrite content to handle relative URLs
        if (!empty($content) && !$error) {
            $baseUrl = $url;
            $content = rewriteUrls($content, $baseUrl);
        }
        
    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
    }
}

// Function to rewrite URLs to absolute
function rewriteUrls($html, $baseUrl) {
    // Handle base tag
    $html = preg_replace_callback(
        '/<base\s+([^>]*?)href=["\']([^"\']*)["\']([^>]*?)>/i',
        function($matches) use ($baseUrl) {
            $href = $matches[2];
            if (!preg_match('/^https?:\/\//i', $href)) {
                $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                return '<base ' . $matches[1] . 'href="' . $href . '"' . $matches[3] . '>';
            }
            return $matches[0];
        },
        $html
    );
    
    // If no base tag, add one
    if (stripos($html, '<base') === false) {
        $html = preg_replace('/<head>/i', '<head><base href="' . $baseUrl . '">', $html);
    }
    
    // Rewrite various tags
    $patterns = [
        '/(<link\s+[^>]*?href=["\'])([^"\']*?)(["\'])/i',
        '/(<script\s+[^>]*?src=["\'])([^"\']*?)(["\'])/i',
        '/(<img\s+[^>]*?src=["\'])([^"\']*?)(["\'])/i',
        '/(<a\s+[^>]*?href=["\'])([^"\']*?)(["\'])/i',
        '/(<iframe\s+[^>]*?src=["\'])([^"\']*?)(["\'])/i',
        '/(<video\s+[^>]*?src=["\'])([^"\']*?)(["\'])/i',
        '/(<audio\s+[^>]*?src=["\'])([^"\']*?)(["\'])/i',
    ];
    
    foreach ($patterns as $pattern) {
        $html = preg_replace_callback(
            $pattern,
            function($matches) use ($baseUrl) {
                $url = $matches[2];
                // Skip if absolute URL or javascript or data or mailto
                if (preg_match('/^(https?:\/\/|javascript:|data:|mailto:|#)/i', $url)) {
                    return $matches[0];
                }
                // Make absolute URL
                if (strpos($url, '//') === 0) {
                    $url = 'https:' . $url;
                } else {
                    $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                }
                return $matches[1] . $url . $matches[3];
            },
            $html
        );
    }
    
    // Rewrite CSS urls in style attributes
    $html = preg_replace_callback(
        '/(style=["\'])([^"\']*?)(["\'])/i',
        function($matches) use ($baseUrl) {
            $style = $matches[2];
            $style = preg_replace_callback(
                '/url\(["\']?([^"\']*?)["\']?\)/i',
                function($urlMatches) use ($baseUrl) {
                    $url = $urlMatches[1];
                    if (!preg_match('/^(https?:\/\/|data:|#)/i', $url)) {
                        $url = rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
                    }
                    return 'url("' . $url . '")';
                },
                $style
            );
            return $matches[1] . $style . $matches[3];
        },
        $html
    );
    
    return $html;
}

// Get current URL for form submission
$currentUrl = !empty($url) ? htmlspecialchars($url) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Browser - 24/7 Real Browser</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #1a1a2e;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* Toolbar */
        .browser-toolbar {
            background: linear-gradient(135deg, #16213e, #0f3460);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            border-bottom: 2px solid #533483;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            z-index: 100;
            flex-wrap: wrap;
        }
        
        .brand {
            color: #e94560;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .brand span {
            color: #fff;
            font-weight: 300;
            font-size: 0.8rem;
            background: #533483;
            padding: 2px 12px;
            border-radius: 20px;
        }
        
        .url-bar {
            flex: 1;
            min-width: 200px;
            display: flex;
            background: rgba(255,255,255,0.1);
            border-radius: 30px;
            border: 2px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .url-bar:focus-within {
            border-color: #e94560;
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 20px rgba(233, 69, 96, 0.2);
        }
        
        .url-bar input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 10px 16px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            min-width: 100px;
        }
        
        .url-bar input::placeholder {
            color: rgba(255,255,255,0.4);
        }
        
        .url-bar button {
            background: #e94560;
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 24px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .url-bar button:hover {
            background: #c73652;
            transform: scale(1.02);
        }
        
        .status-bar {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
            padding: 6px 16px;
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4caf50;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }
        
        .error-dot {
            background: #f44336;
        }
        
        /* Content area */
        .browser-content {
            flex: 1;
            position: relative;
            background: #ffffff;
            overflow: hidden;
        }
        
        .browser-content iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #ffffff;
        }
        
        .error-page {
            padding: 40px;
            text-align: center;
            color: #333;
            background: #f5f5f5;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .error-page h2 {
            color: #e94560;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        
        .error-page .error-details {
            background: #fff;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 20px auto;
            text-align: left;
            border-left: 4px solid #e94560;
        }
        
        .error-page .error-details code {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
            flex-direction: column;
            gap: 20px;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #e94560;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .browser-toolbar {
                padding: 8px 12px;
                gap: 8px;
            }
            .brand {
                font-size: 1rem;
            }
            .brand span {
                font-size: 0.6rem;
                padding: 1px 8px;
            }
            .url-bar input {
                font-size: 0.8rem;
                padding: 8px 12px;
            }
            .url-bar button {
                padding: 8px 16px;
                font-size: 0.8rem;
            }
            .status-bar {
                font-size: 0.6rem;
                padding: 4px 10px;
            }
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <!-- Toolbar -->
    <div class="browser-toolbar">
        <div class="brand">
            🌐 PHP <span>24/7</span>
        </div>
        
        <form method="GET" action="" class="url-bar" style="display:flex; flex:1;">
            <input type="text" name="url" id="urlInput" 
                   placeholder="Enter URL (e.g., google.com)" 
                   value="<?php echo htmlspecialchars($_GET['url'] ?? ''); ?>"
                   autofocus>
            <button type="submit">↗ Go</button>
        </form>
        
        <div class="status-bar">
            <span class="status-dot <?php echo !empty($error) ? 'error-dot' : ''; ?>"></span>
            <span id="statusText">
                <?php 
                if (!empty($error)) {
                    echo 'Error';
                } elseif (!empty($url)) {
                    echo $statusCode . ' - ' . ($statusCode === 200 ? 'OK' : $statusCode);
                } else {
                    echo 'Ready';
                }
                ?>
            </span>
        </div>
    </div>
    
    <!-- Content -->
    <div class="browser-content">
        <?php if (!empty($error)): ?>
            <!-- Error Page -->
            <div class="error-page">
                <h2>⚠️ Error Loading Page</h2>
                <div class="error-details">
                    <p><strong>URL:</strong> <code><?php echo htmlspecialchars($url); ?></code></p>
                    <p><strong>Status:</strong> <?php echo $statusCode; ?></p>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
                    <?php if (!empty($headers)): ?>
                        <p><strong>Headers:</strong></p>
                        <ul style="margin-left: 20px; font-size: 0.8rem; color: #666;">
                            <?php foreach ($headers as $key => $value): ?>
                                <?php if (in_array($key, ['Content-Type', 'Server', 'Date'])): ?>
                                    <li><?php echo htmlspecialchars($key . ': ' . $value); ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <p style="margin-top: 20px; color: #999; font-size: 0.9rem;">
                    💡 Check the URL and try again, or use a different website.
                </p>
            </div>
        <?php elseif (!empty($url) && !empty($content)): ?>
            <!-- Display the fetched content -->
            <?php 
            // Detect content type
            $contentType = $headers['Content-Type'] ?? '';
            $isHtml = strpos(strtolower($contentType), 'text/html') !== false;
            
            if ($isHtml): ?>
                <!-- HTML Content -->
                <iframe id="browserFrame" srcdoc="<?php echo htmlspecialchars($content, ENT_QUOTES); ?>"></iframe>
            <?php else: ?>
                <!-- Non-HTML Content (JSON, XML, text, etc.) -->
                <div style="padding: 40px; background: #1a1a2e; color: #fff; height: 100%; overflow: auto; font-family: monospace;">
                    <h3 style="color: #e94560; margin-bottom: 20px;">📄 Raw Content</h3>
                    <div style="background: #2d2d44; padding: 20px; border-radius: 8px; overflow: auto; max-height: 80vh;">
                        <pre style="white-space: pre-wrap; word-wrap: break-word; color: #a8a8b8;"><?php echo htmlspecialchars($content); ?></pre>
                    </div>
                    <div style="margin-top: 20px; color: #888;">
                        <strong>Content-Type:</strong> <?php echo htmlspecialchars($contentType); ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Home Page -->
            <div class="error-page" style="background: linear-gradient(135deg, #1a1a2e, #16213e); color: #fff;">
                <div style="max-width: 500px;">
                    <h1 style="font-size: 4rem; margin-bottom: 10px;">🌐</h1>
                    <h2 style="color: #e94560; margin-bottom: 10px;">PHP Browser</h2>
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 30px;">
                        Enter a URL in the address bar above to browse the web.
                    </p>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <a href="?url=https://google.com" style="background: #e94560; color: #fff; padding: 10px 20px; border-radius: 25px; text-decoration: none; transition: 0.3s;">Google</a>
                        <a href="?url=https://github.com" style="background: #533483; color: #fff; padding: 10px 20px; border-radius: 25px; text-decoration: none; transition: 0.3s;">GitHub</a>
                        <a href="?url=https://wikipedia.org" style="background: #0f3460; color: #fff; padding: 10px 20px; border-radius: 25px; text-decoration: none; transition: 0.3s;">Wikipedia</a>
                        <a href="?url=https://php.net" style="background: #16213e; color: #e94560; padding: 10px 20px; border-radius: 25px; text-decoration: none; border: 2px solid #e94560; transition: 0.3s;">PHP.net</a>
                    </div>
                    <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <p style="color: rgba(255,255,255,0.4); font-size: 0.8rem;">
                            🔒 SSL verification disabled for compatibility<br>
                            🍪 Cookies supported<br>
                            🔄 Follows redirects<br>
                            📝 Handles forms
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus on URL input
        document.addEventListener('DOMContentLoaded', function() {
            const urlInput = document.getElementById('urlInput');
            if (urlInput) {
                urlInput.focus();
                urlInput.select();
            }
            
            // Handle form submission for the browser
            const form = document.querySelector('.url-bar');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const input = document.getElementById('urlInput');
                    let url = input.value.trim();
                    if (url && !url.match(/^https?:\/\//i)) {
                        url = 'http://' + url;
                        input.value = url;
                    }
                });
            }
        });
    </script>
</body>
</html>
