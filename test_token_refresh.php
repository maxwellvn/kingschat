<?php
/**
 * Test script for KingsChat token refresh functionality
 * 
 * This script tests the token refresh functionality by:
 * 1. Checking the current token status
 * 2. Forcing a token refresh
 * 3. Verifying the new token
 */

// Start session
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Include token refresh functionality
require_once 'token_refresh.php';

// Function to display token information
function displayTokenInfo() {
    if (!isset($_SESSION['kc_access_token'])) {
        echo "<p>No token found in session.</p>";
        return;
    }
    
    $token = $_SESSION['kc_access_token'];
    $expiresAt = $_SESSION['kc_token_expires_at'] ?? 0;
    $timeRemaining = $expiresAt - time();
    
    echo "<p><strong>Token (first 20 chars):</strong> " . substr($token, 0, 20) . "...</p>";
    echo "<p><strong>Expires at:</strong> " . date('Y-m-d H:i:s', $expiresAt) . "</p>";
    echo "<p><strong>Time remaining:</strong> " . round($timeRemaining / 60, 1) . " minutes</p>";
    
    // Check if token needs refreshing
    if (needsTokenRefresh()) {
        echo "<p style='color: red;'><strong>Status:</strong> Token needs refreshing</p>";
    } else {
        echo "<p style='color: green;'><strong>Status:</strong> Token is valid</p>";
    }
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'refresh') {
            // Force token refresh
            if (refreshKingsChatToken()) {
                $message = "Token refreshed successfully.";
            } else {
                $error = "Failed to refresh token.";
            }
        } elseif ($_POST['action'] === 'test_api') {
            // Test API call with current token
            try {
                $ch = curl_init('https://connect.kingsch.at/api/contacts');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $_SESSION['kc_access_token'],
                        'Content-Type: application/json'
                    ]
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $message = "API test successful. HTTP code: $httpCode";
                } else {
                    $error = "API test failed. HTTP code: $httpCode, Response: $response";
                }
            } catch (Exception $e) {
                $error = "Exception during API test: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KingsChat Token Refresh Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4A90E2;
            border-bottom: 2px solid #4A90E2;
            padding-bottom: 10px;
        }
        .info-box {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        button {
            background: #4A90E2;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
        }
        button:hover {
            background: #357ABD;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>KingsChat Token Refresh Test</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h2>Current Token Information</h2>
            <?php displayTokenInfo(); ?>
        </div>
        
        <div class="actions">
            <form method="post">
                <input type="hidden" name="action" value="refresh">
                <button type="submit">Force Token Refresh</button>
            </form>
            
            <form method="post">
                <input type="hidden" name="action" value="test_api">
                <button type="submit">Test API Call</button>
            </form>
            
            <a href="dashboard.php"><button type="button">Back to Dashboard</button></a>
        </div>
    </div>
</body>
</html>