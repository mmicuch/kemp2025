<?php
/**
 * API entry point for the registration system
 */

require_once 'config.php';
require_once 'database.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enable CORS for same origin only
header('Access-Control-Allow-Origin: *'); // Allow from any origin during testing
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Get request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extract the endpoint
if (preg_match('/\\/api\\/([\\w-]+)/', $path, $matches)) {
    $endpoint = $matches[1];
} else {
    // Try extracting the endpoint directly from path
    $pathParts = explode('/', trim($path, '/'));
    $endpoint = end($pathParts);
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle API routes
try {
    if ($method === 'GET') {
        // GET requests
        switch ($endpoint) {
            case 'activities':
                $activities = getAvailableActivities();
                echo json_encode(['success' => true, 'data' => $activities]);
                break;

            case 'accommodations':
                // Required parameters: gender, type
                $gender = sanitizeInput($_GET['gender'] ?? '');
                $type = sanitizeInput($_GET['type'] ?? 'ucastnik');

                if (empty($gender) || !in_array($gender, ['muz', 'zena'])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid gender parameter. Must be "muz" or "zena".']);
                    exit;
                }

                if (!in_array($type, ['ucastnik', 'veduci', 'host'])) {
                    $type = 'ucastnik'; // Default to participant
                }

                $accommodations = getAvailableAccommodation($gender, $type);
                echo json_encode(['success' => true, 'data' => $accommodations]);
                break;

            case 'youth-groups':
                $groups = getYouthGroups();
                echo json_encode(['success' => true, 'data' => $groups]);
                break;

            case 'allergies':
                $allergies = getAllergies();
                echo json_encode(['success' => true, 'data' => $allergies]);
                break;

            case 'verify-code':
                // Required parameters: code, type
                $code = sanitizeInput($_GET['code'] ?? '');
                $type = sanitizeInput($_GET['type'] ?? '');

                if (empty($code) || empty($type) || !in_array($type, ['veduci', 'host'])) {
                    $result = ['success' => false, 'valid' => false];
                } else {
                    $valid = verifySecurityCode($code, $type);
                    $result = ['success' => true, 'valid' => $valid];
                }

                echo json_encode($result);
                break;

            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Endpoint not found: ' . $endpoint]);
                break;
        }
    } elseif ($method === 'POST') {
        // POST requests
        switch ($endpoint) {
            case 'register':
                // Get POST data
                $data = [];

                // Sanitize and validate fields
                $data['meno'] = sanitizeInput($_POST['meno'] ?? '');
                $data['priezvisko'] = sanitizeInput($_POST['priezvisko'] ?? '');
                $data['email'] = sanitizeInput($_POST['email'] ?? '');
                $data['datum_narodenia'] = sanitizeInput($_POST['datum_narodenia'] ?? '');
                $data['pohlavie'] = sanitizeInput($_POST['pohlavie'] ?? '');
                $data['mladez_id'] = sanitizeInput($_POST['mladez_id'] ?? '');
                $data['vlastny_mladez'] = sanitizeInput($_POST['vlastny_mladez'] ?? '');
                $data['prvy_krat'] = isset($_POST['prvy_krat']);
                $data['poznamka'] = sanitizeInput($_POST['poznamka'] ?? '');
                $data['gdpr'] = isset($_POST['gdpr']);
                $data['typ'] = sanitizeInput($_POST['typ'] ?? 'ucastnik');
                $data['ubytovanie_id'] = (int)($_POST['ubytovanie_id'] ?? 0);

                // Handle arrays
                $data['aktivity'] = [];
                if (isset($_POST['aktivity']) && is_array($_POST['aktivity'])) {
                    foreach ($_POST['aktivity'] as $aktivitaId) {
                        $data['aktivity'][] = (int)$aktivitaId;
                    }
                }

                $data['alergie'] = [];
                if (isset($_POST['alergie']) && is_array($_POST['alergie'])) {
                    foreach ($_POST['alergie'] as $alergiaId) {
                        $data['alergie'][] = (int)$alergiaId;
                    }
                }

                $data['vlastne_alergie'] = sanitizeInput($_POST['vlastne_alergie'] ?? '');

                // Validate required fields
                $requiredFields = ['meno', 'priezvisko', 'email', 'datum_narodenia', 'pohlavie', 'gdpr'];
                $errors = [];

                foreach ($requiredFields as $field) {
                    if (empty($data[$field])) {
                        $errors[] = "Field {$field} is required.";
                    }
                }

                // Validate email format
                if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format.';
                }

                // Validate date of birth (must be at least 14 years old)
                if (!empty($data['datum_narodenia'])) {
                    $birthDate = new DateTime($data['datum_narodenia']);
                    $today = new DateTime();
                    $age = $today->diff($birthDate)->y;

                    if ($age < 14) {
                        $errors[] = 'Musíte mať aspoň 14 rokov.';
                    }
                }

                // Validate gender
                if (!in_array($data['pohlavie'], ['muz', 'zena'])) {
                    $errors[] = 'Gender must be "muz" or "zena".';
                }

                // Validate GDPR consent
                if (!$data['gdpr']) {
                    $errors[] = 'GDPR consent is required.';
                }

                // Validate registration type
                if (!in_array($data['typ'], ['ucastnik', 'veduci', 'host'])) {
                    $data['typ'] = 'ucastnik'; // Default to participant
                }

                // If special registration type, verify security code
                if ($data['typ'] !== 'ucastnik') {
                    $code = sanitizeInput($_POST['code'] ?? '');
                    if (!verifySecurityCode($code, $data['typ'])) {
                        $errors[] = 'Invalid security code for special registration.';
                    }
                }

                // If validation fails, return errors
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'errors' => $errors]);
                    exit;
                }

                // Register participant
                $result = registerParticipant($data);
                echo json_encode($result);
                break;

            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Endpoint not found: ' . $endpoint]);
                break;
        }
    } else {
        // Unsupported method
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again later.']);
}
