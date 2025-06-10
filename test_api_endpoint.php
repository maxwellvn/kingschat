<?php
/**
 * KingsChat API Endpoint Testing Script
 * 
 * This script tests various KingsChat API endpoints to discover
 * available functionality and search capabilities.
 */

session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['kc_access_token'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated. Please log in first.'
    ]);
    exit;
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['endpoint'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request data. Endpoint is required.'
    ]);
    exit;
}

$endpoint = $data['endpoint'];
$accessToken = $_SESSION['kc_access_token'];

// Log the API test attempt
error_log("API endpoint test initiated: endpoint='$endpoint'");

/**
 * Helper function to make API requests with detailed logging
 */
function testApiEndpoint($endpoint, $accessToken) {
    $baseUrl = 'https://connect.kingsch.at';
    $fullUrl = $baseUrl . $endpoint;
    
    $results = [
        'endpoint' => $endpoint,
        'full_url' => $fullUrl,
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => []
    ];
    
    // Test 1: Simple GET request
    $results['tests']['get_simple'] = makeRequest($fullUrl, $accessToken, 'GET');
    
    // Test 2: GET with common query parameters
    $queryParams = [
        'q=test',
        'query=test',
        'search=test',
        'username=test',
        'name=test',
        'limit=10',
        'page=1'
    ];
    
    foreach ($queryParams as $param) {
        $testUrl = $fullUrl . '?' . $param;
        $results['tests']['get_with_' . explode('=', $param)[0]] = makeRequest($testUrl, $accessToken, 'GET');
    }
    
    // Test 3: POST with different body formats
    $postBodies = [
        ['query' => 'test'],
        ['search' => 'test'],
        ['username' => 'test'],
        ['q' => 'test'],
        ['name' => 'test'],
        ['user_id' => 'test'],
        ['filters' => ['query' => 'test']],
        'test' // Simple string
    ];
    
    foreach ($postBodies as $index => $body) {
        $results['tests']['post_body_' . $index] = makeRequest($fullUrl, $accessToken, 'POST', $body);
    }
    
    // Test 4: Different HTTP methods
    $methods = ['PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    foreach ($methods as $method) {
        $results['tests'][strtolower($method)] = makeRequest($fullUrl, $accessToken, $method);
    }
    
    return $results;
}

/**
 * Make HTTP request with detailed response information
 */
function makeRequest($url, $accessToken, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: KingsChat-Search-Tool/1.0'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_VERBOSE => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data !== null) {
        if (is_array($data) || is_object($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Separate headers and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Try to parse JSON response
    $jsonData = null;
    if ($body) {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $jsonData = $decoded;
        }
    }
    
    return [
        'method' => $method,
        'url' => $url,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'response_time' => round(($endTime - $startTime) * 1000, 2) . 'ms',
        'curl_time' => round($totalTime * 1000, 2) . 'ms',
        'error' => $error ?: null,
        'headers' => $headers,
        'body' => $body,
        'json_data' => $jsonData,
        'body_length' => strlen($body),
        'request_data' => $data
    ];
}

/**
 * Test specific known endpoints
 */
function testKnownEndpoints($accessToken) {
    $knownEndpoints = [
        '/api/profile' => 'User profile information',
        '/api/contacts' => 'User contacts list',
        '/api/users' => 'Users endpoint',
        '/api/search' => 'Search endpoint',
        '/api/users/search' => 'User search endpoint',
        '/api/directory' => 'User directory',
        '/api/public/users' => 'Public users',
        '/api/search/users' => 'Search users',
        '/api/directory/search' => 'Directory search',
        '/api/friends' => 'Friends list',
        '/api/following' => 'Following list',
        '/api/followers' => 'Followers list',
        '/api/discover' => 'Discover users',
        '/api/suggestions' => 'User suggestions',
        '/api/recommendations' => 'User recommendations'
    ];
    
    $results = [];
    
    foreach ($knownEndpoints as $endpoint => $description) {
        $results[$endpoint] = [
            'description' => $description,
            'test_result' => makeRequest('https://connect.kingsch.at' . $endpoint, $accessToken, 'GET')
        ];
    }
    
    return $results;
}

try {
    if ($endpoint === 'all_known') {
        // Test all known endpoints
        $result = [
            'success' => true,
            'test_type' => 'all_known_endpoints',
            'data' => testKnownEndpoints($accessToken)
        ];
    } else {
        // Test specific endpoint
        $result = [
            'success' => true,
            'test_type' => 'single_endpoint',
            'data' => testApiEndpoint($endpoint, $accessToken)
        ];
    }
    
    // Log the test result
    error_log("API endpoint test completed: endpoint='$endpoint'");
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("API test error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'API test failed: ' . $e->getMessage()
    ]);
}
?>
