<?php
// Start session and set security headers
session_start();
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://cdn1.kingschat.online https://dvvu9r5ep0og0.cloudfront.net https://cdn.jsdelivr.net https://kingschat.online; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; font-src 'self' data:;");

// Include database connection
require_once 'includes/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['firebase_user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: index.php?error=' . urlencode('Unauthorized access'));
    exit;
}

// Handle plan assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_plan') {
        $userId = $_POST['user_id'];
        $planType = $_POST['plan_type'];
        $messageLimit = $_POST['message_limit'];
        $expiryDays = $_POST['expiry_days'];
        
        // Calculate expiry date
        $expiryDate = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
        
        // Update user's plan
        $stmt = $conn->prepare("UPDATE users SET plan_type = ?, message_limit = ?, is_premium = 1, plan_expires_at = ?, status = 'active' WHERE user_id = ?");
        $stmt->bind_param("siss", $planType, $messageLimit, $expiryDate, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Plan successfully assigned to user";
        } else {
            $_SESSION['error_message'] = "Failed to assign plan: " . $conn->error;
        }
        
        header('Location: admin.php');
        exit;
    }
}

// Get all users with their plan details
$users = [];
$query = "SELECT u.*, 
          COALESCE((SELECT COUNT(*) FROM messages WHERE user_id = u.user_id), 0) as messages_sent,
          DATEDIFF(u.plan_expires_at, NOW()) as days_remaining 
          FROM users u 
          ORDER BY u.created_at DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get success/error messages
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KingsBlast Admin - User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-bold text-gray-900">KingsBlast Admin</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="logout.php" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md transition-colors duration-200">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-xl font-semibold text-gray-800">User Management</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($user['user_id']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo ucfirst(htmlspecialchars($user['plan_type'])); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php if ($user['plan_expires_at']): ?>
                                            <?php if ($user['days_remaining'] > 0): ?>
                                                Expires in <?php echo htmlspecialchars($user['days_remaining']); ?> days
                                            <?php else: ?>
                                                Expired
                                            <?php endif; ?>
                                        <?php else: ?>
                                            No expiry
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['messages_sent']); ?> / <?php echo htmlspecialchars($user['message_limit']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch ($user['status']) {
                                            case 'active':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'expired':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <button onclick="openAssignPlanModal('<?php echo htmlspecialchars($user['user_id']); ?>')" 
                                            class="text-blue-600 hover:text-blue-900">
                                        Assign Plan
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Assign Plan Modal -->
    <div id="assignPlanModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden">
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <form id="assignPlanForm" method="POST" action="admin.php">
                        <input type="hidden" name="action" value="assign_plan">
                        <input type="hidden" name="user_id" id="modalUserId">
                        
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Assign Plan</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Plan Type</label>
                                    <select name="plan_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="premium_monthly">Premium Monthly</option>
                                        <option value="premium_annual">Premium Annual</option>
                                        <option value="premium_plus_monthly">Premium Plus Monthly</option>
                                        <option value="premium_plus_annual">Premium Plus Annual</option>
                                        <option value="ultimate_monthly">Ultimate Monthly</option>
                                        <option value="ultimate_annual">Ultimate Annual</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Message Limit</label>
                                    <input type="number" name="message_limit" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           min="1" value="5">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Duration (days)</label>
                                    <input type="number" name="expiry_days" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           min="1" value="30">
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="submit" class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto">
                                Assign Plan
                            </button>
                            <button type="button" onclick="closeAssignPlanModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAssignPlanModal(userId) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('assignPlanModal').classList.remove('hidden');
        }
        
        function closeAssignPlanModal() {
            document.getElementById('assignPlanModal').classList.add('hidden');
        }
        
        // Update message limit based on plan selection
        document.querySelector('select[name="plan_type"]').addEventListener('change', function(e) {
            const messageLimitInput = document.querySelector('input[name="message_limit"]');
            const expiryDaysInput = document.querySelector('input[name="expiry_days"]');
            
            switch(e.target.value) {
                case 'premium_monthly':
                case 'premium_annual':
                    messageLimitInput.value = '5';
                    expiryDaysInput.value = e.target.value.includes('annual') ? '365' : '30';
                    break;
                case 'premium_plus_monthly':
                case 'premium_plus_annual':
                    messageLimitInput.value = '8';
                    expiryDaysInput.value = e.target.value.includes('annual') ? '365' : '30';
                    break;
                case 'ultimate_monthly':
                case 'ultimate_annual':
                    messageLimitInput.value = '15';
                    expiryDaysInput.value = e.target.value.includes('annual') ? '365' : '30';
                    break;
            }
        });
    </script>
</body>
</html> 