<?php
session_start();

// Read the config file to get the stored token
$configFile = __DIR__ . '/kc_config.json';
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    
    if (isset($config['access_token'])) {
        $token = $config['access_token'];
        
        // Store the token in session
        $_SESSION['kc_access_token'] = $token;
        
        if (isset($config['refresh_token'])) {
            $_SESSION['kc_refresh_token'] = $config['refresh_token'];
        }
        
        // Fetch user profile from KingsChat API
        $ch = curl_init('https://connect.kingsch.at/api/profile');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "<h1>Login Test Results</h1>";
        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
        
        if ($httpCode === 200) {
            $userData = json_decode($response, true);
            if ($userData) {
                $_SESSION['kc_user'] = $userData;
                $_SESSION['success_message'] = 'Successfully logged in to KingsChat!';
                
                echo "<p style='color: green;'><strong>Success!</strong> User profile retrieved successfully.</p>";
                echo "<p><strong>User:</strong> " . htmlspecialchars($userData['profile']['user']['name']) . "</p>";
                echo "<p><strong>Username:</strong> @" . htmlspecialchars($userData['profile']['user']['username']) . "</p>";
                echo "<p><strong>User ID:</strong> " . htmlspecialchars($userData['profile']['user']['user_id']) . "</p>";
                
                echo "<p><a href='dashboard.php' style='background: #4A90E2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>";
            } else {
                echo "<p style='color: red;'>Failed to parse user data.</p>";
            }
        } else {
            echo "<p style='color: red;'><strong>Error:</strong> Failed to fetch user profile. Response: " . htmlspecialchars($response) . "</p>";
        }
    } else {
        echo "<p style='color: red;'>No access token found in config file.</p>";
    }
} else {
    echo "<p style='color: red;'>Config file not found.</p>";
}
?>