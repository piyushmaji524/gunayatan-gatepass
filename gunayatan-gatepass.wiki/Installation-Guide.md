# Installation Guide

This comprehensive guide will help you install and configure the Gunayatan Gatepass System on your server.

## 📋 Prerequisites

### System Requirements
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 7.4+ (8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Storage**: Minimum 500MB disk space
- **Memory**: 512MB RAM minimum (1GB recommended)

### PHP Extensions
Ensure these PHP extensions are installed:
- `mysqli` or `pdo_mysql`
- `gd` (for image processing)
- `mbstring` (for multi-byte string support)
- `zip` (for file compression)
- `curl` (for external API calls)
- `json` (usually enabled by default)

## 🚀 Installation Steps

### Step 1: Download the Project

```bash
# Option 1: Clone from GitHub
git clone https://github.com/piyushmaji524/gunayatan-gatepass.git
cd gunayatan-gatepass

# Option 2: Download ZIP
# Download from: https://github.com/piyushmaji524/gunayatan-gatepass/archive/refs/heads/main.zip
# Extract to your web server directory
```

### Step 2: Database Setup

1. **Create Database**:
```sql
CREATE DATABASE gatepass CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Create Database User** (recommended for security):
```sql
CREATE USER 'gatepass_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON gatepass.* TO 'gatepass_user'@'localhost';
FLUSH PRIVILEGES;
```

3. **Import Database Schema**:
```bash
mysql -u gatepass_user -p gatepass < database.sql
```

### Step 3: Configuration

1. **Copy Configuration Template**:
```bash
cp includes/config.php.template includes/config.php
```

2. **Edit Configuration** (`includes/config.php`):
```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'gatepass_user');
define('DB_PASSWORD', 'your_secure_password');
define('DB_NAME', 'gatepass');

// Application configuration
define('APP_NAME', 'Gunayatan Gatepass System');
define('BASE_URL', 'http://yourdomain.com/gatepass/');
define('TIMEZONE', 'Asia/Kolkata');

// Email configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Gatepass System');

// Upload configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,pdf,doc,docx');

// Security settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('ENABLE_HTTPS', false); // Set to true in production
define('ENABLE_CSRF_PROTECTION', true);
?>
```

### Step 4: File Permissions

Set appropriate permissions for the web server:

**For Apache/Nginx (usually www-data):**
```bash
# Change ownership
sudo chown -R www-data:www-data /path/to/gatepass-system/

# Set directory permissions
sudo find /path/to/gatepass-system/ -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /path/to/gatepass-system/ -type f -exec chmod 644 {} \;

# Make uploads directory writable
sudo chmod -R 777 /path/to/gatepass-system/uploads/
```

**For shared hosting:**
```bash
chmod -R 755 /path/to/gatepass-system/
chmod -R 777 /path/to/gatepass-system/uploads/
```

### Step 5: Web Server Configuration

#### Apache Configuration

Create virtual host file (`/etc/apache2/sites-available/gatepass.conf`):
```apache
<VirtualHost *:80>
    ServerName gatepass.yourdomain.com
    DocumentRoot /path/to/gatepass-system
    
    <Directory /path/to/gatepass-system>
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set X-Content-Type-Options nosniff
        Header always set X-Frame-Options DENY
        Header always set X-XSS-Protection "1; mode=block"
    </Directory>
    
    # Prevent access to sensitive files
    <Files "*.php~">
        Deny from all
    </Files>
    
    <Files "config.php">
        <RequireAll>
            Require local
            Require ip 127.0.0.1
        </RequireAll>
    </Files>
    
    ErrorLog ${APACHE_LOG_DIR}/gatepass_error.log
    CustomLog ${APACHE_LOG_DIR}/gatepass_access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite gatepass.conf
sudo systemctl reload apache2
```

#### Nginx Configuration

Create server block (`/etc/nginx/sites-available/gatepass`):
```nginx
server {
    listen 80;
    server_name gatepass.yourdomain.com;
    root /path/to/gatepass-system;
    index index.php index.html;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ config\.php$ {
        allow 127.0.0.1;
        deny all;
    }
    
    # Optimize static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    access_log /var/log/nginx/gatepass_access.log;
    error_log /var/log/nginx/gatepass_error.log;
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/gatepass /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 6: SSL Certificate (Production)

For production deployment, secure your application with SSL:

**Using Let's Encrypt:**
```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache  # For Apache
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Get certificate
sudo certbot --apache -d gatepass.yourdomain.com     # For Apache
sudo certbot --nginx -d gatepass.yourdomain.com      # For Nginx
```

### Step 7: Testing Installation

1. **Access your application**: `http://gatepass.yourdomain.com`
2. **Check for errors**: Look for any PHP errors or database connection issues
3. **Test login** with default credentials:
   - **Admin**: admin / admin123
   - **Security**: security / security123
   - **User**: user / user123

⚠️ **Important**: Change these default passwords immediately!

## 🔧 Post-Installation Steps

### 1. Security Hardening

```bash
# Remove installation files
rm install.php
rm database.sql

# Secure file permissions
chmod 644 includes/config.php
chown root:root includes/config.php

# Create .htaccess for additional security (Apache)
cat > .htaccess << 'EOF'
# Prevent directory browsing
Options -Indexes

# Protect sensitive files
<Files "config.php">
    Require all denied
</Files>

<Files "*.log">
    Require all denied
</Files>
EOF
```

### 2. Configure Email Notifications

Test email functionality:
```bash
# Check if PHP mail function works
php -r "mail('test@example.com', 'Test', 'Test message');"
```

### 3. Set Up Backups

Create backup script (`backup.sh`):
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/gatepass"
DB_NAME="gatepass"
DB_USER="gatepass_user"
DB_PASS="your_password"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup database
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/database_$DATE.sql"

# Backup files
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" /path/to/gatepass-system/uploads/

# Keep only last 7 days of backups
find "$BACKUP_DIR" -name "*.sql" -mtime +7 -delete
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +7 -delete
```

Make it executable and add to cron:
```bash
chmod +x backup.sh
crontab -e
# Add line: 0 2 * * * /path/to/backup.sh
```

## 📊 Performance Optimization

### PHP Configuration

Edit `php.ini`:
```ini
; Recommended settings
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
max_input_vars = 3000

; Security settings
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### Database Optimization

```sql
-- Add indexes for better performance
ALTER TABLE gatepasses ADD INDEX idx_status (status);
ALTER TABLE gatepasses ADD INDEX idx_created_by (created_by);
ALTER TABLE gatepasses ADD INDEX idx_created_at (created_at);
ALTER TABLE gatepass_items ADD INDEX idx_gatepass_id (gatepass_id);
```

## ✅ Verification Checklist

- [ ] Database connection successful
- [ ] All default users can log in
- [ ] File uploads work correctly
- [ ] Email notifications send properly
- [ ] PDF generation functions
- [ ] Hindi translation displays correctly
- [ ] All user roles function as expected
- [ ] SSL certificate installed (production)
- [ ] Backups configured
- [ ] Log files rotating properly

## 🆘 Troubleshooting

See our **[Troubleshooting Guide](Troubleshooting)** for common installation issues and solutions.

---

Next: **[Configuration Guide](Configuration)** for advanced settings.
