<?php
/**
 * KingsChat Token Refresh Functions
 *
 * This file contains functions to check and refresh the KingsChat access token
 * when needed. It's designed to be included in other files that need to use
 * the KingsChat API.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

/**
 * Check if the current token is expired or about to expire
 *
 * @param int $bufferSeconds Number of seconds before actual expiration to consider token as expired
 * @return bool True if token needs refreshing, false otherwise
 */
function needsTokenRefresh($bufferSeconds = 300) {
    // Check if token exists in session
    if (!isset($_SESSION['kc_access_token'])) {
        return true;
    }

    // Check if expiration time is set
    if (!isset($_SESSION['kc_token_expires_at'])) {
        return true;
    }

    // Check if token is about to expire
    $expiresAt = $_SESSION['kc_token_expires_at'];
    $timeRemaining = $expiresAt - time();

    // If less than buffer time remains, refresh the token
    return ($timeRemaining < $bufferSeconds);
}

/**
 * Refresh the KingsChat access token
 *
 * @return bool True if token was refreshed successfully, false otherwise
 */
function refreshKingsChatToken() {
    // Define config file path if not already defined
    if (!defined('KC_CONFIG_FILE')) {
        define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
    }

    // Log the refresh attempt
    error_log("Attempting to refresh KingsChat token");

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
    
    // Run the curl command to refresh the token
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

        // Update the session with the new token
        $_SESSION['kc_access_token'] = $accessToken;
        $_SESSION['kc_token_expires_at'] = $expiresAt;
        $_SESSION['kc_token_updated_at'] = time();

        // Update the config file
        if (file_exists(KC_CONFIG_FILE)) {
            $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
            if (is_array($config)) {
                $config['access_token'] = $accessToken;
                $config['expires_at'] = $expiresAt;
                $result = file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
                if ($result !== false) {
                    error_log("Updated token in config file successfully");
                } else {
                    error_log("Failed to write to config file: " . KC_CONFIG_FILE);
                }
            } else {
                error_log("Invalid config format in file: " . KC_CONFIG_FILE);
            }
        } else {
            error_log("Config file does not exist: " . KC_CONFIG_FILE);
            
            // Get the user ID from the session if available
            $userId = '';
            if (isset($_SESSION['kc_user']) && isset($_SESSION['kc_user']['profile']['user']['user_id'])) {
                $userId = $_SESSION['kc_user']['profile']['user']['user_id'];
            }
            
            // If no user ID in session, use the default
            if (empty($userId)) {
                $userId = '67c6d4860b20977035865f98';
            }
            
            // Create the file with default values plus the new token
            $config = [
                'access_token' => $accessToken,
                'refresh_token' => '55zki1eYgyAQFdK8guwIRbSyqceGsRoaLZl09apqJno=',
                'sender_user_id' => $userId,
                'expires_at' => $expiresAt
            ];
            $result = file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
            if ($result !== false) {
                error_log("Created new config file with token and user ID: $userId");
            } else {
                error_log("Failed to create config file: " . KC_CONFIG_FILE);
            }
        }

        // Log the successful refresh
        error_log("KingsChat token refreshed successfully. Expires in " . round($expiresIn / 60, 1) . " minutes");

        return true;
    } else {
        // Log the failure
        error_log("Failed to refresh KingsChat token: " . $response);

        return false;
    }
}

/**
 * Check and refresh the token if needed
 *
 * @param int $bufferSeconds Number of seconds before actual expiration to consider token as expired
 * @return bool True if token is valid (either refreshed or still valid), false if failed to refresh
 */
function ensureValidToken($bufferSeconds = 300) {
    if (needsTokenRefresh($bufferSeconds)) {
        return refreshKingsChatToken();
    }

    return true; // Token is still valid
}