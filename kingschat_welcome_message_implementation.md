# KingsChat Welcome Message Implementation

This document describes the implementation of an automatic welcome message system that can be integrated with any login system.

## Overview

When a user logs into any system, they automatically receive a welcome message from a system account. This creates a better user experience and confirms that their login was successful.

## Implementation Details

### 1. Welcome Message Sender Function

The system uses a dedicated function to send messages via the KingsChat API:

```php
function sendWelcomeMessage($recipientId, $recipientName = 'User') {
    // The sender user ID (system account)
    $senderUserId = '67c6d4860b20977035865f98';

    // The access token for the sender user
    $accessToken = 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ';

    // Create a personalized welcome message
    $message = "Welcome, $recipientName! Your login was successful. You can now use the system.";

    // Send the message using the KingsChat API
    // ...
}
```

### 2. Integration with Any Login System

This function can be integrated with any login system by calling it after successful authentication:

```php
// Example integration with a login system
function onSuccessfulLogin($userId, $userName) {
    // Other login processing...

    // Send welcome message to the user
    $messageSent = sendWelcomeMessage($userId, $userName);

    // Log the result if needed
    if ($messageSent) {
        logActivity("Welcome message sent to user: $userName ($userId)");
    }
}
```

### 3. API Call Details

The welcome message is sent using the KingsChat API with the following parameters:

- **Endpoint**: `https://connect.kingsch.at/api/users/{recipient_id}/new_message`
- **Method**: POST
- **Headers**:
  - `Authorization: Bearer {access_token}`
  - `Content-Type: application/json`
- **Body**:
  ```json
  {
    "message": {
      "body": {
        "text": {
          "body": "Welcome to KingsBlast, {name}! Your login was successful. You can now use the system to send messages to your KingsChat contacts."
        }
      }
    }
  }
  ```

## Example API Call

Here's an example of how the API call would look using curl:

```bash
curl -X POST "https://connect.kingsch.at/api/users/{recipient_id}/new_message" \
  -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ" \
  -H "Content-Type: application/json" \
  -d '{"message": {"body": {"text": {"body": "Welcome, {username}! Your login was successful."}}}}'
```

## System Account Details

- **System User ID**: `67c6d4860b20977035865f98`
- **Access Token**: The long-lived access token for the system account is included in the implementation.

## Integration Examples

### 1. With PHP Session-Based Authentication

```php
// After successful login and session creation
if ($loginSuccessful) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;

    // Send welcome message
    sendWelcomeMessage($userId, $userName);

    // Redirect to dashboard or home page
    header('Location: dashboard.php');
    exit;
}
```

### 2. With JWT Authentication

```php
// After validating JWT and creating a new token
if ($validCredentials) {
    $token = createJwtToken($userId, $userName);

    // Send welcome message
    sendWelcomeMessage($userId, $userName);

    // Return token in response
    echo json_encode(['token' => $token, 'user' => ['id' => $userId, 'name' => $userName]]);
}
```

### 3. With OAuth Authentication

```php
// In the OAuth callback handler
function handleOAuthCallback($providerResponse) {
    // Process OAuth provider response
    $userId = $providerResponse['user_id'];
    $userName = $providerResponse['name'];

    // Create local user session
    createUserSession($userId, $userName);

    // Send welcome message
    sendWelcomeMessage($userId, $userName);

    // Redirect to application
    redirect('/app');
}
```

## Security Considerations

- The access token is stored in the server-side code, not exposed to clients
- The system only sends messages to authenticated users
- All API calls use HTTPS for secure communication
- Error handling is implemented to log any issues with message delivery
- The welcome message function can be rate-limited to prevent abuse
