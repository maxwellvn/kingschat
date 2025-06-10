<?php
/**
 * Comprehensive OAuth Handler for KingsChat
 * This handles the permissions loop issue by implementing multiple fallback strategies
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (!defined('KC_CONFIG_FILE')) {
    define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
}

/**
 * Strategy 1: Check if we already have a valid token from previous successful login
 */
function checkExistingToken() {
    if (file_exists(KC_CONFIG_FILE)) {
        $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
        if (is_array($config) && isset($config['access_token'])) {
            $expiresAt = $config['expires_at'] ?? 0;
            if ($expiresAt > time()) {
                return $config['access_token'];
            }
        }
    }
    return null;
}

/**
 * Strategy 2: Try to refresh the token using the refresh token
 */
function refreshToken() {
    if (file_exists(KC_CONFIG_FILE)) {
        $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
        if (is_array($config) && isset($config['refresh_token'])) {
            $refreshToken = $config['refresh_token'];
            
            $command = 'curl -s -X POST "https://connect.kingsch.at/oauth2/token" ' .
                       '-d "client_id=619b30ea-a682-47fb-b90f-5b8e780b89ca&refresh_token=' . urlencode($refreshToken) . '&grant_type=refresh_token" ' .
                       '-H "Content-Type: application/x-www-form-urlencoded" ' .
                       '-H "Accept: application/json"';

            $response = shell_exec($command);
            $responseData = json_decode($response, true);

            if ($responseData && isset($responseData['access_token'])) {
                $accessToken = $responseData['access_token'];
                $expiresInMillis = $responseData['expires_in_millis'] ?? 3600000;
                $expiresIn = floor($expiresInMillis / 1000);
                $expiresAt = time() + $expiresIn;

                // Update config
                $config['access_token'] = $accessToken;
                $config['expires_at'] = $expiresAt;
                file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
                
                error_log("Token refreshed successfully");
                return $accessToken;
            }
        }
    }
    return null;
}

/**
 * Strategy 3: Validate token and setup session
 */
function setupSessionWithToken($token) {
    $ch = curl_init('https://connect.kingsch.at/api/profile');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
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
            $_SESSION['kc_access_token'] = $token;
            $_SESSION['kc_user'] = $userData;
            $_SESSION['success_message'] = 'Successfully logged in to KingsChat!';
            
            // Set cookies for persistence
            setcookie('kc_access_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
            
            error_log("Session setup successful for user: " . $userData['profile']['user']['name']);
            return true;
        }
    }
    return false;
}

// Main logic
$action = $_GET['action'] ?? 'check';

switch ($action) {
    case 'check':
        // Check existing token
        $token = checkExistingToken();
        if ($token && setupSessionWithToken($token)) {
            header('Location: dashboard.php?restored=1');
            exit;
        }
        
        // Try refresh token
        $token = refreshToken();
        if ($token && setupSessionWithToken($token)) {
            header('Location: dashboard.php?refreshed=1');
            exit;
        }
        
        // No valid token, need fresh login
        header('Location: index.php?need_login=1');
        exit;
        break;
        
    case 'force_refresh':
        $token = refreshToken();
        if ($token && setupSessionWithToken($token)) {
            echo json_encode(['success' => true, 'message' => 'Token refreshed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to refresh token']);
        }
        exit;
        break;
        
    case 'status':
        $status = [
            'logged_in' => isset($_SESSION['kc_access_token']),
            'user' => $_SESSION['kc_user']['profile']['user']['name'] ?? null,
            'config_exists' => file_exists(KC_CONFIG_FILE)
        ];
        
        if (file_exists(KC_CONFIG_FILE)) {
            $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
            $status['has_access_token'] = isset($config['access_token']);
            $status['has_refresh_token'] = isset($config['refresh_token']);
            $status['token_expires'] = $config['expires_at'] ?? 0;
            $status['token_valid'] = ($config['expires_at'] ?? 0) > time();
        }
        
        header('Content-Type: application/json');
        echo json_encode($status);
        exit;
        break;
}
?>