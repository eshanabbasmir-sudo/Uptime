<?php
// index.php - Improved cURL PHP Web Proxy

session_start();

define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

$url = $_GET['url'] ?? '';
$content = '';
$error = '';
$statusCode = 0;

if (!empty($url)) {
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "Invalid URL format.";
    }
}

if (!empty($url) && empty($error)) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '', // Handles gzip/deflate automatically
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Upgrade-Insecure-Requests: 1'
        ]
    ]);
    
    $content = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = "cURL Error: " . curl_error($ch);
    }
    curl_close($ch);
    
    // Fix Relative Assets & Links by injecting <base> tag
    if (!empty($content) && !empty($url)) {
        $parsedUrl = parse_url($url);
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        $baseTag = '<base href="' . htmlspecialchars($url) . '" target="_self">';
        
        if (strpos($content, '<head>') !== false) {
            $content = str_replace('<head>', '<head>' . $baseTag, $content);
        } else {
            $content = $baseTag . $content;
        }
    }
}

$isError = !empty($error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Web Proxy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #1a1a2e; height: 100vh; display: flex; flex-direction: column; }
        .toolbar { background: #16213e; padding: 10px 16px; display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #533483; }
        .brand { color: #e94560; font-weight: bold; }
        .url-form { flex: 1; display: flex; }
        .url-form input { flex: 1; padding: 8px 12px; background: #0f3460; border: 1px solid #533483; color: #fff; border-radius: 4px 0 0 4px; outline: none; }
        .url-form button { background: #e94560; border: none; color: #fff; padding: 8px 16px; cursor: pointer; border-radius: 0 4px 4px 0; }
        .content { flex: 1; background: #fff; position: relative; }
        iframe { width: 100%; height: 100%; border: none; }
        .error-box { padding: 20px; color: #d32f2f; background: #ffebee; margin: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="brand">🌐 PHP Browser</div>
        <form method="GET" class="url-form">
            <input type="text" name="url" placeholder="Enter URL (e.g., wikipedia.org)" value="<?php echo htmlspecialchars($url); ?>">
            <button type="submit">Go</button>
        </form>
    </div>
    
    <div class="content">
        <?php if ($isError): ?>
            <div class="error-box">
                <h3>Error Fetching Site</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php elseif (!empty($content)): ?>
            <iframe id="browserFrame" 
                    srcdoc="<?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?>"
                    sandbox="allow-same-origin allow-scripts allow-forms">
            </iframe>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; color: #aaa;">
                <h2>Enter a web address above to start browsing.</h2>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
