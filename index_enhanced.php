<?php
// Enhanced login page with better OAuth handling
session_start();

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

// Security Headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
if ($is_production) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Set error log path
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

// Try to restore session from cookies or config file
if (!isset($_SESSION['kc_access_token'])) {
    // First try cookies
    if (isset($_COOKIE['kc_access_token'])) {
        $_SESSION['kc_access_token'] = $_COOKIE['kc_access_token'];
        if (isset($_COOKIE['kc_refresh_token'])) {
            $_SESSION['kc_refresh_token'] = $_COOKIE['kc_refresh_token'];
        }
    }
    // Then try config file
    elseif (file_exists(KC_CONFIG_FILE)) {
        $config = json_decode(file_get_contents(KC_CONFIG_FILE), true);
        if (is_array($config) && isset($config['access_token'])) {
            // Check if token is not expired
            $expiresAt = $config['expires_at'] ?? 0;
            if ($expiresAt > time()) {
                $_SESSION['kc_access_token'] = $config['access_token'];
                if (isset($config['refresh_token'])) {
                    $_SESSION['kc_refresh_token'] = $config['refresh_token'];
                }
                error_log("Restored session from config file");
            }
        }
    }
    
    // Verify the token if we have one
    if (isset($_SESSION['kc_access_token'])) {
        $ch = curl_init('https://connect.kingsch.at/api/profile');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
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
                error_log("Token verified successfully");
                header('Location: dashboard.php');
                exit;
            }
        } else {
            // Token is invalid, clear it
            unset($_SESSION['kc_access_token']);
            unset($_SESSION['kc_refresh_token']);
            setcookie('kc_access_token', '', time() - 3600, '/');
            setcookie('kc_refresh_token', '', time() - 3600, '/');
            error_log("Token verification failed, cleared session");
        }
    }
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
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <img src="https://kingschat.online/svg/logo-horizontal.svg" alt="KingsChat Logo" class="mx-auto h-12 w-auto">
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Welcome Back</h2>
            <p class="mt-2 text-sm text-gray-600">Sign in to continue to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white py-8 px-6 shadow-xl rounded-xl border border-gray-100 backdrop-blur-sm backdrop-filter">
            <div class="flex flex-col items-center space-y-6">
                <button onclick="loginWithKingschat()"
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-base font-medium rounded-lg text-white bg-kc-blue hover:bg-kc-blue-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-kc-blue transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-white/80 group-hover:text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Continue with KingsChat
                </button>

                <div class="text-xs text-gray-500 text-center">
                    By continuing, you agree to KingsChat's
                    <a href="#" class="text-kc-blue hover:text-kc-blue-dark">Terms of Service</a>
                    and
                    <a href="#" class="text-kc-blue hover:text-kc-blue-dark">Privacy Policy</a>
                </div>

                <div class="text-center">
                    <a href="index.php?fresh=1" class="text-sm text-gray-500 hover:text-gray-700 underline">
                        Having trouble? Click here to clear session and try again
                    </a>
                </div>
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
            const callbackUrl = baseUrl + 'callback_enhanced.php';

            // Try multiple OAuth flows
            const flows = [
                // Flow 1: Authorization Code Flow (most reliable)
                {
                    response_type: 'code',
                    redirect_uri: callbackUrl,
                    client_id: clientId,
                    scopes: JSON.stringify(scopes),
                    state: generateRandomState()
                },
                // Flow 2: Implicit Flow (fallback)
                {
                    response_type: 'token',
                    redirect_uri: callbackUrl,
                    client_id: clientId,
                    scopes: JSON.stringify(scopes),
                    post_redirect: true
                }
            ];

            // Try the first flow
            const params = flows[0];
            
            // Store state for verification
            sessionStorage.setItem('oauth_state', params.state);

            const queryString = Object.keys(params)
                .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`)
                .join('&');

            const loginUrl = `https://accounts.kingsch.at/?${queryString}`;

            // Add loading state to button
            const button = document.querySelector('button');
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin h-5 w-5 text-white mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            // Set a timeout to restore button if something goes wrong
            setTimeout(() => {
                if (button.disabled) {
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            }, 30000);

            // Redirect after a short delay to show loading state
            setTimeout(() => {
                console.log('Redirecting to:', loginUrl);
                window.location.href = loginUrl;
            }, 500);
        }

        function generateRandomState() {
            return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        }

        // Check if we're returning from an OAuth flow
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            
            if (error) {
                console.error('OAuth error:', error);
                const description = urlParams.get('error_description');
                if (description) {
                    console.error('Error description:', description);
                }
            }
        });
    </script>
</body>
</html>