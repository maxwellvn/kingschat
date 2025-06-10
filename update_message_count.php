<?php
session_start();

// Set max execution time to 1 hour (3600 seconds)
set_time_limit(3600);
ini_set('max_execution_time', '3600');

header('Content-Type: application/json');

// Include database connection
require_once 'includes/db_connect.php';

// Include Firebase authentication helper
require_once 'firebase_auth.php';

// Check Firebase authentication
requireFirebaseAuth();

// Get user ID from session
$userId = $_SESSION['firebase_user_id'] ?? null;

if (!$userId) {
    error_log("Error: No user ID found in session");
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Check if user exists in database
if (!userExists($userId)) {
    // Get user info from session
    $username = $_SESSION['firebase_username'] ?? 'User_' . substr($userId, -6);
    $email = $_SESSION['firebase_email'] ?? null;
    $isPremium = isset($_SESSION['premium_user']) && $_SESSION['premium_user'] === true ? 1 : 0;
    $messageLimit = $_SESSION['message_limit'] ?? 10;
    
    // Create user in database
    if (!createUser($userId, $username, $email, $isPremium, $messageLimit)) {
        error_log("Error: Failed to create user in database");
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create user record'
        ]);
        exit;
    }
}

// Get user message count
$messagesSent = getUserMessageCount($userId);

// Get user message limit (query from database)
$stmt = $conn->prepare("SELECT is_premium, message_limit FROM users WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$isPremium = $user['is_premium'] == 1;
$messageLimit = $user['message_limit'];

// Update session variables for backward compatibility
$_SESSION['premium_user'] = $isPremium;
$_SESSION['message_limit'] = $messageLimit;
$_SESSION['messages_sent'] = $messagesSent;

// Check if user is premium
if ($isPremium) {
    // Check if user has reached their message limit
    if ($messagesSent < $messageLimit) {
        // Return success response
        echo json_encode([
            'success' => true,
            'messages_sent' => $messagesSent,
            'message_limit' => $messageLimit,
            'remaining' => $messageLimit - $messagesSent
        ]);
    } else {
        // Return error response - limit reached
        echo json_encode([
            'success' => false,
            'message' => 'Message limit reached',
            'messages_sent' => $messagesSent,
            'message_limit' => $messageLimit,
            'remaining' => 0
        ]);
    }
} else {
    // Return error response - not premium
    echo json_encode([
        'success' => false,
        'message' => 'User is not premium',
        'messages_sent' => $messagesSent,
        'message_limit' => $messageLimit,
        'remaining' => $messageLimit > $messagesSent ? $messageLimit - $messagesSent : 0
    ]);
}
?> 