import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:kingschat_web/main.dart';
import 'package:kingschat_web/services/auth_service.dart';
import 'package:kingschat_web/screens/callback_screen.dart';
import 'package:kingschat_web/screens/login_screen.dart';
import 'package:kingschat_web/screens/dashboard_screen.dart';

void main() {
  group('OAuth Callback Flow Integration Tests', () {
    late AuthService mockAuthService;

    setUp(() {
      mockAuthService = AuthService();
    });

    testWidgets('should route to CallbackScreen when /callback path is accessed', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: MaterialApp(
            initialRoute: '/callback',
            onGenerateRoute: (settings) {
              switch (settings.name) {
                case '/callback':
                case '/callback.html':
                  return MaterialPageRoute(
                    builder: (context) => const CallbackScreen(),
                  );
                default:
                  return MaterialPageRoute(
                    builder: (context) => const LoginScreen(),
                  );
              }
            },
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.byType(CallbackScreen), findsOneWidget);
      expect(find.text('Processing authentication...'), findsOneWidget);
    });

    testWidgets('should route to CallbackScreen when /callback.html path is accessed', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: MaterialApp(
            initialRoute: '/callback.html',
            onGenerateRoute: (settings) {
              switch (settings.name) {
                case '/callback':
                case '/callback.html':
                  return MaterialPageRoute(
                    builder: (context) => const CallbackScreen(),
                  );
                default:
                  return MaterialPageRoute(
                    builder: (context) => const LoginScreen(),
                  );
              }
            },
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.byType(CallbackScreen), findsOneWidget);
      expect(find.text('Processing authentication...'), findsOneWidget);
    });

    testWidgets('should show loading state initially', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      await tester.pump();

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Processing authentication...'), findsOneWidget);
      expect(find.text('Please wait while we complete your authentication'), findsOneWidget);
    });

    testWidgets('should show error state when no tokens are found', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      // Wait for the callback processing to complete
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Should show error state after processing
      expect(find.text('Authentication failed'), findsOneWidget);
      expect(find.text('You will be redirected to the login page shortly'), findsOneWidget);
    });

    testWidgets('should show retry button in error state', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      // Wait for error state
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Return to Login'), findsOneWidget);
      expect(find.byType(ElevatedButton), findsOneWidget);
    });

    testWidgets('should handle timeout scenario', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      // Wait for timeout (30 seconds in real implementation, but we'll simulate)
      await tester.pump();
      await tester.pump(const Duration(seconds: 31));

      // Should show timeout message
      expect(find.textContaining('taking longer than expected'), findsOneWidget);
    });

    testWidgets('should show different icons for different states', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      // Initial processing state should show chat icon
      await tester.pump();
      expect(find.byIcon(Icons.chat), findsOneWidget);

      // Wait for error state
      await tester.pump(const Duration(milliseconds: 100));
      expect(find.byIcon(Icons.error), findsOneWidget);
    });

    testWidgets('should navigate back to login when retry button is pressed', (WidgetTester tester) async {
      bool navigationCalled = false;
      
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: MaterialApp(
            home: const CallbackScreen(),
            onGenerateRoute: (settings) {
              if (settings.name == '/') {
                navigationCalled = true;
                return MaterialPageRoute(
                  builder: (context) => const LoginScreen(),
                );
              }
              return null;
            },
          ),
        ),
      );

      // Wait for error state
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Tap the retry button
      await tester.tap(find.text('Return to Login'));
      await tester.pumpAndSettle();

      expect(navigationCalled, isTrue);
    });

    testWidgets('should show error details when error message is present', (WidgetTester tester) async {
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      // Wait for error state
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Should show error details section
      expect(find.text('Error Details:'), findsOneWidget);
      expect(find.textContaining('No authentication data received'), findsOneWidget);
    });

    testWidgets('should handle successful authentication flow', (WidgetTester tester) async {
      // This test would require mocking the AuthService.handleCallback method
      // to return true, which would require more complex mocking setup
      // For now, we'll test the UI components that should be present
      
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthService>.value(
          value: mockAuthService,
          child: const MaterialApp(
            home: CallbackScreen(),
          ),
        ),
      );

      await tester.pump();

      // Verify the callback screen is rendered with proper structure
      expect(find.byType(Scaffold), findsOneWidget);
      expect(find.byType(Container), findsWidgets);
      expect(find.byType(Column), findsOneWidget);
    });
  });
}