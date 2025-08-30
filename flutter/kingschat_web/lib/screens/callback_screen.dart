import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';

enum CallbackState {
  processing,
  success,
  error,
  timeout
}

class CallbackTokenExtractor {
  static Map<String, String>? extractFromUrl(Uri uri) {
    debugPrint('[CallbackTokenExtractor] === MULTIPLE EXTRACTION STRATEGIES ===');
    debugPrint('[CallbackTokenExtractor] Extracting tokens from URL: ${uri.toString()}');
    debugPrint('[CallbackTokenExtractor] URL path: ${uri.path}');
    debugPrint('[CallbackTokenExtractor] URL fragment: ${uri.fragment}');
    debugPrint('[CallbackTokenExtractor] URL query: ${uri.query}');
    
    Map<String, String> tokens = {};
    
    // Strategy 1: Extract from URL fragment (#access_token=...)
    if (uri.fragment.isNotEmpty) {
      debugPrint('[CallbackTokenExtractor] Strategy 1: Checking URL fragment');
      try {
        final fragmentParams = Uri.splitQueryString(uri.fragment);
        debugPrint('[CallbackTokenExtractor] Fragment params: $fragmentParams');
        if (fragmentParams['access_token'] != null) {
          tokens.addAll(fragmentParams);
          debugPrint('[CallbackTokenExtractor] ✅ Strategy 1 SUCCESS: Extracted tokens from fragment');
        }
      } catch (e) {
        debugPrint('[CallbackTokenExtractor] Strategy 1 failed: $e');
      }
    }
    
    // Strategy 2: Extract from query parameters (?access_token=...)
    if (tokens.isEmpty && uri.queryParameters.isNotEmpty) {
      debugPrint('[CallbackTokenExtractor] Strategy 2: Checking query parameters');
      try {
        debugPrint('[CallbackTokenExtractor] Query params: ${uri.queryParameters}');
        if (uri.queryParameters['access_token'] != null) {
          tokens.addAll(uri.queryParameters);
          debugPrint('[CallbackTokenExtractor] ✅ Strategy 2 SUCCESS: Extracted tokens from query parameters');
        }
      } catch (e) {
        debugPrint('[CallbackTokenExtractor] Strategy 2 failed: $e');
      }
    }
    
    // Strategy 3: Check for authorization code (for code flow)
    if (tokens.isEmpty) {
      debugPrint('[CallbackTokenExtractor] Strategy 3: Checking for authorization code');
      final code = uri.queryParameters['code'] ?? 
                   (uri.fragment.isNotEmpty ? Uri.splitQueryString(uri.fragment)['code'] : null);
      if (code != null) {
        debugPrint('[CallbackTokenExtractor] ✅ Strategy 3: Found authorization code: ${code.substring(0, 10)}...');
        tokens['code'] = code;
        tokens['grant_type'] = 'authorization_code';
      }
    }
    
    // Strategy 4: Check localStorage (for callback.html compatibility)
    debugPrint('[CallbackTokenExtractor] Strategy 4: Would check localStorage (web only)');
    
    // Strategy 5: Parse complex fragment formats
    if (tokens.isEmpty && uri.fragment.isNotEmpty) {
      debugPrint('[CallbackTokenExtractor] Strategy 5: Trying alternative fragment parsing');
      try {
        // Handle fragments like: #/callback#access_token=...
        final parts = uri.fragment.split('#');
        for (final part in parts) {
          if (part.contains('access_token=')) {
            final params = Uri.splitQueryString(part);
            if (params['access_token'] != null) {
              tokens.addAll(params);
              debugPrint('[CallbackTokenExtractor] ✅ Strategy 5 SUCCESS: Extracted from complex fragment');
              break;
            }
          }
        }
      } catch (e) {
        debugPrint('[CallbackTokenExtractor] Strategy 5 failed: $e');
      }
    }
    
    // Check for error parameters in both fragment and query
    String? error;
    String? errorDescription;
    
    if (uri.fragment.isNotEmpty) {
      try {
        final fragmentParams = Uri.splitQueryString(uri.fragment);
        error = fragmentParams['error'];
        errorDescription = fragmentParams['error_description'];
      } catch (e) {
        debugPrint('[CallbackTokenExtractor] Error parsing fragment for errors: $e');
      }
    }
    
    if (error == null) {
      error = uri.queryParameters['error'];
      errorDescription = uri.queryParameters['error_description'];
    }
    
    if (error != null) {
      debugPrint('[CallbackTokenExtractor] ❌ OAuth error detected: $error');
      throw Exception('OAuth error: $error${errorDescription != null ? ' - $errorDescription' : ''}');
    }
    
    if (tokens.isEmpty) {
      debugPrint('[CallbackTokenExtractor] ❌ No tokens found with any strategy');
      return null;
    }
    
    debugPrint('[CallbackTokenExtractor] ✅ Successfully extracted ${tokens.length} parameters: ${tokens.keys.toList()}');
    return tokens;
  }
}

class CallbackScreen extends StatefulWidget {
  const CallbackScreen({super.key});

  @override
  State<CallbackScreen> createState() => _CallbackScreenState();
}

class _CallbackScreenState extends State<CallbackScreen> {
  CallbackState _state = CallbackState.processing;
  String _statusMessage = 'Processing authentication...';
  String? _errorMessage;
  
  @override
  void initState() {
    super.initState();
    _handleCallback();
  }

  void _updateState(CallbackState newState, String message, [String? error]) {
    if (mounted) {
      setState(() {
        _state = newState;
        _statusMessage = message;
        _errorMessage = error;
      });
    }
  }

