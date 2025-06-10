<?php
// This file handles sending a welcome message to users when they log in to dashboard.php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Include token refresh functionality if not already included
if (!function_exists('ensureValidToken')) {
    require_once __DIR__ . '/token_refresh.php';
}

// Function to send a welcome message to a user
function sendWelcomeMessage($recipientId, $recipientName = 'User') {
    // The sender user ID (fixed as the system user)
    $senderUserId = '67c6d4860b20977035865f98';

    // Ensure we have a valid token before sending the message
    if (!ensureValidToken()) {
        error_log("Failed to ensure valid token for welcome message");
        return false;
    }

    // Get the access token from the session
    $accessToken = $_SESSION['kc_access_token'];

    // Create a personalized welcome message
    $message = "Welcome to KingsBlast, $recipientName! Your login was successful. You can now use the system to send messages to your KingsChat contacts.";

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

        // Log the response
        error_log("Welcome message response code: " . $httpCode);
        error_log("Welcome message response body: " . $response);

        // If unauthorized, try refreshing token once more and retry
        if ($httpCode === 401) {
            error_log('Received 401 Unauthorized when sending welcome message. Attempting to refresh token and retry...');
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

                error_log("Welcome message retry response code: " . $httpCode);
                error_log("Welcome message retry response body: " . $response);
            }
        }

        // Check if the request was successful
        if ($httpCode === 200) {
            error_log("Welcome message sent to $recipientName ($recipientId) successfully");
            return true;
        } else {
            error_log("Failed to send welcome message to $recipientName ($recipientId) - HTTP $httpCode");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception sending welcome message: " . $e->getMessage());
        return false;
    }
}
