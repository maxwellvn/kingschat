<?php
/**
 * KingsChat Token Refresh Script
 *
 * This script is designed to be run periodically (via cron job or similar)
 * to refresh the KingsChat access token before it expires.
 *
 * Recommended cron schedule: Run every 30 minutes
 * (This will run every 30 minutes)
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/kingschat_refresh.log');

// Include the KingsChat configuration
require_once __DIR__ . '/config.php';

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
$logMessage = "[$timestamp] Starting KingsChat token refresh process";
error_log($logMessage);
if (php_sapi_name() !== 'cli') echo $logMessage . "\n";

// Check if token file exists
if (!file_exists(KC_TOKEN_FILE)) {
    $logMessage = "[$timestamp] Token file does not exist. Cannot refresh.";
    error_log($logMessage);
    if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
    exit(1);
}

// Read current token data
try {
    $tokenData = json_decode(file_get_contents(KC_TOKEN_FILE), true);
    if (!$tokenData || !isset($tokenData['access_token'])) {
        $logMessage = "[$timestamp] Invalid token data in file. Cannot refresh.";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(1);
    }

    // Check if refresh token exists
    if (!isset($tokenData['refresh_token'])) {
        $logMessage = "[$timestamp] No refresh token available. Cannot refresh.";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(1);
    }

    // Check if token is about to expire
    $expiresAt = $tokenData['expires_at'] ?? 0;
    $timeRemaining = $expiresAt - time();
    $refreshThreshold = KC_TOKEN_REFRESH_INTERVAL; // Refresh when less than this many seconds remain

    $logMessage = "[$timestamp] Current token expires in " . round($timeRemaining / 60, 1) . " minutes";
    error_log($logMessage);
    if (php_sapi_name() !== 'cli') echo $logMessage . "\n";

    // Only refresh if token is about to expire
    if ($timeRemaining > $refreshThreshold) {
        $logMessage = "[$timestamp] Token is still valid. No need to refresh yet.";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(0);
    }

    // Force refresh the token
    $newToken = getKingsChatToken(true);

    if ($newToken) {
        // Read the updated token data to get the new expiration time
        $updatedTokenData = json_decode(file_get_contents(KC_TOKEN_FILE), true);
        $newExpiresAt = $updatedTokenData['expires_at'] ?? 0;
        $newTimeRemaining = $newExpiresAt - time();

        $logMessage = "[$timestamp] Token refreshed successfully. New token expires in " . round($newTimeRemaining / 60, 1) . " minutes";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(0);
    } else {
        $logMessage = "[$timestamp] Failed to refresh token.";
        error_log($logMessage);
        if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
        exit(1);
    }
} catch (Exception $e) {
    $logMessage = "[$timestamp] Error refreshing token: " . $e->getMessage();
    error_log($logMessage);
    if (php_sapi_name() !== 'cli') echo $logMessage . "\n";
    exit(1);
}
