<?php
/**
 * Create this file as test_email.php in your register/api/ directory
 */
require_once 'config.php';
require_once 'email.php';

// Add the test function from the previous snippet if it's not already there
if (!function_exists('testEmailConfiguration')) {
    function testEmailConfiguration() {
        // Function body as above
        // (Add the function here if you're creating this as a standalone file)
    }
}

// Run the test
header('Content-Type: application/json');

// Test email configuration
$emailConfig = testEmailConfiguration();

// Try to send a test email
$testRecipient = isset($_GET['email']) ? $_GET['email'] : 'micuchmartin19@gmail.com';
$success = false;
$error = '';

try {
    $subject = 'Kemp 2025 - Test Email';
    $message = 'This is a test email from the Kemp 2025 registration system. If you received this, email sending is working!';
    $headers = [
        'From: Kemp 2025 <noreply@kemp.baptist.sk>',
        'Reply-To: c.k.mladeze@gmail.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $success = mail($testRecipient, $subject, $message, implode("\r\n", $headers));
    
    if (!$success) {
        $error = 'mail() function returned false';
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Output results
echo json_encode([
    'email_config' => $emailConfig,
    'test_recipient' => $testRecipient,
    'test_email_sent' => $success,
    'error' => $error
], JSON_PRETTY_PRINT);
