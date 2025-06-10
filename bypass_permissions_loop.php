<?php
/**
 * Bypass Permissions Loop Solution
 * This script helps users bypass the KingsChat permissions loop issue
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

if (!defined('KC_CONFIG_FILE')) {
    define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'use_existing_token') {
        // Try to use existing token from config
        if (file_exists(KC_CONFIG_FILE)) {
            $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
            if (is_array($config) && isset($config['access_token'])) {
                $token = $config['access_token'];
                
                // Verify token
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
                        $_SESSION['success_message'] = 'Successfully bypassed permissions loop!';
                        
                        if (isset($config['refresh_token'])) {
                            $_SESSION['kc_refresh_token'] = $config['refresh_token'];
                        }
                        
                        setcookie('kc_access_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                        
                        $message = 'Success! Redirecting to dashboard...';
                        $success = true;
                        
                        header('refresh:2;url=dashboard.php');
                    }
                } else {
                    $message = 'Token is invalid or expired. Please try refreshing the token.';
                }
            } else {
                $message = 'No access token found in config file.';
            }
        } else {
            $message = 'Config file not found.';
        }
    }
    
    if ($action === 'refresh_token') {
        // Try to refresh token
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
                    
                    // Setup session
                    $_SESSION['kc_access_token'] = $accessToken;
                    
                    // Get user profile
                    $ch = curl_init('https://connect.kingsch.at/api/profile');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json'
                        ]
                    ]);

                    $profileResponse = curl_exec($ch);
                    $profileHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($profileHttpCode === 200) {
                        $userData = json_decode($profileResponse, true);
                        if ($userData) {
                            $_SESSION['kc_user'] = $userData;
                            $_SESSION['success_message'] = 'Token refreshed successfully!';
                            
                            $message = 'Token refreshed successfully! Redirecting to dashboard...';
                            $success = true;
                            
                            header('refresh:2;url=dashboard.php');
                        }
                    }
                } else {
                    $message = 'Failed to refresh token. Response: ' . $response;
                }
            } else {
                $message = 'No refresh token found in config file.';
            }
        } else {
            $message = 'Config file not found.';
        }
    }
}

// Check current status
$currentStatus = [];
if (file_exists(KC_CONFIG_FILE)) {
    $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
    $currentStatus = [
        'has_access_token' => isset($config['access_token']),
        'has_refresh_token' => isset($config['refresh_token']),
        'token_expires' => isset($config['expires_at']) ? date('Y-m-d H:i:s', $config['expires_at']) : 'Unknown',
        'token_valid' => isset($config['expires_at']) ? ($config['expires_at'] > time()) : false,
        'user_id' => $config['sender_user_id'] ?? 'Unknown'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bypass Permissions Loop - KingsChat</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-2xl mx-auto px-4">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">KingsChat Permissions Loop Bypass</h1>
            
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $success ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Current Status</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <?php if (!empty($currentStatus)): ?>
                        <ul class="space-y-2">
                            <li><strong>Has Access Token:</strong> <?php echo $currentStatus['has_access_token'] ? '✅ Yes' : '❌ No'; ?></li>
                            <li><strong>Has Refresh Token:</strong> <?php echo $currentStatus['has_refresh_token'] ? '✅ Yes' : '❌ No'; ?></li>
                            <li><strong>Token Valid:</strong> <?php echo $currentStatus['token_valid'] ? '✅ Yes' : '❌ No'; ?></li>
                            <li><strong>Token Expires:</strong> <?php echo htmlspecialchars($currentStatus['token_expires']); ?></li>
                            <li><strong>User ID:</strong> <?php echo htmlspecialchars($currentStatus['user_id']); ?></li>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-600">No configuration file found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-800">Available Actions</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <button type="submit" name="action" value="use_existing_token" 
                                class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            Use Existing Token (Bypass Permissions Loop)
                        </button>
                        <p class="text-sm text-gray-600 mt-1">Try to use the existing token from your config file</p>
                    </div>
                    
                    <div>
                        <button type="submit" name="action" value="refresh_token" 
                                class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors">
                            Refresh Token
                        </button>
                        <p class="text-sm text-gray-600 mt-1">Get a new access token using your refresh token</p>
                    </div>
                </form>
                
                <div class="pt-4 border-t">
                    <a href="index.php" class="inline-block bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors">
                        Back to Login
                    </a>
                    <a href="dashboard.php" class="inline-block bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700 transition-colors ml-2">
                        Try Dashboard
                    </a>
                </div>
            </div>
            
            <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">About the Permissions Loop Issue</h3>
                <p class="text-sm text-yellow-700">
                    The permissions loop occurs when KingsChat's OAuth server successfully authenticates you but fails to redirect back to the application after granting permissions. This tool helps bypass that issue by using previously obtained valid tokens.
                </p>
            </div>
        </div>
    </div>
</body>
</html>