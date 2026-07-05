<?php
require_once 'config.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    http_response_code(200);
    exit();
}

// Get database connection
$conn = getDBConnection();

// Validate Bearer token — returns the authenticated user's ID
$authUserId = _requireAuth($conn);

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the request URI and parse it
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Support both local dev (/tasks/5) and App Engine (/index.php/tasks/5)
// Find the 'tasks' segment and take the part after it as the ID
$task_id = null;
$tasks_index = array_search('tasks', $path_parts);
if ($tasks_index !== false && isset($path_parts[$tasks_index + 1]) && $path_parts[$tasks_index + 1] !== '') {
    $task_id = $path_parts[$tasks_index + 1];
}

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        if ($task_id) {
            // Get a single task — scoped to the authenticated user
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$task_id, $authUserId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                echo json_encode($task);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
            }
        } else {
            // Get only tasks belonging to the authenticated user
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY dueDate DESC");
            $stmt->execute([$authUserId]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($tasks);
        }
        break;

    case 'POST':
        // Create a new task — always tied to the authenticated user
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['title']) || !isset($data['description']) || !isset($data['dueDate']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }

        $stmt = $conn->prepare("INSERT INTO tasks (title, description, dueDate, status, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['dueDate'],
            $data['status'],
            $authUserId,
        ]);

        $data['id'] = $conn->lastInsertId();
        $data['user_id'] = $authUserId;

        // Send email notification
        $stmtUser = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmtUser->execute([$authUserId]);
        $userRow = $stmtUser->fetch();
        if ($userRow) {
            $userEmail = $userRow['email'];
            $taskTitle = htmlspecialchars($data['title']);
            $dueDate = htmlspecialchars($data['dueDate']);
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; background-color: #f9f9f9;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #4CAF50; margin: 0;'>New Task Created</h2>
                </div>
                <div style='background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <p style='font-size: 16px; color: #333333;'>Hello,</p>
                    <p style='font-size: 16px; color: #333333;'>You have successfully created a new task in your task tracker.</p>
                    
                    <div style='background-color: #e8f5e9; border-left: 4px solid #4CAF50; padding: 15px; margin: 20px 0;'>
                        <h3 style='margin: 0 0 10px 0; color: #333333;'>{$taskTitle}</h3>
                        <p style='margin: 0; color: #666666;'><strong>Due Date:</strong> {$dueDate}</p>
                    </div>
                    
                    <p style='font-size: 16px; color: #333333;'>Keep up the good work and stay organized!</p>
                </div>
            </div>
            ";
            
            sendEmail($userEmail, "New Task Created: $taskTitle", $body);
        }

        http_response_code(201);
        echo json_encode($data);
        break;

    case 'PUT':
        // Update a task — only if it belongs to the authenticated user
        if (!$task_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID is required']);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['title']) || !isset($data['description']) || !isset($data['dueDate']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }

        $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, dueDate = ?, status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['dueDate'],
            $data['status'],
            $task_id,
            $authUserId,
        ]);

        if ($stmt->rowCount() > 0) {
            $data['id'] = $task_id;
            echo json_encode($data);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
        }
        break;

    case 'DELETE':
        // Delete a task — only if it belongs to the authenticated user
        if (!$task_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID is required']);
            break;
        }

        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $authUserId]);

        if ($stmt->rowCount() > 0) {
            http_response_code(204);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// ─── Auth helpers ─────────────────────────────────────────────────────────────

/**
 * Validates the Bearer token and returns the authenticated user's ID.
 * Exits with 401 if the token is missing or invalid.
 */
function _requireAuth($conn): int {
    $token = _extractBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: No token provided']);
        exit();
    }
    $stmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid or expired token']);
        exit();
    }
    return (int) $user['id'];
}

function _extractBearerToken(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if ($authHeader === '') {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        return $matches[1];
    }
    return null;
}
?>