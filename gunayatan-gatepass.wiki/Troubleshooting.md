# Troubleshooting Guide

This comprehensive troubleshooting guide helps users and administrators resolve common issues with the Gunayatan Gatepass System. Follow the structured approach to quickly identify and fix problems.

## 🔍 General Troubleshooting Approach

### Step-by-Step Diagnosis

1. **Identify the Problem**
   - What exactly is not working?
   - When did the issue start?
   - Who is affected?
   - What error messages appear?

2. **Check System Status**
   - Verify server is running
   - Check database connectivity
   - Review system logs
   - Confirm network connectivity

3. **Isolate the Issue**
   - Test with different users
   - Try different browsers
   - Check on different devices
   - Test core functionality

4. **Apply Solutions**
   - Start with simple fixes
   - Test after each change
   - Document what works
   - Monitor for recurrence

## 🖥️ Installation Issues

### Database Connection Problems

#### Error: "Connection refused" or "Access denied"

**Symptoms:**
- Cannot access the application
- Database error messages
- Installation fails at database step

**Solutions:**

1. **Check Database Credentials**
```bash
# Test MySQL connection
mysql -u username -p -h localhost database_name
```

2. **Verify Database Server Status**
```bash
# Check MySQL service status
sudo systemctl status mysql
# or
sudo service mysql status

# Start MySQL if stopped
sudo systemctl start mysql
```

3. **Check Configuration**
```php
// In includes/config.php
define('DB_HOST', 'localhost');     // Correct host
define('DB_USER', 'your_username'); // Valid username
define('DB_PASS', 'your_password'); // Correct password
define('DB_NAME', 'gunayatan_gatepass'); // Existing database
```

4. **Grant Proper Permissions**
```sql
GRANT ALL PRIVILEGES ON gunayatan_gatepass.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

### Apache/Nginx Configuration Issues

#### Error: "404 Not Found" or "Permission Denied"

**For Apache:**

1. **Check .htaccess File**
```apache
# Ensure .htaccess exists with proper rules
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

2. **Verify mod_rewrite is Enabled**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

3. **Check Directory Permissions**
```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/html/gatepass
sudo chmod -R 755 /var/www/html/gatepass
sudo chmod -R 777 /var/www/html/gatepass/uploads
```

**For Nginx:**

1. **Check Nginx Configuration**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### PHP Configuration Problems

#### Error: "PHP Extensions Missing"

**Required Extensions Check:**
```bash
# Check installed PHP extensions
php -m | grep -E "(mysqli|json|openssl|curl|gd|mbstring)"

# Install missing extensions (Ubuntu/Debian)
sudo apt-get install php-mysql php-json php-openssl php-curl php-gd php-mbstring

# Install missing extensions (CentOS/RHEL)
sudo yum install php-mysql php-json php-openssl php-curl php-gd php-mbstring
```

#### Error: "Upload Size Limit Exceeded"

**Solution:**
```ini
# In php.ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
memory_limit = 256M

# Restart web server after changes
sudo systemctl restart apache2
# or
sudo systemctl restart nginx
```

## 🔐 Authentication & Login Issues

### Cannot Login / "Invalid Credentials"

#### Problem: Valid credentials rejected

**Check User Status:**
```sql
SELECT username, email, status, failed_login_attempts, account_locked_until 
FROM users 
WHERE username = 'your_username';
```

**Solutions:**

1. **Account Status Issues**
```sql
-- Activate pending account
UPDATE users SET status = 'active' WHERE username = 'your_username';

-- Unlock locked account
UPDATE users SET 
    failed_login_attempts = 0, 
    account_locked_until = NULL 
WHERE username = 'your_username';
```

2. **Password Reset**
```php
// Use reset_password_tool.php
// Or manually reset:
$new_password = password_hash('new_password', PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $new_password, $username);
$stmt->execute();
```

3. **Clear Session Issues**
```bash
# Clear PHP sessions
sudo rm -rf /var/lib/php/sessions/*

# Or in application
session_destroy();
```

