<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Include database connection
require_once 'includes/db_connect.php';

// Include Firebase authentication helper
require_once 'firebase_auth.php';

// Check Firebase authentication
requireFirebaseAuth();

// Get user ID from session
$userId = $_SESSION['firebase_user_id'] ?? null;

if (!$userId) {
    die("Error: No user ID found in session");
}

// Check if user exists in database
if (!userExists($userId)) {
    die("Error: User does not exist in database. Please send a message first.");
}

// Get user message count
$messagesSent = getUserMessageCount($userId);

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get recent messages
$stmt = $conn->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY sent_at DESC LIMIT 10");
$stmt->bind_param("s", $userId);
$stmt->execute();
$messagesResult = $stmt->get_result();
$messages = [];
while ($row = $messagesResult->fetch_assoc()) {
    $messages[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Check</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6">Message Tracking Check</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">User Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p><strong>User ID:</strong> <?php echo htmlspecialchars($userId); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></p>
                </div>
                <div>
                    <p><strong>Premium Status:</strong> <?php echo $user['is_premium'] ? 'Premium' : 'Free'; ?></p>
                    <p><strong>Message Limit:</strong> <?php echo $user['message_limit']; ?></p>
                    <p><strong>Messages Sent (Database):</strong> <?php echo $messagesSent; ?></p>
                    <p><strong>Messages Sent (Session):</strong> <?php echo $_SESSION['messages_sent'] ?? 'Not set'; ?></p>
                    <p><strong>Remaining:</strong> <?php echo max(0, $user['message_limit'] - $messagesSent); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Recent Messages</h2>
            <?php if (count($messages) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Recipient</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Message</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                                <tr>
                                    <td class="py-2 px-4 border-b border-gray-200"><?php echo $message['id']; ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars($message['recipient_id']); ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?php echo htmlspecialchars(substr($message['message_text'], 0, 50) . (strlen($message['message_text']) > 50 ? '...' : '')); ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?php echo $message['sent_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No messages sent yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="mt-6 flex space-x-4 justify-center">
            <a href="dashboard.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded">
                Back to Dashboard
            </a>
            <a href="db_status.php" class="inline-block bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded">
                View Database Status
            </a>
        </div>
    </div>
</body>
</html> 