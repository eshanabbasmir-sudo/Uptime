#!/bin/bash
# web.sh – 24/7 Browser (single-file, self-hosted)
# Usage: chmod +x web.sh && ./web.sh

set -e

# ----- extract and serve the embedded HTML -----
HTML_CONTENT='<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>24/7 Browser</title>
  <style>
    /* ----- RESET & BASE ----- */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    body {
      background: #0c1018;
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* ----- TOOLBAR (premium glass) ----- */
    .toolbar {
      background: rgba(22, 30, 44, 0.92);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      padding: 14px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      flex-shrink: 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
      z-index: 20;
      flex-wrap: wrap;
    }

    .brand {
      color: #eef4ff;
      font-weight: 650;
      font-size: 1.15rem;
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
      letter-spacing: -0.2px;
    }
    .brand .badge {
      background: #2d7aff;
      color: white;
      font-size: 0.65rem;
      font-weight: 600;
      padding: 2px 12px;
      border-radius: 40px;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      background: linear-gradient(145deg, #3b7fff, #1a5fd9);
    }

    .url-box {
      flex: 1;
      min-width: 240px;
      display: flex;
      align-items: center;
      background: rgba(15, 20, 30, 0.7);
      border-radius: 48px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(4px);
      transition: border 0.2s, box-shadow 0.2s;
      padding: 0 6px 0 20px;
    }
    .url-box:focus-within {
      border-color: #4a82ff;
      box-shadow: 0 0 0 4px rgba(45, 122, 255, 0.15);
      background: rgba(18, 26, 40, 0.8);
    }

    .url-box input {
      background: transparent;
      border: none;
      padding: 12px 8px 12px 0;
      color: #f0f6ff;
      font-size: 0.95rem;
      width: 100%;
      outline: none;
      letter-spacing: 0.2px;
    }
    .url-box input::placeholder {
      color: #7a8aa5;
      font-weight: 350;
      letter-spacing: 0px;
    }

    .url-box button {
      background: linear-gradient(145deg, #2d7aff, #1a5fd9);
      border: none;
      color: white;
      font-weight: 600;
      padding: 8px 22px;
      border-radius: 40px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: 0.2s;
      white-space: nowrap;
      margin: 4px 0;
      box-shadow: 0 2px 8px rgba(45, 122, 255, 0.2);
    }
    .url-box button:hover {
      transform: scale(1.03);
      background: linear-gradient(145deg, #4b8aff, #2a6df0);
      box-shadow: 0 4px 14px rgba(45, 122, 255, 0.35);
    }
    .url-box button:active {
      transform: scale(0.94);
    }

    .status-chip {
      color: #a0b4d0;
      font-size: 0.75rem;
      background: rgba(20, 30, 48, 0.7);
      padding: 5px 16px;
      border-radius: 60px;
      border: 1px solid rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(4px);
      white-space: nowrap;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .status-chip .dot {
      display: inline-block;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #3b8b3b;
      animation: pulse-dot 2s infinite;
    }
    @keyframes pulse-dot {
      0% { opacity: 0.4; transform: scale(1); }
      50% { opacity: 1; transform: scale(1.2); }
      100% { opacity: 0.4; transform: scale(1); }
    }

    /* ----- IFRAME CONTAINER ----- */
    .frame-wrap {
      flex: 1;
      background: #0c1018;
      position: relative;
      overflow: hidden;
    }
    .frame-wrap iframe {
      width: 100%;
      height: 100%;
      border: none;
      background: #ffffff;
      display: block;
    }

    /* loading overlay (subtle) */
    .frame-overlay {
      position: absolute;
      bottom: 28px;
      right: 32px;
      background: rgba(12, 16, 24, 0.6);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      color: #c8d8f0;
      padding: 6px 18px;
      border-radius: 60px;
      font-size: 0.78rem;
      border: 1px solid rgba(255, 255, 255, 0.06);
      pointer-events: none;
      transition: opacity 0.25s;
      opacity: 0.8;
    }

    /* responsive */
    @media (max-width: 700px) {
      .toolbar { padding: 10px 14px; gap: 10px; }
      .brand { font-size: 1rem; }
      .url-box { min-width: 160px; padding: 0 4px 0 14px; }
      .url-box input { font-size: 0.85rem; padding: 10px 4px 10px 0; }
      .url-box button { padding: 6px 16px; font-size: 0.8rem; }
      .status-chip { font-size: 0.65rem; padding: 3px 12px; }
    }
  </style>
</head>
<body>

  <!-- TOOLBAR -->
  <div class="toolbar">
    <div class="brand">
      🧭 24/7 <span class="badge">always on</span>
    </div>

    <div class="url-box">
      <input type="text" id="urlInput" placeholder="Type URL … e.g. github.com" spellcheck="false" autofocus />
      <button id="goBtn">↗ Open</button>
    </div>

    <div class="status-chip">
      <span class="dot"></span>
      <span id="statusText">ready</span>
    </div>
  </div>

  <!-- IFRAME -->
  <div class="frame-wrap">
    <iframe id="browserFrame" src="about:blank" loading="eager" sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals"></iframe>
    <div class="frame-overlay" id="frameBadge">🌐 enter URL</div>
  </div>

  <script>
    (function() {
      const frame = document.getElementById("browserFrame");
      const input = document.getElementById("urlInput");
      const goBtn = document.getElementById("goBtn");
      const statusText = document.getElementById("statusText");
      const badge = document.getElementById("frameBadge");

      // ----- normalize URL (add https if missing) -----
      function normalizeUrl(raw) {
        let trimmed = raw.trim();
        if (!trimmed) return null;
        // if it looks like a domain (no protocol), add https://
        if (!/^https?:\/\//i.test(trimmed)) {
          trimmed = "https://" + trimmed;
        }
        return trimmed;
      }

      // ----- navigate -----
      function navigateTo(rawUrl) {
        const url = normalizeUrl(rawUrl);
        if (!url) {
          badge.textContent = "⚠️ enter a valid URL";
          statusText.textContent = "invalid";
          return;
        }

        // update UI
        badge.textContent = "⏳ loading …";
        statusText.textContent = "loading";
        frame.src = url;
        input.value = url; // show normalized

        // reset badge after a moment (will be updated by load event)
      }

      // ----- event listeners -----
      goBtn.addEventListener("click", function() {
        navigateTo(input.value);
      });

      input.addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
          e.preventDefault();
          navigateTo(input.value);
        }
      });

      // ----- iframe load / error -----
      frame.addEventListener("load", function() {
        try {
          // try to read location (same-origin only)
          const finalUrl = frame.contentWindow?.location?.href || frame.src;
          if (finalUrl && finalUrl !== "about:blank") {
            badge.textContent = "✅ " + finalUrl;
            statusText.textContent = "loaded";
          } else {
            badge.textContent = "✅ loaded";
            statusText.textContent = "loaded";
          }
        } catch (_) {
          // cross-origin: can't access location
          badge.textContent = "✅ site loaded (cross-origin)";
          statusText.textContent = "loaded";
        }
        // keep badge readable
        badge.style.opacity = "1";
      });

      frame.addEventListener("error", function() {
        badge.textContent = "❌ failed to load";
        statusText.textContent = "error";
      });

      // ----- extra: if user clicks on a link inside iframe, we keep toolbar -----
      // (no action needed, iframe handles navigation)

      // ----- init hint -----
      badge.textContent = "🌐 type URL and press ↵";
      statusText.textContent = "ready";

      // (optional) prefill with a demo
      // input.value = "example.com";
    })();
  </script>
</body>
</html>'

# ----- ask for port -----
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐  24/7 Browser  –  single-file"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
read -p "⚡ Enter port (1024-65535, default 8080): " USER_PORT

if [[ -z "$USER_PORT" ]]; then
    PORT=8080
elif [[ "$USER_PORT" =~ ^[0-9]+$ ]] && [ "$USER_PORT" -ge 1024 ] && [ "$USER_PORT" -le 65535 ]; then
    PORT=$USER_PORT
else
    echo "⚠️  Invalid port. Using default 8080."
    PORT=8080
fi

# ----- write index.html from embedded content -----
echo "$HTML_CONTENT" > index.html
echo "✅ index.html created"

# ----- start server -----
echo ""
echo "🚀 Server running at http://localhost:$PORT"
echo "📂 Serving from: $(pwd)"
echo "🔄 Press Ctrl+C to stop"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# pick python
if command -v python3 &> /dev/null; then
    PYTHON_CMD="python3"
elif command -v python &> /dev/null; then
    PYTHON_CMD="python"
else
    echo "❌ Python not found. Please install Python."
    exit 1
fi

$PYTHON_CMD -m http.server $PORT
