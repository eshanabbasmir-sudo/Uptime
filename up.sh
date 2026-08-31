#!/bin/bash

read -p "Enter port to host the uptime server: " PORT

python3 -c "
import http.server, socketserver, subprocess

PORT = $PORT
visitor_count = 0

class UptimeHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        global visitor_count
        visitor_count += 1
        
        # Get machine uptime
        uptime_output = subprocess.check_output(['uptime', '-p']).decode('utf-8').strip()
        
        # Build response body
        response_text = f'System Uptime: {uptime_output}\nTotal Requests / Connections: {visitor_count}\n'
        
        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.send_header('Content-Length', str(len(response_text)))
        self.end_headers()
        self.wfile.write(response_text.encode('utf-8'))

    def log_message(self, format, *args):
        return  # Suppress default HTTP logging in terminal

with socketserver.TCPServer(('0.0.0.0', PORT), UptimeHandler) as httpd:
    print(f'Starting server on port {PORT}...')
    print(f'Access it at http://<your-vps-ip>:{PORT}')
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print('\nServer stopped.')
"
