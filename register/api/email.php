<?php
/**
 * Email functionality for the registration system
 * Handles sending confirmation emails to participants after registration
 */

require_once 'config.php';

/**
 * Send a confirmation email to participant after successful registration
 * 
 * @param array $participantData Participant data (name, email, etc.)
 * @param int $registrationId The ID of the registration in the database
 * @return bool Whether the email was sent successfully
 */
function sendConfirmationEmail($participantData, $registrationId) {
    // Log the attempt
    logMessage("Attempting to send confirmation email to: {$participantData['mail']}", "info");
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Kemp 2025 <noreply@kemp.baptist.sk>',
        'Reply-To: c.k.mladeze@gmail.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Participant name
    $fullName = htmlspecialchars($participantData['meno'] . ' ' . $participantData['priezvisko']);
    
    // Email subject
    $subject = "Potvrdenie registrácie - Kemp 2025";
    
    // Determine participant type text
    $participantTypeText = "";
    switch ($participantData['ucastnik']) {
        case 'ucastnik':
            $participantTypeText = "účastník";
            break;
        case 'veduci':
            $participantTypeText = "vedúci";
            break;
        case 'host':
            $participantTypeText = "hosť";
            break;
        default:
            $participantTypeText = "účastník";
    }
    
    // Format birth date
    $birthDate = $participantData['datum_narodenia'] ? date('d.m.Y', strtotime($participantData['datum_narodenia'])) : '';
    
    // Format activity data
    $activitiesHtml = '';
    if (isset($participantData['aktivity']) && is_array($participantData['aktivity'])) {
        $activitiesHtml = "<p><strong>Aktivity:</strong><br>";
        foreach ($participantData['aktivity'] as $activity) {
            $activityDay = htmlspecialchars($activity['den']);
            $activityName = htmlspecialchars($activity['nazov']);
            $activitiesHtml .= "- {$activityDay}: {$activityName}<br>";
        }
        $activitiesHtml .= "</p>";
    }
    
    // Format allergies
    $allergiesHtml = '';
    if (!empty($participantData['alergie'])) {
        $allergiesHtml = "<p><strong>Alergie:</strong> " . htmlspecialchars($participantData['alergie']) . "</p>";
    }
    
    // Build HTML email body
    $htmlMessage = "
    <!DOCTYPE html>
    <html lang='sk'>
    <head>
        <meta charset='UTF-8'>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1e88e5; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
            .info-block { background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #1e88e5; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Potvrdenie registrácie</h1>
            </div>
            
            <div class='content'>
                <p>Ahoj {$fullName},</p>
                
                <p>Ďakujeme za Tvoju registráciu na Kemp 2025! Tvoja registrácia bola úspešne spracovaná.</p>
                
                <div class='info-block'>
                    <h3>Detaily registrácie:</h3>
                    <p><strong>Registračné číslo:</strong> #{$registrationId}</p>
                    <p><strong>Meno a priezvisko:</strong> {$fullName}</p>
                    <p><strong>Email:</strong> {$participantData['mail']}</p>
                    <p><strong>Dátum narodenia:</strong> {$birthDate}</p>
                    <p><strong>Pohlavie:</strong> " . ($participantData['pohlavie'] == 'M' ? 'Muž' : 'Žena') . "</p>
                    <p><strong>Typ registrácie:</strong> {$participantTypeText}</p>
                    
                    " . (!empty($participantData['mladez']) ? "<p><strong>Mládež:</strong> " . htmlspecialchars($participantData['mladez']) . "</p>" : "") . "
                    " . (!empty($participantData['ubytovanie']) ? "<p><strong>Ubytovanie:</strong> " . htmlspecialchars($participantData['ubytovanie']) . "</p>" : "") . "
                    
                    {$activitiesHtml}
                    {$allergiesHtml}
                    
                    " . (!empty($participantData['poznamka']) ? "<p><strong>Poznámka:</strong> " . htmlspecialchars($participantData['poznamka']) . "</p>" : "") . "
                </div>
                
                <p>Prosím, zapíš si svoje registračné číslo pre budúcu komunikáciu.</p>
                
                <p>Ďalšie informácie o podujatí ti budú zaslané v nasledujúcich dňoch. Ak máš akékoľvek otázky, 
                neváhaj nás kontaktovať na emailovej adrese c.k.mladeze@gmail.com.</p>
                
                <p>Tešíme sa na teba na Kempe 2025!</p>
                
                <p>S pozdravom,<br>Tím Kemp 2025</p>
            </div>
            
            <div class='footer'>
                <p>Tento email bol vygenerovaný automaticky, prosíme, neodpovedajte naň. 
                Pre komunikáciu použite c.k.mladeze@gmail.com.</p>
                <p>&copy; " . date('Y') . " Kemp 2025 | <a href='https://kemp.baptist.sk'>kemp.baptist.sk</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Create plain text version for email clients that don't support HTML
    $textMessage = "Potvrdenie registrácie - Kemp 2025\n\n"
        . "Ahoj {$fullName},\n\n"
        . "Ďakujeme za Tvoju registráciu na Kemp 2025! Tvoja registrácia bola úspešne spracovaná.\n\n"
        . "Detaily registrácie:\n"
        . "Registračné číslo: #{$registrationId}\n"
        . "Meno a priezvisko: {$fullName}\n"
        . "Email: {$participantData['mail']}\n"
        . "Dátum narodenia: {$birthDate}\n"
        . "Pohlavie: " . ($participantData['pohlavie'] == 'M' ? 'Muž' : 'Žena') . "\n"
        . "Typ registrácie: {$participantTypeText}\n";
    
    if (!empty($participantData['mladez'])) {
        $textMessage .= "Mládež: {$participantData['mladez']}\n";
    }
    
    if (!empty($participantData['ubytovanie'])) {
        $textMessage .= "Ubytovanie: {$participantData['ubytovanie']}\n";
    }
    
    if (isset($participantData['aktivity']) && is_array($participantData['aktivity'])) {
        $textMessage .= "\nAktivity:\n";
        foreach ($participantData['aktivity'] as $activity) {
            $textMessage .= "- {$activity['den']}: {$activity['nazov']}\n";
        }
    }
    
    if (!empty($participantData['alergie'])) {
        $textMessage .= "\nAlergie: {$participantData['alergie']}\n";
    }
    
    if (!empty($participantData['poznamka'])) {
        $textMessage .= "\nPoznámka: {$participantData['poznamka']}\n";
    }
    
    $textMessage .= "\nProsím, zapíš si svoje registračné číslo pre budúcu komunikáciu.\n\n"
        . "Ďalšie informácie o podujatí ti budú zaslané v nasledujúcich dňoch. "
        . "Ak máš akékoľvek otázky, neváhaj nás kontaktovať na emailovej adrese c.k.mladeze@gmail.com\n\n"
        . "Tešíme sa na teba na Kempe 2025!\n\n"
        . "S pozdravom,\nTím Kemp 2025\n\n"
        . "Tento email bol vygenerovaný automaticky, prosíme, neodpovedajte naň. "
        . "Pre komunikáciu použite c.k.mladeze@gmail.com.\n"
        . "© " . date('Y') . " Kemp 2025 | kemp.baptist.sk";
    
    // Create message with both HTML and plain text alternatives
    $boundary = md5(time());
    
    $message = "--{$boundary}\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\n";
    $message .= "Content-Transfer-Encoding: 8bit\n\n";
    $message .= $textMessage . "\n\n";
    
    $message .= "--{$boundary}\n";
    $message .= "Content-Type: text/html; charset=UTF-8\n";
    $message .= "Content-Transfer-Encoding: 8bit\n\n";
    $message .= $htmlMessage . "\n\n";
    
    $message .= "--{$boundary}--";
    
    // Set content-type header for sending HTML email
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
    
    // Send email
    $email = filter_var($participantData['mail'], FILTER_SANITIZE_EMAIL);
    $success = mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, implode("\r\n", $headers));
    
    // Log the result
    if ($success) {
        logMessage("Confirmation email sent successfully to: {$email}", "info");
    } else {
        logMessage("Failed to send confirmation email to: {$email}", "error");
    }
    
    return $success;
}

