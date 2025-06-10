<?php
// This is a test script to verify the welcome message functionality

// Include the welcome message functionality
require_once 'send_welcome_message.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Test recipient ID (replace with a valid user ID if needed)
$recipientId = '5d03686cde867f0001b9df3d'; // The recipient ID from your request
$recipientName = 'Test User';

echo "<h1>Testing Welcome Message Functionality</h1>";
echo "<p>Attempting to send a welcome message to: <strong>$recipientName</strong> (ID: $recipientId)</p>";

// Send the welcome message
$result = sendWelcomeMessage($recipientId, $recipientName);

// Display the result
if ($result) {
    echo "<p style='color: green; font-weight: bold;'>Success! Welcome message sent successfully.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Error! Failed to send welcome message.</p>";
}

// Display instructions for checking the dashboard
echo "<h2>Next Steps</h2>";
echo "<p>To test the automatic welcome message when logging in:</p>";
echo "<ol>";
echo "<li>Log out of KingsChat</li>";
echo "<li>Log back in to KingsChat</li>";
echo "<li>You should receive a welcome message from user ID 67c6d4860b20977035865f98</li>";
echo "</ol>";

// Add a link to go back to the dashboard
echo "<p><a href='dashboard.php' style='color: blue; text-decoration: underline;'>Return to Dashboard</a></p>";
