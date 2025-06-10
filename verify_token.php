<?php
// This file is for testing the new KingsChat access token and user ID

require_once 'send_welcome_message.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/kingschat_verify.log');

// Test recipient (this should be a valid KingsChat user ID)
$testRecipientId = '6811d9b00df12d54adecc0ab'; // Using the same ID as sender for testing
$testRecipientName = 'Test User';

// Send a test message
$result = sendWelcomeMessage($testRecipientId, $testRecipientName);

// Output the result
echo '<h1>KingsChat Token Verification</h1>';
echo '<p>Testing welcome message with new credentials and format...</p>';

if ($result) {
    echo '<div style="color: green; font-weight: bold;">Success! The welcome message was sent successfully.</div>';
    echo '<p>Check your KingsChat account to verify the message was received.</p>';

    // Show example of the message format
    $currentDateTime = gmdate('Y-m-d H:i:s');
    $currentTimeFormatted = gmdate('g:i a');
    $currentDateFormatted = gmdate('F j, Y');

    echo '<h2>Message Format Preview:</h2>';
    echo '<div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap;">';
    echo "Hello $testRecipientName! Your GPD Ordering Portal account was accessed on $currentDateFormatted, $currentTimeFormatted GMT. Please contact support if you do not recognise this activity.\n\nFor issues, feedback, complaints and inquiries, please contact the administrator.\n\nDate: $currentDateTime GMT";
    echo '</div>';
} else {
    echo '<div style="color: red; font-weight: bold;">Failed to send the welcome message.</div>';
    echo '<p>Please check the logs for more details: logs/kingschat_verify.log</p>';
}

// Display the current configuration
echo '<h2>Current Configuration</h2>';
echo '<ul>';
echo '<li><strong>Sender User ID:</strong> 6811d9b00df12d54adecc0ab</li>';
echo '<li><strong>Access Token:</strong> [Hidden for security]</li>';
echo '<li><strong>Test Recipient ID:</strong> ' . htmlspecialchars($testRecipientId) . '</li>';
echo '<li><strong>Test Recipient Name:</strong> ' . htmlspecialchars($testRecipientName) . '</li>';
echo '</ul>';

echo '<p><a href="../dashboard.php">Return to Dashboard</a></p>';
?>
