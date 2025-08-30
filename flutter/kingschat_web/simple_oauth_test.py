#!/usr/bin/env python3
"""
Simple KingsChat OAuth Test (No external dependencies)
This script helps debug the OAuth flow and token extraction
"""

import urllib.parse
import webbrowser
import json
from http.server import HTTPServer, BaseHTTPRequestHandler
import threading
import time

# Configuration (matching your Flutter app)
CLIENT_ID = "619b30ea-a682-47fb-b90f-5b8e780b89ca"
AUTH_URL = "https://accounts.kingsch.at"
SCOPES = ["kingschat"]
REDIRECT_URI = "http://localhost:8090/callback"

# Global variables
callback_received = False
callback_data = {}

class CallbackHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        global callback_received, callback_data
        
        print(f"\n=== OAUTH CALLBACK RECEIVED ===")
        print(f"Full path: {self.path}")
        print(f"Complete URL: http://localhost:8090{self.path}")
        
        # Parse the URL
        parsed_url = urllib.parse.urlparse(self.path)
        query_params = urllib.parse.parse_qs(parsed_url.query)
        
        print(f"Query string: {parsed_url.query}")
        print(f"Query parameters: {query_params}")
        print(f"Fragment: {parsed_url.fragment}")
        
        # Store callback data
        callback_data = {
            'path': self.path,
            'query': parsed_url.query,
            'query_params': query_params,
            'fragment': parsed_url.fragment
        }
        
        # Create response HTML with JavaScript to extract fragment
        html_response = """
        <!DOCTYPE html>
        <html>
        <head>
            <title>OAuth Callback Debug</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .success { color: green; }
                .error { color: red; }
                .info { color: blue; }
                .code { background: #f0f0f0; padding: 10px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <h1>OAuth Callback Debug</h1>
            
            <div id="status">Processing...</div>
            
            <h2>URL Analysis</h2>
            <div class="code">
                <strong>Complete URL:</strong><br>
                <span id="fullUrl"></span>
            </div>
            
            <div class="code">
                <strong>URL Hash (Fragment):</strong><br>
                <span id="urlHash"></span>
            </div>
            
            <div class="code">
                <strong>Query String:</strong><br>
                <span id="queryString"></span>
            </div>
            
            <h2>Token Extraction</h2>
            <div id="tokenInfo"></div>
            
            <script>
                console.log('=== JavaScript Debug ===');
                
                // Get URL components
                const fullUrl = window.location.href;
                const hash = window.location.hash;
                const search = window.location.search;
                
                console.log('Full URL:', fullUrl);
                console.log('Hash:', hash);
                console.log('Search:', search);
                
                // Display URL info
                document.getElementById('fullUrl').textContent = fullUrl;
                document.getElementById('urlHash').textContent = hash || '(empty)';
                document.getElementById('queryString').textContent = search || '(empty)';
                
                // Try to extract tokens from fragment
                let accessToken = null;
                let refreshToken = null;
                let tokenSource = 'none';
                
                // Check fragment (hash) first - common for implicit flow
                if (hash && hash.length > 1) {
                    const hashParams = new URLSearchParams(hash.substring(1));
                    accessToken = hashParams.get('access_token');
                    refreshToken = hashParams.get('refresh_token');
                    if (accessToken) tokenSource = 'fragment';
                    
                    console.log('Fragment params:', Object.fromEntries(hashParams));
                }
                
                // Check query parameters if not found in fragment
                if (!accessToken && search) {
                    const searchParams = new URLSearchParams(search);
                    accessToken = searchParams.get('access_token');
                    refreshToken = searchParams.get('refresh_token');
                    if (accessToken) tokenSource = 'query';
                    
                    console.log('Query params:', Object.fromEntries(searchParams));
                }
                
                // Display results
                let tokenHtml = '';
                if (accessToken) {
                    tokenHtml = `
                        <div class="success">
                            <h3>✓ Tokens Found!</h3>
                            <p><strong>Source:</strong> ${tokenSource}</p>
                            <p><strong>Access Token:</strong> ${accessToken.substring(0, 30)}...</p>
                            ${refreshToken ? `<p><strong>Refresh Token:</strong> ${refreshToken.substring(0, 30)}...</p>` : ''}
                        </div>
                    `;
                    document.getElementById('status').innerHTML = '<span class="success">✓ OAuth Success!</span>';
                } else {
                    tokenHtml = `
                        <div class="error">
                            <h3>✗ No Tokens Found</h3>
                            <p>No access_token found in URL fragment or query parameters</p>
                        </div>
                    `;
                    document.getElementById('status').innerHTML = '<span class="error">✗ No Tokens Found</span>';
                }
                
                document.getElementById('tokenInfo').innerHTML = tokenHtml;
                
                // Send detailed info to Python (for debugging)
                const debugInfo = {
                    fullUrl: fullUrl,
                    hash: hash,
                    search: search,
                    accessToken: accessToken ? 'found' : 'not_found',
                    tokenSource: tokenSource
                };
                
                console.log('Debug info:', debugInfo);
                
                // Try to send to Python server (ignore errors)
                try {
                    fetch('/debug', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(debugInfo)
                    }).catch(() => {}); // Ignore fetch errors
                } catch (e) {
                    console.log('Could not send debug info:', e);
                }
            </script>
        </body>
        </html>
        """
        
        # Send response
        self.send_response(200)
        self.send_header('Content-Type', 'text/html')
        self.end_headers()
        self.wfile.write(html_response.encode())
        
        callback_received = True
    
    def do_POST(self):
        if self.path == '/debug':
            # Read debug info from JavaScript
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            try:
                debug_info = json.loads(post_data.decode())
                print(f"\n=== DEBUG INFO FROM JAVASCRIPT ===")
                for key, value in debug_info.items():
                    print(f"{key}: {value}")
            except:
                pass
            
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'OK')
    
    def log_message(self, format, *args):
        pass  # Suppress server logs