### Session Timeout Issues

#### Problem: Frequent logouts or session expired messages

**Solutions:**

1. **Increase Session Timeout**
```php
// In config.php
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_set_cookie_params(3600);
```

2. **Check Session Storage**
```php
// Verify session directory is writable
$session_path = session_save_path();
echo "Session path: " . $session_path . "\n";
echo "Writable: " . (is_writable($session_path) ? 'Yes' : 'No');
```

## 📊 Database Issues

### Performance Problems

#### Problem: Slow query execution or timeouts

**Diagnosis:**
```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Analyze query performance
EXPLAIN SELECT * FROM gatepasses WHERE status = 'pending';

-- Check table sizes
SELECT 
    table_name,
    round(((data_length + index_length) / 1024 / 1024), 2) 'Size in MB'
FROM information_schema.tables 
WHERE table_schema = 'gunayatan_gatepass';
```

**Solutions:**

1. **Add Missing Indexes**
```sql
-- Common indexes for better performance
CREATE INDEX idx_gatepass_status_date ON gatepasses(status, created_at);
CREATE INDEX idx_user_status ON users(status);
CREATE INDEX idx_notification_user_read ON notifications(user_id, is_read);
```

2. **Optimize Tables**
```sql
OPTIMIZE TABLE gatepasses;
OPTIMIZE TABLE users;
OPTIMIZE TABLE system_logs;
```

3. **Clean Old Data**
```sql
-- Remove old system logs (keep 6 months)
DELETE FROM system_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Remove old notifications (keep 3 months)
DELETE FROM notifications 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH) 
  AND is_read = 1;
```

### Data Corruption Issues

#### Problem: Incorrect data or foreign key errors

**Diagnosis:**
```sql
-- Check for orphaned records
SELECT g.id, g.user_id 
FROM gatepasses g 
LEFT JOIN users u ON g.user_id = u.id 
WHERE u.id IS NULL;

-- Check referential integrity
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'gunayatan_gatepass';
```

**Solutions:**

1. **Fix Orphaned Records**
```sql
-- Remove orphaned gatepasses
DELETE FROM gatepasses 
WHERE user_id NOT IN (SELECT id FROM users);

-- Or create placeholder user
INSERT INTO users (username, email, full_name, status) 
VALUES ('deleted_user', 'deleted@example.com', 'Deleted User', 'inactive');

UPDATE gatepasses 
SET user_id = LAST_INSERT_ID() 
WHERE user_id NOT IN (SELECT id FROM users WHERE id != LAST_INSERT_ID());
```

2. **Repair Tables**
```sql
REPAIR TABLE gatepasses;
REPAIR TABLE users;
CHECK TABLE gatepasses;
```

## 🔔 Notification System Issues

### Email Notifications Not Working

#### Problem: Emails not being sent

**Check Configuration:**
```php
// In includes/config.php
define('SMTP_HOST', 'smtp.gmail.com');     // Correct SMTP server
define('SMTP_PORT', 587);                  // Correct port
define('SMTP_USERNAME', 'your@email.com'); // Valid email
define('SMTP_PASSWORD', 'your_password');  // App password for Gmail
define('SMTP_ENCRYPTION', 'tls');          // TLS or SSL
```

**Test Email Function:**
```php
// Create test_email.php
require_once 'includes/config.php';
require_once 'includes/phpmailer/PHPMailer.php';

try {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port = SMTP_PORT;
    
    $mail->setFrom(SMTP_USERNAME, 'Test System');
    $mail->addAddress('test@example.com');
    $mail->Subject = 'Test Email';
    $mail->Body = 'If you receive this, email is working!';
    
    if ($mail->send()) {
        echo 'Email sent successfully!';
    } else {
        echo 'Email failed: ' . $mail->ErrorInfo;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
```

**Common Solutions:**

1. **Gmail App Passwords**
   - Enable 2-factor authentication
   - Generate app-specific password
   - Use app password instead of regular password

