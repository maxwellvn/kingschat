# KingsChat Blast - Flutter Web

A Flutter web application that replicates the KingsChat authentication process and dashboard functionality.

## Features

- **Authentication System**: Replicates the OAuth flow from the PHP version
- **Modern UI**: Clean, professional interface using Material Design 3
- **Responsive Design**: Works on desktop and mobile browsers
- **State Management**: Uses Provider for state management
- **Routing**: Uses GoRouter for web-friendly routing
- **Local Storage**: Persists authentication state using SharedPreferences

## Project Structure

```
lib/
├── main.dart                 # App entry point and routing configuration
├── models/
│   ├── user_model.dart      # User data models with JSON serialization
│   └── user_model.g.dart    # Generated JSON serialization code
├── services/
│   └── auth_service.dart    # Authentication service and API calls
└── screens/
    ├── login_screen.dart    # Login page with KingsChat branding
    ├── dashboard_screen.dart # Main dashboard with user info and navigation
    └── callback_screen.dart # OAuth callback handler
```

## Development Setup

### Prerequisites
- Flutter SDK (latest stable version)
- Web browser for testing

### Installation
```bash
cd flutter/kingschat_web
flutter pub get
dart run build_runner build  # Generate JSON serialization code
```

### Running the App
```bash
flutter run -d web-server --web-port 8080
```

The app will be available at `http://localhost:8080`

## Authentication Flow

1. User clicks "Continue with KingsChat" on login screen
2. App simulates OAuth redirect (demo mode)
3. Callback screen processes authentication
4. User is redirected to dashboard upon successful authentication

## Configuration

### API Endpoints
- KingsChat API: `https://connect.kingsch.at/api`
- OAuth Provider: `https://accounts.kingsch.at`
- Client ID: `619b30ea-a682-47fb-b90f-5b8e780b89ca`

### Routes
- `/` - Login screen or dashboard (based on auth state)
- `/dashboard` - Main dashboard
- `/auth/callback` - OAuth callback handler

## Demo Mode

Currently configured in demo mode for development:
- Login simulation without actual OAuth
- Placeholder user data
- Local token storage

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.
