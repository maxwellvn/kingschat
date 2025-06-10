<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "task_scheduler";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper emoji support
$conn->set_charset("utf8mb4");

/**
 * Get user by ID
 */
function getUserById($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Create a new user
 */
function createUser($userId, $username, $email) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO users (user_id, username, email) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $userId, $username, $email);
    
    return $stmt->execute();
}

/**
 * Get user tasks
 */
function getUserTasks($userId, $status = null) {
    global $conn;
    
    $sql = "SELECT * FROM tasks WHERE user_id = ?";
    $params = array($userId);
    $types = "s";
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN due_date IS NULL THEN 1
            ELSE 0 
        END,
        due_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $tasks = array();
    
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    return $tasks;
}

/**
 * Create a new task
 */
function createTask($userId, $title, $description, $dueDate, $priority = 'medium') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $userId, $title, $description, $dueDate, $priority);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Update task status
 */
function updateTaskStatus($taskId, $status, $userId) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sis", $status, $taskId, $userId);
    
    return $stmt->execute();
}

/**
 * Add a notification
 */
function addNotification($taskId, $userId, $message) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO task_notifications (task_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $taskId, $userId, $message);
    
    return $stmt->execute();
}

/**
 * Get user notifications
 */
function getUserNotifications($userId, $limit = 10) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM task_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("si", $userId, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $notifications = array();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notificationId, $userId) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE task_notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("is", $notificationId, $userId);
    
    return $stmt->execute();
}
?>
