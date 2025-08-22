<?php
/**
 * Student Credentials Email Template
 * 
 * Available variables:
 * - $full_name: Student's full name
 * - $username: Student's username
 * - $password: Student's password
 * - $login_url: URL to the login page
 * - $site_name: Name of the coaching center
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?> - Your Student Account</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background-color: #4e73df; 
            color: white; 
            padding: 20px; 
            text-align: center; 
            border-radius: 8px 8px 0 0;
        }
        .content { 
            padding: 25px;
            color: #4a4a4a;
        }
        .credentials { 
            background-color: #f8f9fc; 
            padding: 20px; 
            border-radius: 5px; 
            margin: 20px 0;
            border-left: 4px solid #4e73df;
        }
        .button {
            background-color: #4e73df;
            color: white !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 15px 0;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
        p {
            margin: 0 0 15px 0;
        }
        h1, h2, h3 {
            margin-top: 0;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to <?= htmlspecialchars($site_name) ?></h1>
        </div>
        
        <div class="content">
            <p>Hello <?= htmlspecialchars($full_name) ?>,</p>
            
            <p>Your student account has been successfully created. Here are your login details:</p>
            
            <div class="credentials">
                <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
                <p><strong>Password:</strong> <?= htmlspecialchars($password) ?></p>
                <p><strong>Login URL:</strong> <a href="<?= $login_url ?>"><?= $login_url ?></a></p>
            </div>
            
            <p>For security reasons, please change your password after your first login.</p>
            
            <div style="text-align: center; margin: 25px 0;">
                <a href="<?= $login_url ?>" class="button">Login to Your Account</a>
            </div>
            
            <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
            
            <p>Best regards,<br>
            <strong><?= htmlspecialchars($site_name) ?> Team</strong></p>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
