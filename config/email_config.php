<?php
// Email Configuration
return [
    'smtp' => [
        'host' => 'smtp.gmail.com',  // Gmail SMTP server
        'username' => 'ctxofficial2025@gmail.com',  // Your Gmail address
        'password' => 'jdgmojyaqonmimbm',  // Generate from Google Account > Security > App Passwords
        'port' => 587,              // Gmail SMTP port for TLS
        'encryption' => 'tls',       // Encryption: 'tls' for Gmail
        'from_email' => 'ctxofficial2025@gmail.com', // Must match the username
        'from_name' => SITE_NAME,    // Sender name
        'debug' => 2,               // 0 = off, 1 = client messages, 2 = client and server messages
        'use_smtp' => true,         // Set to false to use PHP's mail() function
        'smtp_auth' => true,        // Enable SMTP authentication
        'smtp_keepalive' => false,  // Keep SMTP connection alive if multiple emails
        'smtp_timeout' => 10        // Timeout in seconds
    ],
    'templates' => [
        'student_registration' => [
            'subject' => 'Welcome to ' . SITE_NAME . ' - Your Student Account Details',
            'template' => 'student_registration' // Template name (without .php)
        ],
        'student_credentials' => [
            'subject' => 'Thank You for Registering with ' . SITE_NAME,
            'template' => 'student_credentials' // Template name (without .php)
        ]
    ]
];
