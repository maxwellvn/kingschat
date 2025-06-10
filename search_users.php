<?php
/**
 * KingsChat User Search API
 *
 * This script handles searching for KingsChat users using various methods
 * and API endpoints to discover available search functionality.
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

if (!$data || !isset($data['query']) || !isset($data['type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request data. Query and type are required.'
    ]);
    exit;
}

$query = trim($data['query']);
$type = $data['type'];

if (empty($query)) {
    echo json_encode([
        'success' => false,
        'error' => 'Search query cannot be empty.'
    ]);
    exit;
}

$accessToken = $_SESSION['kc_access_token'];
$results = [];
$searchMethods = [];

// Log the search attempt
error_log("User search initiated: query='$query', type='$type'");

/**
 * Helper function to make API requests
 */
function makeApiRequest($url, $accessToken, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error
    ];
}

/**
 * Search Method 1: Search within existing contacts
 */
function searchContacts($query, $accessToken) {
    $result = makeApiRequest('https://connect.kingsch.at/api/contacts', $accessToken);

    if ($result['http_code'] === 200) {
        $contacts = json_decode($result['response'], true);
        $matches = [];

        if (is_array($contacts)) {
            foreach ($contacts as $contact) {
                $name = $contact['name'] ?? '';
                $username = $contact['username'] ?? '';
                $userId = $contact['user_id'] ?? '';

                // Search in name, username, or user ID
                if (stripos($name, $query) !== false ||
                    stripos($username, $query) !== false ||
                    stripos($userId, $query) !== false) {
                    $matches[] = $contact;
                }
            }
        }

        return [
            'success' => true,
            'data' => $matches,
            'method' => 'contacts',
            'total' => count($matches)
        ];
    }

    return [
        'success' => false,
        'error' => 'Failed to fetch contacts',
        'method' => 'contacts'
    ];
}

/**
 * Search Method 2: Try direct user ID lookup
 */
function searchByUserId($userId, $accessToken) {
    // Try to get user profile by ID
    $result = makeApiRequest("https://connect.kingsch.at/api/users/$userId", $accessToken);

    if ($result['http_code'] === 200) {
        $user = json_decode($result['response'], true);
        return [
            'success' => true,
            'data' => [$user],
            'method' => 'user_id_lookup',
            'total' => 1
        ];
    }

    return [
        'success' => false,
        'error' => 'User not found or access denied',
        'method' => 'user_id_lookup'
    ];
}

/**
 * Search Method 3: Test various search endpoints
 */
function testSearchEndpoints($query, $accessToken) {
    $endpoints = [
        '/api/search',
        '/api/users/search',
        '/api/directory',
        '/api/users',
        '/api/search/users',
        '/api/public/users',
        '/api/directory/search'
    ];

    $results = [];

    foreach ($endpoints as $endpoint) {
        $url = 'https://connect.kingsch.at' . $endpoint;

        // Try GET with query parameter
        $getResult = makeApiRequest($url . '?q=' . urlencode($query), $accessToken);

        // Try POST with query in body
        $postResult = makeApiRequest($url, $accessToken, 'POST', ['query' => $query]);

        $results[] = [
            'endpoint' => $endpoint,
            'get_response' => [
                'http_code' => $getResult['http_code'],
                'response' => $getResult['response'],
                'error' => $getResult['error']
            ],
            'post_response' => [
                'http_code' => $postResult['http_code'],
                'response' => $postResult['response'],
                'error' => $postResult['error']
            ]
        ];
    }

    return [
        'success' => true,
        'data' => $results,
        'method' => 'endpoint_testing'
    ];
}

/**
 * Enhanced username search that can find multiple users
 */
function searchByUsername($searchTerm, $accessToken) {
    $searchTerm = trim($searchTerm);
    $searchTerm = ltrim($searchTerm, '@'); // Remove @ if present

    $results = [];
    $searchedUsernames = [];

    // Strategy 1: Direct username search
    $directResult = searchSingleUsername($searchTerm, $accessToken);
    if ($directResult) {
        $results[] = $directResult;
        $searchedUsernames[] = strtolower($searchTerm);
    }

    // Strategy 2: Try common variations
    $variations = [
        strtolower($searchTerm),
        ucfirst(strtolower($searchTerm)),
        strtoupper($searchTerm)
    ];

    foreach ($variations as $variation) {
        if (!in_array(strtolower($variation), $searchedUsernames)) {
            $result = searchSingleUsername($variation, $accessToken);
            if ($result) {
                $results[] = $result;
                $searchedUsernames[] = strtolower($variation);
            }
        }
    }

    // Strategy 3: Partial username search (if search term is short, try common prefixes)
    if (strlen($searchTerm) >= 2 && strlen($searchTerm) <= 4) {
        $commonUsernames = [
            $searchTerm . '1', $searchTerm . '2', $searchTerm . '123',
            $searchTerm . '_', $searchTerm . 'er', $searchTerm . 'man',
            'the' . $searchTerm, $searchTerm . 'official'
        ];

        foreach ($commonUsernames as $testUsername) {
            if (!in_array(strtolower($testUsername), $searchedUsernames)) {
                $result = searchSingleUsername($testUsername, $accessToken);
                if ($result) {
                    $results[] = $result;
                    $searchedUsernames[] = strtolower($testUsername);

                    // Limit to prevent too many results
                    if (count($results) >= 10) break;
                }
            }
        }
    }

    // Strategy 4: Search in contacts for partial matches
    $contactResult = searchContacts($searchTerm, $accessToken);
    if ($contactResult['success'] && !empty($contactResult['data'])) {
        foreach ($contactResult['data'] as $contact) {
            $contactUserId = $contact['user_id'] ?? '';
            $alreadyFound = false;

            foreach ($results as $existingUser) {
                if (($existingUser['user_id'] ?? '') === $contactUserId) {
                    $alreadyFound = true;
                    break;
                }
            }

            if (!$alreadyFound) {
                $results[] = $contact;
            }
        }
    }

    return [
        'success' => true,
        'data' => $results,
        'method' => 'enhanced_username_search',
        'total' => count($results),
        'searched_variations' => count($searchedUsernames)
    ];
}

/**
 * Search for a single username using the KingsChat API
 */
function searchSingleUsername($username, $accessToken) {
    $result = makeApiRequest("https://connect.kingsch.at/api/users?username=" . urlencode($username), $accessToken);

    if ($result['http_code'] === 200) {
        $user = json_decode($result['response'], true);
        if ($user && isset($user['user_id'])) {
            // Normalize the user data structure
            return [
                'user_id' => $user['user_id'] ?? $user['id'] ?? '',
                'name' => $user['name'] ?? '',
                'username' => $user['username'] ?? '',
                'bio' => $user['bio'] ?? '',
                'avatar_url' => $user['avatar']['url'] ?? null,
                'private_account' => $user['private_account'] ?? false,
                'posts_count' => $user['posts_count'] ?? 0,
                'has_superuser' => $user['has_superuser'] ?? false,
                'verified' => $user['verified'] ?? false
            ];
        }
    }

    return null;
}

// Execute username search
try {
    $searchResult = searchByUsername($query, $accessToken);

    // Log the search result
    $resultCount = isset($searchResult['data']) ? count($searchResult['data']) : 0;
    error_log("Username search completed: found $resultCount results for '$query'");

    echo json_encode($searchResult);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
}
?>
