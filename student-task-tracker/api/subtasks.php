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

// Supported URL shapes:
//   Nested REST (assignment spec):
//     GET|POST   /tasks/{taskId}/subtasks         → list / create under a task
//   Flat routes (used by the app UI + single-subtask ops):
//     GET|POST   /subtasks/task/{taskId}          → list / create
//     PUT|DELETE /subtasks/{subtaskId}            → update / delete one
//     DELETE     /subtasks/task/{taskId}/completed → clear all completed
// Tolerant of an /index.php or /api prefix (App Engine / dev proxy).
$tasks_index    = array_search('tasks', $path_parts);
$subtasks_index = array_search('subtasks', $path_parts);

$task_id = null;
$subtask_id = null;
$clear_completed = false;

if (
    $tasks_index !== false
    && $subtasks_index !== false
    && $subtasks_index > $tasks_index
    && isset($path_parts[$tasks_index + 1])
    && $path_parts[$tasks_index + 1] !== ''
) {
    // Nested REST shape: /tasks/{taskId}/subtasks
    $task_id = $path_parts[$tasks_index + 1];
} elseif ($subtasks_index !== false) {
    // Flat shapes: /subtasks/task/{taskId}[/completed] or /subtasks/{subtaskId}
    $remainder = array_slice($path_parts, $subtasks_index + 1);
    if (isset($remainder[0]) && $remainder[0] === 'task' && isset($remainder[1]) && $remainder[1] !== '') {
        $task_id = $remainder[1];
        if (isset($remainder[2]) && $remainder[2] === 'completed') {
            $clear_completed = true;
        }
    } elseif (isset($remainder[0]) && $remainder[0] !== '') {
        $subtask_id = $remainder[0];
    }
}

