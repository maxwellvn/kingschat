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

// Try to get token from various sources
$token = null;

// Check POST data
if (!empty($_POST['accessToken'])) {
    $token = $_POST['accessToken'];
}

// Check GET data
if (!$token && !empty($_GET['accessToken'])) {
    $token = $_GET['accessToken'];
}

// Check JSON input
$input_data = file_get_contents('php://input');
if (!$token && !empty($input_data)) {
    $json_data = json_decode($input_data, true);
    if ($json_data && isset($json_data['accessToken'])) {
        $token = $json_data['accessToken'];
        $_SESSION['kc_data'] = $json_data;
        if (isset($json_data['user'])) {
            $_SESSION['kc_user'] = $json_data['user'];
        }
    }
}

// Check Authorization header
$headers = getallheaders();
if (!$token && isset($headers['Authorization'])) {
    $auth_header = $headers['Authorization'];
    if (strpos($auth_header, 'Bearer ') === 0) {
        $token = substr($auth_header, 7);
    }
}

// If we found a token, store it and redirect to dashboard
if ($token) {
    $_SESSION['kc_access_token'] = $token;

    // Note: Session settings should be set in php.ini or before session_start()
    // We'll use cookies instead for persistent login

    // Store tokens in persistent cookies (1 year expiry)
    setcookie('kc_access_token', $token, time() + (365 * 24 * 60 * 60), '/', '', true, true);
    
    // Check for refresh token in various places
    $refreshToken = null;
    
    // First check in JSON data
    if (isset($json_data['refreshToken'])) {
        $refreshToken = $json_data['refreshToken'];
        error_log("Found refresh token in JSON data");
    }
    // Then check in POST data
    else if (!empty($_POST['refreshToken'])) {
        $refreshToken = $_POST['refreshToken'];
        error_log("Found refresh token in POST data");
    }
    // Then check in GET data
    else if (!empty($_GET['refreshToken'])) {
        $refreshToken = $_GET['refreshToken'];
        error_log("Found refresh token in GET data");
    }
    
    // If we found a refresh token, store it
    if ($refreshToken) {
        $_SESSION['kc_refresh_token'] = $refreshToken;
        setcookie('kc_refresh_token', $refreshToken, time() + (365 * 24 * 60 * 60), '/', '', true, true);
        error_log("Stored refresh token in session and cookie");
    } else {
        error_log("No refresh token found in request");
    }
    
    // Log the token for debugging
    error_log("Received token: " . substr($token, 0, 20) . "...");
    
    // Decode the token to get user information
    $tokenParts = explode('.', $token);
    if (count($tokenParts) === 3) {
        $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $tokenParts[1]))), true);
        if (isset($payload['sub'])) {
            $tokenUserId = $payload['sub'];
            error_log("Token user ID from JWT: " . $tokenUserId);
        }
    }

    // If we haven't stored user data yet, try to get it from other sources
    if (!isset($_SESSION['kc_user'])) {
        if (!empty($_POST['user'])) {
            $_SESSION['kc_user'] = $_POST['user'];
        } elseif (!empty($_GET['user'])) {
            $_SESSION['kc_user'] = $_GET['user'];
        }
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
                
                // Update the sender_user_id and refresh_token in the config file with the logged-in user's details
                if (file_exists(KC_CONFIG_FILE)) {
                    $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
                    if (is_array($config)) {
                        $config['sender_user_id'] = $userId;
                        
                        // Also update the refresh token if available
                        if (isset($_SESSION['kc_refresh_token'])) {
                            $config['refresh_token'] = $_SESSION['kc_refresh_token'];
                            error_log("[" . date('Y-m-d H:i:s') . "] Updated refresh_token in config");
                        }
                        
                        file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
                        error_log("[" . date('Y-m-d H:i:s') . "] Updated sender_user_id in config to: $userId");
                    }
                }
            }

            header('Location: dashboard.php?auth=' . uniqid());
            exit;
        }
    }

    header('Location: index.php?error=' . urlencode('Failed to fetch user profile'));
    exit;
}

// If we get here, no token was found
header('Location: index.php?error=' . urlencode('No authentication data received'));
exit;
?>