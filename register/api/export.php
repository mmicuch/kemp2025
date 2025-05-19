<?php
/**
 * Script for exporting data to Google Sheets
 * This script requires database.php and loads config.php first to ensure proper error handling
 */

// Load configuration first
require_once 'config.php';  // Loads configuration including logMessage()

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

logMessage("Export script started", "info");

// Check if database.php exists
if (!file_exists('database.php')) {
    logMessage("database.php file not found", "error");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration file not found',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once 'database.php';

try {
    logMessage("Creating database connection", "info");
    
    // Use the getDbConnection function instead of Database class
    $conn = getDbConnection();
    
    // Test database connection
    logMessage("Testing database connection", "info");
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    logMessage("Database connection successful", "info");
    
    $query = "SELECT
        ou.id AS 'ID',
        ou.meno AS 'Meno',
        ou.priezvisko AS 'Priezvisko',
        ou.datum_narodenia AS 'Dátum narodenia',
        CASE ou.pohlavie
            WHEN 'M' THEN 'Muž'
            WHEN 'F' THEN 'Žena'
        END AS 'Pohlavie',
        COALESCE(m.nazov, ou.mladez) AS 'Mládež',
        ou.mail AS 'E-mail',
        ou.poznamka AS 'Poznámka',
        IF(ou.novy = 1, 'Áno', 'Nie') AS 'Nový',
        ou.ucastnik AS 'Účastník',
        u.izba AS 'Ubytovanie',
        MAX(CASE WHEN a.den = 'streda' THEN a.nazov END) AS 'Aktivity (Streda)',
        MAX(CASE WHEN a.den = 'stvrtok' THEN a.nazov END) AS 'Aktivity (Štvrtok)',
        MAX(CASE WHEN a.den = 'piatok' THEN a.nazov END) AS 'Aktivity (Piatok)',
        GROUP_CONCAT(DISTINCT al.nazov SEPARATOR ', ') AS 'Alergie'
    FROM os_udaje ou
    LEFT JOIN mladez m ON m.nazov = ou.mladez
    LEFT JOIN os_udaje_ubytovanie ouu ON ou.id = ouu.os_udaje_id
    LEFT JOIN ubytovanie u ON u.id = ouu.ubytovanie_id
    LEFT JOIN os_udaje_aktivity oua ON ou.id = oua.os_udaje_id
    LEFT JOIN aktivity a ON a.id = oua.aktivita_id
    LEFT JOIN os_udaje_alergie oual ON ou.id = oual.os_udaje_id
    LEFT JOIN alergie al ON al.id = oual.alergie_id
    GROUP BY ou.id
    ORDER BY ou.id ASC";
    
    logMessage("Executing database query", "info");
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    logMessage("Query executed successfully, fetching data", "info");
    
    // Fetch all rows
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    logMessage("Data fetched successfully, records found: " . count($data), "info");
    
    // Convert to array format for Google Sheets
    $result = [];
    foreach ($data as $row) {
        $values = [];
        foreach ($row as $value) {
            $values[] = $value === null ? '' : $value;
        }
        $result[] = $values;
    }
    
    logMessage("Data processed and formatted for export", "info");
    
    $jsonResult = json_encode([
        'success' => true,
        'data' => $result,
        'count' => count($result),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    if ($jsonResult === false) {
        throw new Exception("JSON encoding failed: " . json_last_error_msg());
    }
    
    logMessage("JSON encoding successful, sending response", "info");
    
    echo $jsonResult;
    logMessage("Export completed successfully", "info");
    
    // Close the connection
    $conn->close();
    
} catch (Exception $e) {
    logMessage("Export Error: " . $e->getMessage(), "error");
    logMessage("Stack trace: " . $e->getTraceAsString(), "error");
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>