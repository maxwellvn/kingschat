# Design Document

## Overview

The OAuth callback routing issue stems from Flutter web's single-page application (SPA) architecture conflicting with the static HTML callback approach. The current implementation tries to serve a static `callback.html` file, but Flutter's routing system intercepts all requests and doesn't know how to handle this specific path.

The solution involves implementing proper Flutter web routing to handle the callback URL pattern and process OAuth tokens directly within the Flutter application, eliminating the need for the separate HTML file.

## Architecture

### Current Flow (Problematic)
1. User initiates OAuth → Redirected to OAuth provider
2. OAuth provider redirects to `/callback.html` → 404 Error (Flutter doesn't serve static HTML)
3. Authentication fails

### Proposed Flow (Fixed)
1. User initiates OAuth → Redirected to OAuth provider  
2. OAuth provider redirects to `/callback` → Flutter route handler processes tokens
3. Tokens extracted and processed → User redirected to dashboard

## Components and Interfaces

### 1. Router Configuration Enhancement
- **File**: `lib/main.dart`
- **Changes**: Update `onGenerateRoute` to handle `/callback` and `/callback.html` routes
- **Purpose**: Ensure both callback URLs are properly routed to the CallbackScreen

### 2. AuthService URL Generation Update
- **File**: `lib/services/auth_service.dart`
- **Changes**: Modify `getLoginUrl()` to use `/callback` instead of `/callback.html`
- **Purpose**: Use Flutter route instead of static HTML file

### 3. CallbackScreen Enhancement
- **File**: `lib/screens/callback_screen.dart`
- **Changes**: Improve token extraction logic and error handling
- **Purpose**: Better handle various callback scenarios and provide user feedback

### 4. Static File Cleanup
- **File**: `web/callback.html`
- **Action**: Remove or repurpose as fallback
- **Purpose**: Eliminate confusion and potential conflicts

## Data Models

### Token Extraction Logic
```dart
class CallbackTokenExtractor {
  static TokenData? extractFromUrl(Uri uri) {
    // Extract from URL fragment (#access_token=...)
    // Extract from query parameters (?access_token=...)
    // Handle error parameters
  }
}
```

### Callback State Management
```dart
enum CallbackState {
  processing,
  success,
  error,
  timeout
}
```

## Error Handling

### 1. Missing Tokens
- **Scenario**: Callback URL doesn't contain access_token
- **Response**: Show error message, redirect to login after delay
- **User Feedback**: "Authentication failed. Please try again."

### 2. Invalid Tokens
- **Scenario**: Tokens are present but invalid/expired
- **Response**: Attempt token refresh, fallback to login
- **User Feedback**: "Authentication expired. Please log in again."

### 3. Network Errors
- **Scenario**: Profile fetch fails after token extraction
- **Response**: Retry with exponential backoff, show detailed error
- **User Feedback**: "Connection error. Retrying..."

### 4. Timeout Handling
- **Scenario**: Callback processing takes too long
- **Response**: Show timeout message, provide manual retry option
- **User Feedback**: "Authentication is taking longer than expected..."

## Testing Strategy

### 1. Unit Tests
- Token extraction from various URL formats
- Error handling for missing/invalid tokens
- AuthService callback processing logic

### 2. Integration Tests
- End-to-end OAuth flow simulation
- Route handling verification
- State management during callback processing

### 3. Manual Testing Scenarios
- Test with localhost development server
- Test callback URL variations (`/callback`, `/callback.html`)
- Test error scenarios (denied access, network failures)
- Test timeout scenarios

### 4. Cross-Environment Testing
- Development (localhost with port)
- Production (custom domain)
- Different OAuth response formats

## Implementation Notes

### URL Handling Strategy
The design supports both `/callback` and `/callback.html` routes to ensure backward compatibility and handle various OAuth provider redirect configurations.

### State Persistence
During callback processing, the application state should be preserved to handle page refreshes or navigation interruptions.

### Security Considerations
- Tokens should be processed immediately and not logged
- Error messages should not expose sensitive information
- Callback URLs should validate origin to prevent CSRF attacks