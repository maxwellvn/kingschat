#!/usr/bin/env python3
"""
KingsChat OAuth Flow Test
This script tests the complete OAuth flow and token extraction
"""

import requests
import urllib.parse
import webbrowser
import json
from http.server import HTTPServer, BaseHTTPRequestHandler
import threading
import time
import sys

# Configuration (matching your Flutter app)
CLIENT_ID = "619b30ea-a682-47fb-b90f-5b8e780b89ca"
AUTH_URL = "https://accounts.kingsch.at"
API_BASE_URL = "https://connect.kingsch.at/api"
TOKEN_URL = "https://connect.kingsch.at/oauth2/token"
SCOPES = ["kingschat"]
REDIRECT_URI = "http://localhost:8090/callback"  # Using different port to avoid conflicts

# Global variables to capture the callback
callback_received = False
auth_code = None
access_token = None
refresh_token = None
error_message = None

class CallbackHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        global callback_received, auth_code, access_token, refresh_token, error_message
        
        print(f"\n=== CALLBACK RECEIVED ===")
        print(f"Path: {self.path}")
        print(f"Full URL: http://localhost:8090{self.path}")
        
        # Parse the URL
        parsed_url = urllib.parse.urlparse(self.path)
        query_params = urllib.parse.parse_qs(parsed_url.query)
        fragment = parsed_url.fragment
        
        print(f"Query parameters: {query_params}")
        print(f"Fragment: {fragment}")
        
        # Check for tokens in query parameters
        if 'access_token' in query_params:
            access_token = query_params['access_token'][0]
            refresh_token = query_params.get('refresh_token', [None])[0]
            print(f"✓ Found access token in query: {access_token[:20]}...")
            if refresh_token:
                print(f"✓ Found refresh token in query: {refresh_token[:20]}...")
        
        # Check for authorization code
        elif 'code' in query_params:
            auth_code = query_params['code'][0]
            print(f"✓ Found authorization code: {auth_code[:20]}...")
        
        # Check for errors
        elif 'error' in query_params:
            error_message = query_params['error'][0]
            print(f"✗ OAuth error: {error_message}")
        
        else:
            print("✗ No tokens or code found in callback")
            print("This might be normal if tokens are in the fragment (implicit flow)")
        
        # Send response
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        
        html_response = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <title>OAuth Callback</title>
            <script>
                // Extract tokens from URL fragment (for implicit flow)
                function extractTokensFromFragment() {{
                    const hash = window.location.hash.substring(1);
                    const params = new URLSearchParams(hash);
                    
                    console.log('URL Hash:', hash);
                    console.log('Fragment params:', Object.fromEntries(params));
                    
                    const accessToken = params.get('access_token');
                    const refreshToken = params.get('refresh_token');
                    
                    if (accessToken) {{
                        console.log('Access token found in fragment:', accessToken.substring(0, 20) + '...');
                        document.getElementById('status').innerHTML = 
                            '<h2 style="color: green;">✓ Success!</h2>' +
                            '<p>Access token found: ' + accessToken.substring(0, 20) + '...</p>' +
                            (refreshToken ? '<p>Refresh token found: ' + refreshToken.substring(0, 20) + '...</p>' : '');
                        
                        // Send tokens to Python script via a request
                        fetch('/token_callback?access_token=' + encodeURIComponent(accessToken) + 
                              (refreshToken ? '&refresh_token=' + encodeURIComponent(refreshToken) : ''))
                            .then(() => console.log('Tokens sent to Python script'));
                    }} else {{
                        document.getElementById('status').innerHTML = 
                            '<h2 style="color: red;">✗ No Access Token</h2>' +
                            '<p>No access token found in URL fragment or query parameters</p>';
                    }}
                }}
                
                // Run when page loads
                window.onload = extractTokensFromFragment;
            </script>
        </head>
        <body>
            <h1>OAuth Callback</h1>
            <div id="status">Processing...</div>
            <hr>
            <h3>Debug Info:</h3>
            <p><strong>Full URL:</strong> <span id="fullUrl"></span></p>
            <p><strong>Query:</strong> {parsed_url.query}</p>
            <p><strong>Fragment:</strong> {fragment}</p>
            <script>
                document.getElementById('fullUrl').textContent = window.location.href;
            </script>
        </body>
        </html>
        """
        
        self.wfile.write(html_response.encode())
        callback_received = True
    
    def do_GET_token_callback(self):
        """Handle token extraction from JavaScript"""
        global access_token, refresh_token
        
        parsed_url = urllib.parse.urlparse(self.path)
        query_params = urllib.parse.parse_qs(parsed_url.query)
        
        if 'access_token' in query_params:
            access_token = query_params['access_token'][0]
            refresh_token = query_params.get('refresh_token', [None])[0]
            print(f"✓ Tokens received from JavaScript:")
            print(f"  Access token: {access_token[:20]}...")
            if refresh_token:
                print(f"  Refresh token: {refresh_token[:20]}...")
        
        self.send_response(200)
        self.send_header('Content-type', 'text/plain')
        self.end_headers()
        self.wfile.write(b'OK')
    
    def log_message(self, format, *args):
        # Suppress default logging
        pass

def start_callback_server():
    """Start the callback server in a separate thread"""
    server = HTTPServer(('localhost', 8090), CallbackHandler)
    print(f"✓ Callback server started on {REDIRECT_URI}")
    server.handle_request()  # Handle one request and stop
    return server

def test_oauth_flow():
    """Test the complete OAuth flow"""
    print("=== KingsChat OAuth Flow Test ===\n")
    
    # Start callback server
    print("1. Starting callback server...")
    server_thread = threading.Thread(target=start_callback_server)
    server_thread.daemon = True
    server_thread.start()
    time.sleep(1)  # Give server time to start
    
    # Build OAuth URL
    print("2. Building OAuth URL...")
    params = {
        'client_id': CLIENT_ID,
        'scopes': json.dumps(SCOPES),
        'redirect_uri': REDIRECT_URI,
        'response_type': 'token',  # Implicit flow
        'post_redirect': 'true'
    }
    
    query_string = urllib.parse.urlencode(params)
    oauth_url = f"{AUTH_URL}/?{query_string}"
    
    print(f"OAuth URL: {oauth_url}")
    print(f"Redirect URI: {REDIRECT_URI}")
    
    # Open browser
    print("\n3. Opening browser for OAuth...")
    print("Please complete the OAuth flow in your browser.")
    webbrowser.open(oauth_url)
    
    # Wait for callback
    print("\n4. Waiting for OAuth callback...")
    timeout = 60  # 1 minute timeout
    start_time = time.time()
    
    while not callback_received and (time.time() - start_time) < timeout:
        time.sleep(0.5)
    
    if not callback_received:
        print("✗ Timeout waiting for OAuth callback")
        return False
    
    # Give JavaScript time to process tokens
    time.sleep(2)
    
    # Check results
    print("\n=== RESULTS ===")
    if access_token:
        print(f"✓ Access Token: {access_token[:20]}...")
        if refresh_token:
            print(f"✓ Refresh Token: {refresh_token[:20]}...")
        
        # Test the access token
        print("\n5. Testing access token with API...")
        test_access_token(access_token)
        return True
    
    elif auth_code:
        print(f"✓ Authorization Code: {auth_code[:20]}...")
        print("Note: This is authorization code flow, not implicit flow")
        return True
    
    elif error_message:
        print(f"✗ OAuth Error: {error_message}")
        return False
    
    else:
        print("✗ No tokens received")
        return False

def test_access_token(token):
    """Test the access token with the KingsChat API"""
    try:
        print("Testing profile API...")
        response = requests.get(
            f"{API_BASE_URL}/profile",
            headers={
                'Authorization': f'Bearer {token}',
                'Content-Type': 'application/json'
            },
            timeout=10
        )
        
        print(f"Profile API Response: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            print("✓ Profile API Success!")
            print(f"User: {data.get('profile', {}).get('user', {}).get('name', 'Unknown')}")
        else:
            print(f"✗ Profile API Error: {response.text}")
    
    except Exception as e:
        print(f"✗ Error testing access token: {e}")

def test_api_endpoints():
    """Test API endpoints without authentication"""
    print("\n=== Testing API Endpoints ===")
    
    endpoints = [
        ("Profile API", f"{API_BASE_URL}/profile"),
        ("Contacts API", f"{API_BASE_URL}/contacts"),
        ("Auth URL", AUTH_URL),
        ("Token URL", TOKEN_URL)
    ]
    
    for name, url in endpoints:
        try:
            response = requests.get(url, timeout=5)
            print(f"{name}: HTTP {response.status_code}")
        except Exception as e:
            print(f"{name}: Error - {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--test-api":
        test_api_endpoints()
    else:
        # Test complete OAuth flow
        success = test_oauth_flow()
        
        if not success:
            print("\n=== Troubleshooting ===")
            print("1. Check if the OAuth provider allows the redirect URI")
            print("2. Verify the client ID is correct")
            print("3. Make sure you're logged into KingsChat")
            print("4. Check the browser console for JavaScript errors")
            
        print("\nTo test API endpoints only, run: python test_oauth_flow.py --test-api") 