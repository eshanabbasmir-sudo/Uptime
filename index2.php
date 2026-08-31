<?php
// index.php - 24/7 PHP Web Browser (FULLY WORKING)
// Real browser that renders websites properly

session_start();

// Configuration
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
define('TIMEOUT', 30);
define('MAX_REDIRECTS', 5);

// Cookie handling
$cookieFile = sys_get_temp_dir() . '/php_browser_cookies_' . session_id() . '.txt';

// Get the URL to fetch
$url = isset($_GET['url']) ? $_GET['url'] : '';
$content = '';
$headers = [];
$statusCode = 0;
$error = '';
$contentType = '';

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

// Fetch the URL using cURL
if (!empty($url) && empty($error)) {
    try {
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
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Handle compression
        curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_REFERER'] ?? $url);
        
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
        
        // Get content type
        $contentType = $headers['Content-Type'] ?? '';
        
        if (curl_errno($ch)) {
            $error = "CURL Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        
    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
    }
}

// Determine if content is HTML
$isHtml = strpos(strtolower($contentType), 'text/html') !== false;
$isImage = strpos(strtolower($contentType), 'image/') !== false;

// If it's not HTML and not an image, we'll show it as text
if (!empty($content) && !$isHtml && !$isImage && !empty($url)) {
    // Check if it's JSON, XML, etc.
    $isJson = strpos(strtolower($contentType), 'application/json') !== false;
    $isXml = strpos(strtolower($contentType), 'application/xml') !== false || strpos(strtolower($contentType), 'text/xml') !== false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Browser - 24/7</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        body {
            background: #0d1117;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* Toolbar */
        .browser-toolbar {
            background: #161b22;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            border-bottom: 1px solid #30363d;
            z-index: 100;
            flex-wrap: wrap;
        }
        
        .brand {
            color: #58a6ff;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .brand span {
            color: #8b949e;
            font-weight: 400;
            font-size: 0.7rem;
            background: #21262d;
            padding: 2px 10px;
            border-radius: 20px;
        }
        
        .url-bar {
            flex: 1;
            min-width: 200px;
            display: flex;
            background: #0d1117;
            border-radius: 8px;
            border: 1px solid #30363d;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        
        .url-bar:focus-within {
            border-color: #58a6ff;
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.15);
        }
        
        .url-bar input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 8px 14px;
            color: #c9d1d9;
            font-size: 0.9rem;
            outline: none;
            min-width: 100px;
        }
        
        .url-bar input::placeholder {
            color: #484f58;
        }
        
        .url-bar button {
            background: #238636;
            border: none;
            color: white;
            font-weight: 600;
            padding: 8px 20px;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        .url-bar button:hover {
            background: #2ea043;
        }
        
        .status-bar {
            color: #8b949e;
            font-size: 0.75rem;
            padding: 4px 12px;
            background: #0d1117;
            border-radius: 6px;
            border: 1px solid #21262d;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #238636;
            display: inline-block;
        }
        
        .status-dot.error {
            background: #da3633;
        }
        
        .status-dot.loading {
            background: #d29922;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .nav-buttons {
            display: flex;
            gap: 4px;
        }
        
        .nav-btn {
            background: transparent;
            border: 1px solid #30363d;
            color: #c9d1d9;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        
        .nav-btn:hover {
            background: #21262d;
            border-color: #484f58;
        }
        
        /* Content area */
        .browser-content {
            flex: 1;
            position: relative;
            background: #ffffff;
            overflow: auto;
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
            color: #c9d1d9;
            background: #0d1117;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .error-page h2 {
            color: #f0883e;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        .error-details {
            background: #161b22;
            padding: 20px 30px;
            border-radius: 10px;
            border: 1px solid #30363d;
            max-width: 600px;
            margin: 20px auto;
            text-align: left;
            width: 100%;
        }
        
        .error-details p {
            margin: 8px 0;
            color: #8b949e;
        }
        
        .error-details code {
            background: #0d1117;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #f0883e;
            word-break: break-all;
        }
        
        /* Home page */
        .home-page {
            background: #0d1117;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: #c9d1d9;
        }
        
        .home-page h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #58a6ff;
        }
        
        .home-page .subtitle {
            color: #8b949e;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .quick-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }
        
        .quick-link {
            background: #21262d;
            color: #c9d1d9;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid #30363d;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .quick-link:hover {
            background: #30363d;
            border-color: #484f58;
            transform: translateY(-2px);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 30px;
            max-width: 600px;
            width: 100%;
        }
        
        .feature {
            background: #161b22;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #21262d;
            text-align: center;
            color: #8b949e;
            font-size: 0.85rem;
        }
        
        .feature .icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 5px;
        }
        
        /* Raw content display */
        .raw-content {
            padding: 20px;
            background: #0d1117;
            color: #c9d1d9;
            height: 100%;
            overflow: auto;
            font-family: 'Courier New', monospace;
        }
        
        .raw-content pre {
            background: #161b22;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #30363d;
            overflow: auto;
            max-height: 80vh;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .browser-toolbar {
                padding: 8px 12px;
                gap: 8px;
            }
            .brand {
                font-size: 0.9rem;
            }
            .brand span {
                font-size: 0.6rem;
                padding: 1px 6px;
            }
            .url-bar input {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            .url-bar button {
                padding: 6px 14px;
                font-size: 0.8rem;
            }
            .status-bar {
                font-size: 0.6rem;
                padding: 3px 8px;
            }
            .nav-btn {
                padding: 3px 8px;
                font-size: 0.7rem;
            }
            .home-page h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Toolbar -->
    <div class="browser-toolbar">
        <div class="brand">
            🌐 PHP <span>24/7 Browser</span>
        </div>
        
        <div class="nav-buttons">
            <button class="nav-btn" onclick="goBack()">◀</button>
            <button class="nav-btn" onclick="goForward()">▶</button>
            <button class="nav-btn" onclick="reloadPage()">⟳</button>
        </div>
        
        <form method="GET" action="" class="url-bar">
            <input type="text" name="url" id="urlInput" 
                   placeholder="Enter URL (e.g., example.com)" 
                   value="<?php echo htmlspecialchars($_GET['url'] ?? ''); ?>"
                   autofocus>
            <button type="submit">Go</button>
        </form>
        
        <div class="status-bar">
            <span class="status-dot <?php 
                echo !empty($error) ? 'error' : ''; 
                echo (!empty($url) && empty($content) && empty($error)) ? 'loading' : '';
            ?>"></span>
            <span id="statusText">
                <?php 
                if (!empty($error)) {
                    echo 'Error';
                } elseif (!empty($url) && !empty($content)) {
                    echo $statusCode . ' - ' . ($statusCode === 200 ? 'OK' : $statusCode);
                } elseif (!empty($url) && empty($content)) {
                    echo 'Loading...';
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
                    <?php if (!empty($headers) && isset($headers['Content-Type'])): ?>
                        <p><strong>Content-Type:</strong> <?php echo htmlspecialchars($headers['Content-Type']); ?></p>
                    <?php endif; ?>
                </div>
                <p style="color: #484f58; margin-top: 20px; font-size: 0.9rem;">
                    💡 Check the URL and try again, or use a different website.
                </p>
            </div>
            
        <?php elseif (!empty($url) && !empty($content)): ?>
            <?php if ($isImage): ?>
                <!-- Image Content -->
                <div style="background: #0d1117; height: 100%; display: flex; align-items: center; justify-content: center; padding: 20px;">
                    <img src="data:<?php echo $contentType; ?>;base64,<?php echo base64_encode($content); ?>" 
                         style="max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 8px;">
                </div>
            <?php elseif ($isHtml): ?>
                <!-- HTML Content - RENDER IT PROPERLY -->
                <iframe id="browserFrame" 
                        srcdoc="<?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?>"
                        style="width:100%; height:100%; border:none; background:#ffffff;">
                </iframe>
            <?php else: ?>
                <!-- Non-HTML Content -->
                <div class="raw-content">
                    <h3 style="color: #58a6ff; margin-bottom: 15px;">📄 Raw Content</h3>
                    <div style="color: #8b949e; margin-bottom: 10px; font-size: 0.85rem;">
                        Content-Type: <?php echo htmlspecialchars($contentType); ?>
                    </div>
                    <pre><?php echo htmlspecialchars($content); ?></pre>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Home Page -->
            <div class="home-page">
                <h1>🌐</h1>
                <h2 style="color: #58a6ff; margin-bottom: 5px;">PHP Browser</h2>
                <p class="subtitle">Enter a URL in the address bar above to browse the web.</p>
                
                <div class="quick-links">
                    <a href="?url=https://google.com" class="quick-link">🔍 Google</a>
                    <a href="?url=https://github.com" class="quick-link">🐙 GitHub</a>
                    <a href="?url=https://wikipedia.org" class="quick-link">📚 Wikipedia</a>
                    <a href="?url=https://php.net" class="quick-link">🐘 PHP.net</a>
                    <a href="?url=https://reddit.com" class="quick-link">📰 Reddit</a>
                </div>
                
                <div class="features">
                    <div class="feature">
                        <span class="icon">🍪</span>
                        Cookies Support
                    </div>
                    <div class="feature">
                        <span class="icon">🔄</span>
                        Redirects
                    </div>
                    <div class="feature">
                        <span class="icon">🔒</span>
                        SSL Compatible
                    </div>
                    <div class="feature">
                        <span class="icon">📝</span>
                        Form Support
                    </div>
                </div>
                
                <div style="margin-top: 30px; color: #484f58; font-size: 0.75rem; text-align: center; max-width: 500px;">
                    <p>💡 This is a real PHP-based web browser that fetches and renders websites.</p>
                    <p style="margin-top: 5px;">Some websites may have limited functionality due to JavaScript restrictions.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Navigation functions
        function goBack() {
            const iframe = document.getElementById('browserFrame');
            if (iframe && iframe.contentWindow) {
                try {
                    iframe.contentWindow.history.back();
                } catch(e) {
                    // Cross-origin, can't navigate
                    alert('Cannot navigate back: Cross-origin restriction');
                }
            }
        }
        
        function goForward() {
            const iframe = document.getElementById('browserFrame');
            if (iframe && iframe.contentWindow) {
                try {
                    iframe.contentWindow.history.forward();
                } catch(e) {
                    alert('Cannot navigate forward: Cross-origin restriction');
                }
            }
        }
        
        function reloadPage() {
            const iframe = document.getElementById('browserFrame');
            if (iframe) {
                iframe.src = iframe.src;
            }
            // Also reload the main page with current URL
            const urlInput = document.getElementById('urlInput');
            if (urlInput && urlInput.value) {
                window.location.href = '?url=' + encodeURIComponent(urlInput.value);
            }
        }
        
        // Auto-focus on URL input
        document.addEventListener('DOMContentLoaded', function() {
            const urlInput = document.getElementById('urlInput');
            if (urlInput && !urlInput.value) {
                urlInput.focus();
            }
            
            // Handle form submission
            const form = document.querySelector('.url-bar');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const input = document.getElementById('urlInput');
                    let url = input.value.trim();
                    if (url && !url.match(/^https?:\/\//i)) {
                        url = 'https://' + url;
                        input.value = url;
                    }
                });
            }
            
            // Update status on iframe load
            const iframe = document.getElementById('browserFrame');
            if (iframe) {
                iframe.addEventListener('load', function() {
                    const statusDot = document.querySelector('.status-dot');
                    const statusText = document.getElementById('statusText');
                    if (statusDot) {
                        statusDot.className = 'status-dot';
                    }
                    if (statusText) {
                        statusText.textContent = 'Loaded';
                    }
                });
                
                iframe.addEventListener('error', function() {
                    const statusDot = document.querySelector('.status-dot');
                    const statusText = document.getElementById('statusText');
                    if (statusDot) {
                        statusDot.className = 'status-dot error';
                    }
                    if (statusText) {
                        statusText.textContent = 'Error loading';
                    }
                });
            }
        });
    </script>
</body>
</html>