2. **Firewall Issues**
```bash
# Check if ports are blocked
telnet smtp.gmail.com 587
nc -v smtp.gmail.com 587
```

3. **Check Logs**
```bash
# Check mail logs
tail -f /var/log/mail.log
tail -f /var/log/apache2/error.log
```

### Push Notifications Not Working

#### Problem: Browser notifications not appearing

**Check Browser Support:**
```javascript
// Add to your JavaScript
if ('Notification' in window) {
    console.log('Notifications supported');
    console.log('Permission:', Notification.permission);
} else {
    console.log('Notifications not supported');
}

if ('serviceWorker' in navigator) {
    console.log('Service Worker supported');
} else {
    console.log('Service Worker not supported');
}
```

**Debug Service Worker:**
```javascript
// Check service worker registration
navigator.serviceWorker.getRegistrations().then(function(registrations) {
    console.log('Active service workers:', registrations.length);
    registrations.forEach(function(registration) {
        console.log('SW scope:', registration.scope);
        console.log('SW state:', registration.active?.state);
    });
});
```

**Solutions:**

1. **Request Permission**
```javascript
if (Notification.permission === 'default') {
    Notification.requestPermission().then(function(permission) {
        console.log('Permission result:', permission);
    });
}
```

2. **Check HTTPS Requirement**
   - Push notifications require HTTPS
   - Test on localhost or secure domain
   - Verify SSL certificate is valid

## 📁 File Upload Issues

### Upload Failures

#### Problem: "File upload failed" or size limit errors

**Check File Permissions:**
```bash
# Verify upload directory
ls -la uploads/
drwxrwxrwx 2 www-data www-data 4096 Jan 15 10:30 uploads

# Fix permissions if needed
sudo chmod 777 uploads/
sudo chown www-data:www-data uploads/
```

**Check PHP Configuration:**
```php
// Check current limits
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
```

**Solutions:**

1. **Increase Upload Limits**
```ini
# In php.ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
memory_limit = 256M
```

2. **Check Disk Space**
```bash
df -h /var/www/html/gatepass/uploads
```

3. **Validate File Types**
```php
// In upload.php - add debugging
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$file_type = $_FILES['file']['type'];
echo "File type: " . $file_type . "\n";
echo "Allowed: " . (in_array($file_type, $allowed_types) ? 'Yes' : 'No') . "\n";
```

## 🌐 Browser Compatibility Issues

### Display Problems

#### Problem: Layout issues or missing features

**Check Browser Version:**
```javascript
// Add to your page for debugging
console.log('User Agent:', navigator.userAgent);
console.log('Browser:', {
    chrome: navigator.userAgent.includes('Chrome'),
    firefox: navigator.userAgent.includes('Firefox'),
    safari: navigator.userAgent.includes('Safari'),
    edge: navigator.userAgent.includes('Edge')
});
```

**Common Solutions:**

1. **Clear Browser Cache**
   - Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
   - Clear browser cache and cookies
   - Try incognito/private mode

2. **JavaScript Console Errors**
```javascript
// Check for JavaScript errors
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
});
```

3. **CSS Compatibility**
```css
/* Add browser prefixes for compatibility */
.button {
    -webkit-border-radius: 4px;
    -moz-border-radius: 4px;
    border-radius: 4px;
}
```

## 🔧 System Maintenance Issues

### Log File Management

#### Problem: Log files growing too large

**Check Log Sizes:**
```bash
# Check log file sizes
du -sh /var/log/apache2/*.log
du -sh includes/mail_log.txt
```

**Solutions:**

1. **Rotate Logs**
```bash
# Configure logrotate
sudo nano /etc/logrotate.d/gatepass

/var/www/html/gatepass/includes/mail_log.txt {
    daily
    rotate 30
    compress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

2. **Clean Application Logs**
```sql
-- Clean old system logs
DELETE FROM system_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Clean old notifications
DELETE FROM notifications 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH) 
  AND is_read = 1;
