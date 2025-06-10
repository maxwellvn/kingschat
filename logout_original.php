<?php
session_start();

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear any cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}

// Redirect to login page
header('Location: index.php');
exit;
?>