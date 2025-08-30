<?php
// Set error reporting based on environment
$is_production = $_SERVER['HTTP_HOST'] === 'veedcos.com';
if ($is_production) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Configure session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $is_production ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Security Headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
if ($is_production) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Updated CSP without Firebase
$csp = "default-src 'self'; " .
       "img-src 'self' data: https://kingschat.online https://*.kingschat.online https://*.cloudfront.net https://cdn.jsdelivr.net; " .
       "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; " .
       "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; " .
       "font-src 'self' data:; " .
       "connect-src 'self';";
header("Content-Security-Policy: " . $csp);

// Set error log path based on environment
if ($is_production) {
    ini_set('log_errors', 1);
    ini_set('error_log', '/home/u410505784/domains/veedcos.com/public_html/error.log');
} else {
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
}

// Define config file path
if (!defined('KC_CONFIG_FILE')) {
    define('KC_CONFIG_FILE', __DIR__ . '/kc_config.json');
}

// Try to restore session from cookies
if (!isset($_SESSION['kc_access_token']) && isset($_COOKIE['kc_access_token'])) {
    $_SESSION['kc_access_token'] = $_COOKIE['kc_access_token'];
    if (isset($_COOKIE['kc_refresh_token'])) {
        $_SESSION['kc_refresh_token'] = $_COOKIE['kc_refresh_token'];
    }

    // Verify the token by making an API call
    $ch = curl_init('https://connect.kingsch.at/api/profile');
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

    if ($httpCode === 200) {
        $userData = json_decode($response, true);
        if ($userData) {
            $_SESSION['kc_user'] = $userData;

            // Extract user ID and name from the profile data
            $userId = isset($userData['profile']['user']['user_id']) ? $userData['profile']['user']['user_id'] : '';
            $userName = isset($userData['profile']['user']['name']) ? $userData['profile']['user']['name'] : 'User';

            // Log the login event
            if (!empty($userId)) {
                error_log("[" . date('Y-m-d H:i:s') . "] User logged in via cookie: $userId ($userName)");
                
                // Update the sender_user_id and refresh_token in the config file with the logged-in user's details
                if (file_exists(KC_CONFIG_FILE)) {
                    $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
                    if (is_array($config)) {
                        $config['sender_user_id'] = $userId;
                        
                        // Also update the refresh token if available
                        if (isset($_SESSION['kc_refresh_token'])) {
                            $config['refresh_token'] = $_SESSION['kc_refresh_token'];
                            error_log("[" . date('Y-m-d H:i:s') . "] Updated refresh_token in config");
                        }
                        
                        file_put_contents(KC_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
                        error_log("[" . date('Y-m-d H:i:s') . "] Updated sender_user_id in config to: $userId");
                    }
                }
            }

            header('Location: dashboard.php');
            exit;
        }
    }

    // If verification failed, clear cookies and session
    setcookie('kc_access_token', '', time() - 3600, '/');
    setcookie('kc_refresh_token', '', time() - 3600, '/');
    session_unset();
    session_destroy();
    session_start();
}

// Clear any existing session data if coming from a fresh login attempt
if (isset($_GET['fresh'])) {
    session_unset();
    session_destroy();
    session_start();

    // Also clear any cookies that might be causing issues
    if (isset($_SERVER['HTTP_COOKIE'])) {
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $name = trim($parts[0]);
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
        }
    }

    header('Location: index.php');
    exit;
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['kc_access_token'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle error messages
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="KingsChat Login">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - KingsChat Blast</title>
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
<body class="bg-gradient-to-br from-gray-700 to-gray-900 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="text-center mb-10">
            <div class="flex justify-center items-center mb-6">
                <img src="https://kingschat.online/svg/logo-horizontal.svg"
                     alt="KingsChat Logo"
                     class="h-14 transform hover:scale-105 transition-transform duration-300">
            </div>
            <h2 class="text-3xl font-extrabold text-white mb-2">Welcome Back</h2>
            <p class="text-sm text-gray-300">
                Sign in to continue to your account
            </p>
        </div>

        <?php if ($error): ?>
            <div class="rounded-lg bg-red-50 p-4 mb-6 animate-fade-in">
                <div class="flex">
                    <di-v class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">
                            <?php echo htmlspecialchars($error); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white py-8 px-6 shadow-xl rounded-xl border border-gray-100 backdrop-blur-sm backdrop-filter">
            <div class="flex flex-col items-center space-y-6">
                <button onclick="loginWithKingschat()"
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-base font-medium rounded-lg text-white bg-kc-blue hover:bg-kc-blue-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-kc-blue transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-white/80 group-hover:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Continue with KingsChat
                </button>

                <p class="text-xs text-center text-gray-500">
                    By continuing, you agree to KingsChat's
                    <a href="#" class="text-kc-blue hover:text-kc-blue-dark">Terms of Service</a> and
                    <a href="#" class="text-kc-blue hover:text-kc-blue-dark">Privacy Policy</a>
                </p>

                <?php if ($error): ?>
                <div class="mt-4 text-center">
                    <a href="index.php?fresh=1" class="text-sm text-kc-blue hover:text-kc-blue-dark">
                        Having trouble? Click here to clear session and try again
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function loginWithKingschat() {
            const clientId = '619b30ea-a682-47fb-b90f-5b8e780b89ca';
            const scopes = ['conference_calls'];

            // Get the full URL of the callback page
            const currentUrl = window.location.href;
            const baseUrl = currentUrl.substring(0, currentUrl.lastIndexOf('/') + 1);
            const callbackUrl = baseUrl + 'callback.php';

            // Construct the login URL with all necessary parameters
            const params = {
                client_id: clientId,
                scopes: JSON.stringify(scopes),
                redirect_uri: callbackUrl,
                response_type: 'token',
                post_redirect: true
            };

            const queryString = Object.keys(params)
                .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
                .join('&');

            const loginUrl = `https://accounts.kingsch.at/?${queryString}`;

            // Add loading state to button
            const button = document.querySelector('button');
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            // Redirect after a short delay to show loading state
            setTimeout(() => {
                window.location.href = loginUrl;
            }, 500);
        }
    </script>
</body>
</html>