```

### Performance Monitoring

#### Problem: System running slowly

**Check System Resources:**
```bash
# Check CPU and memory usage
top
htop

# Check disk usage
df -h

# Check MySQL processes
mysql -e "SHOW PROCESSLIST;"
```

**Solutions:**

1. **Optimize Database**
```sql
-- Analyze tables
ANALYZE TABLE gatepasses, users, system_logs;

-- Optimize tables
OPTIMIZE TABLE gatepasses, users, system_logs;
```

2. **Enable Caching**
```php
// In config.php
// Enable OPcache
ini_set('opcache.enable', 1);
ini_set('opcache.memory_consumption', 128);
ini_set('opcache.max_accelerated_files', 4000);
```

## 🆘 Emergency Procedures

### System Recovery

#### Complete System Failure

**Recovery Steps:**

1. **Check Basic Services**
```bash
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status php7.4-fpm  # if using PHP-FPM
```

2. **Restore from Backup**
```bash
# Restore database
mysql -u username -p gunayatan_gatepass < backup_file.sql

# Restore files
tar -xzf file_backup.tar.gz -C /var/www/html/
```

3. **Reset Permissions**
```bash
sudo chown -R www-data:www-data /var/www/html/gatepass
sudo chmod -R 755 /var/www/html/gatepass
sudo chmod -R 777 /var/www/html/gatepass/uploads
```

### Emergency Access

#### Admin Account Locked Out

**Create Emergency Admin:**
```sql
-- Create emergency admin user
INSERT INTO users (username, email, password, role, status, full_name) 
VALUES (
    'emergency_admin',
    'admin@yourdomain.com',
    '$2y$10$example_hash_here',  -- Use password_hash() to generate
    'superadmin',
    'active',
    'Emergency Admin'
);
```

**Reset Existing Admin:**
```sql
-- Reset admin password and unlock account
UPDATE users SET 
    password = '$2y$10$new_hash_here',
    failed_login_attempts = 0,
    account_locked_until = NULL,
    status = 'active'
WHERE role = 'superadmin' AND username = 'admin';
```

## 📞 Getting Help

### Debug Information Collection

When reporting issues, collect this information:

```php
// Create debug_info.php
echo "=== SYSTEM INFORMATION ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Upload Dir Writable: " . (is_writable('uploads/') ? 'Yes' : 'No') . "\n";

echo "\n=== DATABASE ===\n";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    echo "Database Connection: OK\n";
    echo "MySQL Version: " . $conn->server_info . "\n";
} catch (Exception $e) {
    echo "Database Connection: FAILED - " . $e->getMessage() . "\n";
}

echo "\n=== PHP EXTENSIONS ===\n";
$required = ['mysqli', 'json', 'openssl', 'curl', 'gd', 'mbstring'];
foreach ($required as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? 'OK' : 'MISSING') . "\n";
}
```

### Log Analysis

**Common Error Patterns:**

1. **MySQL Errors:**
   - `Access denied for user` → Check credentials
   - `Unknown database` → Create database
   - `Connection refused` → Start MySQL service

2. **PHP Errors:**
   - `Fatal error: Call to undefined function` → Missing extension
   - `Permission denied` → File permissions issue
   - `Maximum execution time exceeded` → Increase time limit

3. **Apache/Nginx Errors:**
   - `404 Not Found` → Check URL rewriting
   - `403 Forbidden` → Check file permissions
   - `500 Internal Server Error` → Check error logs

### Contact Support

If you cannot resolve the issue:

1. **Check System Logs:**
   - Apache/Nginx error logs
   - PHP error logs
   - Application logs in `includes/mail_log.txt`

2. **Document the Issue:**
   - Exact error messages
   - Steps to reproduce
   - System information
   - Recent changes made

3. **Community Support:**
   - GitHub Issues: Report bugs and feature requests
   - Documentation: Check wiki for solutions
   - Forums: Ask questions in community discussions

---

This troubleshooting guide covers the most common issues. For complex problems, systematic diagnosis and step-by-step resolution usually leads to successful fixes.
