<?php
// Start session
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Include token refresh functionality
require_once 'token_refresh.php';

// Define constants for config file if not already defined
if (!defined('KC_CONFIG_FILE')) {
    define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
}

// Function to load configuration
function loadConfig() {
    if (file_exists(KC_CONFIG_FILE)) {
        $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
        if (is_array($config)) {
            return $config;
        }
    }
    
    // Default config
    return [
        'access_token' => '',
        'refresh_token' => '55zki1eYgyAQFdK8guwIRbSyqceGsRoaLZl09apqJno=',
        'sender_user_id' => '67c6d4860b20977035865f98',
        'expires_at' => 0
    ];
}

// Function to save configuration
function saveConfig($config) {
    file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

// Load current configuration
$config = loadConfig();

// Set content type to JSON
header('Content-Type: application/json');

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refresh_token') {
    // Refresh token
    if (refreshKingsChatToken()) {
        // Update config with new token while preserving the sender_user_id
        $config['access_token'] = $_SESSION['kc_access_token'];
        $config['expires_at'] = $_SESSION['kc_token_expires_at'];
        
        // Get the user ID from the session if available
        if (isset($_SESSION['kc_user']) && isset($_SESSION['kc_user']['profile']['user']['user_id'])) {
            $config['sender_user_id'] = $_SESSION['kc_user']['profile']['user']['user_id'];
        }
        
        saveConfig($config);
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'expires_at' => date('Y-m-d H:i:s', $config['expires_at']),
            'time_remaining' => $config['expires_at'] - time()
        ]);
    } else {
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'Failed to refresh token'
        ]);
    }
} else {
    // Return error for invalid request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}