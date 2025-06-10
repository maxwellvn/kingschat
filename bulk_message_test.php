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

// Load current configuration
$config = loadConfig();

// Initialize variables
$message = '';
$error = '';
$isProcessing = false;
$progress = 0;
$totalMessages = 0;
$sentMessages = 0;

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

// Fetch contacts for contact selector
$contacts = [];
try {
    // Ensure token is valid before making API call
    ensureValidToken();

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

    // If unauthorized, try refreshing token once more and retry
    if ($httpCode === 401) {
        error_log('Received 401 Unauthorized. Attempting to refresh token and retry...');
        if (refreshKingsChatToken()) {
            // Retry the API call with the new token
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
        }
    }

    if ($httpCode === 200) {
        $contactsData = json_decode($response, true);
        if ($contactsData && isset($contactsData['contacts'])) {
            $contacts = $contactsData['contacts'];
            usort($contacts, function($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
        }
    } else {
        error_log("Failed to fetch contacts. HTTP code: $httpCode, Response: $response");
    }
} catch (Exception $e) {
    error_log('Error fetching contacts: ' . $e->getMessage());
}

// Function to send a message
function sendMessage($recipientId, $message, $config) {
    // Always refresh the token before sending each message
    refreshKingsChatToken();

    // Get the access token
    $accessToken = $_SESSION['kc_access_token'] ?? $config['access_token'];

    try {
        // Prepare the API endpoint URL
        $sendUrl = "https://connect.kingsch.at/api/users/" . urlencode($recipientId) . "/new_message";

        // Prepare the message payload
        $messagePayload = [
            'message' => [
                'body' => [
                    'text' => [
                        'body' => $message
                    ]
                ]
            ]
        ];

        // Initialize cURL session
        $ch = curl_init($sendUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($messagePayload)
        ]);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log the response
        error_log("Message response code: " . $httpCode);
        error_log("Message response body: " . $response);

        // If unauthorized, try refreshing token once more and retry
        if ($httpCode === 401) {
            error_log('Received 401 Unauthorized. Attempting to refresh token and retry...');
            if (refreshKingsChatToken()) {
                // Get the new access token
                $accessToken = $_SESSION['kc_access_token'];

                // Retry the API call with the new token
                $ch = curl_init($sendUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    CURLOPT_POSTFIELDS => json_encode($messagePayload)
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                error_log("Message retry response code: " . $httpCode);
                error_log("Message retry response body: " . $response);
            }
        }

        // Check if the request was successful
        if ($httpCode === 200) {
            return [
                'success' => true,
                'message' => 'Message sent successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to send message. HTTP code: $httpCode, Response: $response"
            ];
        }
    } catch (Exception $e) {
        error_log("Exception sending message: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Exception: " . $e->getMessage()
        ];
    }
}

// Handle form submission for sending bulk messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_bulk') {
    // Get form data
    $recipientIds = trim($_POST['recipient_id']);
    $messageContent = trim($_POST['message_content']);
    $messageCount = intval($_POST['message_count']);
    $interval = floatval($_POST['interval']);

    // Parse recipient IDs (comma-separated)
    $recipientArray = array_filter(array_map('trim', explode(',', $recipientIds)));

    // Validate input
    if (empty($recipientIds) || empty($recipientArray)) {
        $error = 'At least one recipient is required';
        header('Location: bulk_message_test.php?status=error&msg=' . urlencode($error));
        exit;
    } elseif (empty($messageContent)) {
        $error = 'Message content is required';
        header('Location: bulk_message_test.php?status=error&msg=' . urlencode($error));
        exit;
    } elseif ($messageCount < 1) {
        $error = 'Message count must be at least 1';
        header('Location: bulk_message_test.php?status=error&msg=' . urlencode($error));
        exit;
    } elseif ($interval < 0.1) {
        $error = 'Interval must be at least 0.1 seconds';
        header('Location: bulk_message_test.php?status=error&msg=' . urlencode($error));
        exit;
    } else {
        // Set processing flag
        $isProcessing = true;
        $totalMessages = $messageCount * count($recipientArray); // Total messages = count per recipient * number of recipients

        // Send the first message to the first recipient immediately
        $firstRecipient = $recipientArray[0];
        $result = sendMessage($firstRecipient, $messageContent, $config);

        if ($result['success']) {
            $sentMessages = 1;
            $progress = ($sentMessages / $totalMessages) * 100;
            $message = "Sent 1 of $totalMessages messages to " . count($recipientArray) . " recipient(s). Continuing to send...";

            // Store data in session for AJAX updates
            $_SESSION['bulk_message'] = [
                'recipient_ids' => $recipientArray, // Store array of recipient IDs
                'message_content' => $messageContent,
                'message_count' => $messageCount,
                'interval' => $interval,
                'sent_messages' => 1,
                'total_messages' => $totalMessages,
                'start_time' => microtime(true),
                'next_send_time' => microtime(true) + $interval,
                'is_complete' => false
            ];

            // Redirect to prevent form resubmission
            header('Location: bulk_message_test.php');
            exit;
        } else {
            $error = $result['message'];
            $isProcessing = false;

            // Redirect to prevent form resubmission
            header('Location: bulk_message_test.php?status=error&msg=' . urlencode($error));
            exit;
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

// Handle AJAX request to send next message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_next') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['bulk_message']) || $_SESSION['bulk_message']['is_complete']) {
        echo json_encode([
            'success' => false,
            'message' => 'No active bulk message session',
            'is_complete' => true
        ]);
        exit;
    }

    $bulkData = $_SESSION['bulk_message'];
    $currentTime = microtime(true);

    // Check if it's time to send the next message
    if ($currentTime >= $bulkData['next_send_time']) {
        // Calculate which recipient and message number we're on
        $recipientIds = $bulkData['recipient_ids'];
        $totalRecipients = count($recipientIds);
        $messagesPerRecipient = $bulkData['message_count'];

        // Calculate current recipient index and message number for that recipient
        $currentMessageIndex = $bulkData['sent_messages']; // 0-based index
        $currentRecipientIndex = floor($currentMessageIndex / $messagesPerRecipient);
        $currentMessageForRecipient = ($currentMessageIndex % $messagesPerRecipient) + 1;

        // Get the current recipient ID
        $currentRecipientId = $recipientIds[$currentRecipientIndex];

        // Send the next message
        $result = sendMessage($currentRecipientId, $bulkData['message_content'], $config);

        if ($result['success']) {
            $bulkData['sent_messages']++;
            $bulkData['next_send_time'] = microtime(true) + $bulkData['interval'];

            // Check if all messages have been sent
            if ($bulkData['sent_messages'] >= $bulkData['total_messages']) {
                $bulkData['is_complete'] = true;
            }

            // Update session data
            $_SESSION['bulk_message'] = $bulkData;

            echo json_encode([
                'success' => true,
                'sent_messages' => $bulkData['sent_messages'],
                'total_messages' => $bulkData['total_messages'],
                'progress' => ($bulkData['sent_messages'] / $bulkData['total_messages']) * 100,
                'next_send_time' => $bulkData['next_send_time'],
                'is_complete' => $bulkData['is_complete'],
                'message' => "Sent {$bulkData['sent_messages']} of {$bulkData['total_messages']} messages to " . count($recipientIds) . " recipient(s)"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
    } else {
        // Not time to send yet
        echo json_encode([
            'success' => true,
            'sent_messages' => $bulkData['sent_messages'],
            'total_messages' => $bulkData['total_messages'],
            'progress' => ($bulkData['sent_messages'] / $bulkData['total_messages']) * 100,
            'next_send_time' => $bulkData['next_send_time'],
            'is_complete' => false,
            'wait_time' => $bulkData['next_send_time'] - $currentTime,
            'message' => "Waiting to send next message... " . ($bulkData['next_send_time'] - $currentTime) . " seconds remaining"
        ]);
    }

    exit;
}

// Handle AJAX request to check status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_status') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['bulk_message'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No active bulk message session',
            'is_complete' => true
        ]);
        exit;
    }

    $bulkData = $_SESSION['bulk_message'];
    $currentTime = microtime(true);

    echo json_encode([
        'success' => true,
        'sent_messages' => $bulkData['sent_messages'],
        'total_messages' => $bulkData['total_messages'],
        'progress' => ($bulkData['sent_messages'] / $bulkData['total_messages']) * 100,
        'next_send_time' => $bulkData['next_send_time'],
        'is_complete' => $bulkData['is_complete'],
        'wait_time' => max(0, $bulkData['next_send_time'] - $currentTime),
        'message' => $bulkData['is_complete']
            ? "Completed: Sent {$bulkData['sent_messages']} of {$bulkData['total_messages']} messages"
            : "Sent {$bulkData['sent_messages']} of {$bulkData['total_messages']} messages"
    ]);

    exit;
}

// Handle AJAX request to cancel bulk sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    header('Content-Type: application/json');

    if (isset($_SESSION['bulk_message'])) {
        $sentMessages = $_SESSION['bulk_message']['sent_messages'];
        $totalMessages = $_SESSION['bulk_message']['total_messages'];
        unset($_SESSION['bulk_message']);

        echo json_encode([
            'success' => true,
            'message' => "Cancelled after sending $sentMessages of $totalMessages messages",
            'is_complete' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No active bulk message session to cancel',
            'is_complete' => true
        ]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KingsChat Bulk Message Test</title>
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
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">KingsChat Bulk Message Test</h1>

            <div class="flex space-x-4">
                <a href="dashboard.php" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                <a href="token_config.php" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Token Config</span>
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div id="successAlert" class="mb-6 p-4 bg-green-50 border-2 border-green-200 text-green-700 rounded-xl transition-all duration-500 ease-in-out opacity-100 shadow-sm">
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
            <div id="errorAlert" class="mb-6 p-4 bg-red-50 border-2 border-red-200 text-red-700 rounded-xl transition-all duration-500 ease-in-out opacity-100 shadow-sm">
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

                        <div class="mt-4">
                            <a href="token_config.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-kc-blue bg-white border-2 border-kc-blue rounded-md hover:bg-kc-blue hover:text-white transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Configure Tokens
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200 text-red-700 mb-4">
                            <p class="font-medium"><?php echo htmlspecialchars($tokenInfo['message']); ?></p>
                        </div>

                        <div>
                            <a href="token_config.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-kc-blue rounded-md hover:bg-kc-blue-dark transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Configure Tokens
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border-0 overflow-hidden mb-8 transition-all duration-300 hover:shadow-xl">
            <div class="px-8 py-6 bg-gradient-to-r from-kc-blue to-kc-blue-dark">
                <h2 class="text-2xl font-bold text-white">Bulk Message Test</h2>
            </div>

            <div class="p-8">
                <?php if (isset($_SESSION['bulk_message']) && !$_SESSION['bulk_message']['is_complete']): ?>
                    <div id="progressSection" class="bg-gray-50 p-5 rounded-xl shadow-inner-lg border border-gray-100 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Sending Messages</h3>

                        <div class="mb-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Progress</span>
                                <span id="progressPercentage" class="text-sm font-medium text-kc-blue">
                                    <?php echo round(($_SESSION['bulk_message']['sent_messages'] / $_SESSION['bulk_message']['total_messages']) * 100); ?>%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div id="progressBar" class="bg-kc-blue h-2.5 rounded-full transition-all duration-300" style="width: <?php echo ($_SESSION['bulk_message']['sent_messages'] / $_SESSION['bulk_message']['total_messages']) * 100; ?>%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Recipient ID</p>
                                <p class="text-gray-900 font-mono text-sm"><?php echo htmlspecialchars($_SESSION['bulk_message']['recipient_id']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Messages</p>
                                <p class="text-gray-900">
                                    <span id="sentCount"><?php echo $_SESSION['bulk_message']['sent_messages']; ?></span> of
                                    <span id="totalCount"><?php echo $_SESSION['bulk_message']['total_messages']; ?></span> sent
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Interval</p>
                                <p class="text-gray-900"><?php echo number_format($_SESSION['bulk_message']['interval'], 1); ?> seconds</p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Next Message</p>
                                <p id="nextMessageTime" class="text-gray-900">
                                    <?php
                                    $waitTime = max(0, $_SESSION['bulk_message']['next_send_time'] - microtime(true));
                                    echo "In " . number_format($waitTime, 1) . " seconds";
                                    ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Estimated Time Left</p>
                                <p id="estimatedTimeLeft" class="text-gray-900">
                                    <?php
                                    $messagesLeft = $_SESSION['bulk_message']['total_messages'] - $_SESSION['bulk_message']['sent_messages'];
                                    $timeLeft = $messagesLeft * $_SESSION['bulk_message']['interval'];
                                    $minutes = floor($timeLeft / 60);
                                    $seconds = $timeLeft % 60;
                                    echo "$minutes min " . number_format($seconds, 1) . " sec";
                                    ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-500">Estimated Completion</p>
                                <p id="estimatedCompletion" class="text-gray-900">
                                    <?php
                                    $completionTime = time() + $timeLeft;
                                    echo date('H:i:s', $completionTime);
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div id="statusMessage" class="p-3 bg-blue-50 rounded-lg border border-blue-200 text-blue-700 mb-4">
                            Sending messages...
                        </div>

                        <div class="flex justify-end">
                            <button id="cancelButton" type="button" class="px-4 py-2 text-sm font-medium border-2 border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>Cancel</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="bulkMessageForm" method="post" class="space-y-6" <?php echo (isset($_SESSION['bulk_message']) && !$_SESSION['bulk_message']['is_complete']) ? 'style="display: none;"' : ''; ?>>
                    <input type="hidden" name="action" value="send_bulk">

                    <!-- Recipient Selection Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700">Recipients</label>
                            <span id="selectedRecipientsCount" class="text-sm text-gray-500">0 selected</span>
                        </div>

                        <!-- Search Input -->
                        <div class="relative">
                            <input type="text" id="userSearchInput"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-kc-blue focus:border-kc-blue transition-all duration-200"
                                   placeholder="Search for users by username (e.g., john, mary, test)">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Search Results -->
                        <div id="searchResults" class="hidden">
                            <div class="bg-white border-2 border-gray-200 rounded-lg max-h-60 overflow-y-auto">
                                <div id="searchResultsContent" class="p-4">
                                    <!-- Results will be populated here -->
                                </div>
                            </div>
                        </div>

                        <!-- Selected Recipients -->
                        <div id="selectedRecipientsContainer" class="hidden">
                            <div class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Selected Recipients
                                </h4>
                                <div id="selectedRecipientsList" class="space-y-2">
                                    <!-- Selected recipients will be shown here -->
                                </div>
                                <button type="button" id="clearAllRecipients"
                                        class="mt-3 text-sm text-red-600 hover:text-red-800 transition-colors duration-200">
                                    Clear All Recipients
                                </button>
                            </div>
                        </div>

                        <!-- Hidden input to store selected recipient IDs -->
                        <input type="hidden" id="recipient_id" name="recipient_id" required>

                        <p class="text-xs text-gray-500">Search for users by username to add them as recipients. You can select multiple recipients for bulk messaging.</p>
                    </div>

                    <div>
                        <label for="message_content" class="block text-sm font-medium text-gray-700 mb-1">Message Content</label>
                        <textarea id="message_content" name="message_content" rows="4" required
                                  class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm"
                                  placeholder="Type your message here"></textarea>
                        <p class="mt-1 text-xs text-gray-500">The content of the message to send.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="message_count" class="block text-sm font-medium text-gray-700 mb-1">Number of Messages</label>
                            <input type="number" id="message_count" name="message_count" min="1" max="100" value="5" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">How many messages to send (1-100).</p>
                        </div>

                        <div>
                            <label for="interval" class="block text-sm font-medium text-gray-700 mb-1">Interval (seconds)</label>
                            <input type="number" id="interval" name="interval" min="0.1" max="60" step="0.1" value="5" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">Time between messages (0.1-60 seconds).</p>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-kc-blue text-white rounded-lg hover:bg-kc-blue-dark font-medium transition-all duration-200 shadow-sm hover:shadow text-base sm:text-sm flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            Start Sending Messages
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');

            if (successAlert) {
                successAlert.style.opacity = '0';
                setTimeout(() => {
                    successAlert.style.display = 'none';
                }, 500);
            }

            if (errorAlert) {
                errorAlert.style.opacity = '0';
                setTimeout(() => {
                    errorAlert.style.display = 'none';
                }, 500);
            }
        }, 5000);

        // User search functionality for bulk messaging
        let selectedRecipients = [];
        let searchTimeout;

        document.addEventListener('DOMContentLoaded', function() {
            const userSearchInput = document.getElementById('userSearchInput');
            const searchResults = document.getElementById('searchResults');
            const searchResultsContent = document.getElementById('searchResultsContent');
            const selectedRecipientsContainer = document.getElementById('selectedRecipientsContainer');
            const selectedRecipientsList = document.getElementById('selectedRecipientsList');
            const selectedRecipientsCount = document.getElementById('selectedRecipientsCount');
            const clearAllRecipients = document.getElementById('clearAllRecipients');
            const recipientIdInput = document.getElementById('recipient_id');

            // Search input handler with debouncing
            userSearchInput.addEventListener('input', function() {
                const query = this.value.trim();

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    return;
                }

                searchTimeout = setTimeout(() => {
                    performUserSearch(query);
                }, 500);
            });

            // Clear all recipients
            clearAllRecipients.addEventListener('click', function() {
                selectedRecipients = [];
                updateSelectedRecipientsDisplay();
                updateRecipientIdInput();
            });

            // Perform user search
            async function performUserSearch(query) {
                searchResultsContent.innerHTML = '<div class="text-center py-4"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-kc-blue mx-auto"></div><p class="text-sm text-gray-500 mt-2">Searching...</p></div>';
                searchResults.classList.remove('hidden');

                try {
                    const response = await fetch('search_users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            query: query,
                            type: 'username'
                        })
                    });

                    const result = await response.json();

                    if (result.success && result.data && result.data.length > 0) {
                        displaySearchResults(result.data, query);
                    } else {
                        displayNoResults(query);
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    displaySearchError('Network error occurred during search');
                }
            }

            // Display search results
            function displaySearchResults(results, query) {
                let html = '<div class="space-y-2">';
                html += `<div class="text-sm font-medium text-gray-700 mb-3">Found ${results.length} user${results.length > 1 ? 's' : ''} for "${query}"</div>`;

                results.forEach(user => {
                    const userId = user.user_id || user.id || '';
                    const name = user.name || user.display_name || 'Unknown';
                    const username = user.username ? '@' + user.username : '';

                    // Check if user is already selected
                    const isSelected = selectedRecipients.some(recipient => recipient.id === userId);

                    html += `
                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors duration-200 border border-gray-100">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-kc-blue text-white flex items-center justify-center font-bold text-sm">
                                    ${name.charAt(0).toUpperCase()}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">${escapeHtml(name)}</p>
                                    ${username ? `<p class="text-xs text-gray-500">${escapeHtml(username)}</p>` : ''}
                                    <p class="text-xs text-gray-400 font-mono">${escapeHtml(userId)}</p>
                                </div>
                            </div>
                            <button type="button"
                                    onclick="addRecipient('${escapeHtml(userId)}', '${escapeHtml(name)}', '${escapeHtml(username)}')"
                                    ${isSelected ? 'disabled' : ''}
                                    class="px-3 py-1 text-sm font-medium ${isSelected ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-kc-blue text-white hover:bg-kc-blue-dark'} rounded-md transition-colors duration-200">
                                ${isSelected ? 'Added' : 'Add'}
                            </button>
                        </div>
                    `;
                });

                html += '</div>';
                searchResultsContent.innerHTML = html;
            }

            // Display no results
            function displayNoResults(query) {
                searchResultsContent.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500">No users found with username "${escapeHtml(query)}"</p>
                        <p class="text-gray-400 text-sm mt-2">Try searching for a different username</p>
                    </div>
                `;
            }

            // Display search error
            function displaySearchError(error) {
                searchResultsContent.innerHTML = `
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>${escapeHtml(error)}</span>
                        </div>
                    </div>
                `;
            }

            // Update selected recipients display
            function updateSelectedRecipientsDisplay() {
                selectedRecipientsCount.textContent = `${selectedRecipients.length} selected`;

                if (selectedRecipients.length === 0) {
                    selectedRecipientsContainer.classList.add('hidden');
                    return;
                }

                selectedRecipientsContainer.classList.remove('hidden');

                let html = '';
                selectedRecipients.forEach(recipient => {
                    html += `
                        <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-kc-blue text-white flex items-center justify-center font-bold text-xs">
                                    ${recipient.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">${escapeHtml(recipient.name)}</p>
                                    ${recipient.username ? `<p class="text-xs text-gray-500">${escapeHtml(recipient.username)}</p>` : ''}
                                    <p class="text-xs text-gray-400 font-mono">${escapeHtml(recipient.id)}</p>
                                </div>
                            </div>
                            <button type="button"
                                    onclick="removeRecipient('${escapeHtml(recipient.id)}')"
                                    class="text-red-600 hover:text-red-800 transition-colors duration-200 p-1">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    `;
                });

                selectedRecipientsList.innerHTML = html;
            }

            // Update hidden input with recipient IDs
            function updateRecipientIdInput() {
                const recipientIds = selectedRecipients.map(recipient => recipient.id);
                recipientIdInput.value = recipientIds.join(',');
            }

            // Make functions globally available
            window.addRecipient = function(userId, name, username) {
                // Check if already selected
                if (selectedRecipients.some(recipient => recipient.id === userId)) {
                    return;
                }

                selectedRecipients.push({
                    id: userId,
                    name: name,
                    username: username
                });

                updateSelectedRecipientsDisplay();
                updateRecipientIdInput();

                // Refresh search results to show updated button states
                const query = userSearchInput.value.trim();
                if (query.length >= 2) {
                    performUserSearch(query);
                }
            };

            window.removeRecipient = function(userId) {
                selectedRecipients = selectedRecipients.filter(recipient => recipient.id !== userId);
                updateSelectedRecipientsDisplay();
                updateRecipientIdInput();

                // Refresh search results to show updated button states
                const query = userSearchInput.value.trim();
                if (query.length >= 2) {
                    performUserSearch(query);
                }
            };

            // Utility function to escape HTML
            window.escapeHtml = function(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            };
        });

        <?php if (isset($_SESSION['bulk_message']) && !$_SESSION['bulk_message']['is_complete']): ?>
        // Bulk message sending functionality
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = document.getElementById('progressPercentage');
            const sentCount = document.getElementById('sentCount');
            const totalCount = document.getElementById('totalCount');
            const nextMessageTime = document.getElementById('nextMessageTime');
            const statusMessage = document.getElementById('statusMessage');
            const cancelButton = document.getElementById('cancelButton');

            // Function to update progress UI
            function updateProgress(data) {
                progressBar.style.width = data.progress + '%';
                progressPercentage.textContent = Math.round(data.progress) + '%';
                sentCount.textContent = data.sent_messages;

                if (data.wait_time !== undefined) {
                    nextMessageTime.textContent = 'In ' + parseFloat(data.wait_time).toFixed(1) + ' seconds';
                }

                // Update estimated time left
                const estimatedTimeLeft = document.getElementById('estimatedTimeLeft');
                const estimatedCompletion = document.getElementById('estimatedCompletion');

                if (estimatedTimeLeft && estimatedCompletion) {
                    const messagesLeft = data.total_messages - data.sent_messages;
                    const interval = <?php echo isset($_SESSION['bulk_message']) ? $_SESSION['bulk_message']['interval'] : 5; ?>;
                    const timeLeftSeconds = messagesLeft * interval;

                    const minutes = Math.floor(timeLeftSeconds / 60);
                    const seconds = timeLeftSeconds % 60;

                    estimatedTimeLeft.textContent = minutes + ' min ' + seconds.toFixed(1) + ' sec';

                    // Calculate completion time
                    const now = new Date();
                    const completionTime = new Date(now.getTime() + (timeLeftSeconds * 1000));
                    const timeString = completionTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});

                    estimatedCompletion.textContent = timeString;
                }

                statusMessage.textContent = data.message;

                if (data.is_complete) {
                    statusMessage.className = 'p-3 bg-green-50 rounded-lg border border-green-200 text-green-700 mb-4';
                    cancelButton.textContent = 'Done';
                    cancelButton.onclick = function() {
                        window.location.href = 'bulk_message_test.php';
                    };
                }
            }

            // Function to send the next message
            function sendNextMessage() {
                fetch('bulk_message_test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=send_next'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProgress(data);

                        if (!data.is_complete) {
                            // Schedule the next check
                            setTimeout(checkStatus, 1000);
                        }
                    } else {
                        statusMessage.textContent = data.message;
                        statusMessage.className = 'p-3 bg-red-50 rounded-lg border border-red-200 text-red-700 mb-4';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusMessage.textContent = 'Error: ' + error.message;
                    statusMessage.className = 'p-3 bg-red-50 rounded-lg border border-red-200 text-red-700 mb-4';
                });
            }

            // Function to check status
            function checkStatus() {
                fetch('bulk_message_test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=check_status'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProgress(data);

                        if (!data.is_complete) {
                            // If it's time to send the next message
                            if (data.wait_time <= 0) {
                                sendNextMessage();
                            } else {
                                // Otherwise, schedule another check
                                setTimeout(checkStatus, 1000);
                            }
                        }
                    } else {
                        statusMessage.textContent = data.message;
                        statusMessage.className = 'p-3 bg-red-50 rounded-lg border border-red-200 text-red-700 mb-4';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusMessage.textContent = 'Error: ' + error.message;
                    statusMessage.className = 'p-3 bg-red-50 rounded-lg border border-red-200 text-red-700 mb-4';
                });
            }

            // Set up cancel button
            cancelButton.addEventListener('click', function() {
                fetch('bulk_message_test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=cancel'
                })
                .then(response => response.json())
                .then(data => {
                    statusMessage.textContent = data.message;
                    statusMessage.className = 'p-3 bg-yellow-50 rounded-lg border border-yellow-200 text-yellow-700 mb-4';

                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.href = 'bulk_message_test.php';
                    }, 2000);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });

            // Start checking status
            checkStatus();
        });
        <?php endif; ?>

        // Form validation for bulk message form
        document.getElementById('bulkMessageForm').addEventListener('submit', function(e) {
            const recipientIdInput = document.getElementById('recipient_id');
            const recipientIds = recipientIdInput.value.trim();

            if (!recipientIds || recipientIds === '') {
                e.preventDefault();
                alert('Please select at least one recipient before sending messages.');
                return false;
            }

            const recipientArray = recipientIds.split(',').filter(id => id.trim() !== '');
            if (recipientArray.length === 0) {
                e.preventDefault();
                alert('Please select at least one recipient before sending messages.');
                return false;
            }

            // Confirm before sending
            const messageCount = document.getElementById('message_count').value;
            const totalMessages = messageCount * recipientArray.length;
            const confirmMessage = `Are you sure you want to send ${messageCount} message(s) to ${recipientArray.length} recipient(s) (${totalMessages} total messages)?`;

            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>