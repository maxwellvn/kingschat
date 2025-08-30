import 'package:flutter_test/flutter_test.dart';
import 'package:kingschat_web/screens/callback_screen.dart';

void main() {
  group('CallbackTokenExtractor', () {
    test('should extract tokens from URL fragment', () {
      final uri = Uri.parse('http://localhost:3000/callback#access_token=abc123&refresh_token=def456&token_type=bearer');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNotNull);
      expect(result!['access_token'], equals('abc123'));
      expect(result['refresh_token'], equals('def456'));
      expect(result['token_type'], equals('bearer'));
    });

    test('should extract tokens from query parameters', () {
      final uri = Uri.parse('http://localhost:3000/callback?access_token=xyz789&refresh_token=uvw012');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNotNull);
      expect(result!['access_token'], equals('xyz789'));
      expect(result['refresh_token'], equals('uvw012'));
    });

    test('should prioritize fragment over query parameters', () {
      final uri = Uri.parse('http://localhost:3000/callback?access_token=query_token#access_token=fragment_token');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNotNull);
      expect(result!['access_token'], equals('fragment_token'));
    });

    test('should return null when no tokens are found', () {
      final uri = Uri.parse('http://localhost:3000/callback');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNull);
    });

    test('should return null when only non-token parameters are present', () {
      final uri = Uri.parse('http://localhost:3000/callback#state=somestate&code=somecode');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNull);
    });

    test('should throw exception when error parameter is present in fragment', () {
      final uri = Uri.parse('http://localhost:3000/callback#error=access_denied&error_description=User%20denied%20access');
      
      expect(
        () => CallbackTokenExtractor.extractFromUrl(uri),
        throwsA(isA<Exception>().having(
          (e) => e.toString(),
          'message',
          contains('OAuth error: access_denied - User denied access'),
        )),
      );
    });

    test('should throw exception when error parameter is present in query', () {
      final uri = Uri.parse('http://localhost:3000/callback?error=invalid_request');
      
      expect(
        () => CallbackTokenExtractor.extractFromUrl(uri),
        throwsA(isA<Exception>().having(
          (e) => e.toString(),
          'message',
          contains('OAuth error: invalid_request'),
        )),
      );
    });

    test('should handle URL encoded parameters correctly', () {
      final uri = Uri.parse('http://localhost:3000/callback#access_token=abc%2B123&refresh_token=def%2F456');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNotNull);
      expect(result!['access_token'], equals('abc+123'));
      expect(result['refresh_token'], equals('def/456'));
    });

    test('should handle empty fragment gracefully', () {
      final uri = Uri.parse('http://localhost:3000/callback#');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNull);
    });

    test('should handle malformed fragment gracefully', () {
      final uri = Uri.parse('http://localhost:3000/callback#invalid_fragment_format');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNull);
    });

    test('should extract access_token even without refresh_token', () {
      final uri = Uri.parse('http://localhost:3000/callback#access_token=solo_token');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNotNull);
      expect(result!['access_token'], equals('solo_token'));
      expect(result['refresh_token'], isNull);
    });

    test('should handle complex OAuth response with multiple parameters', () {
      final uri = Uri.parse('http://localhost:3000/callback#access_token=complex_token&refresh_token=refresh_complex&token_type=Bearer&expires_in=3600&scope=read%20write');
      
      final result = CallbackTokenExtractor.extractFromUrl(uri);
      
      expect(result, isNotNull);
      expect(result!['access_token'], equals('complex_token'));
      expect(result['refresh_token'], equals('refresh_complex'));
      expect(result['token_type'], equals('Bearer'));
      expect(result['expires_in'], equals('3600'));
      expect(result['scope'], equals('read write'));
    });
  });
}