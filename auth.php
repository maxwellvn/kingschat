<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Authentication">
    <meta name="robots" content="noindex, nofollow">
    <title>Authentication - KingsChat Blast</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'firebase': '#FFCA28',
                        'firebase-dark': '#FFA000',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
        <div class="text-center mb-10">
            <img src="https://www.gstatic.com/mobilesdk/160503_mobilesdk/logo/2x/firebase_96dp.png" 
                 alt="Firebase Logo" 
                 class="h-16 mx-auto mb-6 transform hover:scale-105 transition-transform duration-300">
            <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Authentication Required</h2>
            <p class="text-sm text-gray-600">
                Please sign in to continue to KingsChat Blast
            </p>
        </div>

        <div id="error-message" class="rounded-lg bg-red-50 p-4 mb-6 hidden">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p id="error-text" class="text-sm font-medium text-red-800"></p>
                </div>
            </div>
        </div>

        <div class="bg-white py-8 px-6 shadow-xl rounded-xl border border-gray-100 backdrop-blur-sm backdrop-filter">
            <!-- Login Form -->
            <div id="login-form" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" 
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                            Remember me
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="#" id="forgot-password" class="font-medium text-indigo-600 hover:text-indigo-500">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div>
                    <button id="login-button" type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Sign in
                    </button>
                </div>

                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Or continue with</span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-3">
                        <button id="google-login" type="button" 
                                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <svg class="w-5 h-5 mr-2" viewBox="0 0 48 48">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                <path fill="none" d="M0 0h48v48H0z"/>
                            </svg>
                            Sign in with Google
                        </button>
                    </div>
                </div>
            </div>

            <!-- Register Form (Hidden by default) -->
            <div id="register-form" class="space-y-6 hidden">
                <div>
                    <label for="register-email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <div class="mt-1">
                        <input id="register-email" name="email" type="email" autocomplete="email" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <label for="register-password" class="block text-sm font-medium text-gray-700">Password</label>
                    <div class="mt-1">
                        <input id="register-password" name="password" type="password" autocomplete="new-password" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <div class="mt-1">
                        <input id="confirm-password" name="confirm-password" type="password" autocomplete="new-password" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <button id="register-button" type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Register
                    </button>
                </div>
            </div>

            <!-- Reset Password Form (Hidden by default) -->
            <div id="reset-form" class="space-y-6 hidden">
                <div>
                    <label for="reset-email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <div class="mt-1">
                        <input id="reset-email" name="email" type="email" autocomplete="email" required 
                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div>
                    <button id="reset-button" type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Send Reset Link
                    </button>
                </div>
            </div>

            <div class="mt-6 text-center text-sm">
                <p id="login-text">
                    Don't have an account? 
                    <a href="#" id="show-register" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Register
                    </a>
                </p>
                <p id="register-text" class="hidden">
                    Already have an account? 
                    <a href="#" id="show-login" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Sign in
                    </a>
                </p>
                <p id="reset-text" class="hidden">
                    Remember your password? 
                    <a href="#" id="back-to-login" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Back to login
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-auth-compat.js"></script>

    <script>
        // Debugging function
        function debugAuth(message) {
            console.log(`[Auth Debug] ${message}`);
            // Uncomment the line below to show debug messages on the page
            // document.getElementById('error-text').textContent = message;
            // document.getElementById('error-message').classList.remove('hidden');
        }
        
        // Check for logout flags before initializing Firebase
        const urlParams = new URLSearchParams(window.location.search);
        const isLoggingOut = urlParams.get('logout') === 'true' || 
                            urlParams.get('force_logout') === 'true' || 
                            localStorage.getItem('firebase_logging_out') === 'true';
        
        debugAuth(`Initial state - isLoggingOut: ${isLoggingOut}`);
        
        // If we're in the process of logging out, clear any Firebase token
        if (isLoggingOut) {
            document.cookie = "firebase_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            // Clear the localStorage flag
            localStorage.removeItem('firebase_logging_out');
            debugAuth('Clearing Firebase token and logout flag');
        }
        
        // Firebase configuration
        const firebaseConfig = {
            apiKey: "AIzaSyC57H6iPn2URZXL0NhMyqDB-5kf4xrQfW0",
            authDomain: "gpdreporting.firebaseapp.com",
            projectId: "gpdreporting",
            storageBucket: "gpdreporting.firebasestorage.app",
            messagingSenderId: "307075634730",
            appId: "1:307075634730:web:abb1b8e0fadd20448fd553"
        };

        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);

        // Configure auth persistence and settings
        firebase.auth().setPersistence(firebase.auth.Auth.Persistence.LOCAL)
            .then(() => {
                debugAuth('Auth persistence set to LOCAL');
            })
            .catch((error) => {
                console.error('Error setting auth persistence:', error);
            });

        // Configure auth settings
        const auth = firebase.auth();
        auth.useDeviceLanguage();
        
        // Handle redirect result
        firebase.auth().getRedirectResult().then((result) => {
            if (result.user) {
                debugAuth('Got redirect result, user signed in');
                // Hide any error messages that might be showing
                errorMessage.classList.add('hidden');
                // Get the token and redirect
                return result.user.getIdToken().then((idToken) => {
                    debugAuth('Got token from redirect, setting cookie and redirecting');
                    // Store the token in a session cookie
                    document.cookie = "firebase_token=" + idToken + "; path=/";
                    // Redirect to index.php
                    window.location.href = 'index.php';
                });
            }
        }).catch((error) => {
            if (error.code !== 'auth/credential-already-in-use') {
                debugAuth(`Error handling redirect result: ${error.message}`);
                // Show user-friendly error message
                let errorMessage = 'An error occurred during Google sign-in. Please try again.';
                if (error.code === 'auth/network-request-failed') {
                    errorMessage = 'Network error. Please check your internet connection and try again.';
                } else if (error.code === 'auth/user-disabled') {
                    errorMessage = 'This account has been disabled. Please contact support.';
                }
                showError(errorMessage);
            }
        });
        
        // If we're logging out, sign out immediately
        if (isLoggingOut) {
            firebase.auth().signOut().then(() => {
                console.log('Successfully signed out from Firebase');
                // Clear the logout parameter from the URL to prevent signing out again on refresh
                if (history.pushState) {
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.pushState({path: newUrl}, '', newUrl);
                }
            }).catch((error) => {
                console.error('Error signing out from Firebase:', error);
            });
        }
        
        // DOM elements
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        const resetForm = document.getElementById('reset-form');
        const loginText = document.getElementById('login-text');
        const registerText = document.getElementById('register-text');
        const resetText = document.getElementById('reset-text');
        const showRegister = document.getElementById('show-register');
        const showLogin = document.getElementById('show-login');
        const forgotPassword = document.getElementById('forgot-password');
        const backToLogin = document.getElementById('back-to-login');
        const errorMessage = document.getElementById('error-message');
        const errorText = document.getElementById('error-text');

        // Form switching
        showRegister.addEventListener('click', (e) => {
            e.preventDefault();
            loginForm.classList.add('hidden');
            registerForm.classList.remove('hidden');
            resetForm.classList.add('hidden');
            loginText.classList.add('hidden');
            registerText.classList.remove('hidden');
            resetText.classList.add('hidden');
            errorMessage.classList.add('hidden');
        });

        showLogin.addEventListener('click', (e) => {
            e.preventDefault();
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
            resetForm.classList.add('hidden');
            loginText.classList.remove('hidden');
            registerText.classList.add('hidden');
            resetText.classList.add('hidden');
            errorMessage.classList.add('hidden');
        });

        forgotPassword.addEventListener('click', (e) => {
            e.preventDefault();
            loginForm.classList.add('hidden');
            registerForm.classList.add('hidden');
            resetForm.classList.remove('hidden');
            loginText.classList.add('hidden');
            registerText.classList.add('hidden');
            resetText.classList.remove('hidden');
            errorMessage.classList.add('hidden');
        });

        backToLogin.addEventListener('click', (e) => {
            e.preventDefault();
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
            resetForm.classList.add('hidden');
            loginText.classList.remove('hidden');
            registerText.classList.add('hidden');
            resetText.classList.add('hidden');
            errorMessage.classList.add('hidden');
        });

        // Show error message
        function showError(message) {
            errorText.textContent = message;
            errorMessage.classList.remove('hidden');
        }

        // Email/Password Login
        document.getElementById('login-button').addEventListener('click', () => {
            debugAuth('Login button clicked');
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('remember-me').checked;

            if (!email || !password) {
                showError('Please enter both email and password');
                return;
            }

            // Show loading state
            const loginButton = document.getElementById('login-button');
            const originalButtonText = loginButton.innerHTML;
            loginButton.disabled = true;
            loginButton.innerHTML = 'Signing in...';
            debugAuth('Attempting to sign in with email and password');

            firebase.auth().setPersistence(rememberMe ? 
                firebase.auth.Auth.Persistence.LOCAL : 
                firebase.auth.Auth.Persistence.SESSION
            ).then(() => {
                return firebase.auth().signInWithEmailAndPassword(email, password);
            }).then((userCredential) => {
                // Sign-in successful, get the token and redirect manually
                return userCredential.user.getIdToken();
            }).then((idToken) => {
                // Store the token in a session cookie
                document.cookie = "firebase_token=" + idToken + "; path=/";
                // Redirect to index.php
                window.location.href = 'index.php';
            }).catch((error) => {
                // Reset button state
                loginButton.disabled = false;
                loginButton.innerHTML = originalButtonText;
                showError(error.message);
            });
        });

        // Google Sign In
        document.getElementById('google-login').addEventListener('click', () => {
            debugAuth('Google login button clicked');
            
            // Check if Firebase is properly initialized
            if (typeof firebase === 'undefined' || !firebase.auth) {
                showError('Firebase authentication is not properly initialized. Please refresh the page and try again.');
                return;
            }
            
            // Show loading state
            const googleButton = document.getElementById('google-login');
            const originalButtonText = googleButton.innerHTML;
            googleButton.disabled = true;
            googleButton.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Signing in with Google...';

            const provider = new firebase.auth.GoogleAuthProvider();
            provider.setCustomParameters({
                prompt: 'select_account'
            });
            
            debugAuth('Attempting to sign in with Google');
            
            // Try popup first, fall back to redirect if popup fails
            firebase.auth().signInWithPopup(provider).catch((error) => {
                if (error.code === 'auth/popup-blocked') {
                    debugAuth('Popup blocked, falling back to redirect');
                    return firebase.auth().signInWithRedirect(provider);
                }
                throw error;
            }).then((result) => {
                if (!result) {
                    // This means we're using redirect method
                    debugAuth('Redirect method in progress...');
                    return;
                }
                debugAuth('Google sign-in successful, getting token');
                return result.user.getIdToken();
            }).then((idToken) => {
                if (!idToken) {
                    // This means we're using redirect method
                    return;
                }
                debugAuth('Got token, setting cookie and redirecting');
                // Store the token in a session cookie
                document.cookie = "firebase_token=" + idToken + "; path=/";
                // Redirect to index.php
                window.location.href = 'index.php';
            }).catch((error) => {
                debugAuth(`Google sign-in error: ${error.message}`);
                // Reset button state
                googleButton.disabled = false;
                googleButton.innerHTML = originalButtonText;
                
                // Show user-friendly error message
                let errorMessage = 'An error occurred during Google sign-in. Please try again.';
                if (error.code === 'auth/popup-closed-by-user') {
                    errorMessage = 'Sign-in was cancelled. Please try again.';
                } else if (error.code === 'auth/network-request-failed') {
                    errorMessage = 'Network error. Please check your internet connection and try again.';
                } else if (error.code === 'auth/user-disabled') {
                    errorMessage = 'This account has been disabled. Please contact support.';
                }
                showError(errorMessage);
            });
        });

        // Registration
        document.getElementById('register-button').addEventListener('click', () => {
            debugAuth('Register button clicked');
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (!email || !password || !confirmPassword) {
                showError('Please fill in all fields');
                return;
            }

            if (password !== confirmPassword) {
                showError('Passwords do not match');
                return;
            }

            // Show loading state
            const registerButton = document.getElementById('register-button');
            const originalButtonText = registerButton.innerHTML;
            registerButton.disabled = true;
            registerButton.innerHTML = 'Creating account...';
            debugAuth('Attempting to create account');

            firebase.auth().createUserWithEmailAndPassword(email, password).then((userCredential) => {
                debugAuth('Account created successfully, getting token');
                // Registration successful, get the token and redirect manually
                return userCredential.user.getIdToken();
            }).then((idToken) => {
                debugAuth('Got token, setting cookie and redirecting');
                // Store the token in a session cookie
                document.cookie = "firebase_token=" + idToken + "; path=/";
                // Redirect to index.php
                window.location.href = 'index.php';
            }).catch((error) => {
                debugAuth(`Registration error: ${error.message}`);
                // Reset button state
                registerButton.disabled = false;
                registerButton.innerHTML = originalButtonText;
                showError(error.message);
            });
        });

        // Password Reset
        document.getElementById('reset-button').addEventListener('click', () => {
            const email = document.getElementById('reset-email').value;

            if (!email) {
                showError('Please enter your email address');
                return;
            }

            firebase.auth().sendPasswordResetEmail(email).then(() => {
                showError('Password reset email sent. Please check your inbox.');
            }).catch((error) => {
                showError(error.message);
            });
        });

        // Auth state change listener - only redirect if not logging out
        firebase.auth().onAuthStateChanged((user) => {
            debugAuth(`Auth state changed: user ${user ? 'signed in' : 'signed out'}`);
            // Don't redirect if we're in the process of logging out
            // Also don't redirect if we're handling the login manually in the button click handlers
            if (user && !isLoggingOut && !document.activeElement?.id?.includes('login') && 
                !document.activeElement?.id?.includes('register') && 
                !document.activeElement?.id?.includes('google')) {
                debugAuth('Auth state changed: User is signed in, redirecting...');
                // User is signed in, redirect to index.php
                user.getIdToken().then(function(idToken) {
                    debugAuth('Got token from auth state change, setting cookie and redirecting');
                    // Store the token in a session cookie
                    document.cookie = "firebase_token=" + idToken + "; path=/";
                    window.location.href = 'index.php';
                }).catch(error => {
                    debugAuth(`Error getting token: ${error.message}`);
                });
            } else if (!user && !isLoggingOut) {
                debugAuth('Auth state changed: User is signed out');
                // Clear any existing Firebase token cookie
                document.cookie = "firebase_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            } else {
                debugAuth(`Auth state not triggering redirect: isLoggingOut=${isLoggingOut}, activeElement=${document.activeElement?.id}`);
            }
        });

        // Check if this is a logout redirect
        if (urlParams.get('logout') === 'true' || urlParams.get('force_logout') === 'true') {
            // Clear any existing Firebase token cookie
            document.cookie = "firebase_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            
            // Sign out from Firebase
            if (typeof firebase !== 'undefined' && firebase.auth) {
                firebase.auth().signOut().then(() => {
                    console.log('Successfully signed out from Firebase');
                    // Clear the logout parameter from the URL to prevent signing out again on refresh
                    if (history.pushState) {
                        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                        window.history.pushState({path: newUrl}, '', newUrl);
                    }
                }).catch((error) => {
                    console.error('Error signing out from Firebase:', error);
                });
            }
        }
    </script>
</body>
</html> 