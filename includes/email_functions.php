<?php
/**
 * Email Functions
 * 
 * Handles sending emails using PHPMailer with SMTP
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

// Load email configuration
$email_config = require __DIR__ . '/../config/email_config.php';

/**
 * Send an email using a template
 * 
 * @param string $to Recipient email address
 * @param string $template Template name (without .php)
 * @param array $data Data to pass to the template
 * @return bool True on success, false on failure
 */
function sendEmailTemplate($to, $template, $data = []) {
    global $email_config;
    
    // Start output buffering to capture any debug output
    ob_start();
    
    // Get template configuration
    if (!isset($email_config['templates'][$template])) {
        error_log("Email template '$template' not found in configuration");
        ob_end_clean();
        return false;
    }
    
    $template_config = $email_config['templates'][$template];
    $template_file = __DIR__ . '/../emails/' . $template . '.php';
    
    // Check if template file exists
    if (!file_exists($template_file)) {
        error_log("Email template file not found: $template_file");
        return false;
    }
    
    // Extract data for the template
    extract($data);
    
    // Start output buffering
    ob_start();
    
    // Include the template file
    include $template_file;
    
    // Get the contents of the buffer and end buffering
    $html_body = ob_get_clean();
    
    // Create plain text version (simple conversion from HTML)
    $plain_body = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $html_body));
    $plain_body = html_entity_decode($plain_body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $email_config['smtp']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp']['username'];
        $mail->Password = $email_config['smtp']['password'];
        $mail->SMTPSecure = $email_config['smtp']['encryption'] === 'ssl' ? 
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : 
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $email_config['smtp']['port'];
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = $email_config['smtp']['debug'];
        
        // Recipients
        $from_email = $email_config['smtp']['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];
        $from_name = $email_config['smtp']['from_name'] ?? SITE_NAME;
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to, $data['full_name'] ?? '');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $template_config['subject'] ?? 'Message from ' . SITE_NAME;
        $mail->Body = $html_body;
        $mail->AltBody = $plain_body;
        
        // Send the email
        $mail->send();
        
        // Clear any debug output that was captured
        ob_end_clean();
        return true;
        
    } catch (Exception $e) {
        // Log the error and clean the output buffer
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        ob_end_clean();
        return false;
    }
}

/**
 * Send a simple text email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body
 * @param string $from From email address (optional)
 * @param string $from_name From name (optional)
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message, $from = '', $from_name = '') {
    global $email_config;
    
    // Start output buffering to capture any debug output
    ob_start();
    
    // Load PHPMailer with exceptions enabled
    $mail = new PHPMailer\PHPMailer\PHMailer(true);
    
    try {
        // Configure debug output to go to error log instead of browser
        $mail->SMTPDebug = $email_config['smtp']['debug'];
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer: $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $email_config['smtp']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp']['username'];
        $mail->Password = $email_config['smtp']['password'];
        $mail->SMTPSecure = $email_config['smtp']['encryption'] === 'ssl' ? 
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : 
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $email_config['smtp']['port'];
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $from_email = $from ?: $email_config['smtp']['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];
        $from_name = $from_name ?: $email_config['smtp']['from_name'] ?? SITE_NAME;
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Send the email
        $mail->send();
        
        // Clear any debug output that was captured
        ob_end_clean();
        return true;
        
    } catch (Exception $e) {
        // Log the error and clean the output buffer
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        ob_end_clean();
        return false;
    }
}
