<?php
// Start session and set security headers
session_start();

// Set max execution time to 1 hour (3600 seconds)
set_time_limit(3600);
ini_set('max_execution_time', '3600');

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

// Include database connection
require_once 'includes/db_connect.php';

// Include token refresh functionality
require_once 'token_refresh.php';

// Check if user is logged in
if (!isset($_SESSION['kc_access_token'])) {
    header('Location: index.php?error=' . urlencode('Please login first'));
    exit;
}

// Check if token needs refreshing and refresh it if needed
if (needsTokenRefresh()) {
    $refreshed = refreshKingsChatToken();
    if (!$refreshed) {
        // If token refresh failed, redirect to login
        error_log("Token refresh failed. Redirecting to login page.");
        header('Location: index.php?error=' . urlencode('Your session has expired. Please login again.'));
        exit;
    }
    error_log("Token refreshed successfully.");
}

// Get user data from session
$userData = isset($_SESSION['kc_user']['profile']['user']) ? $_SESSION['kc_user']['profile']['user'] : null;
$userEmail = isset($_SESSION['kc_user']['profile']['email']['address']) ? $_SESSION['kc_user']['profile']['email']['address'] : null;
$userId = $userData['_id'] ?? null;

// Ensure user exists in our database
if ($userId) {
    $user = getUserById($userId);
    if (!$user) {
        // Create user if doesn't exist
        $username = $userData['username'] ?? '';
        $email = $userEmail ?? '';
        createUser($userId, $username, $email);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_task':
                if (!empty($_POST['title']) && $userId) {
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description'] ?? '');
                    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                    $priority = in_array($_POST['priority'] ?? 'medium', ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
                    
                    if (createTask($userId, $title, $description, $dueDate, $priority)) {
                        $_SESSION['success_message'] = 'Task added successfully!';
                        // Add notification
                        addNotification($conn->insert_id, $userId, "Task '$title' has been created");
                    } else {
                        $error = 'Failed to add task. Please try again.';
                    }
                } else {
                    $error = 'Title is required';
                }
                break;
                
            case 'update_status':
                if (isset($_POST['task_id'], $_POST['status']) && $userId) {
                    $taskId = (int)$_POST['task_id'];
                    $status = in_array($_POST['status'], ['pending', 'in_progress', 'completed', 'overdue']) ? 
                              $_POST['status'] : 'pending';
                    
                    if (updateTaskStatus($taskId, $status, $userId)) {
                        echo json_encode(['success' => true]);
                        exit;
                    }
                }
                echo json_encode(['success' => false]);
                exit;
        }
    }
}

// Get tasks for the current user
$pendingTasks = [];
$inProgressTasks = [];
$completedTasks = [];

if ($userId) {
    $pendingTasks = getUserTasks($userId, 'pending');
    $inProgressTasks = getUserTasks($userId, 'in_progress');
    $completedTasks = getUserTasks($userId, 'completed');
    
    // Get recent notifications
    $notifications = getUserNotifications($userId, 5);
}

// Get success message from session and clear it
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Get error message if any
$error = $_GET['error'] ?? '';

// Check if this is a fresh login (indicated by auth parameter in URL)
if (isset($_GET['auth']) && $userData && isset($userData['user_id'])) {
    // Send welcome message to the user
    $recipientId = $userData['user_id'];
    $recipientName = isset($userData['name']) ? $userData['name'] : 'User';

    // Log the attempt to send a welcome message
    error_log("Attempting to send welcome message to $recipientName ($recipientId)");

    // Send the welcome message
    $messageSent = sendWelcomeMessage($recipientId, $recipientName);

    // Log the result
    if ($messageSent) {
        error_log("Welcome message sent successfully to $recipientName ($recipientId)");
    } else {
        error_log("Failed to send welcome message to $recipientName ($recipientId)");
    }
}