/**
 * Send a notification email to administrators about a new registration
 * 
 * @param array $participantData Participant data
 * @param int $registrationId The ID of the registration
 * @return bool Whether the email was sent successfully
 */
function sendAdminNotification($participantData, $registrationId) {
    // Admin email addresses
    $adminEmails = getenv('ADMIN_EMAILS') ?: 'c.k.mladeze@gmail.com';
    $adminEmailsArray = explode(',', $adminEmails);
    
    // Log the attempt
    logMessage("Attempting to send admin notification email for registration #{$registrationId}", "info");
    
    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Kemp Registrácia <noreply@baptist.sk>',
        'Reply-To: c.k.mladeze@gmail.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Email subject
    $subject = "Nová registrácia - Kemp 2025 (#" . $registrationId . ")";
    
    // Participant name
    $fullName = htmlspecialchars($participantData['meno'] . ' ' . $participantData['priezvisko']);
    
    // Build HTML email body
    $htmlMessage = "
    <!DOCTYPE html>
    <html lang='sk'>
    <head>
        <meta charset='UTF-8'>
        <title>{$subject}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1e88e5; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; }
            .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Nová registrácia</h1>
            </div>
            
            <div class='content'>
                <p>Bola vytvorená nová registrácia na Kemp 2025.</p>
                
                <h3>Detaily registrácie:</h3>
                <p><strong>ID:</strong> {$registrationId}</p>
                <p><strong>Meno a priezvisko:</strong> {$fullName}</p>
                <p><strong>Email:</strong> {$participantData['mail']}</p>
                <p><strong>Typ:</strong> {$participantData['ucastnik']}</p>
                <p><strong>Čas registrácie:</strong> " . date('d.m.Y H:i:s') . "</p>
                
                <p>Pre zobrazenie všetkých detailov prejdite do administračného rozhrania alebo Google Sheets.</p>
            </div>
            
            <div class='footer'>
                <p>Tento email bol vygenerovaný automaticky.</p>
                <p>&copy; " . date('Y') . " Kemp 2025 | <a href='https://kemp.baptist.sk'>kemp.baptist.sk</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send emails to all admin addresses
    $allSent = true;
    foreach ($adminEmailsArray as $adminEmail) {
        $adminEmail = trim($adminEmail);
        if (!empty($adminEmail)) {
            $success = mail($adminEmail, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlMessage, implode("\r\n", $headers));
            if (!$success) {
                logMessage("Failed to send admin notification to: {$adminEmail}", "error");
                $allSent = false;
            }
        }
    }
    
    if ($allSent) {
        logMessage("Admin notification emails sent successfully", "info");
    } else {
        logMessage("Some admin notification emails failed to send", "warning");
    }
    
    return $allSent;
}
