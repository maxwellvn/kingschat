import 'dart:convert';
import 'package:flutter/widgets.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';

class AuthService extends ChangeNotifier {
  static const String _baseUrl = 'https://connect.kingsch.at/api';
  static const String _authUrl = 'https://accounts.kingsch.at';
  static const String _clientId = '619b30ea-a682-47fb-b90f-5b8e780b89ca';
  static const List<String> _scopes = ['conference_calls'];
  
  AuthTokens? _tokens;
  KingsChatUserData? _userData;
  bool _isLoading = false;
  String? _error;

  // Getters
  AuthTokens? get tokens => _tokens;
  KingsChatUserData? get userData => _userData;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _tokens != null && !_tokens!.isExpired;
  UserModel? get currentUser => _userData?.profile.user;

  AuthService() {
    _log('AuthService: Constructor called');
    _loadStoredAuth();
  }

  void _log(String message) {
    final timestamp = DateTime.now().toIso8601String();
    final logMessage = '[$timestamp] [AuthService] $message';
    print(logMessage);
    debugPrint(logMessage);
  }

  void _setLoading(bool loading) {
    _log('Setting loading state: $loading');
    _isLoading = loading;
    notifyListeners();
  }

  void _setError(String? error) {
    _log('Setting error: $error');
    _error = error;
    notifyListeners();
  }

  // Load stored authentication data
  Future<void> _loadStoredAuth() async {
    _log('Loading stored authentication data...');
    try {
      final prefs = await SharedPreferences.getInstance();
      final tokenData = prefs.getString('auth_tokens');
      final userData = prefs.getString('user_data');
      
      _log('Stored token data: ${tokenData != null ? 'Found' : 'Not found'}');
      _log('Stored user data: ${userData != null ? 'Found' : 'Not found'}');
      
      if (tokenData != null) {
        _tokens = AuthTokens.fromJson(jsonDecode(tokenData));
        final expiresAtMs = (_tokens!.expiresAt ?? 0) * 1000;
        _log('Loaded tokens from storage - expires: ${DateTime.fromMillisecondsSinceEpoch(expiresAtMs.toInt())}');
        
        // Check if token is expired
        if (_tokens!.isExpired) {
          _log('Token is expired, attempting refresh...');
          await _refreshToken();
        } else {
          _log('Token is still valid');
        }
      }
      
      if (userData != null) {
        _userData = KingsChatUserData.fromJson(jsonDecode(userData));
        _log('Loaded user data from storage - user: ${_userData?.profile.user.name}');
      }
      
      notifyListeners();
      _log('Stored auth loading completed');
    } catch (e) {
      _log('Error loading stored auth: $e');
    }
  }

  // Save authentication data
  Future<void> _saveAuth() async {
    _log('Saving authentication data...');
    try {
      final prefs = await SharedPreferences.getInstance();
      
      if (_tokens != null) {
        final tokenJson = jsonEncode(_tokens!.toJson());
        await prefs.setString('auth_tokens', tokenJson);
        _log('Saved tokens to storage');
      }
      
      if (_userData != null) {
        final userJson = jsonEncode(_userData!.toJson());
        await prefs.setString('user_data', userJson);
        _log('Saved user data to storage');
      }
      
      _log('Auth data saved successfully');
    } catch (e) {
      _log('Error saving auth: $e');
    }
  }

