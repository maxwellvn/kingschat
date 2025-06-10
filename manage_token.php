<?php
session_start();
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/config.php';

// Ensure only admins can access this page
Session::requireAdmin();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/kingschat.log');

$message = '';
$error = '';
$currentToken = getKingsChatToken();
$tokenInfo = null;

// If token exists, decode it to show expiration info
if ($currentToken) {
    $tokenInfo = decodeJwtToken($currentToken);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'refresh_token') {
            // Use the curl-based refresh script that we know works
            $output = shell_exec('/Applications/XAMPP/xamppfiles/bin/php ' . __DIR__ . '/refresh_token_curl.php');

            // Check if the token file exists and was updated
            if (file_exists(KC_TOKEN_FILE)) {
                $tokenData = json_decode(file_get_contents(KC_TOKEN_FILE), true);
                if (isset($tokenData['access_token'])) {
                    $message = 'Token refreshed successfully using curl method.';
                    $currentToken = $tokenData['access_token'];
                    $tokenInfo = decodeJwtToken($currentToken);
                } else {
                    $error = 'Failed to refresh token. Token file exists but contains invalid data.';
                }
            } else {
                $error = 'Failed to refresh token. Token file does not exist.';
            }
        } elseif ($_POST['action'] === 'update_token') {
            $newToken = trim($_POST['new_token'] ?? '');
            $refreshToken = trim($_POST['refresh_token'] ?? '');

            if (empty($newToken)) {
                $error = 'Token cannot be empty';
            } else {
                // Validate token format (should be a JWT token)
                $tokenParts = explode('.', $newToken);
                if (count($tokenParts) !== 3) {
                    $error = 'Invalid token format. Must be a valid JWT token.';
                } else {
                    // Decode token to get expiration
                    $payload = decodeJwtToken($newToken);

                    if (!$payload || !isset($payload['exp'])) {
                        $error = 'Invalid token payload. Missing expiration.';
                    } else {
                        // Calculate expiration time in seconds from now
                        $expiresAt = $payload['exp'];
                        $expiresIn = $expiresAt - time();

                        if ($expiresIn <= 0) {
                            $error = 'Token is already expired.';
                        } else {
                            // Save the new token with refresh token if provided
                            if (updateKingsChatToken($newToken, $expiresIn, $refreshToken)) {
                                $message = 'Token updated successfully. Expires in ' . round($expiresIn / 86400, 1) . ' days.';
                                if (!empty($refreshToken)) {
                                    $message .= ' Refresh token also saved.';
                                }
                                $currentToken = $newToken;
                                $tokenInfo = $payload;
                            } else {
                                $error = 'Failed to save token.';
                            }
                        }
                    }
                }
            }
        }
    } else if ($_POST['action'] === 'test_token') {
        // Test the current token by sending a test message
        require_once __DIR__ . '/send_welcome_message.php';

        $testRecipientId = KC_SYSTEM_USER_ID; // Send to self for testing
        $testRecipientName = 'Admin';

        $result = sendWelcomeMessage($testRecipientId, $testRecipientName);

        if ($result) {
            $message = 'Test message sent successfully. Check your KingsChat account.';
        } else {
            $error = 'Failed to send test message. Token may be invalid or expired.';
        }
    }
}

