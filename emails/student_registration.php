<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?= htmlspecialchars($site_name) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4e73df;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .credentials {
            background-color: #f8f9fc;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4e73df;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to <?= htmlspecialchars($site_name) ?>!</h1>
    </div>
    
    <div class="content">
        <p>Hello <?= htmlspecialchars($full_name) ?>,</p>
        
        <p>Welcome to <?= htmlspecialchars($site_name) ?>! Your student account has been successfully created.</p>
        
        <div class="credentials">
            <h3>Your Login Details:</h3>
            <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
            <p><strong>Temporary Password:</strong> <?= htmlspecialchars($password) ?></p>
            <p><strong>Course:</strong> <?= htmlspecialchars($course_name) ?> (<?= htmlspecialchars($course_code) ?>)</p>
            <p><strong>Enrollment Date:</strong> <?= htmlspecialchars($enrollment_date) ?></p>
        </div>
        
        <p>For security reasons, you'll be required to change your temporary password when you first log in.</p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="<?= htmlspecialchars($login_url) ?>" class="button">Login to Your Account</a>
        </div>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team at 
        <a href="mailto:<?= htmlspecialchars($support_email) ?>"><?= htmlspecialchars($support_email) ?></a>.</p>
        
        <p>Best regards,<br>The <?= htmlspecialchars($site_name) ?> Team</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.</p>
    </div>
</body>
</html>