switch ($method) {
    case 'GET':
        // List subtasks for a task — scoped to the authenticated user
        if (!$task_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID is required']);
            break;
        }

        if (!_verifyTaskOwnership($conn, $task_id, $authUserId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            break;
        }

        $stmt = $conn->prepare("SELECT * FROM subtasks WHERE task_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$task_id]);
        $subtasks = array_map('_normalizeSubtask', $stmt->fetchAll(PDO::FETCH_ASSOC));
        echo json_encode($subtasks);
        break;

    case 'POST':
        // Create a subtask under a task — only if the task belongs to the authenticated user
        if (!$task_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Task ID is required']);
            break;
        }

        if (!_verifyTaskOwnership($conn, $task_id, $authUserId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $title = trim($data['title'] ?? '');

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required']);
            break;
        }

        if (isset($data['sort_order'])) {
            $sortOrder = (int) $data['sort_order'];
        } else {
            $stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM subtasks WHERE task_id = ?");
            $stmt->execute([$task_id]);
            $sortOrder = (int) $stmt->fetch()['next_order'];
        }

        $stmt = $conn->prepare("INSERT INTO subtasks (task_id, title, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$task_id, $title, $sortOrder]);

        $stmt = $conn->prepare("SELECT * FROM subtasks WHERE id = ?");
        $stmt->execute([$conn->lastInsertId()]);
        $newSubtask = _normalizeSubtask($stmt->fetch(PDO::FETCH_ASSOC));

        _syncTaskStatus($conn, $task_id);
        $newSubtask['task_status'] = _getTaskStatus($conn, $task_id);

        http_response_code(201);
        echo json_encode($newSubtask);
        break;

    case 'PUT':
        // Update a subtask — only if it belongs to a task owned by the authenticated user
        if (!$subtask_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Subtask ID is required']);
            break;
        }

        if (!_verifySubtaskOwnership($conn, $subtask_id, $authUserId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Subtask not found']);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $values = [];

        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if ($title === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Title cannot be empty']);
                break;
            }
            $fields[] = 'title = ?';
            $values[] = $title;
        }
        if (array_key_exists('is_completed', $data)) {
            $fields[] = 'is_completed = ?';
            $values[] = $data['is_completed'] ? 1 : 0;
        }
        if (array_key_exists('sort_order', $data)) {
            $fields[] = 'sort_order = ?';
            $values[] = (int) $data['sort_order'];
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No updatable fields provided']);
            break;
        }

        $values[] = $subtask_id;
        $stmt = $conn->prepare("UPDATE subtasks SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($values);

        $stmt = $conn->prepare("SELECT * FROM subtasks WHERE id = ?");
        $stmt->execute([$subtask_id]);
        $updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $updatedSubtask = _normalizeSubtask($updatedRow);

        _syncTaskStatus($conn, $updatedRow['task_id']);
        $updatedSubtask['task_status'] = _getTaskStatus($conn, $updatedRow['task_id']);

        echo json_encode($updatedSubtask);
        break;

    case 'DELETE':
        if ($clear_completed) {
            // Bulk-delete all completed subtasks for a task
            if (!_verifyTaskOwnership($conn, $task_id, $authUserId)) {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
                break;
            }

            $stmt = $conn->prepare("DELETE FROM subtasks WHERE task_id = ? AND is_completed = 1");
            $stmt->execute([$task_id]);
            $deletedCount = $stmt->rowCount();

            _syncTaskStatus($conn, $task_id);

            http_response_code(200);
            echo json_encode([
                'deleted_count' => $deletedCount,
                'task_status' => _getTaskStatus($conn, $task_id),
            ]);
            break;
        }

        // Delete a subtask — only if it belongs to a task owned by the authenticated user
        if (!$subtask_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Subtask ID is required']);
            break;
        }

        if (!_verifySubtaskOwnership($conn, $subtask_id, $authUserId)) {
            http_response_code(404);
            echo json_encode(['error' => 'Subtask not found']);
            break;
        }

        $stmt = $conn->prepare("SELECT task_id FROM subtasks WHERE id = ?");
        $stmt->execute([$subtask_id]);
        $ownerTaskId = $stmt->fetch()['task_id'];

        $stmt = $conn->prepare("DELETE FROM subtasks WHERE id = ?");
        $stmt->execute([$subtask_id]);

        _syncTaskStatus($conn, $ownerTaskId);

        http_response_code(204);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function _verifyTaskOwnership($conn, $taskId, $userId): bool {
    $stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$taskId, $userId]);
    return (bool) $stmt->fetch();
}

function _verifySubtaskOwnership($conn, $subtaskId, $userId): bool {
    $stmt = $conn->prepare(
        "SELECT s.id FROM subtasks s JOIN tasks t ON s.task_id = t.id WHERE s.id = ? AND t.user_id = ?"
    );
    $stmt->execute([$subtaskId, $userId]);
    return (bool) $stmt->fetch();
}

function _normalizeSubtask(array $row): array {
    $row['id'] = (int) $row['id'];
    $row['task_id'] = (int) $row['task_id'];
    $row['sort_order'] = (int) $row['sort_order'];
    $row['is_completed'] = (bool) $row['is_completed'];
    return $row;
}

/**
 * Recomputes the parent task's status from its subtasks' completion state.
 * Only ever moves status forward (Pending -> In Progress -> Completed) —
 * never downgrades, since the user may have set the current status manually.
 */
function _syncTaskStatus($conn, $taskId): void {
    $stmt = $conn->prepare("SELECT is_completed FROM subtasks WHERE task_id = ?");
    $stmt->execute([$taskId]);
    $flags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total = count($flags);
    if ($total === 0) {
        return;
    }

    $completedCount = array_sum(array_map('intval', $flags));
    $currentStatus = _getTaskStatus($conn, $taskId);
    if ($currentStatus === null) {
        return;
    }

    if ($completedCount === $total) {
        if ($currentStatus !== 'Completed') {
            $stmt = $conn->prepare("UPDATE tasks SET status = 'Completed' WHERE id = ?");
            $stmt->execute([$taskId]);
        }
    } elseif ($completedCount > 0) {
        if ($currentStatus === 'Pending') {
            $stmt = $conn->prepare("UPDATE tasks SET status = 'In Progress' WHERE id = ?");
            $stmt->execute([$taskId]);
        }
    }
    // $completedCount === 0: leave status unchanged
}

function _getTaskStatus($conn, $taskId): ?string {
    $stmt = $conn->prepare("SELECT status FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();
    return $row ? $row['status'] : null;
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
