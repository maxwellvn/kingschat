<?php
// Start session
session_start();

// Clear all session data
session_unset();
session_destroy();

// Clear all cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}

// Set success message
$message = "All cookies and session data have been cleared. Please try logging in again.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookies Cleared</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow-md">
        <div class="text-center mb-6">
            <svg class="mx-auto h-12 w-12 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <h2 class="mt-3 text-lg font-medium text-gray-900">Cookies Cleared</h2>
            <p class="mt-1 text-sm text-gray-500"><?php echo htmlspecialchars($message); ?></p>
        </div>
        <div class="mt-6">
            <a href="index.php" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Return to Login
            </a>
        </div>
        
        <div class="mt-6 border-t border-gray-200 pt-4">
            <h3 class="text-sm font-medium text-gray-900">If you're still having issues:</h3>
            <ul class="mt-2 text-sm text-gray-500 list-disc pl-5 space-y-1">
                <li>Try using a different browser</li>
                <li>Clear your browser cache</li>
                <li>Disable browser extensions</li>
                <li>Try using incognito/private browsing mode</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Clear localStorage as well
        localStorage.clear();
        
        // Clear any cookies for kingschat.online domain
        document.cookie.split(";").forEach(function(c) {
            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
        });
    </script>
</body>
</html> 