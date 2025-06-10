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

// Define constants for config file
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

// Initialize variables
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_config') {
            // Update configuration
            $config['access_token'] = trim($_POST['access_token']);
            $config['refresh_token'] = trim($_POST['refresh_token']);
            $config['sender_user_id'] = trim($_POST['sender_user_id']);

            // Decode token to get expiration time
            $tokenParts = explode('.', $config['access_token']);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $tokenParts[1]))), true);
                if (isset($payload['exp'])) {
                    $config['expires_at'] = $payload['exp'];
                }
            }

            // Save configuration
            saveConfig($config);

            // Update session with new token
            $_SESSION['kc_access_token'] = $config['access_token'];
            $_SESSION['kc_token_expires_at'] = $config['expires_at'];

            $message = 'Configuration updated successfully.';

            // Redirect to prevent form resubmission
            header('Location: token_config.php?status=success&msg=' . urlencode('Configuration updated successfully'));
            exit;
        } elseif ($_POST['action'] === 'refresh_token') {
            // Refresh token
            if (refreshKingsChatToken()) {
                // Update config with new token
                $config['access_token'] = $_SESSION['kc_access_token'];
                $config['expires_at'] = $_SESSION['kc_token_expires_at'];
                saveConfig($config);

                // Reload the config to get the updated values
                $config = loadConfig();

                // Redirect to prevent form resubmission
                header('Location: token_config.php?status=success&msg=' . urlencode('Token refreshed successfully'));
                exit;
            } else {
                $error = 'Failed to refresh token.';

                // Redirect to prevent form resubmission
                header('Location: token_config.php?status=error&msg=' . urlencode('Failed to refresh token'));
                exit;
            }
        } elseif ($_POST['action'] === 'test_token') {
            // Test token with a simple API call
            try {
                $ch = curl_init('https://connect.kingsch.at/api/contacts');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $config['access_token'],
                        'Content-Type: application/json'
                    ]
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    // Redirect to prevent form resubmission
                    header('Location: token_config.php?status=success&msg=' . urlencode('Token is valid. API test successful'));
                    exit;
                } else {
                    // Redirect to prevent form resubmission
                    header('Location: token_config.php?status=error&msg=' . urlencode("Token test failed. HTTP code: $httpCode"));
                    exit;
                }
            } catch (Exception $e) {
                // Redirect to prevent form resubmission
                header('Location: token_config.php?status=error&msg=' . urlencode("Exception during API test: " . $e->getMessage()));
                exit;
            }
        }
    }
}

// Check for status messages from redirects
if (isset($_GET['status']) && isset($_GET['msg'])) {
    if ($_GET['status'] === 'success') {
        $message = urldecode($_GET['msg']);
    } elseif ($_GET['status'] === 'error') {
        $error = urldecode($_GET['msg']);
    }
}

// Function to display token information
function getTokenInfo($token) {
    if (empty($token)) {
        return [
            'valid' => false,
            'message' => 'No token provided'
        ];
    }

    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return [
            'valid' => false,
            'message' => 'Invalid token format'
        ];
    }

    try {
        $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $tokenParts[1]))), true);

        if (!$payload) {
            return [
                'valid' => false,
                'message' => 'Could not decode token payload'
            ];
        }

        $expiresAt = $payload['exp'] ?? 0;
        $timeRemaining = $expiresAt - time();

        return [
            'valid' => $timeRemaining > 0,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'time_remaining' => $timeRemaining,
            'subject' => $payload['sub'] ?? 'Unknown',
            'issuer' => $payload['iss'] ?? 'Unknown',
            'audience' => $payload['aud'] ?? [],
            'message' => $timeRemaining > 0
                ? 'Token is valid for ' . round($timeRemaining / 60, 1) . ' minutes'
                : 'Token has expired'
        ];
    } catch (Exception $e) {
        return [
            'valid' => false,
            'message' => 'Error decoding token: ' . $e->getMessage()
        ];
    }
}