def build_oauth_url():
    """Build the OAuth URL matching your Flutter app"""
    params = {
        'client_id': CLIENT_ID,
        'scopes': json.dumps(SCOPES),
        'redirect_uri': REDIRECT_URI,
        'response_type': 'token',  # Implicit flow
        'post_redirect': 'true'
    }
    
    query_string = urllib.parse.urlencode(params)
    return f"{AUTH_URL}/?{query_string}"

def main():
    print("=== Simple OAuth Debug Test ===\n")
    
    # Build OAuth URL
    oauth_url = build_oauth_url()
    print(f"OAuth URL:")
    print(oauth_url)
    print(f"\nRedirect URI: {REDIRECT_URI}")
    
    # Start server
    print(f"\nStarting callback server on {REDIRECT_URI}...")
    server = HTTPServer(('localhost', 8090), CallbackHandler)
    
    # Open browser
    print("Opening browser for OAuth...")
    print("Complete the login process in your browser.")
    print("The callback page will show detailed debug information.")
    webbrowser.open(oauth_url)
    
    # Handle callback
    print("\nWaiting for OAuth callback...")
    server.handle_request()  # Handle the callback
    
    # Show results
    if callback_received:
        print(f"\n=== CALLBACK ANALYSIS ===")
        print(f"Path received: {callback_data.get('path', 'N/A')}")
        print(f"Query string: {callback_data.get('query', 'N/A')}")
        print(f"Query params: {callback_data.get('query_params', {})}")
        print(f"Fragment: {callback_data.get('fragment', 'N/A')}")
        
        # Check for tokens in query params
        query_params = callback_data.get('query_params', {})
        if 'access_token' in query_params:
            token = query_params['access_token'][0]
            print(f"\n✓ Access token found in query: {token[:30]}...")
        else:
            print(f"\n! No access token in query parameters")
            print("Check the browser page for fragment analysis")
    else:
        print("No callback received")
    
    print(f"\nDone! Check the browser page for complete token analysis.")

if __name__ == "__main__":
    main() 