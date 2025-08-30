import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'dart:html' as html;
import '../services/auth_service.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  bool _isLoggingIn = false;
  int _currentStrategy = 0;

  Future<void> _login(BuildContext context) async {
    setState(() {
      _isLoggingIn = true;
    });

    try {
      final authService = Provider.of<AuthService>(context, listen: false);
      final loginUrls = authService.getLoginUrls();
      
      debugPrint('=== TRYING MULTIPLE OAUTH STRATEGIES ===');
      debugPrint('Available strategies: ${loginUrls.length}');
      
      // Try the current strategy
      final loginUrl = loginUrls[_currentStrategy];
      debugPrint('Trying strategy ${_currentStrategy + 1}/${loginUrls.length}: $loginUrl');

      // For Flutter Web - redirect in the same window
      html.window.location.href = loginUrl;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Login failed: $e'),
            backgroundColor: Colors.red,
          ),
        );
        setState(() {
          _isLoggingIn = false;
        });
      }
    }
  }

  Future<void> _tryNextStrategy(BuildContext context) async {
    final authService = Provider.of<AuthService>(context, listen: false);
    final loginUrls = authService.getLoginUrls();
    
    if (_currentStrategy < loginUrls.length - 1) {
      setState(() {
        _currentStrategy++;
        _isLoggingIn = true;
      });
      
      debugPrint('Trying next strategy: ${_currentStrategy + 1}/${loginUrls.length}');
      final loginUrl = loginUrls[_currentStrategy];
      html.window.location.href = loginUrl;
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('All OAuth strategies have been tried. Please check your configuration.'),
          backgroundColor: Colors.orange,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('KingsChat Login'),
        backgroundColor: Colors.blue[700],
        foregroundColor: Colors.white,
      ),
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.blue[50]!, Colors.white],
          ),
        ),
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Logo or Icon
                Container(
                  width: 100,
                  height: 100,
                  decoration: BoxDecoration(
                    color: Colors.blue[700],
                    borderRadius: BorderRadius.circular(50),
                  ),
                  child: const Icon(
                    Icons.chat,
                    size: 50,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 30),
                
                // Welcome Text
                const Text(
                  'Welcome to KingsChat',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    color: Colors.black87,
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Connect with your KingsChat account to continue',
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.black54,
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 50),
                
                // Login Button
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    onPressed: _isLoggingIn ? null : () => _login(context),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue[700],
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(25),
                      ),
                    ),
                    child: _isLoggingIn
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : Text(
                            'Login with KingsChat (Strategy ${_currentStrategy + 1})',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                          ),
                  ),
                ),
                
                const SizedBox(height: 15),
                
                // Try Next Strategy Button
                if (_currentStrategy < 3) // We have 4 strategies (0-3)
                  SizedBox(
                    width: double.infinity,
                    height: 45,
                    child: OutlinedButton(
                      onPressed: _isLoggingIn ? null : () => _tryNextStrategy(context),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.blue[700],
                        side: BorderSide(color: Colors.blue[700]!),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(25),
                        ),
                      ),
                      child: Text(
                        'Try Alternative Method (${_currentStrategy + 2}/4)',
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                      ),
                    ),
                  ),
                
                const SizedBox(height: 30),
                
                // Error Display
                Consumer<AuthService>(
                  builder: (context, authService, child) {
                    if (authService.error != null) {
                      return Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.red[50],
                          border: Border.all(color: Colors.red[200]!),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.error_outline, color: Colors.red[700]),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                authService.error!,
                                style: TextStyle(color: Colors.red[700]),
                              ),
                            ),
                          ],
                        ),
                      );
                    }
                    return const SizedBox.shrink();
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
