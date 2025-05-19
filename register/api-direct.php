<?php
/**
 * Direct API Connector
 * This file bypasses all routing by directly including the API files and
 * manually executing the code with the requested endpoint.
 */

// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Define the endpoint from the URL parameter
$endpoint = $_GET['endpoint'] ?? '';
if (empty($endpoint)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'No endpoint specified. Use ?endpoint=youth-groups, allergies, activities, etc.'
    ]);
    exit;
}

// Create a clean environment for API execution
$_SERVER['REQUEST_URI'] = "/register/api/$endpoint";
$_SERVER['SCRIPT_NAME'] = "/register/api/index.php";

// Include the API files
try {
    // Include the API code
    require_once __DIR__ . '/api/config.php';
    require_once __DIR__ . '/api/database.php';

    // Set headers for JSON response
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Execute the API code based on the endpoint
    switch ($endpoint) {
        case 'youth-groups':
            $groups = getYouthGroups();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $groups]);
            break;

        case 'allergies':
            $allergies = getAllergies();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $allergies]);
            break;

        case 'activities':
            $activities = getAvailableActivities();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $activities]);
            break;

        case 'accommodations':
            // Required parameters: gender, type
            $gender = $_GET['gender'] ?? '';
            $type = $_GET['type'] ?? 'ucastnik';

            if (empty($gender) || !in_array($gender, ['muz', 'zena'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid gender parameter. Must be "muz" or "zena".']);
                exit;
            }

            if (!in_array($type, ['ucastnik', 'veduci', 'host'])) {
                $type = 'ucastnik'; // Default to participant
            }

            try {
                // Ensure both parameters are passed to the function
                if (function_exists('getAvailableAccommodation')) {
                    $accommodations = getAvailableAccommodation($gender, $type);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'data' => $accommodations]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Function getAvailableAccommodation not found'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Error getting accommodations'
                ]);
            }
            break;

        case 'register':
            // Handle registration POST request
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'error' => 'Method not allowed. Use POST.'
                ]);
                exit;
            }

            // Debug logging - start registration
            error_log("==== ZAČIATOK REGISTRÁCIE ====");
            error_log("POST DATA: " . print_r($_POST, true));
            error_log("Registration type: " . ($_POST['typ'] ?? 'undefined'));

            // Parse registration data from POST
            $registrationData = [
                'meno' => $_POST['meno'] ?? '',
                'priezvisko' => $_POST['priezvisko'] ?? '',
                'email' => $_POST['email'] ?? '',
                'datum_narodenia' => $_POST['datum_narodenia'] ?? '',
                'pohlavie' => $_POST['pohlavie'] ?? '',
                'mladez_id' => $_POST['mladez_id'] ?? '',
                'vlastny_mladez' => $_POST['vlastny_mladez'] ?? '',
                'prvy_krat' => isset($_POST['prvy_krat']) && $_POST['prvy_krat'] === 'on',
                'poznamka' => $_POST['poznamka'] ?? '',
                'gdpr' => isset($_POST['gdpr']),
                'typ' => $_POST['typ'] ?? 'ucastnik',
                'ubytovanie_id' => $_POST['ubytovanie_id'] ?? '',
                'aktivity' => isset($_POST['aktivity']) ? $_POST['aktivity'] : [],
                'alergie' => isset($_POST['alergie']) ? $_POST['alergie'] : [],
                'vlastne_alergie' => $_POST['vlastne_alergie'] ?? ''
            ];

            // Debug log registration data
            error_log("Parsed registration data: " . print_r($registrationData, true));

            // Check special registration token if applicable
            if (in_array($registrationData['typ'], ['veduci', 'host'])) {
                $token = $_POST['token'] ?? '';

                if (empty($token)) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Pre registráciu vedúceho alebo hosťa je potrebný platný token.'
                    ]);
                    exit;
                }

                // For demonstration, we're accepting any well-formed UUID token
                $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
                if (!preg_match($uuidRegex, $token)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Neplatný token pre špeciálnu registráciu.'
                    ]);
                    exit;
                }
            }

            // Validate required fields
            $requiredFields = [
                'meno' => 'Meno je povinné pole.',
                'priezvisko' => 'Priezvisko je povinné pole.',
                'email' => 'Email je povinné pole.',
                'datum_narodenia' => 'Dátum narodenia je povinné pole.',
                'pohlavie' => 'Pohlavie je povinné pole.',
                'gdpr' => 'Súhlas s GDPR je povinný.'
            ];

            // Debug logging
            error_log("User registration type: " . $registrationData['typ']);

            // Poznámka je povinná pre hostí
            if ($registrationData['typ'] === 'host' && empty($registrationData['poznamka'])) {
                $errors[] = 'Pre hostí je poznámka povinná. Prosím, uveďte detaily vášho pobytu.';
            }

            // Ubytovanie je povinné iba pre bežných účastníkov a vedúcich, nie hostí
            if ($registrationData['typ'] !== 'host') {
                $requiredFields['ubytovanie_id'] = 'Ubytovanie je povinné pole.';

                // Aktivita je povinná iba pre bežných účastníkov a vedúcich
                if (empty($registrationData['aktivity'])) {
                    $errors[] = 'Vyberte aspoň jednu aktivitu.';
                }
            } else {
                // Registrácia hosťa - debug
                error_log("HOST REGISTRATION: Skipping accommodation and activities validation");

                // Pridáme defaultné hodnoty pre hostí
                if (empty($registrationData['ubytovanie_id'])) {
                    $registrationData['ubytovanie_id'] = 6; // Použijeme špeciálne 'Host' ubytovanie
                    error_log("Added default accommodation ID 6 for host");
                }

                if (empty($registrationData['mladez_id']) || $registrationData['mladez_id'] === 'iny') {
                    $registrationData['mladez_id'] = 'iny';
                    $registrationData['vlastny_mladez'] = 'Host';
                    error_log("Set mladez_id to 'iny' and vlastny_mladez to 'Host' for host");
                }

                // Host nepotrebuje aktivity, preskakujeme
                $registrationData['aktivity'] = [];
                error_log("Skipping activities for host");
            }

            // Validácia povinných polí
            $errors = [];

            foreach ($requiredFields as $field => $message) {
                if (empty($registrationData[$field])) {
                    $errors[] = $message;
                    error_log("Validation error: missing required field: $field");
                }
            }

            // Additional validation
            if (!empty($registrationData['email']) && !filter_var($registrationData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email nie je v správnom formáte.';
            }

            // Validácia mládeže iba pre účastníkov a vedúcich, nie pre hostí
            if ($registrationData['typ'] !== 'host' && $registrationData['mladez_id'] === 'iny' && empty($registrationData['vlastny_mladez'])) {
                $errors[] = 'Zadajte názov vášho spoločenstva.';
            }

            // Check if user is at least 14 years old within this calendar year
            if (!empty($registrationData['datum_narodenia'])) {
                $birthDate = new DateTime($registrationData['datum_narodenia']);
                $today = new DateTime();
                $currentYear = (int)$today->format('Y');
                $birthYear = (int)$birthDate->format('Y');
                $age = $currentYear - $birthYear;

                if ($age < 14) {
                    $errors[] = 'Musíte mať aspoň 14 rokov v tomto kalendárnom roku.';
                }
            }

            // Debug - log registration data before processing
            error_log("==== FINAL REGISTRATION DATA BEFORE PROCESSING ====");
            error_log(print_r($registrationData, true));

            // Check for errors
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'errors' => $errors
                ]);
                exit;
            }

            // Process registration
            try {
                $result = registerParticipant($registrationData);

                // Debug - log result
                error_log("Registration result: " . print_r($result, true));

                // Clean response before sending
                header('Content-Type: application/json');
                echo json_encode($result);
            } catch (Exception $e) {
                error_log("Registration exception: " . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Server error occurred during registration'
                ]);
            }

            // Debug - registration end
            error_log("==== KONIEC REGISTRÁCIE ====");
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Endpoint not found'
            ]);
            break;
    }
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error. Please try again later.'
    ]);
}