  // Multiple OAuth login strategies - tries different approaches
  List<String> getLoginUrls() {
    _log('Generating multiple OAuth login URLs...');
    
    final currentUrl = Uri.base;
    _log('Current base URL: $currentUrl');
    
    // Handle port properly - if default ports, don't include them
    String portPart = '';
    if ((currentUrl.scheme == 'http' && currentUrl.port != 80) ||
        (currentUrl.scheme == 'https' && currentUrl.port != 443)) {
      portPart = ':${currentUrl.port}';
    }
    
    final baseRedirectUri = '${currentUrl.scheme}://${currentUrl.host}$portPart';
    _log('Base redirect URI: $baseRedirectUri');
    
    List<String> oauthUrls = [];
    
    // Strategy 1: Standard implicit flow with /callback
    final redirectUri1 = '$baseRedirectUri/callback';
    final params1 = {
      'client_id': _clientId,
      'scopes': jsonEncode(_scopes),
      'redirect_uri': redirectUri1,
      'response_type': 'token',
      'state': 'flutter_web_auth_v1',
    };
    final queryString1 = params1.entries
        .map((e) => '${Uri.encodeComponent(e.key)}=${Uri.encodeComponent(e.value)}')
        .join('&');
    final oauthUrl1 = '$_authUrl/?$queryString1';
    oauthUrls.add(oauthUrl1);
    _log('Strategy 1 - Standard implicit: $oauthUrl1');
    
    // Strategy 2: With post_redirect for compatibility
    final params2 = {
      'client_id': _clientId,
      'scopes': jsonEncode(_scopes),
      'redirect_uri': redirectUri1,
      'response_type': 'token',
      'state': 'flutter_web_auth_v2',
      'post_redirect': 'true',
    };
    final queryString2 = params2.entries
        .map((e) => '${Uri.encodeComponent(e.key)}=${Uri.encodeComponent(e.value)}')
        .join('&');
    final oauthUrl2 = '$_authUrl/?$queryString2';
    oauthUrls.add(oauthUrl2);
    _log('Strategy 2 - With post_redirect: $oauthUrl2');
    
    // Strategy 3: Authorization code flow (fallback)
    final params3 = {
      'client_id': _clientId,
      'scopes': jsonEncode(_scopes),
      'redirect_uri': redirectUri1,
      'response_type': 'code',
      'state': 'flutter_web_auth_v3',
    };
    final queryString3 = params3.entries
        .map((e) => '${Uri.encodeComponent(e.key)}=${Uri.encodeComponent(e.value)}')
        .join('&');
    final oauthUrl3 = '$_authUrl/?$queryString3';
    oauthUrls.add(oauthUrl3);
    _log('Strategy 3 - Authorization code: $oauthUrl3');
    
    // Strategy 4: Different scopes format
    final params4 = {
      'client_id': _clientId,
      'scopes': _scopes.join(' '), // Space-separated instead of JSON
      'redirect_uri': redirectUri1,
      'response_type': 'token',
      'state': 'flutter_web_auth_v4',
    };
    final queryString4 = params4.entries
        .map((e) => '${Uri.encodeComponent(e.key)}=${Uri.encodeComponent(e.value)}')
        .join('&');
    final oauthUrl4 = '$_authUrl/?$queryString4';
    oauthUrls.add(oauthUrl4);
    _log('Strategy 4 - Space-separated scopes: $oauthUrl4');
    
    return oauthUrls;
  }
  
  // Primary login URL (Strategy 1)
  String getLoginUrl() {
    final urls = getLoginUrls();
    return urls.first;
  }