// Get user information
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage KingsChat Token | GPD Ordering Portal</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="../assets/images/logo.webp">
    <link rel="alternate icon" type="image/png" href="../assets/images/logo.webp">
    <link rel="apple-touch-icon" href="../assets/images/logo.webp">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="../assets/adminlte/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="../assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../assets/adminlte/css/adminlte.min.css">
    <!-- Overlay Scrollbars -->
    <link rel="stylesheet" href="../assets/adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">

    <style>
    /* Fix for Select2 dropdown visibility */
    .select2-container {
        z-index: 9999 !important; /* Ensure it's above everything */
        width: 100% !important;
    }
    .select2-container--bootstrap4 {
        width: 100% !important;
    }
    .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid #ced4da;
    }
    .select2-container--bootstrap4 .select2-dropdown {
        z-index: 9999 !important; /* Higher z-index to ensure dropdown appears above other elements */
    }
    .select2-dropdown {
        z-index: 9999 !important;
        min-width: 400px !important;
    }
    /* Make the dropdown more visible */
    .select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
        background-color: #007bff;
        color: white;
    }
    /* Fix for dropdown position */
    .select2-container--bootstrap4 .select2-dropdown {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    /* Fix for dropdown search box */
    .select2-search__field {
        width: 100% !important;
    }
    /* Fix for dropdown items */
    .select2-results__option {
        padding: 6px 12px;
        user-select: none;
    }
    /* Fix for dropdown container */
    .select2-container--open {
        z-index: 9999 !important;
    }
    /* Wrapper for better positioning */
    .select2-wrapper {
        position: relative;
        width: 100%;
    }
    /* Fix for mobile devices */
    @media (max-width: 767.98px) {
        .select2-container--bootstrap4 .select2-dropdown {
            width: 100% !important;
            min-width: 200px !important;
            max-width: 100vw !important;
            left: 0 !important;
        }
    }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="../dashboard.php" class="nav-link">Dashboard</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="../dashboard.php" class="brand-link">
            <img src="../assets/images/logo.webp" alt="GPD Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">GPD Ordering Portal</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <div class="img-circle elevation-2 bg-info d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; color: white;">
                        <?php echo substr($user_name, 0, 1); ?>
                    </div>
                </div>
                <div class="info">
                    <a href="#" class="d-block"><?php echo htmlspecialchars($user_name); ?></a>
                    <small class="text-muted"><?php echo strtoupper($user_role); ?></small>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="../dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-header">ADMINISTRATION</li>
                    <li class="nav-item">
                        <a href="manage_token.php" class="nav-link active">
                            <i class="nav-icon fas fa-key"></i>
                            <p>KingsChat Token</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-arrow-left"></i>
                            <p>Back to Dashboard</p>
                        </a>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Manage KingsChat Token</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Manage KingsChat Token</li>
                        </ol>
                    </div>
                </div>
            </div><!-- /.container-fluid -->
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-check"></i> Success!</h5>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-ban"></i> Error!</h5>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Current Token Status</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($currentToken && $tokenInfo): ?>
                                    <div class="alert alert-info">
                                        <h5><i class="icon fas fa-info"></i> Token Information</h5>
                                        <p><strong>Status:</strong>
                                            <?php
                                            if (isset($tokenInfo['exp'])) {
                                                $expiresAt = $tokenInfo['exp'];
                                                $expiresIn = $expiresAt - time();

                                                if ($expiresIn > 0) {
                                                    echo '<span class="text-success">Valid</span>';
                                                    echo ' (Expires in ' . round($expiresIn / 86400, 1) . ' days)';
                                                } else {
                                                    echo '<span class="text-danger">Expired</span>';
                                                }
                                            } else {
                                                echo '<span class="text-warning">Unknown</span>';
                                            }
                                            ?>
                                        </p>
                                        <p><strong>User ID:</strong> <?php echo htmlspecialchars($tokenInfo['sub'] ?? 'Unknown'); ?></p>
                                        <p><strong>Client ID:</strong> <?php echo htmlspecialchars($tokenInfo['cid'] ?? 'Unknown'); ?></p>
                                        <p><strong>Scopes:</strong> <?php echo htmlspecialchars(implode(', ', $tokenInfo['aud'] ?? ['Unknown'])); ?></p>
                                    </div>

                                    <div class="form-group">
                                        <label>Current Token (First 50 characters)</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(substr($currentToken, 0, 50) . '...'); ?>" readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" onclick="copyToken()">
                                                    <i class="fas fa-copy"></i> Copy
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle text-info"></i> This is only a preview. The full token is stored securely.
                                        </small>
                                    </div>

                                    <script>
                                    function copyToken() {
                                        var tokenInput = document.querySelector('input[readonly]');
                                        tokenInput.select();
                                        document.execCommand('copy');
                                        alert('Token preview copied to clipboard!');
                                    }
                                    </script>

                                    <form method="post">
                                        <input type="hidden" name="action" value="test_token">
                                        <button type="submit" class="btn btn-info">Test Current Token</button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <h5><i class="icon fas fa-exclamation-triangle"></i> No Valid Token</h5>
                                        <p>There is no valid KingsChat token configured. Please update the token below.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Update Token</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <form method="post">
                                        <input type="hidden" name="action" value="refresh_token">
                                        <button type="submit" class="btn btn-success btn-block">
                                            <i class="fas fa-sync-alt mr-1"></i> Refresh Token Automatically
                                        </button>
                                    </form>
                                    <small class="form-text text-muted mt-2">
                                        <i class="fas fa-info-circle text-info"></i> This will attempt to refresh the token using the stored refresh token.
                                        If no refresh token is available, this will fail.
                                    </small>
                                </div>

                                <hr>

                                <form method="post">
                                    <input type="hidden" name="action" value="update_token">

                                    <div class="form-group">
                                        <label for="new_token">New KingsChat Token</label>
                                        <div class="input-group">
                                            <textarea id="new_token" name="new_token" class="form-control" rows="5" placeholder="Paste the new JWT token here"></textarea>
                                        </div>
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle text-info"></i> Paste the complete JWT token obtained from KingsChat. This should be a long string starting with "eyJ".
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="refresh_token">Refresh Token (Optional)</label>
                                        <input type="text" id="refresh_token" name="refresh_token" class="form-control" placeholder="Paste the refresh token here" value="55zki1eYgyAQFdK8guwIRbSyqceGsRoaLZl09apqJno=">
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle text-info"></i> The working refresh token is pre-filled. This enables automatic token refreshing.
                                        </small>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Update Token</button>
                                </form>
                            </div>
                        </div>

                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title">How to Get a New Token</h3>
                            </div>
                            <div class="card-body">
                                <p>To obtain a new KingsChat token:</p>
                                <ol>
                                    <li>Log in to your KingsChat account in a web browser</li>
                                    <li>Open the browser's developer tools (F12 or right-click and select "Inspect")</li>
                                    <li>Go to the "Network" tab</li>
                                    <li>Refresh the page and look for requests to "kingschat.online" or "connect.kingsch.at"</li>
                                    <li>Find a request with an "Authorization" header containing "Bearer eyJ..."</li>
                                    <li>Copy the complete token (without "Bearer ") and paste it in the "New KingsChat Token" field above</li>
                                </ol>

                                <p>About the refresh token (for automatic token refreshing):</p>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle mr-2"></i> A working refresh token is already pre-filled in the form above. You don't need to change it.
                                </div>

                                <p>If you need to get a new refresh token in the future:</p>
                                <ol>
                                    <li>In the Network tab of developer tools, look for requests to "oauth2/token" or similar</li>
                                    <li>Check the response JSON for a field called "refresh_token"</li>
                                    <li>Copy this value and paste it in the "Refresh Token" field above</li>
                                </ol>

                                <p>The following curl command is used by the automatic refresh system:</p>

                                <pre>curl -X POST "https://connect.kingsch.at/oauth2/token" \
  -d "client_id=619b30ea-a682-47fb-b90f-5b8e780b89ca&refresh_token=55zki1eYgyAQFdK8guwIRbSyqceGsRoaLZl09apqJno%3D&grant_type=refresh_token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Accept: application/json"</pre>

                                <p>Alternatively, you can use the KingsChat login flow:</p>
                                <ol>
                                    <li>Go to the <a href="../login.php">login page</a> and click "Login with KingsChat"</li>
                                    <li>After successful login, the token will be stored in the database</li>
                                    <li>You can then extract it from the database or from the network requests</li>
                                </ol>

                                <p><a href="setup_cron.php" class="btn btn-info btn-sm">
                                    <i class="fas fa-clock mr-1"></i> Setup Automatic Token Refresh
                                </a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->

    <!-- Main Footer -->
    <footer class="main-footer">
        <strong>Copyright &copy; <?php echo date('Y'); ?> GPD Ordering Portal.</strong>
        All rights reserved.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> 1.0.0
        </div>
    </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="../assets/adminlte/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Select2 -->
<script src="../assets/adminlte/plugins/select2/js/select2.full.min.js"></script>
<!-- AdminLTE App -->
<script src="../assets/adminlte/js/adminlte.min.js"></script>

<script>
// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Make sure jQuery and Select2 are loaded
    if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
        // Initialize Select2 with improved configuration
        function initializeSelect2() {
            $('.select2').select2({
                theme: 'bootstrap4',
                width: '100%',
                dropdownParent: $('body'), // Attach to body to avoid z-index issues
                closeOnSelect: false,
                allowClear: true,
                placeholder: "Select...",
                language: {
                    noResults: function() {
                        return "No results found";
                    }
                }
            }).on('select2:opening', function() {
                // Force dropdown to be visible
                setTimeout(function() {
                    $('.select2-dropdown').css('z-index', 9999);
                    $('.select2-search__field').focus();
                }, 0);
            });
        }

        // Initialize Select2
        initializeSelect2();
    }
});
</script>
</body>
</html>
