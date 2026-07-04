<?php
require_once 'config.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$conn = getDBConnection();

switch ($action) {

    // ─── REGISTER ────────────────────────────────────────────────────────────
    case 'register':
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email']    ?? '');
        $password =       $data['password'] ?? '';

        // Required fields
        if ($username === '' || $email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Username, email and password are required']);
            exit();
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit();
        }

        // Minimum password length
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters']);
            exit();
        }

        // Check duplicate email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email is already registered']);
            exit();
        }

        // Check duplicate username
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Username is already taken']);
            exit();
        }

        // Hash password and generate session token
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));

        $stmt = $conn->prepare(
            "INSERT INTO users (username, email, password, auth_token) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$username, $email, $hashedPassword, $token]);
        $userId = $conn->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'token' => $token,
            'user'  => ['id' => (int)$userId, 'username' => $username, 'email' => $email]
        ]);
        break;

    // ─── LOGIN ────────────────────────────────────────────────────────────────
    case 'login':
        $email    = trim($data['email']    ?? '');
        $password =       $data['password'] ?? '';

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            exit();
        }

        // Look up user
        $stmt = $conn->prepare(
            "SELECT id, username, email, password FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            exit();
        }

        // Issue a fresh token on every login
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
        $stmt->execute([$token, $user['id']]);

        echo json_encode([
            'token' => $token,
            'user'  => [
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'email'    => $user['email']
            ]
        ]);
        break;

    // ─── LOGOUT ───────────────────────────────────────────────────────────────
    case 'logout':
        $token = _extractBearerToken();
        if ($token) {
            $stmt = $conn->prepare("UPDATE users SET auth_token = NULL WHERE auth_token = ?");
            $stmt->execute([$token]);
        }
        echo json_encode(['message' => 'Logged out successfully']);
        break;

    // ─── GET PROFILE ────────────────────────────────────────────────────────────
    case 'profile':
        $userId = _requireAuth($conn);

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

    // ─── UPDATE PROFILE (username / email) ───────────────────────────────────────
    case 'update_profile':
        $userId   = _requireAuth($conn);
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
            'user'    => ['id' => (int)$userId, 'username' => $username, 'email' => $email]
        ]);
        break;

    // ─── CHANGE PASSWORD ─────────────────────────────────────────────────────────
    case 'change_password':
        $userId          = _requireAuth($conn);
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

        // Update the password and rotate the auth token in one step, so the
        // current session stays valid without forcing a re-login.
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $newToken = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("UPDATE users SET password = ?, auth_token = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $newToken, $userId]);

        echo json_encode([
            'message' => 'Password changed successfully',
            'token'   => $newToken,
        ]);
        break;

    // ─── DELETE ACCOUNT ──────────────────────────────────────────────────────────
    case 'delete_account':
        $userId = _requireAuth($conn);

        // Tasks are removed automatically via ON DELETE CASCADE
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['message' => 'Account deleted successfully']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use ?action=register|login|logout|profile|update_profile|change_password|delete_account']);
}

// ─── Auth helper ──────────────────────────────────────────────────────────────
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

// ─── Helper ──────────────────────────────────────────────────────────────────
function _extractBearerToken(): ?string {
    // Try Apache/Nginx header
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    // Fallback: PHP CLI server exposes it here
    if ($authHeader === '') {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }

    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        return $matches[1];
    }
    return null;
}
?>
