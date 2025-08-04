<?php
// This template is used for email notifications about gatepass status changes
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $email_subject; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4a6fdc;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8em;
            color: #777;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4a6fdc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .gatepass-details {
            background-color: #f9f9f9;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #4a6fdc;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2><?php echo APP_NAME; ?></h2>
    </div>
    
    <div class="content">
        <h3><?php echo $email_subject; ?></h3>
        <p>Hello <?php echo $recipient_name; ?>,</p>
        
        <p><?php echo $email_message; ?></p>
        
        <div class="gatepass-details">
            <h4>Gatepass Details:</h4>
            <table>
                <tr>
                    <th>Gatepass Number:</th>
                    <td><?php echo $gatepass_number; ?></td>
                </tr>
                <tr>
                    <th>From Location:</th>
                    <td><?php echo $from_location; ?></td>
                </tr>
                <tr>
                    <th>To Location:</th>
                    <td><?php echo $to_location; ?></td>
                </tr>
                <tr>
                    <th>Material Type:</th>
                    <td><?php echo $material_type; ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td><strong><?php echo $status; ?></strong></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($action_url)): ?>
        <p>
            <a href="<?php echo $action_url; ?>" class="button"><?php echo $action_text; ?></a>
        </p>
        <?php endif; ?>
        
        <p>Thank you for using the Gunayatan Gatepass System.</p>
    </div>
    
    <div class="footer">
        <p>This is an automated message, please do not reply directly to this email.</p>
        <p>&copy; <?php echo date('Y'); ?> Gunayatan. All rights reserved.</p>
    </div>
</body>
</html>
