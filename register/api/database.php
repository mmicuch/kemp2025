<?php
/**
 * Database operations for the registration system
 */

require_once 'config.php';

/**
 * Get database connection
 *
 * @return mysqli Database connection
 */
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        logMessage("Database connection failed: " . $conn->connect_error, 'error');
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8
    $conn->set_charset("utf8");

    return $conn;
}

/**
 * Get all available activities
 *
 * @return array Available activities
 */
function getAvailableActivities() {
    $conn = getDbConnection();
    $activities = [];

    // Use a more explicit query that includes available_spots calculation
    $sql = "SELECT
                a.id,
                a.nazov,
                a.den,
                a.kapacita,
                (SELECT COUNT(*) FROM os_udaje_aktivity WHERE aktivita_id = a.id) as obsadene,
                (a.kapacita - (SELECT COUNT(*) FROM os_udaje_aktivity WHERE aktivita_id = a.id)) as available_spots
            FROM
                aktivity a
            WHERE
                (a.kapacita - (SELECT COUNT(*) FROM os_udaje_aktivity WHERE aktivita_id = a.id)) > 0";

    $result = $conn->query($sql);

    // Log query results for debugging
    logMessage("Activities query executed: " . ($result ? "success" : "failed: " . $conn->error), 'info');

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        logMessage("Found " . count($activities) . " available activities", 'info');
    } else {
        logMessage("No available activities found", 'info');
    }

    $conn->close();
    return $activities;
}

/**
 * Get available accommodations based on gender and registration type
 *
 * @param string $gender The gender ('muz' or 'zena')
 * @param string $type The registration type ('ucastnik', 'veduci', or 'host')
 * @return array Available accommodations
 */
function getAvailableAccommodation($gender, $type) {
    $conn = getDbConnection();
    $accommodations = [];

    // Log parameters for debugging
    logMessage("Getting accommodations for gender: $gender, type: $type", 'info');

    // Base SQL with conditions
    $sql = "SELECT
                u.id,
                u.izba AS nazov,
                u.kapacita,
                u.typ AS pohlavie,
                u.kapacita - COUNT(DISTINCT ouu.os_udaje_id) AS available_spots
            FROM
                ubytovanie u
            LEFT JOIN
                os_udaje_ubytovanie ouu ON u.id = ouu.ubytovanie_id
            GROUP BY
                u.id, u.izba, u.kapacita, u.typ
            HAVING
                u.kapacita - COUNT(DISTINCT ouu.os_udaje_id) > 0";

    // Add gender and type conditions
    if ($type === 'ucastnik') {
        $sql .= " AND (u.typ = ? OR u.typ = 'spolocne')";
    } elseif ($type === 'veduci') {
        $sql .= " AND (u.typ = ? OR u.typ = 'spolocne' OR u.typ = 'veduci')";
    } elseif ($type === 'host') {
        // Pre hostí vyberáme iba ubytovanie typu 'host'
        $sql .= " AND u.typ = 'host'";
    } else {
        $sql .= " AND (u.typ = ? OR u.typ = 'spolocne')";
    }

    // Log the SQL query for debugging
    logMessage("Accommodation SQL: $sql", 'debug');

    // Prepare statement and check for errors
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        logMessage("SQL prepare failed: " . $conn->error, 'error');
        $conn->close();
        return [];
    }

    // Bind parameter and execute - pre hostí nepotrebujeme vkladať parameter pohlavie
    if ($type === 'host') {
        // Pre hostí nepotrebujeme bind parameter, lebo nenasadzujeme pohlavie
    } else {
        if (!$stmt->bind_param("s", $gender)) {
            logMessage("Bind param failed: " . $stmt->error, 'error');
            $stmt->close();
            $conn->close();
            return [];
        }
    }

    // Execute and check for errors
    if (!$stmt->execute()) {
        logMessage("Execute failed: " . $stmt->error, 'error');
        $stmt->close();
        $conn->close();
        return [];
    }

    $result = $stmt->get_result();

    // Log result information
    logMessage("Accommodation query result: " . ($result ? $result->num_rows . " rows" : "failed"), 'info');

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $accommodations[] = $row;
        }
    }

    $stmt->close();
    $conn->close();
    return $accommodations;
}

/**
 * Get all youth groups
 *
 * @return array Youth groups
 */
function getYouthGroups() {
    $conn = getDbConnection();
    $groups = [];

    $sql = "SELECT * FROM mladez";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
    }

    $conn->close();
    return $groups;
}

/**
 * Get all allergies
 *
 * @return array Allergies
 */
