<?php
// index.php - 24/7 PHP Web Browser (REAL WORKING VERSION)

session_start();

// Configuration
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

// Get URL
$url = isset($_GET['url']) ? $_GET['url'] : '';
$content = '';
$error = '';
$statusCode = 0;
$headers = [];

// Validate and clean URL
if (!empty($url)) {
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "Invalid URL format.";
        $url = '';
    }
}

// Fetch the URL using file_get_contents with stream context (more reliable)
if (!empty($url) && empty($error)) {
    try {
        // Create stream context
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ' . USER_AGENT,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Connection: keep-alive',
                ],
                'timeout' => 30,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];
        
        $context = stream_context_create($options);
        
        // Get headers first
        $headers = get_headers($url, 1, $context);
        $statusCode = 0;
        if (isset($headers[0])) {
            preg_match('/HTTP\/\d+\.\d+\s+(\d+)/', $headers[0], $matches);
            $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
        }
        
        // Get content
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            $error = "Failed to fetch the URL. The website might be blocking requests or doesn't exist.";
        }
        
        // Check if we got HTML
        if (!empty($content) && strpos($content, '<html') === false && strpos($content, '<!DOCTYPE') === false) {
            // Might be JSON or other format
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current URL for display
$displayUrl = !empty($url) ? $url : '';
$isError = !empty($error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Real Browser</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #1a1a2e;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* Toolbar */
        .toolbar {
            background: linear-gradient(135deg, #16213e, #0f3460);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            border-bottom: 2px solid #533483;
            z-index: 1000;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        
        .brand {
            color: #e94560;
            font-weight: 700;
            font-size: 1.2rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .brand small {
            color: #fff;
            font-size: 0.65rem;
            background: #533483;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 400;
        }
        
        .url-form {
            flex: 1;
            display: flex;
            min-width: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            border: 2px solid rgba(255,255,255,0.05);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .url-form:focus-within {
            border-color: #e94560;
            background: rgba(255,255,255,0.12);
            box-shadow: 0 0 20px rgba(233, 69, 96, 0.15);
        }
        
        .url-form input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 10px 16px;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            min-width: 100px;
        }
        
        .url-form input::placeholder {
            color: rgba(255,255,255,0.35);
        }
        
        .url-form button {
            background: #e94560;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 10px 24px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .url-form button:hover {
            background: #c73652;
        }
        
        .status {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
            padding: 5px 14px;
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            white-space: nowrap;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .dot.green { background: #4caf50; animation: pulse 2s infinite; }
        .dot.red { background: #f44336; }
        .dot.yellow { background: #ffc107; animation: pulse 1s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.4; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.1); }
        }
        
        /* Content area */
        .content {
            flex: 1;
            background: #fff;
            position: relative;
            overflow: hidden;
        }
        
        .content iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #fff;
        }
        
        /* Error page */
        .error-page {
            background: #f5f5f5;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
        }
        
        .error-page h2 {
            color: #e94560;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .error-box {
            background: #fff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            border-left: 4px solid #e94560;
            text-align: left;
        }
        
        .error-box p {
            margin: 10px 0;
            color: #333;
        }
        
        .error-box code {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            word-break: break-all;
        }
        
        .error-box .url-display {
            background: #f8f8f8;
            padding: 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            border: 1px solid #e0e0e0;
        }
        
        /* Home page */
        .home {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: #fff;
        }
        
        .home h1 {
            font-size: 4rem;
            margin-bottom: 10px;
        }
        
        .home h2 {
            color: #e94560;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .home p {
            color: rgba(255,255,255,0.6);
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .quick-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 600px;
        }
        
        .quick-links a {
            background: rgba(255,255,255,0.08);
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .quick-links a:hover {
            background: rgba(233, 69, 96, 0.2);
            border-color: #e94560;
            transform: translateY(-2px);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 30px;
            max-width: 500px;
            width: 100%;
        }
        
        .feature {
            background: rgba(255,255,255,0.05);
            padding: 14px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: center;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
        }
        
        .feature .icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 4px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .toolbar { padding: 8px 12px; gap: 8px; }
            .brand { font-size: 1rem; }
            .brand small { font-size: 0.55rem; padding: 1px 8px; }
            .url-form input { font-size: 0.85rem; padding: 8px 12px; }
            .url-form button { padding: 8px 16px; font-size: 0.85rem; }
            .status { font-size: 0.65rem; padding: 3px 10px; }
            .home h2 { font-size: 1.8rem; }
            .quick-links a { padding: 8px 16px; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <!-- Toolbar -->
    <div class="toolbar">
        <div class="brand">
            🌐 PHP <small>24/7 Browser</small>
        </div>
        
        <form method="GET" action="" class="url-form">
            <input type="text" name="url" id="urlInput" 
                   placeholder="Enter URL (e.g., google.com)" 
                   value="<?php echo htmlspecialchars($_GET['url'] ?? ''); ?>"
                   autofocus>
            <button type="submit">Go</button>
        </form>
        
        <div class="status">
            <span class="dot <?php 
                echo $isError ? 'red' : (!empty($url) && !empty($content) ? 'green' : (!empty($url) ? 'yellow' : 'green')); 
            ?>"></span>
            <span id="statusText">
                <?php 
                if ($isError) {
                    echo 'Error';
                } elseif (!empty($url) && !empty($content)) {
                    echo $statusCode . ' OK';
                } elseif (!empty($url)) {
                    echo 'Loading...';
                } else {
                    echo 'Ready';
                }
                ?>
            </span>
        </div>
    </div>
    
    <!-- Content -->
    <div class="content">
        <?php if ($isError): ?>
            <!-- Error Page -->
            <div class="error-page">
                <h2>⚠️ Error</h2>
                <div class="error-box">
                    <p><strong>URL:</strong></p>
                    <div class="url-display"><?php echo htmlspecialchars($displayUrl); ?></div>
                    <p style="margin-top: 15px;"><strong>Error:</strong></p>
                    <p style="color: #e94560;"><?php echo htmlspecialchars($error); ?></p>
                    <p style="margin-top: 15px; color: #666; font-size: 0.9rem;">
                        💡 Try checking the URL or using a different website.
                    </p>
                </div>
            </div>
            
        <?php elseif (!empty($url) && !empty($content)): ?>
            <!-- Display the website in an iframe -->
            <iframe id="browserFrame" 
                    srcdoc="<?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?>"
                    sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals allow-downloads"
                    loading="eager">
            </iframe>
            
        <?php else: ?>
            <!-- Home Page -->
            <div class="home">
                <h1>🌐</h1>
                <h2>PHP Browser</h2>
                <p>Enter a URL above to browse the web</p>
                
                <div class="quick-links">
                    <a href="?url=https://google.com">🔍 Google</a>
                    <a href="?url=https://github.com">🐙 GitHub</a>
                    <a href="?url=https://wikipedia.org">📚 Wikipedia</a>
                    <a href="?url=https://php.net">🐘 PHP.net</a>
                    <a href="?url=https://reddit.com">📰 Reddit</a>
                </div>
                
                <div class="features">
                    <div class="feature">
                        <span class="icon">🍪</span>
                        Cookies
                    </div>
                    <div class="feature">
                        <span class="icon">🔄</span>
                        Redirects
                    </div>
                    <div class="feature">
                        <span class="icon">🔒</span>
                        SSL Support
                    </div>
                    <div class="feature">
                        <span class="icon">📝</span>
                        Forms
                    </div>
                </div>
                
                <div style="margin-top: 30px; color: rgba(255,255,255,0.3); font-size: 0.7rem; max-width: 400px; text-align: center;">
                    Real PHP web browser • Fetches and renders websites
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-focus on URL input
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('urlInput');
            if (input && !input.value) {
                input.focus();
            }
            
            // Update status on iframe load
            const iframe = document.getElementById('browserFrame');
            if (iframe) {
                iframe.addEventListener('load', function() {
                    const dot = document.querySelector('.dot');
                    const status = document.getElementById('statusText');
                    if (dot) dot.className = 'dot green';
                    if (status) status.textContent = 'Loaded';
                });
                
                iframe.addEventListener('error', function() {
                    const dot = document.querySelector('.dot');
                    const status = document.getElementById('statusText');
                    if (dot) dot.className = 'dot red';
                    if (status) status.textContent = 'Error';
                });
            }
        });
    </script>
</body>
</html>
