<?php
session_start();

// Remove execution time limits for bulk messaging
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://cdn1.kingschat.online https://dvvu9r5ep0og0.cloudfront.net https://cdn.jsdelivr.net https://kingschat.online; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; font-src 'self' data:;");

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check if this is an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['kc_access_token'])) {
        error_log("Error: No access token found in session");
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Handle send_message action
    if ($action === 'send_message') {
        $recipientId = $_POST['recipientId'] ?? '';
        $message = $_POST['message'] ?? '';
        $recipientName = $_POST['recipientName'] ?? 'Unknown';

        if (empty($message)) {
            error_log("Error: Message is empty");
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }

        if (empty($recipientId)) {
            error_log("Error: Recipient ID is empty");
            echo json_encode(['success' => false, 'error' => 'Recipient ID is required']);
            exit;
        }

        try {
            $sendUrl = "https://connect.kingsch.at/api/users/" . urlencode($recipientId) . "/new_message";
            
            $messagePayload = [
                'message' => [
                    'body' => [
                        'text' => [
                            'body' => $message
                        ]
                    ]
                ]
            ];

            $ch = curl_init($sendUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $_SESSION['kc_access_token'],
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($messagePayload)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            error_log("Response code: " . $httpCode);
            error_log("Response body: " . $response);

            if ($httpCode === 200) {
                error_log("Message sent to $recipientName ($recipientId) successfully");
                echo json_encode(['success' => true]);
            } else {
                error_log("Failed to send message to $recipientName ($recipientId) - HTTP $httpCode");
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to send message',
                    'code' => $httpCode
                ]);
            }
            
            curl_close($ch);
        } catch (Exception $e) {
            error_log("Exception: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send message: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

// Check if user is logged in and has access token
if (!isset($_SESSION['kc_access_token'])) {
    header('Location: index.php?error=' . urlencode('Please login first'));
    exit;
}

// Get user data and access token from session
$userData = isset($_SESSION['kc_user']['profile']['user']) ? $_SESSION['kc_user']['profile']['user'] : null;
$access_token = $_SESSION['kc_access_token'];

// Initialize contacts array
$contacts = [];
$error = '';

try {
    // Fetch contacts from KingsChat API
    $ch = curl_init('https://connect.kingsch.at/api/contacts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    
    curl_close($ch);

    error_log('Contacts API Response Code: ' . $httpCode);
    error_log('Contacts API Response: ' . substr($response, 0, 200) . '...');

    if ($httpCode === 200) {
        $contactsData = json_decode($response, true);
        if ($contactsData && isset($contactsData['contacts'])) {
            $contacts = $contactsData['contacts'];
            
            // Sort contacts by name
            usort($contacts, function($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
        } else {
            throw new Exception('Invalid response format from contacts API');
        }
    } else {
        $errorData = json_decode($response, true);
        $errorMessage = isset($errorData['error']) ? $errorData['error'] : 'Unknown error';
        throw new Exception('API returned status ' . $httpCode . ': ' . $errorMessage);
    }
} catch (Exception $e) {
    error_log('Error fetching contacts: ' . $e->getMessage());
    $error = 'Failed to load contacts: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="KingsChat Message Sender">
    <meta name="robots" content="noindex, nofollow">
    <title>Send Message - KingsChat Blast</title>
    <script>
        // Initialize SDK configuration
        window.KC_CONFIG = {
            accessToken: '<?php echo $_SESSION['kc_access_token']; ?>',
            refreshToken: '<?php echo $_SESSION['kc_refresh_token'] ?? ''; ?>',
            clientId: '619b30ea-a682-47fb-b90f-5b8e780b89ca'
        };
    </script>
    <script src="https://cdn.kingsch.at/sdk/web/latest/kingschat-web-sdk.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <img src="https://kingschat.online/svg/logo-horizontal.svg" 
                         alt="KingsChat Logo" 
                         class="h-8">
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md transition-colors duration-200">
                        Dashboard
                    </a>
                    <span class="text-gray-600">
                        <?php echo htmlspecialchars($userData['name'] ?? 'User'); ?>
                    </span>
                    <a href="logout.php" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition-colors duration-200">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white rounded-lg shadow px-5 py-6 sm:px-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Send Message</h1>
                
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($contacts)): ?>
                    <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded relative">
                        <p class="text-sm">No contacts found. Please make sure you have added some contacts in KingsChat.</p>
                    </div>
                <?php else: ?>
                    <form id="messageForm" class="space-y-6">
                        <div>
                            <label for="recipientId" class="block text-sm font-medium text-gray-700">Select Contact</label>
                            <select name="recipientId" id="recipientId" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm">
                                <option value="">Select a contact...</option>
                                <?php foreach ($contacts as $contact): ?>
                                    <?php 
                                    $userId = isset($contact['user_jid']) ? explode('@', $contact['user_jid'])[0] : '';
                                    $name = isset($contact['name']) ? $contact['name'] : 'Unknown';
                                    $username = isset($contact['username']) ? '@' . $contact['username'] : '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($userId); ?>">
                                        <?php echo htmlspecialchars($name . ($username ? ' (' . $username . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                            <textarea name="message" id="message" rows="4" required
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-kc-blue focus:border-kc-blue sm:text-sm"
                                      placeholder="Type your message here"></textarea>
                        </div>

                        <div>
                            <button type="submit"
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-kc-blue">
                                Send Message
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Status Messages -->
                <div id="statusMessage" class="mt-4 hidden">
                    <div class="rounded-md p-4">
                        <p class="text-sm"></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let kingsChatWebSdk = null;

        // Initialize SDK with token refresh
        async function initializeKC() {
            try {
                // Wait for SDK to be available
                if (typeof window.KingsChatWebSDK === 'undefined') {
                    throw new Error('KingsChat SDK not loaded');
                }

                kingsChatWebSdk = window.KingsChatWebSDK;

                // Refresh authentication if we have refresh token
                if (window.KC_CONFIG.refreshToken) {
                    const refreshResult = await kingsChatWebSdk.refreshAuthenticationToken({
                        clientId: window.KC_CONFIG.clientId,
                        refreshToken: window.KC_CONFIG.refreshToken
                    });
                    
                    if (refreshResult && refreshResult.accessToken) {
                        window.KC_CONFIG.accessToken = refreshResult.accessToken;
                        console.log('Token refreshed successfully');
                    }
                }

                console.log('KingsChat SDK initialized successfully');
                return true;
            } catch (error) {
                console.error('SDK initialization error:', error);
                showStatus('SDK initialization failed: ' + error.message, true);
                return false;
            }
        }

        // Function to show status messages
        function showStatus(message, isError = false) {
            const statusMessage = document.getElementById('statusMessage');
            statusMessage.classList.remove('hidden');
            statusMessage.className = `mt-4 ${isError ? 'bg-red-50' : 'bg-green-50'} rounded-md p-4`;
            statusMessage.querySelector('p').textContent = message;
        }

        // Initialize SDK when page loads
        window.addEventListener('load', initializeKC);

        // Handle form submission
        const messageForm = document.getElementById('messageForm');
        messageForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const recipientId = document.getElementById('recipientId').value;
            const message = document.getElementById('message').value;
            const submitButton = messageForm.querySelector('button[type="submit"]');
            
            // Disable button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<svg class="animate-spin h-5 w-5 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('recipientId', recipientId);
                formData.append('message', message);

                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    showStatus('Message sent successfully!');
                    messageForm.reset();
                } else {
                    throw new Error(result.error || 'Failed to send message');
                }
            } catch (error) {
                showStatus(error.message, true);
            } finally {
                // Restore button state
                submitButton.disabled = false;
                submitButton.innerHTML = 'Send Message';
            }
        });

        // Add global error handler
        window.addEventListener('error', function(event) {
            if (event.target.src && event.target.src.includes('kingschat')) {
                console.error('SDK loading error:', event);
                showStatus('Failed to load KingsChat SDK', true);
            }
        }, true);
    </script>
</body>
</html>