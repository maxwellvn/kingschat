<?php
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Define config file path
if (!defined('KC_CONFIG_FILE')) {
    define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
}

// Log all incoming data for debugging
error_log("=== CALLBACK DEBUG START ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'empty'));
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));
error_log("Headers: " . print_r(getallheaders(), true));

$input_data = file_get_contents('php://input');
error_log("Raw input data: " . $input_data);

// Try to get token from various sources
$token = null;
$refreshToken = null;

// Method 1: Check URL fragment (JavaScript will need to handle this)
// This is handled by the JavaScript on this page

// Method 2: Check GET parameters (authorization code flow)
if (!empty($_GET['code'])) {
    error_log("Found authorization code in GET parameters");
    $authCode = $_GET['code'];
    
    // Exchange authorization code for access token
    $tokenData = exchangeCodeForToken($authCode);
    if ($tokenData) {
        $token = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        error_log("Successfully exchanged code for token");
    }
}

// Method 3: Check GET parameters for direct token
if (!$token && !empty($_GET['access_token'])) {
    $token = $_GET['access_token'];
    $refreshToken = $_GET['refresh_token'] ?? null;
    error_log("Found access token in GET parameters");
}

// Method 4: Check POST data
if (!$token && !empty($_POST['accessToken'])) {
    $token = $_POST['accessToken'];
    $refreshToken = $_POST['refreshToken'] ?? null;
    error_log("Found access token in POST data");
}

// Method 5: Check JSON input
if (!$token && !empty($input_data)) {
    $json_data = json_decode($input_data, true);
    if ($json_data && isset($json_data['accessToken'])) {
        $token = $json_data['accessToken'];
        $refreshToken = $json_data['refreshToken'] ?? null;
        error_log("Found access token in JSON data");
    }
}

// Method 6: Check Authorization header
$headers = getallheaders();
if (!$token && isset($headers['Authorization'])) {
    $auth_header = $headers['Authorization'];
    if (strpos($auth_header, 'Bearer ') === 0) {
        $token = substr($auth_header, 7);
        error_log("Found access token in Authorization header");
    }
}

function exchangeCodeForToken($authCode) {
    $clientId = '619b30ea-a682-47fb-b90f-5b8e780b89ca';
    $redirectUri = getCurrentCallbackUrl();
    
    $postData = [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'code' => $authCode,
        'redirect_uri' => $redirectUri
    ];
    
    $ch = curl_init('https://connect.kingsch.at/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Token exchange response code: $httpCode");
    error_log("Token exchange response: $response");
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

function getCurrentCallbackUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    return $protocol . '://' . $host . $script;
}

// If no token found yet, show the JavaScript handler page
if (!$token) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Processing Login...</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 2s linear infinite; margin: 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <h2>Processing your login...</h2>
        <div class="spinner"></div>
        <p id="status">Checking for authentication data...</p>
        
        <script>
        // Handle URL fragment (for implicit flow)
        function handleUrlFragment() {
            const fragment = window.location.hash.substring(1);
            const params = new URLSearchParams(fragment);
            
            const accessToken = params.get('access_token');
            const refreshToken = params.get('refresh_token');
            
            if (accessToken) {
                document.getElementById('status').textContent = 'Found access token, processing...';
                
                // Send token to server
                const formData = new FormData();
                formData.append('accessToken', accessToken);
                if (refreshToken) {
                    formData.append('refreshToken', refreshToken);
                }
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        return response.text();
                    }
                }).then(text => {
                    if (text) {
                        document.body.innerHTML = text;
                    }
                }).catch(error => {
                    console.error('Error:', error);
                    document.getElementById('status').textContent = 'Error processing login. Please try again.';
                });
                
                return true;
            }
            return false;
        }
        
        // Handle URL query parameters (for authorization code flow)
        function handleUrlQuery() {
            const params = new URLSearchParams(window.location.search);
            const code = params.get('code');
            const accessToken = params.get('access_token');
            
            if (code || accessToken) {
                document.getElementById('status').textContent = 'Found authorization data, processing...';
                // Reload the page to let PHP handle it
                window.location.reload();
                return true;
            }
            return false;
        }
        
        // Try both methods
        if (!handleUrlFragment() && !handleUrlQuery()) {
            // No token found, redirect back to login
            setTimeout(() => {
                window.location.href = 'index.php?error=' + encodeURIComponent('No authentication data received');
            }, 3000);
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// If we found a token, process it
if ($token) {
    error_log("Processing token: " . substr($token, 0, 20) . "...");
    
    // Store token in session
    $_SESSION['kc_access_token'] = $token;
    
    // Store refresh token if available
    if ($refreshToken) {
        $_SESSION['kc_refresh_token'] = $refreshToken;
        error_log("Stored refresh token");
    }
    
    // Store tokens in persistent cookies (1 year expiry)
    setcookie('kc_access_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
    if ($refreshToken) {
        setcookie('kc_refresh_token', $refreshToken, time() + (365 * 24 * 60 * 60), '/', '', false, true);
    }
    
    // Fetch user profile from KingsChat API
    $ch = curl_init('https://connect.kingsch.at/api/profile');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Profile API response code: $httpCode");
    
    if ($httpCode === 200) {
        $userData = json_decode($response, true);
        if ($userData) {
            $_SESSION['kc_user'] = $userData;
            $_SESSION['success_message'] = 'Successfully logged in to KingsChat!';

            // Extract user ID and name from the profile data
            $userId = isset($userData['profile']['user']['user_id']) ? $userData['profile']['user']['user_id'] : '';
            $userName = isset($userData['profile']['user']['name']) ? $userData['profile']['user']['name'] : 'User';

            // Log the login event
            if (!empty($userId)) {
                error_log("[" . date('Y-m-d H:i:s') . "] User logged in: $userId ($userName)");
                
                // Update the config file
                if (file_exists(KC_CONFIG_FILE)) {
                    $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
                    if (is_array($config)) {
                        $config['sender_user_id'] = $userId;
                        $config['access_token'] = $token;
                        
                        if ($refreshToken) {
                            $config['refresh_token'] = $refreshToken;
                        }
                        
                        // Calculate expiration time (assume 1 hour if not specified)
                        $config['expires_at'] = time() + 3600;
                        
                        file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
                        error_log("Updated config file with new token and user data");
                    }
                }
            }

            error_log("=== CALLBACK DEBUG END - SUCCESS ===");
            header('Location: dashboard.php?auth=' . uniqid());
            exit;
        }
    } else {
        error_log("Failed to fetch user profile. Response: $response");
    }
}

error_log("=== CALLBACK DEBUG END - FAILED ===");
header('Location: index.php?error=' . urlencode('Authentication failed. Please try again.'));
exit;
?>