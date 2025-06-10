<?php
session_start();
header('Content-Type: application/json');

// Check if access token exists in session
if (isset($_SESSION['kc_access_token'])) {
    echo json_encode([
        'success' => true,
        'accessToken' => $_SESSION['kc_access_token']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No authentication token available'
    ]);
}