  Future<void> _handleCallback() async {
    debugPrint('[CallbackScreen] Starting callback handling...');
    
    // Wait for the widget to be built
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      try {
        final authService = Provider.of<AuthService>(context, listen: false);
        
        // Extract tokens from URL
        final uri = Uri.base;
        debugPrint('[CallbackScreen] Current URL: ${uri.toString()}');
        debugPrint('[CallbackScreen] URL path: ${uri.path}');
        debugPrint('[CallbackScreen] URL fragment: ${uri.fragment}');
        debugPrint('[CallbackScreen] URL query: ${uri.query}');
        
        final tokenData = CallbackTokenExtractor.extractFromUrl(uri);
        debugPrint('[CallbackScreen] Token data result: $tokenData');
        
        if (tokenData != null && tokenData['access_token'] != null) {
          final accessToken = tokenData['access_token']!;
          final refreshToken = tokenData['refresh_token'];
          
          debugPrint('[CallbackScreen] Tokens extracted successfully');
          _updateState(CallbackState.processing, 'Authenticating with KingsChat...');
          
          // Handle the callback with the tokens
          final success = await authService.handleCallback(accessToken, refreshToken);
          
          if (success && mounted) {
            debugPrint('[CallbackScreen] Authentication successful');
            _updateState(CallbackState.success, 'Authentication successful! Redirecting...');
            
            // Wait a moment to show success message
            await Future.delayed(const Duration(seconds: 1));
            
            if (mounted) {
              Navigator.of(context).pushReplacementNamed('/');
            }
          } else if (mounted) {
            debugPrint('[CallbackScreen] Authentication failed');
            _updateState(CallbackState.error, 'Authentication failed', 
                'Unable to complete authentication. Please try again.');
            _redirectToLoginAfterDelay();
          }
        } else {
          // Check if AuthService already has valid authentication
          if (authService.isAuthenticated && mounted) {
            debugPrint('[CallbackScreen] User already authenticated');
            _updateState(CallbackState.success, 'Already authenticated! Redirecting...');
            await Future.delayed(const Duration(seconds: 1));
            if (mounted) {
              Navigator.of(context).pushReplacementNamed('/');
            }
          } else {
            debugPrint('[CallbackScreen] No tokens found in URL');
            _updateState(CallbackState.error, 'Authentication failed', 
                'No authentication data received from OAuth provider.');
            _redirectToLoginAfterDelay();
          }
        }
      } catch (e) {
        debugPrint('[CallbackScreen] Error during callback handling: $e');
        _updateState(CallbackState.error, 'Authentication failed', e.toString());
        _redirectToLoginAfterDelay();
      }
    });
    
    // Set up timeout handling
    Future.delayed(const Duration(seconds: 30), () {
      if (mounted && _state == CallbackState.processing) {
        debugPrint('[CallbackScreen] Callback processing timeout');
        _updateState(CallbackState.timeout, 'Authentication is taking longer than expected...', 
            'The authentication process has timed out. Please try again.');
        _redirectToLoginAfterDelay();
      }
    });
  }

  void _redirectToLoginAfterDelay() {
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) {
        Navigator.of(context).pushReplacementNamed('/');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.blue[50],
      body: Center(
        child: Container(
          constraints: const BoxConstraints(maxWidth: 400),
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // App Icon
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: _getIconColor(),
                  borderRadius: BorderRadius.circular(40),
                ),
                child: Icon(
                  _getStateIcon(),
                  size: 40,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 30),
              
              // Loading indicator (only show when processing)
              if (_state == CallbackState.processing) ...[
                const CircularProgressIndicator(),
                const SizedBox(height: 20),
              ],
              
              // Status message
              Text(
                _statusMessage,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w500,
                  color: _getTextColor(),
                ),
                textAlign: TextAlign.center,
              ),
              
              const SizedBox(height: 10),
              
              // Additional message based on state
              Text(
                _getSubtitleMessage(),
                style: const TextStyle(
                  fontSize: 14,
                  color: Colors.black54,
                ),
                textAlign: TextAlign.center,
              ),
              
              // Error details (if any)
              if (_errorMessage != null) ...[
                const SizedBox(height: 20),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.red[50],
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.red[200]!),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Error Details:',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          color: Colors.red,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _errorMessage!,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.red,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              
              // Retry button for error states
              if (_state == CallbackState.error || _state == CallbackState.timeout) ...[
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: () {
                    Navigator.of(context).pushReplacementNamed('/');
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.blue[700],
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                  ),
                  child: const Text('Return to Login'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
  
  Color _getIconColor() {
    switch (_state) {
      case CallbackState.processing:
        return Colors.blue[700]!;
      case CallbackState.success:
        return Colors.green[700]!;
      case CallbackState.error:
      case CallbackState.timeout:
        return Colors.red[700]!;
    }
  }
  
  IconData _getStateIcon() {
    switch (_state) {
      case CallbackState.processing:
        return Icons.chat;
      case CallbackState.success:
        return Icons.check_circle;
      case CallbackState.error:
      case CallbackState.timeout:
        return Icons.error;
    }
  }
  
  Color _getTextColor() {
    switch (_state) {
      case CallbackState.processing:
        return Colors.black87;
      case CallbackState.success:
        return Colors.green[700]!;
      case CallbackState.error:
      case CallbackState.timeout:
        return Colors.red[700]!;
    }
  }
  
  String _getSubtitleMessage() {
    switch (_state) {
      case CallbackState.processing:
        return 'Please wait while we complete your authentication';
      case CallbackState.success:
        return 'Taking you to your dashboard...';
      case CallbackState.error:
        return 'You will be redirected to the login page shortly';
      case CallbackState.timeout:
        return 'Please try logging in again';
    }
  }
}
