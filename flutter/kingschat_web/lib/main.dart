import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_web_plugins/url_strategy.dart';
import 'package:kingschat_web/services/auth_service.dart';
import 'package:kingschat_web/screens/login_screen.dart';
import 'package:kingschat_web/screens/dashboard_screen.dart';
import 'package:kingschat_web/screens/callback_screen.dart';

void main() {
  // Use hash-based routing for better compatibility with dev server
  setUrlStrategy(const HashUrlStrategy());
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthService(),
      child: Consumer<AuthService>(
        builder: (context, authService, child) {
          return MaterialApp(
            title: 'KingsChat Auth Demo',
            theme: ThemeData(
              primarySwatch: Colors.blue,
              visualDensity: VisualDensity.adaptivePlatformDensity,
            ),
            initialRoute: '/',
            onGenerateRoute: (settings) {
              // Log route navigation for debugging
              debugPrint('[Router] Navigating to: ${settings.name}');
              debugPrint('[Router] Current URL: ${Uri.base}');
              debugPrint('[Router] URL fragment: ${Uri.base.fragment}');
              
              // Handle URL fragments for web authentication callback
              if (settings.name == '/' && 
                  Uri.base.fragment.contains('access_token')) {
                debugPrint('[Router] Detected OAuth callback in URL fragment, routing to CallbackScreen');
                return MaterialPageRoute(
                  builder: (context) => const CallbackScreen(),
                );
              }
              
              switch (settings.name) {
                case '/':
                  if (authService.isAuthenticated) {
                    debugPrint('[Router] User authenticated, routing to DashboardScreen');
                    return MaterialPageRoute(
                      builder: (context) => const DashboardScreen(),
                    );
                  } else {
                    debugPrint('[Router] User not authenticated, routing to LoginScreen');
                    return MaterialPageRoute(
                      builder: (context) => const LoginScreen(),
                    );
                  }
                case '/callback':
                case '/callback.html':
                  debugPrint('[Router] OAuth callback route detected, routing to CallbackScreen');
                  return MaterialPageRoute(
                    builder: (context) => const CallbackScreen(),
                  );
                default:
                  debugPrint('[Router] Unknown route ${settings.name}, routing to LoginScreen');
                  return MaterialPageRoute(
                    builder: (context) => const LoginScreen(),
                  );
              }
            },
          );
        },
      ),
    );
  }
}
