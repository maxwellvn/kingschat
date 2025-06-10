<?php
session_start();

// Set error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Define config file path
if (!defined('KC_CONFIG_FILE')) {
    define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
}

error_log("Logout initiated");

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear any cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}

// Clear the config file token (but keep other settings)
if (file_exists(KC_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
    if (is_array($config)) {
        // Remove tokens but keep other config
        unset($config['access_token']);
        unset($config['expires_at']);
        // Keep refresh_token and sender_user_id for future use
        
        file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
        error_log("Cleared access token from config file");
    }
}

error_log("Logout completed, redirecting to login");

// Redirect to login page
header('Location: index.php?logged_out=1');
exit;
?>