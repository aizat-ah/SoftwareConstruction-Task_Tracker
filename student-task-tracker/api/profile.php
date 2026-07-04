<?php
/**
 * profile.php — RESTful endpoints for the authenticated user's own profile.
 *
 * Routes (proxied from the Vue dev server, /api is stripped):
 *   GET    /profile           → view current user's info
 *   PUT    /profile           → update username / email
 *   DELETE /profile           → delete account (cascades to tasks)
 *   PUT    /profile/password  → change password (rotates auth token)
 *
 * Password change is modelled as a sub-resource because it's an action on the
 * account rather than a plain state replacement.
 */
require_once 'config.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(200);
    exit();
}

$conn   = getDBConnection();
$userId = _requireAuth($conn);
$method = $_SERVER['REQUEST_METHOD'];
$data   = json_decode(file_get_contents('php://input'), true) ?? [];

// Detect an optional sub-resource, e.g. /profile/password
$path      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$sub       = null;
$profIndex = array_search('profile', $pathParts);
if ($profIndex !== false && isset($pathParts[$profIndex + 1]) && $pathParts[$profIndex + 1] !== '') {
    $sub = $pathParts[$profIndex + 1];
}

// ─── /profile/password ────────────────────────────────────────────────────────
if ($sub === 'password') {
    if ($method !== 'PUT') {
        http_response_code(405);
        header('Allow: PUT');
        echo json_encode(['error' => 'Method not allowed. Use PUT /profile/password']);
        exit();
    }

    $currentPassword = $data['currentPassword'] ?? '';
    $newPassword     = $data['newPassword']     ?? '';

    if ($currentPassword === '' || $newPassword === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Current and new password are required']);
        exit();
    }
    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'New password must be at least 6 characters']);
        exit();
    }

    // Verify the current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Current password is incorrect']);
        exit();
    }

    // Update the password and rotate the auth token so the session stays valid.
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $newToken       = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("UPDATE users SET password = ?, auth_token = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $newToken, $userId]);

    echo json_encode([
        'message' => 'Password changed successfully',
        'token'   => $newToken,
    ]);
    exit();
}

// Unknown sub-resource
if ($sub !== null) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit();
}

// ─── /profile ─────────────────────────────────────────────────────────────────
switch ($method) {

    // View the current user's info
    case 'GET':
        $stmt = $conn->prepare(
            "SELECT id, username, email, created_at FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }

        echo json_encode([
            'id'         => (int)$user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'created_at' => $user['created_at'],
        ]);
        break;

    // Update username / email
    case 'PUT':
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email']    ?? '');

        if ($username === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Username and email are required']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit();
        }

        // Ensure the new email isn't taken by a different user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email is already registered']);
            exit();
        }

        // Ensure the new username isn't taken by a different user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Username is already taken']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->execute([$username, $email, $userId]);

        echo json_encode([
            'message' => 'Profile updated successfully',
            'user'    => ['id' => (int)$userId, 'username' => $username, 'email' => $email],
        ]);
        break;

    // Delete the account (tasks removed via ON DELETE CASCADE)
    case 'DELETE':
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['message' => 'Account deleted successfully']);
        break;

    default:
        http_response_code(405);
        header('Allow: GET, PUT, DELETE');
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
