# Implementation Plan

- [x] 1. Update AuthService to use Flutter route for callback
  - Modify `getLoginUrl()` method to generate `/callback` instead of `/callback.html`
  - Update redirect URI construction to use Flutter route
  - Add logging to track URL generation changes
  - _Requirements: 1.1, 2.2_

- [x] 2. Enhance main.dart routing configuration
  - Update `onGenerateRoute` to handle both `/callback` and `/callback.html` paths
  - Ensure CallbackScreen is properly instantiated for callback routes
  - Add route logging for debugging callback navigation
  - _Requirements: 1.1, 2.1, 2.2_

- [x] 3. Improve CallbackScreen token extraction and processing
  - Create robust token extraction logic for URL fragments and query parameters
  - Add comprehensive error handling for missing or invalid tokens
  - Implement proper loading states and user feedback messages
  - Add timeout handling for long-running callback operations
  - _Requirements: 1.2, 1.3, 1.4, 3.1, 3.2, 3.3, 3.4_

- [x] 4. Add callback state management and user feedback
  - Implement CallbackState enum for tracking processing states
  - Add loading indicators and progress messages
  - Create error display components with retry options
  - Add success confirmation before dashboard redirect
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [x] 5. Create comprehensive error handling system
  - Implement specific error handling for different failure scenarios
  - Add retry logic with exponential backoff for network errors
  - Create user-friendly error messages for each error type
  - Add fallback navigation to login screen with appropriate delays
  - _Requirements: 1.4, 3.3_

- [x] 6. Write unit tests for token extraction logic
  - Test token extraction from URL fragments
  - Test token extraction from query parameters
  - Test error handling for malformed URLs
  - Test edge cases with missing or invalid tokens
  - _Requirements: 1.2_

- [x] 7. Write integration tests for callback flow
  - Test complete OAuth callback processing flow
  - Test route navigation from callback to dashboard
  - Test error scenarios and fallback navigation
  - Test state management during callback processing
  - _Requirements: 1.1, 1.3, 1.4_

- [x] 8. Remove or repurpose static callback.html file
  - Evaluate whether to remove callback.html completely or keep as fallback
  - Update any references to callback.html in documentation or comments
  - Clean up unused callback handling code if removing HTML file
  - _Requirements: 2.2_