// Get token information
$tokenInfo = getTokenInfo($config['access_token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KingsChat Token Configuration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'kc-blue': '#4A90E2',
                        'kc-blue-dark': '#357ABD',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">KingsChat Token Configuration</h1>

            <div class="flex space-x-4">
                <a href="dashboard.php" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="bulk_message_test.php" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    <span>Bulk Message Test</span>
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-50 border-2 border-green-200 text-green-700 rounded-xl transition-all duration-500 ease-in-out opacity-100 shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border-2 border-red-200 text-red-700 rounded-xl transition-all duration-500 ease-in-out opacity-100 shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-lg border-0 overflow-hidden mb-8 transition-all duration-300 hover:shadow-xl">
            <div class="px-8 py-6 bg-gradient-to-r from-kc-blue to-kc-blue-dark">
                <h2 class="text-2xl font-bold text-white">Token Status</h2>
            </div>

            <div class="p-8">
                <div class="bg-gray-50 p-5 rounded-xl shadow-inner-lg border border-gray-100 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Token Information</h3>

                    <?php if ($tokenInfo['valid']): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Status</p>
                                <p class="text-green-600 font-medium">Valid</p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Expires At</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($tokenInfo['expires_at']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Time Remaining</p>
                                <p class="text-gray-900"><?php echo round($tokenInfo['time_remaining'] / 60, 1); ?> minutes</p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">User ID (Subject)</p>
                                <p class="text-gray-900 font-mono text-sm"><?php echo htmlspecialchars($tokenInfo['subject']); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200 text-red-700">
                            <p class="font-medium"><?php echo htmlspecialchars($tokenInfo['message']); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="post">
                            <input type="hidden" name="action" value="refresh_token">
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Refresh Token</span>
                            </button>
                        </form>

                        <form method="post">
                            <input type="hidden" name="action" value="test_token">
                            <button type="submit" class="px-4 py-2 text-sm font-medium border-2 border-kc-blue text-kc-blue hover:bg-kc-blue hover:text-white rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Test Token</span>
                            </button>
                        </form>
                    </div>
                </div>

                <form method="post" class="space-y-6">
                    <input type="hidden" name="action" value="update_config">

                    <div>
                        <label for="access_token" class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                        <textarea id="access_token" name="access_token" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm"><?php echo htmlspecialchars($config['access_token']); ?></textarea>
                        <p class="mt-1 text-xs text-gray-500">The JWT token used for API authentication. Should start with "eyJ".</p>
                    </div>

                    <div>
                        <label for="refresh_token" class="block text-sm font-medium text-gray-700 mb-1">Refresh Token</label>
                        <input type="text" id="refresh_token" name="refresh_token" value="<?php echo htmlspecialchars($config['refresh_token']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">The refresh token used to get new access tokens.</p>
                    </div>

                    <div>
                        <label for="sender_user_id" class="block text-sm font-medium text-gray-700 mb-1">Sender User ID</label>
                        <input type="text" id="sender_user_id" name="sender_user_id" value="<?php echo htmlspecialchars($config['sender_user_id']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">The user ID that will be used as the sender for messages.</p>
                    </div>

                    <div>
                        <button type="submit" class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-kc-blue text-white rounded-lg hover:bg-kc-blue-dark font-medium transition-all duration-200 shadow-sm hover:shadow text-base sm:text-sm flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border-0 overflow-hidden mb-8 transition-all duration-300 hover:shadow-xl">
            <div class="px-8 py-6 bg-gradient-to-r from-kc-blue to-kc-blue-dark">
                <h2 class="text-2xl font-bold text-white">How to Get Tokens</h2>
            </div>

            <div class="p-8">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Getting an Access Token</h3>
                    <ol class="list-decimal pl-5 space-y-2 text-gray-700">
                        <li>Log in to your KingsChat account in a web browser</li>
                        <li>Open the browser's developer tools (F12 or right-click and select "Inspect")</li>
                        <li>Go to the "Network" tab</li>
                        <li>Refresh the page and look for requests to "kingschat.online" or "connect.kingsch.at"</li>
                        <li>Find a request with an "Authorization" header containing "Bearer eyJ..."</li>
                        <li>Copy the complete token (without "Bearer ") and paste it in the "Access Token" field above</li>
                    </ol>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Getting a Refresh Token</h3>
                    <p class="text-gray-700 mb-4">A working refresh token is already pre-filled in the form above. You don't need to change it unless it stops working.</p>

                    <p class="text-gray-700 mb-2">If you need to get a new refresh token:</p>
                    <ol class="list-decimal pl-5 space-y-2 text-gray-700">
                        <li>In the Network tab of developer tools, look for requests to "oauth2/token" or similar</li>
                        <li>Check the response JSON for a field called "refresh_token"</li>
                        <li>Copy this value and paste it in the "Refresh Token" field above</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>