  // Handle OAuth callback - supports both token and code flows
  Future<bool> handleCallback(String accessToken, [String? refreshToken]) async {
    _log('=== HANDLING OAUTH CALLBACK ===');
    _log('Access token length: ${accessToken.length}');
    _log('Refresh token: ${refreshToken != null ? 'Provided (${refreshToken.length} chars)' : 'Not provided'}');
    
    // Use post frame callback to avoid setState during build
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _setLoading(true);
      _setError(null);
    });

    try {
      _log('Creating AuthTokens object...');
      // Create tokens object
      _tokens = AuthTokens(
        accessToken: accessToken,
        refreshToken: refreshToken,
        expiresAt: DateTime.now().add(const Duration(hours: 1)).millisecondsSinceEpoch ~/ 1000,
      );
      final expiresAtMs = (_tokens!.expiresAt ?? 0) * 1000;
      _log('AuthTokens created - expires at: ${DateTime.fromMillisecondsSinceEpoch(expiresAtMs.toInt())}');

      // Fetch user profile
      _log('Fetching user profile...');
      final success = await _fetchUserProfile();
      _log('Profile fetch result: $success');

      if (success) {
        _log('Saving auth data...');
        await _saveAuth();
        _log('Auth data saved, notifying listeners...');
        WidgetsBinding.instance.addPostFrameCallback((_) {
          notifyListeners();
        });
        _log('✅ OAuth callback handled successfully');
        return true;
      } else {
        _log('❌ Profile fetch failed');
        return false;
      }
    } catch (e) {
      _log('❌ Exception in handleCallback: $e');
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _setError('Login failed: $e');
      });
      return false;
    } finally {
      _log('OAuth callback processing finished, setting loading to false');
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _setLoading(false);
      });
    }
  }

  // Fetch user profile from API with retry logic
  Future<bool> _fetchUserProfile({int retryCount = 0}) async {
    _log('=== FETCHING USER PROFILE (Attempt ${retryCount + 1}) ===');
    
    if (_tokens == null) {
      _log('❌ No tokens available for profile fetch');
      _setError('Authentication tokens are missing');
      return false;
    }

    try {
      final tokenPreview = _tokens!.accessToken.length > 20
          ? '${_tokens!.accessToken.substring(0, 20)}...'
          : _tokens!.accessToken;
      _log('Using access token: $tokenPreview');
      _log('Making GET request to: $_baseUrl/profile');

      final headers = {
        'Authorization': 'Bearer ${_tokens!.accessToken}',
        'Content-Type': 'application/json',
      };
      
      _log('Request headers:');
      headers.forEach((key, value) {
        if (key == 'Authorization') {
          _log('  $key: Bearer ${value.substring(7, 27)}...');
        } else {
          _log('  $key: $value');
        }
      });

      final response = await http.get(
        Uri.parse('$_baseUrl/profile'),
        headers: headers,
      ).timeout(const Duration(seconds: 10));

      _log('Profile API Response:');
      _log('  Status: ${response.statusCode}');
      _log('  Headers: ${response.headers}');
      _log('  Body length: ${response.body.length}');
      _log('  Body preview: ${response.body.length > 200 ? response.body.substring(0, 200) + '...' : response.body}');

      if (response.statusCode == 200) {
        _log('✅ Profile API call successful');
        try {
          final data = jsonDecode(response.body);
          _log('JSON parsing successful');
          _log('Response data keys: ${data.keys.toList()}');
          
          _userData = KingsChatUserData.fromJson(data);
          _log('KingsChatUserData created successfully');
          _log('User name: ${_userData?.profile.user.name}');
          _log('User ID: ${_userData?.profile.user.userId}');
          _log('User verified: ${_userData?.profile.user.verified}');
          
          return true;
        } catch (e) {
          _log('❌ Error parsing profile response: $e');
          _setError('Unable to process user profile data. Please try again.');
          return false;
        }
      } else if (response.statusCode == 401) {
        _log('❌ Authentication token expired or invalid');
        _setError('Your session has expired. Please log in again.');
        return false;
      } else if (response.statusCode >= 500) {
        // Server error - retry with exponential backoff
        if (retryCount < 2) {
          final delaySeconds = (retryCount + 1) * 2;
          _log('Server error ${response.statusCode}, retrying in ${delaySeconds}s...');
          await Future.delayed(Duration(seconds: delaySeconds));
          return _fetchUserProfile(retryCount: retryCount + 1);
        } else {
          _log('❌ Server error after ${retryCount + 1} attempts');
          _setError('Server is temporarily unavailable. Please try again later.');
          return false;
        }
      } else {
        final errorMsg = 'Profile API error: ${response.statusCode}';
        _log('❌ $errorMsg');
        _setError('Unable to fetch user profile. Please try again.');
        return false;
      }
    } catch (e) {
      _log('❌ Network error fetching profile: $e');
      
      // Retry on network errors
      if (retryCount < 2 && (e.toString().contains('timeout') || e.toString().contains('connection'))) {
        final delaySeconds = (retryCount + 1) * 2;
        _log('Network error, retrying in ${delaySeconds}s...');
        await Future.delayed(Duration(seconds: delaySeconds));
        return _fetchUserProfile(retryCount: retryCount + 1);
      } else {
        _setError('Connection error. Please check your internet connection and try again.');
        return false;
      }
    }
  }

  // Refresh access token - matches PHP implementation
  Future<bool> _refreshToken() async {
    if (_tokens?.refreshToken == null) return false;
    
    try {
      debugPrint('Refreshing token with refresh token: ${_tokens!.refreshToken!.substring(0, 20)}...');
      
      // Use the same endpoint and parameters as PHP
      final response = await http.post(
        Uri.parse('https://connect.kingsch.at/oauth2/token'),
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
        },
        body: {
          'client_id': _clientId,
          'refresh_token': _tokens!.refreshToken!,
          'grant_type': 'refresh_token',
        },
      );
      
      debugPrint('Token refresh response: ${response.statusCode}');
      debugPrint('Token refresh body: ${response.body}');
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['access_token'] != null) {
          _tokens = AuthTokens(
            accessToken: data['access_token'],
            refreshToken: data['refresh_token'] ?? _tokens!.refreshToken,
            expiresAt: data['expires_in'] != null 
                ? DateTime.now().add(Duration(seconds: data['expires_in'])).millisecondsSinceEpoch ~/ 1000
                : null,
          );
          await _saveAuth();
          debugPrint('Token refreshed successfully');
          return true;
        }
      }
      
      return false;
    } catch (e) {
      debugPrint('Error refreshing token: $e');
      return false;
    }
  }

  // Logout
  Future<void> logout() async {
    _tokens = null;
    _userData = null;
    
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_tokens');
    await prefs.remove('user_data');
    
    notifyListeners();
  }

  // Check if token needs refresh and refresh if needed
  Future<void> ensureValidToken() async {
    if (_tokens != null && _tokens!.needsRefresh()) {
      await _refreshToken();
    }
  }

  // Test method to manually fetch profile
  Future<void> testProfileFetch() async {
    debugPrint('=== Testing Profile Fetch ===');
    debugPrint('Has tokens: ${_tokens != null}');
    if (_tokens != null) {
      debugPrint('Token expires at: ${_tokens!.expiresAt}');
      debugPrint('Token is expired: ${_tokens!.isExpired}');
      debugPrint('Token needs refresh: ${_tokens!.needsRefresh()}');
    }

    final success = await _fetchUserProfile();
    debugPrint('Profile fetch result: $success');
    debugPrint('Current error: $_error');
  }
}