function getAllergies() {
    $conn = getDbConnection();
    $allergies = [];

    $sql = "SELECT * FROM alergie WHERE nazov != 'iné'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $allergies[] = $row;
        }
    }

    $conn->close();
    return $allergies;
}

/**
 * Register a participant
 *
 * @param array $data Registration data
 * @return array Registration result
 */
function registerParticipant($data) {
    $conn = getDbConnection();

    // Log the registration attempt
    logMessage("Processing registration for: {$data['email']}", 'info');
    logMessage("Registration type: {$data['typ']}", 'info');

    // Detailed debugging for host registration
    if ($data['typ'] === 'host') {
        logMessage("==== HOST REGISTRATION DETAILS ====", 'info');
        logMessage("DATA: " . print_r($data, true), 'debug');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if email already exists
        $checkSql = "SELECT id FROM os_udaje WHERE mail = ?";
        $checkStmt = $conn->prepare($checkSql);

        if ($checkStmt === false) {
            logMessage("Failed to prepare check statement: " . $conn->error, 'error');
            $conn->close();
            return [
                'success' => false,
                'error' => 'Database error: Failed to prepare statement'
            ];
        }

        $checkStmt->bind_param("s", $data['email']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $checkStmt->close();
            $conn->close();
            return [
                'success' => false,
                'error' => 'Email je už použitý. Prosím, použite iný email alebo kontaktujte organizátorov.'
            ];
        }
        $checkStmt->close();

        // Convert booleans
        $prvyKrat = isset($data['prvy_krat']) && $data['prvy_krat'] ? 1 : 0;
        $gdpr = isset($data['gdpr']) ? 1 : 0;

        // Map registration type to correct database value
        $ucastnikValue = 1; // Default numeric value for 'ucastnik'
        if ($data['typ'] === 'veduci') {
            $ucastnikValue = 2; // Numeric value for 'veduci'
        } elseif ($data['typ'] === 'host') {
            $ucastnikValue = 3; // Numeric value for 'host'
        }

        // Log the type for debugging
        logMessage("Registration type: {$data['typ']}, mapped to database value: {$ucastnikValue}", 'debug');

        // Handle mladez value - if mladez_id is 'iny', use vlastny_mladez
        $mladezValue = null;
        if (!empty($data['mladez_id']) && $data['mladez_id'] !== 'iny') {
            // Get the mladez name from ID
            $mladezSql = "SELECT nazov FROM mladez WHERE id = ?";
            $mladezStmt = $conn->prepare($mladezSql);
            if ($mladezStmt) {
                $mladezStmt->bind_param("i", $data['mladez_id']);
                $mladezStmt->execute();
                $mladezResult = $mladezStmt->get_result();
                if ($mladezResult && $mladezResult->num_rows > 0) {
                    $mladezRow = $mladezResult->fetch_assoc();
                    $mladezValue = $mladezRow['nazov'];
                }
                $mladezStmt->close();
            }
        } elseif (!empty($data['vlastny_mladez'])) {
            $mladezValue = $data['vlastny_mladez'];
        }

        // Process the note field
        $poznamka = isset($data['poznamka']) ? $data['poznamka'] : '';

        // Debug log the poznamka value to see what's being passed
        logMessage("Note field raw value: " . print_r($data['poznamka'], true), 'debug');
        logMessage("Note field processed value: " . print_r($poznamka, true), 'debug');

        // Pre hostí špeciálne overenie, aby sme mali potrebné hodnoty
        if ($data['typ'] === 'host') {
            // Logujeme, že ide o hostí so špeciálnym spracovaním
            logMessage("Processing HOST registration with special handling", 'info');

            // Ubytovanie pre hostí buď z dát alebo default
            if (empty($data['ubytovanie_id'])) {
                $data['ubytovanie_id'] = 6; // Host ubytovanie (ID 6)
                logMessage("Set default accommodation ID 6 for host", 'info');
            } else {
                logMessage("Using provided accommodation ID: {$data['ubytovanie_id']} for host", 'info');
            }

            // Pre hostí sa aktivity neriešia - prázdne pole
            $data['aktivity'] = [];
            logMessage("Skipping activities for host", 'info');

            // Mládež buď z dát alebo default
            if (empty($mladezValue)) {
                $mladezValue = "Host"; // Default mládež pre hostí
                logMessage("Set default youth group 'Host' for host", 'info');
            } else {
                logMessage("Using provided youth group: $mladezValue for host", 'info');
            }

            // Záloha - ak by bola potrebná
            logMessage("HOST registration final values: ubytovanie_id={$data['ubytovanie_id']}, mladez=$mladezValue", 'info');
        }

        // Convert gender to match database schema (M/F instead of muz/zena)
        $pohlavie = $data['pohlavie'] === 'muz' ? 'M' : 'F';

        // Debug log before insertion
        logMessage("Inserting user with: meno={$data['meno']}, priezvisko={$data['priezvisko']}, email={$data['email']}, datum_narodenia={$data['datum_narodenia']}, pohlavie={$pohlavie}, mladez={$mladezValue}, poznamka={$poznamka}, novy={$prvyKrat}, ucastnik={$ucastnikValue}, gdpr={$gdpr}", 'debug');

        // Use a simpler query with proper parameter binding
        $sql = "INSERT INTO os_udaje (meno, priezvisko, mail, datum_narodenia, pohlavie, mladez, poznamka, novy, ucastnik, GDPR)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        logMessage("SQL for insertion: " . $sql, 'debug');

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            logMessage("Failed to prepare statement: " . $conn->error, 'error');
            $conn->rollback();
            $conn->close();
            return [
                'success' => false,
                'error' => 'Database error: Failed to prepare statement'
            ];
        }

        // Bind all parameters at once
        if (!$stmt->bind_param("sssssssiis",
            $data['meno'],
            $data['priezvisko'],
            $data['email'],
            $data['datum_narodenia'],
            $pohlavie,
            $mladezValue,
            $poznamka,  // Make sure this is bound as a string
            $prvyKrat,
            $ucastnikValue, // Use the updated ucastnikValue
            $gdpr)) {

            logMessage("Failed to bind parameters: " . $stmt->error, 'error');
            $stmt->close();
            $conn->rollback();
            $conn->close();
            return [
                'success' => false,
                'error' => 'Database error: Failed to bind parameters'
            ];
        }

        // Log bound parameters
        logMessage("Binding parameters: meno={$data['meno']}, priezvisko={$data['priezvisko']}, email={$data['email']}, datum_narodenia={$data['datum_narodenia']}, pohlavie={$pohlavie}, mladez={$mladezValue}, poznamka={$poznamka}, novy={$prvyKrat}, ucastnik={$ucastnikValue}, gdpr={$gdpr}", 'debug');

        // Execute the query
        if (!$stmt->execute()) {
            logMessage("Failed to execute insert: " . $stmt->error, 'error');
            $stmt->close();
            $conn->rollback();
            $conn->close();
            return [
                'success' => false,
                'error' => 'Failed to save registration data. Please try again.'
            ];
        }

        $osUdajeId = $stmt->insert_id;
        $stmt->close();

        logMessage("Successfully created user record with ID: $osUdajeId", 'info');

        // Insert activities (preskočiť pre hostí)
        if (!empty($data['aktivity']) && $data['typ'] !== 'host') {
            $activitySql = "INSERT INTO os_udaje_aktivity (os_udaje_id, aktivita_id) VALUES (?, ?)";
            $activityStmt = $conn->prepare($activitySql);

            if ($activityStmt === false) {
                logMessage("Failed to prepare activity statement: " . $conn->error, 'error');
                $conn->rollback();
                $conn->close();
                return [
                    'success' => false,
                    'error' => 'Database error: Failed to save activities'
                ];
            }

            foreach ($data['aktivity'] as $aktivitaId) {
                $activityStmt->bind_param("ii", $osUdajeId, $aktivitaId);
                $activityStmt->execute();
            }

            $activityStmt->close();
            logMessage("Added activities for user ID: $osUdajeId", 'info');
        } else if ($data['typ'] === 'host') {
            logMessage("Skipping activity insertion for host user", 'info');
        }

        // Insert allergies
        if (!empty($data['alergie'])) {
            $allergySql = "INSERT INTO os_udaje_alergie (os_udaje_id, alergie_id) VALUES (?, ?)";
            $allergyStmt = $conn->prepare($allergySql);

            if ($allergyStmt === false) {
                logMessage("Failed to prepare allergy statement: " . $conn->error, 'error');
                $conn->rollback();
                $conn->close();
                return [
                    'success' => false,
                    'error' => 'Database error: Failed to save allergies'
                ];
            }

            // Log the allergies for debugging
            logMessage("Allergies to insert: " . json_encode($data['alergie']), 'info');

            foreach ($data['alergie'] as $alergiaId) {
                // Only insert valid integer IDs
                if (is_numeric($alergiaId)) {
                    $alergiaIdInt = (int)$alergiaId;
                    $allergyStmt->bind_param("ii", $osUdajeId, $alergiaIdInt);
                    $success = $allergyStmt->execute();

                    if (!$success) {
                        logMessage("Failed to insert allergy ID $alergiaIdInt: " . $allergyStmt->error, 'error');
                    } else {
                        logMessage("Inserted allergy ID $alergiaIdInt for user $osUdajeId", 'info');
                    }
                }
            }

            $allergyStmt->close();
            logMessage("Added allergies for user ID: $osUdajeId", 'info');
        }

        // Insert custom allergies
        if (!empty($data['vlastne_alergie'])) {
            logMessage("Processing custom allergies: {$data['vlastne_alergie']}", 'info');

            // Insert new allergy record with 'Ine' as name and custom allergy text as description
            $customAllergySql = "INSERT INTO alergie (nazov, popis) VALUES ('Ine', ?)";
            $customAllergyStmt = $conn->prepare($customAllergySql);

            if (!$customAllergyStmt) {
                logMessage("Failed to prepare custom allergy statement: " . $conn->error, 'error');
            } else {
                if (!$customAllergyStmt->bind_param("s", $data['vlastne_alergie'])) {
                    logMessage("Failed to bind custom allergy parameter: " . $customAllergyStmt->error, 'error');
                } else {
                    if (!$customAllergyStmt->execute()) {
                        logMessage("Failed to insert custom allergy: " . $customAllergyStmt->error, 'error');
                    } else {
                        $customAllergyId = $customAllergyStmt->insert_id;
                        logMessage("Created custom allergy record with ID: $customAllergyId", 'info');

                        // Link this custom allergy to the user
                        $allergyLinkSql = "INSERT INTO os_udaje_alergie (os_udaje_id, alergie_id) VALUES (?, ?)";
                        $allergyLinkStmt = $conn->prepare($allergyLinkSql);

                        if (!$allergyLinkStmt) {
                            logMessage("Failed to prepare allergy link statement: " . $conn->error, 'error');
                        } else {
                            if (!$allergyLinkStmt->bind_param("ii", $osUdajeId, $customAllergyId)) {
                                logMessage("Failed to bind allergy link parameters: " . $allergyLinkStmt->error, 'error');
                            } else {
                                if (!$allergyLinkStmt->execute()) {
                                    logMessage("Failed to link custom allergy: " . $allergyLinkStmt->error, 'error');
                                } else {
                                    logMessage("Successfully linked custom allergy $customAllergyId to user $osUdajeId", 'info');
                                }
                            }
                            $allergyLinkStmt->close();
                        }
                    }
                }
                $customAllergyStmt->close();
            }
        }

        // Insert accommodation
        if (!empty($data['ubytovanie_id'])) {
            $accommodationSql = "INSERT INTO os_udaje_ubytovanie (os_udaje_id, ubytovanie_id) VALUES (?, ?)";
            $accommodationStmt = $conn->prepare($accommodationSql);

            if ($accommodationStmt === false) {
                logMessage("Failed to prepare accommodation statement: " . $conn->error, 'error');
                $conn->rollback();
                $conn->close();
                return [
                    'success' => false,
                    'error' => 'Database error: Failed to save accommodation'
                ];
            }

            $accommodationStmt->bind_param("ii", $osUdajeId, $data['ubytovanie_id']);
            $accommodationStmt->execute();
            $accommodationStmt->close();
            logMessage("Added accommodation ID: {$data['ubytovanie_id']} for user ID: $osUdajeId", 'info');
        } else if ($data['typ'] === 'host') {
            // Ak ide o hosťa a nemá nastavené ubytovanie, použijeme default
            $defaultUbytovanieId = 6; // Host ubytovanie (ID 6)
            $accommodationSql = "INSERT INTO os_udaje_ubytovanie (os_udaje_id, ubytovanie_id) VALUES (?, ?)";
            $accommodationStmt = $conn->prepare($accommodationSql);

            if ($accommodationStmt) {
                $accommodationStmt->bind_param("ii", $osUdajeId, $defaultUbytovanieId);
                $accommodationStmt->execute();
                $accommodationStmt->close();
                logMessage("Added DEFAULT accommodation ID: {$defaultUbytovanieId} for host user ID: $osUdajeId", 'info');
            }
        }

        // Commit transaction
        $conn->commit();

        // Log success
        logMessage("Successful registration for {$data['email']}", 'info');

        return [
            'success' => true,
            'id' => $osUdajeId
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();

        // Log error
        logMessage("Registration error: " . $e->getMessage(), 'error');

        return [
            'success' => false,
            'error' => 'Nastala chyba pri registrácii. Skúste to znova alebo kontaktujte organizátorov.'
        ];
    } finally {
        $conn->close();
    }
}
