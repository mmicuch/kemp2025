<?php
/**
 * Test script for checking database connection
 * This script requires database.php and loads config.php first
 */

// Load configuration first
require_once 'config.php';  // Toto načíta konfiguráciu vrátane funkcie logMessage()

// Nastavenie pre zobrazenie chýb
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Databázového Pripojenia</h1>";

logMessage("Test database connection script started", "info");

// Kontrola existencie súboru database.php
if (!file_exists('database.php')) {
    echo "<p style='color:red'>CHYBA: Súbor database.php neexistuje v aktuálnom adresári.</p>";
    echo "<p>Aktuálny adresár: " . __DIR__ . "</p>";
    $files = scandir(__DIR__);
    echo "<p>Zoznam súborov v adresári:</p>";
    echo "<ul>";
    foreach ($files as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
    logMessage("Database.php file not found", "error");
    exit();
}

// Načítanie databázovej triedy
require_once 'database.php';

try {
    echo "<p>Pokúšam sa vytvoriť inštanciu Database triedy...</p>";
    $db = new Database();
    echo "<p style='color:green'>OK: Trieda Database bola úspešne vytvorená.</p>";
    
    echo "<p>Pokúšam sa pripojiť k databáze...</p>";
    $result = $db->connect();
    
    if (!$result) {
        echo "<p style='color:red'>CHYBA: Pripojenie k databáze zlyhalo.</p>";
        echo "<p>Chybová správa: " . $db->error . "</p>";
        logMessage("Database connection failed: " . $db->error, "error");
    } else {
        echo "<p style='color:green'>OK: Úspešné pripojenie k databáze.</p>";
        logMessage("Database connection successful", "info");
        
        // Test jednoduchého dotazu
        echo "<p>Pokúšam sa vykonať jednoduchý dotaz...</p>";
        $simpleQuery = "SELECT COUNT(*) as count FROM os_udaje";
        $data = $db->query($simpleQuery);
        
        if ($data === false) {
            echo "<p style='color:red'>CHYBA: Jednoduchý dotaz zlyhal.</p>";
            echo "<p>Chybová správa: " . $db->error . "</p>";
            logMessage("Simple query failed: " . $db->error, "error");
        } else {
            echo "<p style='color:green'>OK: Jednoduchý dotaz bol úspešný.</p>";
            echo "<p>Počet záznamov v tabuľke os_udaje: " . $data[0]['count'] . "</p>";
            logMessage("Simple query successful", "info");
            
            // Test komplikovanejšieho dotazu
            echo "<p>Pokúšam sa vykonať komplexný dotaz (rovnaký ako v exporte)...</p>";
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
            LEFT JOIN mladez m ON m.id = ou.mladez_id
            LEFT JOIN os_udaje_ubytovanie ouu ON ou.id = ouu.os_udaje_id
            LEFT JOIN ubytovanie u ON u.id = ouu.ubytovanie_id
            LEFT JOIN os_udaje_aktivity oua ON ou.id = oua.os_udaje_id
            LEFT JOIN aktivity a ON a.id = oua.aktivita_id
            LEFT JOIN os_udaje_alergie oual ON ou.id = oual.os_udaje_id
            LEFT JOIN alergie al ON al.id = oual.alergie_id
            GROUP BY ou.id
            ORDER BY ou.id ASC
            LIMIT 3"; // Limitujeme na 3 záznamy pre test
            
            $complexData = $db->query($query);
            
            if ($complexData === false) {
                echo "<p style='color:red'>CHYBA: Komplexný dotaz zlyhal.</p>";
                echo "<p>Chybová správa: " . $db->error . "</p>";
                logMessage("Complex query failed: " . $db->error, "error");
            } else {
                echo "<p style='color:green'>OK: Komplexný dotaz bol úspešný.</p>";
                echo "<p>Počet získaných záznamov: " . count($complexData) . "</p>";
                logMessage("Complex query successful, returned " . count($complexData) . " records", "info");
                
                // Zobrazenie prvých záznamov pre kontrolu
                echo "<h3>Ukážka prvých záznamov:</h3>";
                echo "<pre>";
                print_r($complexData);
                echo "</pre>";
                
                // Test JSON kódovania
                echo "<p>Pokúšam sa zakódovať dáta do JSON...</p>";
                $json = json_encode($complexData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                
                if ($json === false) {
                    echo "<p style='color:red'>CHYBA: JSON kódovanie zlyhalo.</p>";
                    echo "<p>Chybová správa: " . json_last_error_msg() . "</p>";
                    logMessage("JSON encoding failed: " . json_last_error_msg(), "error");
                } else {
                    echo "<p style='color:green'>OK: JSON kódovanie bolo úspešné.</p>";
                    logMessage("JSON encoding successful", "info");
                }
            }
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>CHYBA: Nastala výnimka.</p>";
    echo "<p>Správa: " . $e->getMessage() . "</p>";
    echo "<p>Súbor: " . $e->getFile() . "</p>";
    echo "<p>Riadok: " . $e->getLine() . "</p>";
    echo "<pre>Stack trace: " . $e->getTraceAsString() . "</pre>";
    logMessage("Exception: " . $e->getMessage(), "error");
}

// Informácie o verzii PHP a nastavení
echo "<h2>Systémové informácie</h2>";
echo "<p>PHP verzia: " . phpversion() . "</p>";

// Informácie o povolených PHP rozšíreniach
echo "<p>Povolené PHP rozšírenia:</p>";
echo "<ul>";
$extensions = get_loaded_extensions();
foreach ($extensions as $ext) {
    if (in_array($ext, ['mysqli', 'PDO', 'pdo_mysql', 'json'])) {
        echo "<li style='color:green'>$ext</li>";
    } else if ($ext === 'curl') {
        echo "<li>$ext</li>";
    }
}
echo "</ul>";

logMessage("Test script completed", "info");
?>