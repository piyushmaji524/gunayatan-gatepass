# Installation Guide

This guide will help you set up the Gunayatan Gatepass System on your server.

## System Requirements

### Server Requirements
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 7.4+ (8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Storage**: Minimum 500MB disk space

### PHP Extensions Required
- `mysqli` or `pdo_mysql`
- `gd` (for image processing)
- `mbstring` (for multi-byte string support)
- `zip` (for file compression)
- `curl` (for external API calls)
- `json` (usually enabled by default)

## Step-by-Step Installation

### 1. Download and Extract

```bash
# Clone the repository
git clone https://github.com/piyushmaji524/gunayatan-gatepass.git

# Navigate to the project directory
cd gunayatan-gatepass
```

### 2. Database Setup

1. Create a new MySQL database:
```sql
CREATE DATABASE gatepass CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Create a database user (recommended):
```sql
CREATE USER 'gatepass_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON gatepass.* TO 'gatepass_user'@'localhost';
FLUSH PRIVILEGES;
```

3. Import the database schema:
```bash
mysql -u gatepass_user -p gatepass < database.sql
```

### 3. Configuration

1. Copy the configuration template:
```bash
cp includes/config.php.template includes/config.php
```

2. Edit `includes/config.php` with your settings:
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

// Upload configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
?>
```

### 4. File Permissions

Set appropriate permissions for the web server:

```bash
# For Apache/Nginx user (usually www-data)
sudo chown -R www-data:www-data /path/to/gatepass-system/
sudo chmod -R 755 /path/to/gatepass-system/
sudo chmod -R 777 /path/to/gatepass-system/uploads/
```

### 5. Web Server Configuration

#### Apache Configuration

Create a virtual host file:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/gatepass-system
    
    <Directory /path/to/gatepass-system>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/gatepass_error.log
    CustomLog ${APACHE_LOG_DIR}/gatepass_access.log combined
</VirtualHost>
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/gatepass-system;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 6. SSL Certificate (Recommended)

For production deployment, secure your application with SSL:

```bash
# Using Let's Encrypt
sudo certbot --apache -d yourdomain.com
# or for Nginx
sudo certbot --nginx -d yourdomain.com
```

### 7. Testing Installation

1. Access your application: `http://yourdomain.com`
2. Log in with default credentials:
   - **Admin**: admin / admin123
   - **Security**: security / security123
   - **User**: user / user123

**Important**: Change these default passwords immediately!

## Post-Installation Security

### 1. Change Default Passwords
Access each role and change the default passwords through the profile section.

### 2. Remove Installation Files
```bash
rm install.php
rm database.sql
```

### 3. Update Configuration
- Set strong database passwords
- Configure proper file upload restrictions
- Set up regular database backups

### 4. Enable HTTPS
Ensure all traffic is encrypted, especially for production environments.

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config.php`
   - Ensure MySQL service is running
   - Verify user permissions

2. **File Upload Issues**
   - Check `uploads/` directory permissions
   - Verify PHP `upload_max_filesize` and `post_max_size` settings

3. **Session Issues**
   - Check PHP session configuration
   - Ensure session directory is writable

4. **Permission Denied Errors**
   - Verify web server user owns the files
   - Check directory permissions

### Log Files

Check these log files for troubleshooting:
- Web server error logs
- PHP error logs
- Application logs in `includes/mail_log.txt`

## Performance Optimization

### 1. PHP Configuration
```ini
; Recommended PHP settings
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

### 2. Database Optimization
- Enable MySQL slow query log
- Optimize database tables regularly
- Consider database indexing for large datasets

### 3. Web Server Optimization
- Enable gzip compression
- Set up browser caching
- Use a reverse proxy like Cloudflare for additional performance

## Support

If you encounter issues during installation:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review the [GitHub Issues](https://github.com/piyushmaji524/gunayatan-gatepass/issues)
3. Create a new issue with detailed information about your problem
