<?php
/**
 * KingsChat Token Refresh Script using curl
 * 
 * This script uses the curl command that is known to work
 * to refresh the KingsChat token.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/kingschat_refresh.log');

// Define constants
define('KC_TOKEN_FILE', __DIR__ . '/../logs/kc_token_storage.json');
define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');

// Check if this is a CLI request
if (php_sapi_name() !== 'cli') {
    // If accessed via web, check if user is admin
    session_start();
    require_once __DIR__ . '/../includes/Session.php';
    
    // Only allow admins to run this script via web
    if (!Session::isAdmin()) {
        http_response_code(403);
        echo "Access denied. Only administrators can run this script.";
        exit;
    }
    
    // Set content type for web output
    header('Content-Type: text/plain');
}

// Log the start of the refresh process
$timestamp = date('Y-m-d H:i:s');
$logMessage = "[$timestamp] Starting KingsChat token refresh process using curl";
error_log($logMessage);
if (php_sapi_name() !== 'cli') echo $logMessage . "\n";

// Get the refresh token from the session or config
$refreshToken = '';

// First try to get it from the session
if (isset($_SESSION['kc_refresh_token'])) {
    $refreshToken = $_SESSION['kc_refresh_token'];
    error_log("Using refresh token from session");
} 
// If not in session, try to get it from the config file
else if (file_exists(KC_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
    if (is_array($config) && isset($config['refresh_token'])) {
        $refreshToken = $config['refresh_token'];
        error_log("Using refresh token from config file");
    }
}

// If still no refresh token, use the default one
if (empty($refreshToken)) {
    $refreshToken = '55zki1eYgyAQFdK8guwIRbSyqceGsRoaLZl09apqJno=';
    error_log("Using default refresh token");
}

// URL encode the refresh token
$encodedRefreshToken = urlencode($refreshToken);

// Run the curl command that works
$command = 'curl -s -X POST "https://connect.kingsch.at/oauth2/token" ' .
           '-d "client_id=619b30ea-a682-47fb-b90f-5b8e780b89ca&refresh_token=' . $encodedRefreshToken . '&grant_type=refresh_token" ' .
           '-H "Content-Type: application/x-www-form-urlencoded" ' .
           '-H "Accept: application/json"';

$response = shell_exec($command);

// Check if the response contains an access token
$responseData = json_decode($response, true);

if ($responseData && isset($responseData['access_token'])) {
    // Extract the access token and other data
    $accessToken = $responseData['access_token'];
    $expiresInMillis = $responseData['expires_in_millis'] ?? 3600000;
    $expiresIn = floor($expiresInMillis / 1000);
    $expiresAt = time() + $expiresIn;
    
    // Create the token data
    $tokenData = [
        'access_token' => $accessToken,
        'expires_at' => $expiresAt,
        'updated_at' => time(),
        'refresh_token' => '55zki1eYgyAQFdK8guwIRbSyqceGsRoaLZl09apqJno='
    ];
    
    // Also update the kc_config.json file if it exists
    if (file_exists(KC_CONFIG_FILE)) {
        $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
        if (is_array($config)) {
            // Update the token while preserving the sender_user_id
            $config['access_token'] = $accessToken;
            $config['expires_at'] = $expiresAt;
            file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
            $logMessage = "[$timestamp] Updated kc_config.json with new token while preserving sender_user_id: " . $config['sender_user_id'];
            error_log($logMessage);
            if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        }
    }
    
    // Save the token to the file
    if (file_put_contents(KC_TOKEN_FILE, json_encode($tokenData)) !== false) {
        $logMessage = "[$timestamp] Token refreshed successfully. New token expires in " . round($expiresIn / 60, 1) . " minutes";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(0);
    } else {
        $logMessage = "[$timestamp] Failed to save token to file.";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(1);
    }
} else {
    $logMessage = "[$timestamp] Failed to refresh token: $response";
    error_log($logMessage);
    if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
    exit(1);
}