// Fetch contacts for message sending
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="KingsBlast - Mass Messaging for KingsChat">
    <meta name="robots" content="noindex, nofollow">
    <title>KingsBlast - Mass Messaging Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="https://kingschat.online/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://kingschat.online/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="https://kingschat.online/apple-touch-icon.png">
    <link rel="shortcut icon" href="https://kingschat.online/favicon.ico">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'kc-blue': '#4A90E2',
                        'kc-blue-dark': '#357ABD',
                    },
                    boxShadow: {
                        'inner-lg': 'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center">
                    <a href="dashboard.php" class="flex items-center space-x-2 transition-transform duration-200 hover:scale-105">
                        <img src="https://kingschat.online/svg/logo-icon.svg" alt="KingsChat Icon" class="h-8 w-8">
                        <span class="text-xl font-bold text-gray-900 whitespace-nowrap bg-gradient-to-r from-kc-blue to-kc-blue-dark bg-clip-text text-transparent">KingsBlast</span>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex md:items-center md:space-x-4">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 px-3 py-1.5 bg-gray-50 rounded-full border border-gray-200">
                            <img src="<?php echo isset($userData['avatar']) ? htmlspecialchars($userData['avatar']) : 'https://kingschat.online/svg/profile-placeholder.svg'; ?>"
                                 alt="Profile"
                                 class="h-7 w-7 rounded-full border border-gray-200 object-cover">
                            <span class="text-sm font-medium text-gray-700">
                                <?php echo isset($userData['name']) ? htmlspecialchars($userData['name']) : ''; ?>
                            </span>
                        </div>
                        <a href="token_config.php" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Token Config</span>
                        </a>
                        <a href="bulk_message_test.php" class="px-4 py-2 text-sm font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Bulk Test</span>
                        </a>
                        <a href="logout.php" class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-md transition-colors duration-200 shadow-sm hover:shadow flex items-center space-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="flex md:hidden">
                    <button type="button" onclick="toggleMobileMenu()"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-kc-blue focus:ring-offset-2">
                        <svg id="menuIcon" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg id="closeIcon" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobileMenu" class="hidden md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 bg-white border-t border-gray-200 shadow-lg rounded-b-lg">
                <div class="px-3 py-2">
                    <div class="flex items-center space-x-2 mb-3 p-2 bg-gray-50 rounded-lg border border-gray-200">
                        <img src="<?php echo isset($userData['avatar']) ? htmlspecialchars($userData['avatar']) : 'https://kingschat.online/svg/profile-placeholder.svg'; ?>"
                             alt="Profile"
                             class="h-10 w-10 rounded-full border border-gray-200 object-cover">
                        <div>
                            <div class="text-base font-medium text-gray-700">
                                <?php echo isset($userData['name']) ? htmlspecialchars($userData['name']) : ''; ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo isset($userEmail) ? htmlspecialchars($userEmail) : ''; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-2 mb-3">
                        <a href="token_config.php" class="flex items-center justify-center text-base font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 py-2 px-4 shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Token Config
                        </a>
                        <a href="bulk_message_test.php" class="flex items-center justify-center text-base font-medium text-white bg-kc-blue hover:bg-kc-blue-dark rounded-md transition-colors duration-200 py-2 px-4 shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            Bulk Message Test
                        </a>
                    </div>
                    <a href="logout.php" class="flex items-center justify-center text-base font-medium text-white bg-red-500 hover:bg-red-600 rounded-md transition-colors duration-200 py-2 px-4 shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const menuIcon = document.getElementById('menuIcon');
            const closeIcon = document.getElementById('closeIcon');

            if (mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.remove('hidden');
                menuIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            } else {
                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        }

        // Close mobile menu when window is resized to desktop size
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) { // md breakpoint
                const mobileMenu = document.getElementById('mobileMenu');
                const menuIcon = document.getElementById('menuIcon');
                const closeIcon = document.getElementById('closeIcon');

                mobileMenu.classList.add('hidden');
                menuIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        });
    </script>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Profile Information</h1>

            <button onclick="openMessageModal()"
                    class="flex-1 sm:flex-none px-4 sm:px-5 py-2.5 bg-kc-blue text-white rounded-lg hover:bg-kc-blue-dark flex items-center justify-center gap-2 font-medium transition-all duration-200 shadow-sm hover:shadow text-base sm:text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" />
                </svg>
                <span class="whitespace-nowrap">Send Message</span>
            </button>
        </div>

        <!-- Message Modal -->
        <div id="messageModal" class="fixed inset-0 bg-gray-800 bg-opacity-80 backdrop-blur-sm hidden overflow-y-auto h-full w-full transition-all duration-300 ease-in-out" style="z-index: 100;">
            <div class="relative sm:top-20 mx-auto p-5 sm:p-8 border-0 w-full sm:w-[90%] max-w-4xl shadow-2xl sm:rounded-2xl bg-white flex flex-col transform transition-all duration-300 min-h-screen sm:min-h-0 opacity-0 scale-95" id="modalContent">
                <div class="flex-none">
                    <div class="flex justify-between items-center mb-5 sm:mb-6 sticky top-0 bg-white z-10 py-2 border-b border-gray-100 pb-4">
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" />
                            </svg>
                            Send Message
                        </h3>
                        <button type="button" onclick="closeMessageModal()"
                                class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 -mr-2 rounded-full hover:bg-gray-100">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <form id="messageForm" class="flex flex-col h-full overflow-hidden" onsubmit="event.preventDefault(); sendMessage();">
                    <div class="flex-none mb-6">
                        <!-- Recipient Selection Tabs -->
                        <div class="flex flex-col gap-4 mb-4">
                            <label class="text-base font-semibold text-gray-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                </svg>
                                Select Recipients
                            </label>

                            <!-- Recipient Selection Tabs -->
                            <div class="flex bg-gray-100 rounded-lg p-1">
                                <button type="button" id="contactsTab" class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-all duration-200 bg-white text-kc-blue shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    My Contacts
                                </button>
                                <button type="button" id="searchTab" class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    Search Users
                                </button>
                                <button type="button" id="recentTab" class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-all duration-200 text-gray-600 hover:text-gray-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Recent
                                </button>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-2">
                                <button type="button" id="selectAllBtn"
                                        class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium border-2 border-kc-blue text-kc-blue hover:bg-kc-blue hover:text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow flex items-center justify-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Select All
                                </button>
                                <button type="button" id="unselectAllBtn"
                                        class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium border-2 border-red-500 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow flex items-center justify-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Unselect All
                                </button>
                                <button type="button" id="clearFiltersBtn"
                                        class="flex-1 sm:flex-none px-4 py-2.5 text-sm font-medium border-2 border-gray-300 text-gray-600 hover:bg-gray-100 rounded-lg transition-all duration-200 shadow-sm hover:shadow flex items-center justify-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Clear Filters
                                </button>
                            </div>
                        </div>

                        <!-- User Search Section -->
                        <div id="userSearchSection" class="mb-4 hidden">
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
                            <div id="userSearchResults" class="hidden mt-3">
                                <div class="bg-white border-2 border-gray-200 rounded-lg max-h-60 overflow-y-auto">
                                    <div id="userSearchResultsContent" class="p-4">
                                        <!-- Results will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alphabet Filter (for contacts tab) -->
                        <div id="alphabetFilter" class="mb-4">
                            <div class="text-sm font-medium text-gray-600 mb-2 flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filter by first letter:
                            </div>
                            <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto touch-pan-x touch-pan-y overscroll-contain p-2 bg-gray-50 rounded-xl border border-gray-100">
                                <button type="button" data-letter="all"
                                        class="alphabet-filter min-w-[2.5rem] px-2 py-2 text-sm font-medium text-gray-700 bg-white rounded-lg hover:bg-kc-blue hover:text-white active:bg-kc-blue active:text-white transition-all duration-200 shadow-sm">
                                    ALL
                                </button>
                                <?php
                                foreach (range('A', 'Z') as $letter) {
                                    echo '<button type="button" data-letter="' . $letter . '"
                                            class="alphabet-filter min-w-[2.5rem] px-2 py-2 text-sm font-medium text-gray-700 bg-white rounded-lg hover:bg-kc-blue hover:text-white active:bg-kc-blue active:text-white transition-all duration-200 shadow-sm">
                                            ' . $letter . '
                                        </button>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Recipients Container -->
                        <div class="border-2 border-gray-200 rounded-xl h-[40vh] sm:h-[30vh] overflow-y-auto shadow-inner-lg bg-white">
                            <!-- Contacts Tab Content -->
                            <div id="contactsContent" class="p-4 space-y-2">
                                <?php foreach ($contacts as $contact): ?>
                                    <?php
                                    $userId = isset($contact['user_jid']) ? explode('@', $contact['user_jid'])[0] : '';
                                    $name = isset($contact['name']) ? $contact['name'] : 'Unknown';
                                    $username = isset($contact['username']) ? '@' . $contact['username'] : '';
                                    $firstLetter = strtoupper(substr($name, 0, 1));
                                    ?>
                                    <div class="flex items-center p-3 hover:bg-gray-50 rounded-lg transition-colors duration-200 contact-item group" data-first-letter="<?php echo htmlspecialchars($firstLetter); ?>">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="recipients[]"
                                                   value="<?php echo htmlspecialchars($userId); ?>"
                                                   id="user_<?php echo htmlspecialchars($userId); ?>"
                                                   data-name="<?php echo htmlspecialchars($name); ?>"
                                                   data-username="<?php echo htmlspecialchars($username); ?>"
                                                   class="h-5 w-5 text-kc-blue focus:ring-2 focus:ring-kc-blue focus:ring-offset-2 rounded transition-all duration-200">
                                            <label for="user_<?php echo htmlspecialchars($userId); ?>"
                                                   class="ml-3 block text-base sm:text-sm font-medium text-gray-700 cursor-pointer hover:text-kc-blue transition-colors duration-200 group-hover:text-kc-blue">
                                                <?php echo htmlspecialchars($name); ?>
                                                <?php if ($username): ?>
                                                    <span class="text-gray-500 font-normal"><?php echo htmlspecialchars($username); ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Search Results Content -->
                            <div id="searchContent" class="p-4 space-y-2 hidden">
                                <div class="text-center py-8 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <p>Search for users to add them as recipients</p>
                                </div>
                            </div>

                            <!-- Recent Recipients Content -->
                            <div id="recentContent" class="p-4 space-y-2 hidden">
                                <div id="recentRecipientsList">
                                    <div class="text-center py-8 text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <p>No recent recipients yet</p>
                                        <p class="text-sm mt-1">Users you've messaged recently will appear here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-gray-600 font-medium flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                            Selected: <span id="selectedCount" class="text-kc-blue font-bold">0</span> contacts
                        </p>
                    </div>

                    <div class="flex-grow flex flex-col min-h-0 overflow-hidden">
                        <div class="flex items-center justify-between mb-2">
                            <label for="message" class="block text-base font-semibold text-gray-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" />
                                    <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 012 2V9a2 2 0 00-2-2h-1z" />
                                </svg>
                                Message Content
                            </label>
                            <div class="flex items-center gap-2">
                                <!-- Draft Status -->
                                <span id="draftStatus" class="text-xs text-gray-400 hidden">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    Draft saved
                                </span>
                                <!-- Character Counter -->
                                <span id="charCounter" class="text-xs text-gray-500">0 characters</span>
                            </div>
                        </div>

                        <!-- Message Templates -->
                        <div class="mb-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-medium text-gray-600">Quick Templates:</span>
                                <button type="button" id="toggleTemplates" class="text-xs text-kc-blue hover:text-kc-blue-dark transition-colors">
                                    Show Templates
                                </button>
                            </div>
                            <div id="messageTemplates" class="hidden flex flex-wrap gap-2">
                                <button type="button" class="template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Hello {name}! How are you doing today?">
                                    👋 Greeting
                                </button>
                                <button type="button" class="template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Thank you {name} for your time and assistance!">
                                    🙏 Thank You
                                </button>
                                <button type="button" class="template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Hi {name}, I hope you're having a great day!">
                                    😊 Friendly
                                </button>
                                <button type="button" class="template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Dear {name}, I wanted to follow up on our previous conversation.">
                                    📝 Follow-up
                                </button>
                                <button type="button" class="template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Good morning {name}! I hope you have a wonderful day ahead.">
                                    🌅 Good Morning
                                </button>
                            </div>
                        </div>

                        <div class="relative flex-grow flex flex-col min-h-0">
                            <div class="relative flex-grow">
                                <textarea name="message" id="message" required
                                        class="block w-full h-full border-2 border-gray-200 rounded-xl shadow-inner-lg focus:ring-2 focus:ring-kc-blue focus:border-kc-blue text-base resize-none p-4 pr-20 transition-all duration-200"
                                        style="min-height: 140px;"
                                        placeholder="Type your message here. Use {name} to automatically insert recipient's name."></textarea>

                                <!-- Message Tools -->
                                <div class="absolute top-3 right-3 flex flex-col gap-2">
                                    <!-- Emoji Picker Button -->
                                    <button type="button" id="emojiBtn" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all duration-200" title="Add Emoji">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Bottom Info -->
                                <div class="absolute bottom-3 left-3 text-xs text-gray-500 bg-white px-2 py-1 rounded-md border border-gray-200 flex items-center gap-1 shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Use {name} to personalize
                                </div>
                            </div>
                        </div>

                        <!-- Emoji Picker -->
                        <div id="emojiPicker" class="hidden absolute bottom-16 right-3 bg-white border-2 border-gray-200 rounded-lg shadow-lg p-3 z-50" style="width: 280px;">
                            <div class="grid grid-cols-8 gap-1 max-h-32 overflow-y-auto">
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😀">😀</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😃">😃</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😄">😄</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😁">😁</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😊">😊</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😍">😍</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="🥰">🥰</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😘">😘</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😎">😎</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="🤔">🤔</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😢">😢</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😭">😭</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😂">😂</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="🤣">🤣</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😉">😉</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="😌">😌</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="👍">👍</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="👎">👎</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="👏">👏</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="🙏">🙏</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="❤️">❤️</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="💕">💕</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="🔥">🔥</button>
                                <button type="button" class="emoji-btn p-1 hover:bg-gray-100 rounded text-lg" data-emoji="⭐">⭐</button>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Send Button Section -->
                    <div class="mt-4 flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <button type="submit" id="sendButton"
                                    class="w-full px-6 py-3 bg-kc-blue text-white rounded-lg hover:bg-kc-blue-dark font-medium transition-all duration-200 shadow-sm hover:shadow flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                <span id="sendButtonText">Send Message</span>
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <!-- Save Draft Button -->
                            <button type="button" id="saveDraftBtn"
                                    class="px-4 py-3 border-2 border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-all duration-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="hidden sm:inline">Save Draft</span>
                            </button>
                            <!-- Clear Button -->
                            <button type="button" id="clearMessageBtn"
                                    class="px-4 py-3 border-2 border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-all duration-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span class="hidden sm:inline">Clear</span>
                            </button>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div id="sendProgress" class="hidden mt-6 border-t border-gray-200 pt-5">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                Sending Progress
                            </span>
                            <span id="progressPercentage" class="text-sm font-bold text-kc-blue">0%</span>
                        </div>
                        <div class="bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div id="progressBar" class="bg-kc-blue h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <div class="mt-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-1 text-sm text-gray-600">
                            <span id="sentCount" class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                0/0 messages sent
                            </span>
                            <span id="estimatedTime" class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                Calculating...
                            </span>
                        </div>
                        <div id="sendStatus" class="mt-3 text-sm p-3 bg-gray-50 rounded-lg border border-gray-200"></div>
                    </div>

                    <div class="flex-none mt-6 flex flex-col sm:flex-row justify-end gap-3 pt-5 border-t border-gray-200">
                        <button type="button" onclick="closeMessageModal()"
                                class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-all duration-200 shadow-sm hover:shadow text-base sm:text-sm flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancel
                        </button>
                        <button type="submit" id="sendButton"
                                class="w-full sm:w-auto px-5 py-3 sm:py-2.5 bg-kc-blue text-white rounded-lg hover:bg-kc-blue-dark font-medium transition-all duration-200 shadow-sm hover:shadow text-base sm:text-sm flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            Send to Selected
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Alert -->
        <?php if ($success): ?>
            <div id="successAlert" class="mb-6 p-4 bg-green-50 border-2 border-green-200 text-green-700 rounded-xl transition-all duration-500 ease-in-out opacity-100 shadow-sm">
                <div class="flex justify-between items-center">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">
                                <?php echo htmlspecialchars($success); ?>
                            </p>
                        </div>
                    </div>
                    <button onclick="hideSuccessAlert()" class="text-green-500 hover:text-green-600 focus:outline-none">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="bg-white rounded-xl shadow-lg border-0 overflow-hidden mb-8 transition-all duration-300 hover:shadow-xl">
            <div class="px-8 py-6 bg-gradient-to-r from-kc-blue to-kc-blue-dark">
                <h1 class="text-2xl font-bold text-white">Profile Information</h1>
            </div>

            <div class="p-8">
                <?php if ($userData): ?>
                    <div class="flex flex-col md:flex-row md:items-start space-y-6 md:space-y-0 md:space-x-8">
                        <?php if (isset($userData['avatar_url'])): ?>
                            <div class="flex-shrink-0">
                                <img src="<?php echo htmlspecialchars($userData['avatar_url']); ?>"
                                     alt="Profile Picture"
                                     class="w-32 h-32 rounded-full object-cover ring-4 ring-kc-blue/20 shadow-lg transition-transform duration-300 hover:scale-105"
                                     onerror="this.src='assets/default-avatar.png'">
                            </div>
                        <?php endif; ?>

                        <div class="flex-1">
                            <div class="grid gap-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="bg-gray-50 p-5 rounded-xl shadow-inner-lg border border-gray-100 transition-all duration-200 hover:shadow-md">
                                        <div class="text-sm font-semibold text-gray-500 mb-1">Name</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($userData['name']); ?></div>
                                    </div>

                                    <div class="bg-gray-50 p-5 rounded-xl shadow-inner-lg border border-gray-100 transition-all duration-200 hover:shadow-md">
                                        <div class="text-sm font-semibold text-gray-500 mb-1">Username</div>
                                        <div class="text-gray-900 font-medium">@<?php echo htmlspecialchars($userData['username']); ?></div>
                                    </div>

                                    <div class="bg-gray-50 p-5 rounded-xl shadow-inner-lg border border-gray-100 transition-all duration-200 hover:shadow-md">
                                        <div class="text-sm font-semibold text-gray-500 mb-1">Email</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($userEmail); ?></div>
                                    </div>

                                    <div class="bg-gray-50 p-5 rounded-xl shadow-inner-lg border border-gray-100 transition-all duration-200 hover:shadow-md">
                                        <div class="text-sm font-semibold text-gray-500 mb-1">User ID</div>
                                        <div class="text-gray-900 font-mono text-sm"><?php echo htmlspecialchars($userData['user_id']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500">User profile information not available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Send Message Modal -->
        <div id="quickSendModal" class="fixed inset-0 bg-gray-800 bg-opacity-80 backdrop-blur-sm hidden overflow-y-auto h-full w-full transition-all duration-300 ease-in-out" style="z-index: 110;">
            <div class="relative sm:top-20 mx-auto p-5 sm:p-8 border-0 w-full sm:w-[90%] max-w-lg shadow-2xl sm:rounded-2xl bg-white flex flex-col transform transition-all duration-300 min-h-screen sm:min-h-0 opacity-0 scale-95" id="quickSendModalContent">
                <div class="flex-none">
                    <div class="flex justify-between items-center mb-5 sm:mb-6 sticky top-0 bg-white z-10 py-2 border-b border-gray-100 pb-4">
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" />
                            </svg>
                            Quick Send Message
                        </h3>
                        <button type="button" onclick="closeQuickSendModal()"
                                class="text-gray-400 hover:text-gray-600 transition-colors duration-200 p-2 -mr-2 rounded-full hover:bg-gray-100">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <form id="quickSendForm" class="flex flex-col h-full overflow-hidden" onsubmit="event.preventDefault(); sendQuickMessage();">
                    <!-- Recipient Info -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-center">
                            <div id="quickRecipientAvatar" class="flex-shrink-0 h-10 w-10 rounded-full bg-kc-blue text-white flex items-center justify-center font-bold text-sm">
                                U
                            </div>
                            <div class="ml-3">
                                <p id="quickRecipientName" class="text-sm font-medium text-gray-900">User Name</p>
                                <p id="quickRecipientUsername" class="text-xs text-gray-500">@username</p>
                                <p id="quickRecipientId" class="text-xs text-gray-400 font-mono">user_id</p>
                            </div>
                        </div>
                    </div>

                    <!-- Message Templates for Quick Send -->
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-medium text-gray-600">Quick Templates:</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="quick-template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Hello {name}! How are you doing today?">
                                👋 Hello
                            </button>
                            <button type="button" class="quick-template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Thank you {name} for your time and assistance!">
                                🙏 Thank You
                            </button>
                            <button type="button" class="quick-template-btn px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" data-template="Hi {name}! I hope you're having a great day!">
                                😊 Friendly
                            </button>
                        </div>
                    </div>

                    <!-- Message Content -->
                    <div class="flex-grow flex flex-col min-h-0 overflow-hidden">
                        <label for="quickMessage" class="block text-base font-semibold text-gray-800 mb-2 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-kc-blue" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z" />
                            </svg>
                            Message
                        </label>
                        <div class="relative flex-grow">
                            <textarea name="quickMessage" id="quickMessage" required
                                    class="block w-full h-full border-2 border-gray-200 rounded-xl shadow-inner-lg focus:ring-2 focus:ring-kc-blue focus:border-kc-blue text-base resize-none p-4 transition-all duration-200"
                                    style="min-height: 120px;"
                                    placeholder="Type your message here..."></textarea>
                            <div class="absolute bottom-3 right-3 text-xs text-gray-500">
                                <span id="quickCharCounter">0 characters</span>
                            </div>
                        </div>
                    </div>

                    <!-- Send Button -->
                    <div class="mt-4 flex gap-3">
                        <button type="submit" id="quickSendButton"
                                class="flex-1 px-6 py-3 bg-kc-blue text-white rounded-lg hover:bg-kc-blue-dark font-medium transition-all duration-200 shadow-sm hover:shadow flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <span id="quickSendButtonText">Send Message</span>
                        </button>
                        <button type="button" onclick="closeQuickSendModal()"
                                class="px-4 py-3 border-2 border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-all duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Users Section -->
        <div class="bg-white rounded-xl shadow-lg border-0 overflow-hidden mb-8 transition-all duration-300 hover:shadow-xl">
            <div class="px-8 py-6 bg-gradient-to-r from-purple-600 to-purple-700">
                <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Search KingsChat Users
                </h2>
                <p class="text-purple-100 mt-2">Find and connect with KingsChat users by username</p>
            </div>

            <div class="p-8">
                <div class="space-y-6">
                    <!-- Search Form -->
                    <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                        <form id="userSearchForm" class="space-y-4">
                            <div class="flex gap-4">
                                <div class="flex-1">
                                    <label for="searchQuery" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                                    <input type="text" id="searchQuery" name="searchQuery"
                                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                                           placeholder="Enter username (e.g., john, mary, test)">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit"
                                            class="bg-purple-600 text-white py-3 px-8 rounded-lg hover:bg-purple-700 font-medium transition-all duration-200 shadow-sm hover:shadow flex items-center justify-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Search
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Search Status -->
                    <div id="searchStatus" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="searchStatusText">Searching...</span>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div id="searchResults" class="hidden">
                        <div class="bg-white border-2 border-gray-200 rounded-xl p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span id="searchResultsTitle">Search Results</span>
                            </h3>
                            <div id="searchResultsContent">
                                <!-- Results will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Auto-hide success alert after 5 seconds
        if (document.getElementById('successAlert')) {
            setTimeout(hideSuccessAlert, 5000);
        }

        function hideSuccessAlert() {
            const alert = document.getElementById('successAlert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            }
        }

        // Enhanced Message Modal Functions
        let currentTab = 'contacts';
        let searchTimeout;
        let draftSaveTimeout;
        let recentRecipients = JSON.parse(localStorage.getItem('recentRecipients') || '[]');

        function openMessageModal() {
            const modal = document.getElementById('messageModal');
            const modalContent = document.getElementById('modalContent');

            modal.classList.remove('hidden');
            // Delay the animation slightly for a smoother appearance
            setTimeout(() => {
                modalContent.classList.remove('opacity-0', 'scale-95');
                modalContent.classList.add('opacity-100', 'scale-100');
            }, 50);

            // Load draft if exists
            loadDraft();

            // Initialize tab functionality
            initializeTabFunctionality();

            // Initialize message enhancements
            initializeMessageEnhancements();
        }

        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            const modalContent = document.getElementById('modalContent');

            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');

            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('messageForm').reset();
                clearDraft();
                hideEmojiPicker();
            }, 300);
        }

        // Quick Send Modal Functions
        function openQuickSendModal(userId, userName, userUsername) {
            const modal = document.getElementById('quickSendModal');
            const modalContent = document.getElementById('quickSendModalContent');

            // Set recipient info
            document.getElementById('quickRecipientName').textContent = userName;
            document.getElementById('quickRecipientUsername').textContent = userUsername || '';
            document.getElementById('quickRecipientId').textContent = userId;
            document.getElementById('quickRecipientAvatar').textContent = userName.charAt(0).toUpperCase();

            // Store recipient data
            modal.dataset.recipientId = userId;
            modal.dataset.recipientName = userName;

            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('opacity-0', 'scale-95');
                modalContent.classList.add('opacity-100', 'scale-100');
            }, 50);

            // Initialize quick message enhancements
            initializeQuickMessageEnhancements();
        }

        function closeQuickSendModal() {
            const modal = document.getElementById('quickSendModal');
            const modalContent = document.getElementById('quickSendModalContent');

            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');

            setTimeout(() => {
                modal.classList.add('hidden');
                document.getElementById('quickSendForm').reset();
            }, 300);
        }

        // Tab Functionality
        function initializeTabFunctionality() {
            const contactsTab = document.getElementById('contactsTab');
            const searchTab = document.getElementById('searchTab');
            const recentTab = document.getElementById('recentTab');

            const contactsContent = document.getElementById('contactsContent');
            const searchContent = document.getElementById('searchContent');
            const recentContent = document.getElementById('recentContent');

            const userSearchSection = document.getElementById('userSearchSection');
            const alphabetFilter = document.getElementById('alphabetFilter');

            // Tab click handlers
            contactsTab.addEventListener('click', () => switchTab('contacts'));
            searchTab.addEventListener('click', () => switchTab('search'));
            recentTab.addEventListener('click', () => switchTab('recent'));

            function switchTab(tab) {
                currentTab = tab;

                // Update tab styles
                [contactsTab, searchTab, recentTab].forEach(t => {
                    t.classList.remove('bg-white', 'text-kc-blue', 'shadow-sm');
                    t.classList.add('text-gray-600');
                });

                // Hide all content
                [contactsContent, searchContent, recentContent].forEach(c => c.classList.add('hidden'));
                userSearchSection.classList.add('hidden');
                alphabetFilter.classList.add('hidden');

                // Show selected tab content
                switch(tab) {
                    case 'contacts':
                        contactsTab.classList.add('bg-white', 'text-kc-blue', 'shadow-sm');
                        contactsTab.classList.remove('text-gray-600');
                        contactsContent.classList.remove('hidden');
                        alphabetFilter.classList.remove('hidden');
                        break;
                    case 'search':
                        searchTab.classList.add('bg-white', 'text-kc-blue', 'shadow-sm');
                        searchTab.classList.remove('text-gray-600');
                        searchContent.classList.remove('hidden');
                        userSearchSection.classList.remove('hidden');
                        break;
                    case 'recent':
                        recentTab.classList.add('bg-white', 'text-kc-blue', 'shadow-sm');
                        recentTab.classList.remove('text-gray-600');
                        recentContent.classList.remove('hidden');
                        loadRecentRecipients();
                        break;
                }
            }
        }

        // Message Enhancement Functions
        function initializeMessageEnhancements() {
            const messageTextarea = document.getElementById('message');
            const charCounter = document.getElementById('charCounter');
            const emojiBtn = document.getElementById('emojiBtn');
            const emojiPicker = document.getElementById('emojiPicker');
            const toggleTemplates = document.getElementById('toggleTemplates');
            const messageTemplates = document.getElementById('messageTemplates');
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const clearMessageBtn = document.getElementById('clearMessageBtn');

            // Character counter
            messageTextarea.addEventListener('input', function() {
                const length = this.value.length;
                charCounter.textContent = `${length} character${length !== 1 ? 's' : ''}`;

                // Auto-save draft
                clearTimeout(draftSaveTimeout);
                draftSaveTimeout = setTimeout(() => {
                    saveDraft();
                }, 2000);
            });

            // Emoji picker
            emojiBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleEmojiPicker();
            });

            // Template toggle
            toggleTemplates.addEventListener('click', function() {
                const isHidden = messageTemplates.classList.contains('hidden');
                messageTemplates.classList.toggle('hidden');
                this.textContent = isHidden ? 'Hide Templates' : 'Show Templates';
            });

            // Template buttons
            document.querySelectorAll('.template-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    messageTextarea.value = this.dataset.template;
                    messageTextarea.dispatchEvent(new Event('input'));
                    messageTextarea.focus();
                });
            });

            // Save draft button
            saveDraftBtn.addEventListener('click', function() {
                saveDraft();
                showDraftStatus();
            });

            // Clear message button
            clearMessageBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to clear the message?')) {
                    messageTextarea.value = '';
                    messageTextarea.dispatchEvent(new Event('input'));
                    clearDraft();
                }
            });

            // Emoji picker buttons
            document.querySelectorAll('.emoji-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    insertEmoji(this.dataset.emoji);
                });
            });

            // User search functionality
            const userSearchInput = document.getElementById('userSearchInput');
            if (userSearchInput) {
                userSearchInput.addEventListener('input', function() {
                    const query = this.value.trim();

                    clearTimeout(searchTimeout);

                    if (query.length < 2) {
                        document.getElementById('userSearchResults').classList.add('hidden');
                        return;
                    }

                    searchTimeout = setTimeout(() => {
                        performUserSearch(query);
                    }, 500);
                });
            }
        }

        function initializeQuickMessageEnhancements() {
            const quickMessage = document.getElementById('quickMessage');
            const quickCharCounter = document.getElementById('quickCharCounter');

            // Character counter for quick message
            quickMessage.addEventListener('input', function() {
                const length = this.value.length;
                quickCharCounter.textContent = `${length} character${length !== 1 ? 's' : ''}`;
            });

            // Quick template buttons
            document.querySelectorAll('.quick-template-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    quickMessage.value = this.dataset.template;
                    quickMessage.dispatchEvent(new Event('input'));
                    quickMessage.focus();
                });
            });
        }

        // Enhanced checkbox selection handling
        const checkboxes = document.querySelectorAll('input[name="recipients[]"]');
        const selectedCountSpan = document.getElementById('selectedCount');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const unselectAllBtn = document.getElementById('unselectAllBtn');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const alphabetFilters = document.querySelectorAll('.alphabet-filter');
        const contactItems = document.querySelectorAll('.contact-item');

        function updateSelectedCount() {
            const selectedCount = document.querySelectorAll('input[name="recipients[]"]:checked').length;
            selectedCountSpan.textContent = selectedCount;
        }

        function getVisibleContacts() {
            const activeFilters = Array.from(alphabetFilters).filter(btn =>
                btn.classList.contains('bg-kc-blue')
            ).map(btn => btn.dataset.letter);

            if (activeFilters.includes('all')) {
                return Array.from(contactItems);
            }

            return Array.from(contactItems).filter(item =>
                activeFilters.includes(item.dataset.firstLetter)
            );
        }

        function clearAllFilters() {
            alphabetFilters.forEach(btn => {
                btn.classList.remove('bg-kc-blue', 'text-white');
                btn.classList.add('text-gray-700', 'bg-white');
            });

            // Set 'ALL' filter as active
            const allFilter = document.querySelector('[data-letter="all"]');
            allFilter.classList.add('bg-kc-blue', 'text-white');
            allFilter.classList.remove('text-gray-700', 'bg-white');

            // Show all contacts
            contactItems.forEach(item => item.style.display = 'flex');
        }

        // Select All button behavior
        selectAllBtn.addEventListener('click', function() {
            const visibleContacts = getVisibleContacts();
            visibleContacts.forEach(contact => {
                const checkbox = contact.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
            });
            updateSelectedCount();
        });

        // Unselect All button behavior
        unselectAllBtn.addEventListener('click', function() {
            const visibleContacts = getVisibleContacts();
            visibleContacts.forEach(contact => {
                const checkbox = contact.querySelector('input[type="checkbox"]');
                checkbox.checked = false;
            });
            updateSelectedCount();
        });

        // Clear Filters button behavior
        clearFiltersBtn.addEventListener('click', clearAllFilters);

        // Checkbox change handler
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // Alphabet Filter functionality
        alphabetFilters.forEach(filter => {
            filter.addEventListener('click', function(e) {
                const letter = this.dataset.letter;

                // Remove active class from all filters
                alphabetFilters.forEach(btn => {
                    btn.classList.remove('bg-kc-blue', 'text-white');
                    btn.classList.add('text-gray-700', 'bg-white');
                });

                // Add active class to clicked filter
                this.classList.add('bg-kc-blue', 'text-white');
                this.classList.remove('text-gray-700', 'bg-white');

                // Show/hide contacts based on filter
                if (letter === 'all') {
                    contactItems.forEach(item => item.style.display = 'flex');
                } else {
                    contactItems.forEach(item => {
                        if (item.dataset.firstLetter === letter) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }

                updateSelectedCount();
            });
        });

        // Set 'ALL' filter as active by default
        document.addEventListener('DOMContentLoaded', function() {
            const allFilter = document.querySelector('[data-letter="all"]');
            if (allFilter) {
                allFilter.classList.add('bg-kc-blue', 'text-white');
                allFilter.classList.remove('text-gray-700', 'bg-white');
            }
        });

        // Update the sendMessage function to handle progress
        async function sendMessage() {
            const form = document.getElementById('messageForm');
            const selectedRecipients = Array.from(form.querySelectorAll('input[name="recipients[]"]:checked'))
                                          .map(checkbox => ({
                                              id: checkbox.value,
                                              name: checkbox.dataset.name || checkbox.getAttribute('data-name') || 'Unknown User',
                                              username: checkbox.dataset.username || checkbox.getAttribute('data-username') || ''
                                          }));

            if (selectedRecipients.length === 0) {
                alert('Please select at least one recipient');
                return;
            }

            const sendButton = document.getElementById('sendButton');
            const messageTemplate = form.message.value;
            const progressSection = document.getElementById('sendProgress');
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = document.getElementById('progressPercentage');
            const sentCount = document.getElementById('sentCount');
            const estimatedTime = document.getElementById('estimatedTime');
            const sendStatus = document.getElementById('sendStatus');

            // Show progress section
            progressSection.classList.remove('hidden');
            sendButton.disabled = true;

            let successCount = 0;
            const totalMessages = selectedRecipients.length;
            const startTime = Date.now();

            // Initialize progress display
            sentCount.textContent = `0/${totalMessages} recipients`;

            // Send messages to all recipients
            for (let i = 0; i < selectedRecipients.length; i++) {
                const recipient = selectedRecipients[i];
                const personalizedMessage = messageTemplate.replace(/{name}/g, recipient.name);

                try {
                    // First, refresh the token before sending each message
                    const refreshFormData = new FormData();
                    refreshFormData.append('action', 'refresh_token');

                    try {
                        await fetch('token_refresh_ajax.php', {
                            method: 'POST',
                            body: refreshFormData
                        });

                        // Add a status message about token refresh
                        const refreshMsg = document.createElement('div');
                        refreshMsg.className = 'text-blue-600 mb-1';
                        refreshMsg.textContent = `ℹ️ Token refreshed before sending to ${recipient.name}`;
                        sendStatus.insertBefore(refreshMsg, sendStatus.firstChild);
                    } catch (refreshError) {
                        console.error('Token refresh error:', refreshError);
                        // Continue anyway, the message might still go through
                    }

                    // Now send the message with the refreshed token
                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('recipientId', recipient.id);
                    formData.append('message', personalizedMessage);
                    formData.append('recipientName', recipient.name);

                    const response = await fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        successCount++;

                        // Update progress
                        const progress = (successCount / totalMessages) * 100;
                        progressBar.style.width = `${progress}%`;
                        progressPercentage.textContent = `${Math.round(progress)}%`;
                        sentCount.textContent = `${successCount}/${totalMessages} recipients`;

                        // Calculate and update estimated time
                        const elapsedTime = (Date.now() - startTime) / 1000;
                        const avgTimePerMessage = elapsedTime / successCount;
                        const remainingMessages = totalMessages - successCount;
                        const estimatedSecondsLeft = Math.round(avgTimePerMessage * remainingMessages);

                        if (estimatedSecondsLeft > 0) {
                            const minutes = Math.floor(estimatedSecondsLeft / 60);
                            const seconds = estimatedSecondsLeft % 60;
                            estimatedTime.textContent = `Estimated time remaining: ${minutes}m ${seconds}s`;
                        }

                        // Update status
                        const statusMsg = document.createElement('div');
                        statusMsg.className = 'text-green-600 mb-1';
                        statusMsg.textContent = `✓ Message sent to ${recipient.name}`;
                        sendStatus.insertBefore(statusMsg, sendStatus.firstChild);
                    } else {
                        throw new Error(result.error || 'Failed to send message');
                    }
                } catch (error) {
                    console.error(`Error sending message to ${recipient.name}:`, error);

                    // Add error status
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'text-red-600 mb-1';
                    errorMsg.textContent = `✗ Failed to send message to ${recipient.name}`;
                    sendStatus.insertBefore(errorMsg, sendStatus.firstChild);
                }

                // Small delay between messages
                if (i < selectedRecipients.length - 1) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
            }

            // Update final status
            sendStatus.innerHTML = ''; // Clear any previous status messages
            const finalMsg = document.createElement('div');
            finalMsg.className = 'font-medium mt-2';
            finalMsg.textContent = `Completed: Message sent to ${successCount} of ${totalMessages} recipients`;
            sendStatus.appendChild(finalMsg);

            // Enable send button
            sendButton.disabled = false;

            // Show completion message
            estimatedTime.textContent = 'Complete';

            // Uncheck all selected recipients
            selectedRecipients.forEach(recipient => {
                const checkbox = document.querySelector(`input[value="${recipient.id}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                }
            });
            updateSelectedCount();
        }

        // User Search Functionality
        document.getElementById('userSearchForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const searchQuery = document.getElementById('searchQuery').value.trim();

            if (!searchQuery) {
                alert('Please enter a username to search');
                return;
            }

            await performUserSearch(searchQuery);
        });

        async function performUserSearch(query) {
            const statusDiv = document.getElementById('searchStatus');
            const resultsDiv = document.getElementById('searchResults');
            const statusText = document.getElementById('searchStatusText');

            // Show loading status
            statusDiv.classList.remove('hidden');
            resultsDiv.classList.add('hidden');
            statusText.textContent = `Searching for users with username "${query}"...`;

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

                // Hide loading status
                statusDiv.classList.add('hidden');

                if (result.success) {
                    displaySearchResults(result.data, query);
                } else {
                    displaySearchError(result.error || 'Search failed');
                }
            } catch (error) {
                console.error('Search error:', error);
                statusDiv.classList.add('hidden');
                displaySearchError('Network error occurred during search');
            }
        }

        function displaySearchResults(results, query) {
            const resultsDiv = document.getElementById('searchResults');
            const contentDiv = document.getElementById('searchResultsContent');
            const titleSpan = document.getElementById('searchResultsTitle');

            if (!results || results.length === 0) {
                titleSpan.textContent = 'No Results Found';
                contentDiv.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500">No users found with username "${query}"</p>
                        <p class="text-gray-400 text-sm mt-2">Try searching for a different username</p>
                    </div>
                `;
            } else {
                titleSpan.textContent = `Found ${results.length} User${results.length > 1 ? 's' : ''}`;

                let html = '<div class="space-y-4">';

                results.forEach(user => {
                    // Safely handle user data with defaults
                    const userName = user.name || 'Unknown';
                    const userUsername = user.username || 'unknown';
                    const userId = user.user_id || 'N/A';
                    const avatarUrl = user.avatar_url || null;
                    const userBio = user.bio || '';

                    html += `
                        <div class="bg-gray-50 p-5 rounded-lg border border-gray-200 hover:border-purple-300 transition-all duration-200 hover:shadow-md">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    ${avatarUrl ? `<img src="${avatarUrl}" alt="Avatar" class="w-14 h-14 rounded-full object-cover ring-2 ring-purple-100">` : `
                                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center ring-2 ring-purple-100">
                                            <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                    `}
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900 text-lg">${userName}</h4>
                                        <p class="text-purple-600 font-medium">@${userUsername}</p>
                                        ${userBio ? `<p class="text-gray-500 text-sm mt-1 line-clamp-2">${userBio}</p>` : ''}
                                        <p class="text-xs text-gray-400 mt-1">ID: ${userId}</p>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <button onclick="sendMessageToUser('${userId}', '${userName.replace(/'/g, "\\'")}')"
                                            class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 text-sm font-medium transition-colors shadow-sm hover:shadow">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                        Message
                                    </button>
                                    <button onclick="addToContacts('${userId}', '${userName.replace(/'/g, "\\'")}')"
                                            class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 text-sm font-medium transition-colors border border-gray-300">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                contentDiv.innerHTML = html;
            }

            resultsDiv.classList.remove('hidden');
        }

        function displaySearchError(error) {
            const resultsDiv = document.getElementById('searchResults');
            const contentDiv = document.getElementById('searchResultsContent');

            contentDiv.innerHTML = `
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>${error}</span>
                    </div>
                </div>
            `;

            resultsDiv.classList.remove('hidden');
        }



        // Enhanced Send Message to Specific User (replaces prompt with modal)
        function sendMessageToUser(userId, userName, userUsername) {
            openQuickSendModal(userId, userName, userUsername);
        }

        // Quick Send Message Function
        async function sendQuickMessage() {
            const modal = document.getElementById('quickSendModal');
            const form = document.getElementById('quickSendForm');
            const sendButton = document.getElementById('quickSendButton');
            const sendButtonText = document.getElementById('quickSendButtonText');

            const recipientId = modal.dataset.recipientId;
            const recipientName = modal.dataset.recipientName;
            let message = document.getElementById('quickMessage').value.trim();

            if (!message) {
                showToast('Please enter a message', 'error');
                return;
            }

            // Replace {name} placeholder with recipient's actual display name
            const personalizedMessage = message.replace(/{name}/g, recipientName);

            // Disable send button
            sendButton.disabled = true;
            sendButtonText.textContent = 'Sending...';

            try {
                // Refresh token first
                await refreshTokenIfNeeded();

                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('recipientId', recipientId);
                formData.append('message', personalizedMessage);
                formData.append('recipientName', recipientName);

                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showToast(`Message sent successfully to ${recipientName}!`, 'success');
                    addToRecentRecipients(recipientId, recipientName);
                    closeQuickSendModal();
                } else {
                    throw new Error(result.error || 'Failed to send message');
                }
            } catch (error) {
                console.error('Send error:', error);
                showToast(`Failed to send message: ${error.message}`, 'error');
            } finally {
                // Re-enable send button
                sendButton.disabled = false;
                sendButtonText.textContent = 'Send Message';
            }
        }

        // Draft Management Functions
        function saveDraft() {
            const message = document.getElementById('message').value;
            const selectedRecipients = Array.from(document.querySelectorAll('input[name="recipients[]"]:checked'))
                .map(cb => ({
                    id: cb.value,
                    name: cb.dataset.name || cb.getAttribute('data-name') || 'Unknown User',
                    username: cb.dataset.username || cb.getAttribute('data-username') || ''
                }));

            const draft = {
                message: message,
                recipients: selectedRecipients,
                timestamp: Date.now()
            };

            localStorage.setItem('messageDraft', JSON.stringify(draft));
        }

        function loadDraft() {
            const draft = localStorage.getItem('messageDraft');
            if (draft) {
                try {
                    const draftData = JSON.parse(draft);
                    document.getElementById('message').value = draftData.message || '';

                    // Restore selected recipients
                    if (draftData.recipients) {
                        // Handle both old format (array of IDs) and new format (array of objects)
                        draftData.recipients.forEach(recipient => {
                            const recipientId = typeof recipient === 'string' ? recipient : recipient.id;
                            const checkbox = document.querySelector(`input[value="${recipientId}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                        updateSelectedCount();
                    }

                    // Update character counter
                    const messageTextarea = document.getElementById('message');
                    messageTextarea.dispatchEvent(new Event('input'));

                    if (draftData.message) {
                        showDraftStatus();
                    }
                } catch (e) {
                    console.error('Error loading draft:', e);
                }
            }
        }

        function clearDraft() {
            localStorage.removeItem('messageDraft');
            hideDraftStatus();
        }

        function showDraftStatus() {
            const draftStatus = document.getElementById('draftStatus');
            draftStatus.classList.remove('hidden');
            setTimeout(() => {
                draftStatus.classList.add('hidden');
            }, 3000);
        }

        function hideDraftStatus() {
            const draftStatus = document.getElementById('draftStatus');
            draftStatus.classList.add('hidden');
        }

        // Emoji Picker Functions
        function toggleEmojiPicker() {
            const emojiPicker = document.getElementById('emojiPicker');
            emojiPicker.classList.toggle('hidden');
        }

        function hideEmojiPicker() {
            const emojiPicker = document.getElementById('emojiPicker');
            emojiPicker.classList.add('hidden');
        }

        function insertEmoji(emoji) {
            const messageTextarea = document.getElementById('message');
            const cursorPos = messageTextarea.selectionStart;
            const textBefore = messageTextarea.value.substring(0, cursorPos);
            const textAfter = messageTextarea.value.substring(cursorPos);

            messageTextarea.value = textBefore + emoji + textAfter;
            messageTextarea.focus();
            messageTextarea.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);

            // Trigger input event for character counter
            messageTextarea.dispatchEvent(new Event('input'));

            hideEmojiPicker();
        }

        // User Search Functions
        async function performUserSearch(query) {
            const searchResultsContent = document.getElementById('userSearchResultsContent');
            const searchResults = document.getElementById('userSearchResults');
            const searchContent = document.getElementById('searchContent');

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
                    displayUserSearchResults(result.data, query);
                } else {
                    displayNoUserSearchResults(query);
                }
            } catch (error) {
                console.error('Search error:', error);
                displayUserSearchError('Network error occurred during search');
            }
        }

        function displayUserSearchResults(results, query) {
            const searchContent = document.getElementById('searchContent');
            let html = '<div class="space-y-2">';
            html += `<div class="text-sm font-medium text-gray-700 mb-3">Found ${results.length} user${results.length > 1 ? 's' : ''} for "${query}"</div>`;

            results.forEach(user => {
                const userId = user.user_id || user.id || '';
                const name = user.name || user.display_name || 'Unknown';
                const username = user.username ? '@' + user.username : '';

                html += `
                    <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors duration-200 border border-gray-100">
                        <div class="flex items-center">
                            <input type="checkbox" name="recipients[]"
                                   value="${escapeHtml(userId)}"
                                   id="search_user_${escapeHtml(userId)}"
                                   data-name="${escapeHtml(name)}"
                                   data-username="${escapeHtml(username)}"
                                   class="h-5 w-5 text-kc-blue focus:ring-2 focus:ring-kc-blue focus:ring-offset-2 rounded transition-all duration-200">
                            <label for="search_user_${escapeHtml(userId)}" class="ml-3 flex items-center cursor-pointer">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-kc-blue text-white flex items-center justify-center font-bold text-sm">
                                    ${name.charAt(0).toUpperCase()}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">${escapeHtml(name)}</p>
                                    ${username ? `<p class="text-xs text-gray-500">${escapeHtml(username)}</p>` : ''}
                                    <p class="text-xs text-gray-400 font-mono">${escapeHtml(userId)}</p>
                                </div>
                            </label>
                        </div>
                        <button type="button"
                                onclick="sendMessageToUser('${escapeHtml(userId)}', '${escapeHtml(name)}', '${escapeHtml(username)}')"
                                class="px-3 py-1 text-sm font-medium bg-kc-blue text-white hover:bg-kc-blue-dark rounded-md transition-colors duration-200">
                            Quick Send
                        </button>
                    </div>
                `;
            });

            html += '</div>';
            searchContent.innerHTML = html;

            // Add event listeners for new checkboxes
            attachCheckboxListeners();
        }

        function displayNoUserSearchResults(query) {
            const searchContent = document.getElementById('searchContent');
            searchContent.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p>No users found with username "${escapeHtml(query)}"</p>
                    <p class="text-sm mt-1">Try searching for a different username</p>
                </div>
            `;
        }

        function displayUserSearchError(error) {
            const searchContent = document.getElementById('searchContent');
            searchContent.innerHTML = `
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

        // Recent Recipients Functions
        function addToRecentRecipients(userId, userName, userUsername = '') {
            const recipient = {
                id: userId,
                name: userName,
                username: userUsername,
                timestamp: Date.now()
            };

            // Remove if already exists
            recentRecipients = recentRecipients.filter(r => r.id !== userId);

            // Add to beginning
            recentRecipients.unshift(recipient);

            // Keep only last 10
            recentRecipients = recentRecipients.slice(0, 10);

            // Save to localStorage
            localStorage.setItem('recentRecipients', JSON.stringify(recentRecipients));
        }

        function loadRecentRecipients() {
            const recentContent = document.getElementById('recentContent');
            const recentList = document.getElementById('recentRecipientsList');

            if (recentRecipients.length === 0) {
                recentList.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p>No recent recipients yet</p>
                        <p class="text-sm mt-1">Users you've messaged recently will appear here</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="space-y-2">';
            recentRecipients.forEach(recipient => {
                const timeAgo = getTimeAgo(recipient.timestamp);
                html += `
                    <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors duration-200 border border-gray-100">
                        <div class="flex items-center">
                            <input type="checkbox" name="recipients[]"
                                   value="${escapeHtml(recipient.id)}"
                                   id="recent_user_${escapeHtml(recipient.id)}"
                                   data-name="${escapeHtml(recipient.name)}"
                                   data-username="${escapeHtml(recipient.username)}"
                                   class="h-5 w-5 text-kc-blue focus:ring-2 focus:ring-kc-blue focus:ring-offset-2 rounded transition-all duration-200">
                            <label for="recent_user_${escapeHtml(recipient.id)}" class="ml-3 flex items-center cursor-pointer">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-kc-blue text-white flex items-center justify-center font-bold text-sm">
                                    ${recipient.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">${escapeHtml(recipient.name)}</p>
                                    ${recipient.username ? `<p class="text-xs text-gray-500">${escapeHtml(recipient.username)}</p>` : ''}
                                    <p class="text-xs text-gray-400">${timeAgo}</p>
                                </div>
                            </label>
                        </div>
                        <button type="button"
                                onclick="sendMessageToUser('${escapeHtml(recipient.id)}', '${escapeHtml(recipient.name)}', '${escapeHtml(recipient.username)}')"
                                class="px-3 py-1 text-sm font-medium bg-kc-blue text-white hover:bg-kc-blue-dark rounded-md transition-colors duration-200">
                            Quick Send
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            recentList.innerHTML = html;

            // Add event listeners for new checkboxes
            attachCheckboxListeners();
        }

        // Utility Functions
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function getTimeAgo(timestamp) {
            const now = Date.now();
            const diff = now - timestamp;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
            if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            return 'Just now';
        }

        function attachCheckboxListeners() {
            // Re-attach event listeners for dynamically added checkboxes
            document.querySelectorAll('input[name="recipients[]"]').forEach(checkbox => {
                checkbox.removeEventListener('change', updateSelectedCount);
                checkbox.addEventListener('change', updateSelectedCount);
            });
        }

        // Toast Notification Function
        function showToast(message, type = 'info') {
            // Remove existing toast
            const existingToast = document.getElementById('toast');
            if (existingToast) {
                existingToast.remove();
            }

            const toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-full`;

            const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
            toast.className += ` ${bgColor} text-white`;

            toast.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${type === 'success' ?
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />' :
                            type === 'error' ?
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />' :
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
                        }
                    </svg>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);

            // Animate out and remove
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to send message (when modal is open)
            if (e.ctrlKey && e.key === 'Enter') {
                const messageModal = document.getElementById('messageModal');
                const quickSendModal = document.getElementById('quickSendModal');

                if (!messageModal.classList.contains('hidden')) {
                    e.preventDefault();
                    sendMessage();
                } else if (!quickSendModal.classList.contains('hidden')) {
                    e.preventDefault();
                    sendQuickMessage();
                }
            }

            // Escape to close modals
            if (e.key === 'Escape') {
                const messageModal = document.getElementById('messageModal');
                const quickSendModal = document.getElementById('quickSendModal');

                if (!messageModal.classList.contains('hidden')) {
                    closeMessageModal();
                } else if (!quickSendModal.classList.contains('hidden')) {
                    closeQuickSendModal();
                }

                hideEmojiPicker();
            }
        });

        // Add user to contacts list (placeholder functionality)
        function addToContacts(userId, userName) {
            showToast(`Feature coming soon: Add ${userName} (${userId}) to contacts list`, 'info');
        }
    </script>
</body>
</html>