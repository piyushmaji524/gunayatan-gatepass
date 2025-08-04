# Security Features

The Gunayatan Gatepass System implements comprehensive security measures to protect sensitive data, prevent unauthorized access, and ensure compliance with security standards. This documentation covers all security features and best practices.

## 🔐 Authentication & Authorization

### Multi-Factor Authentication (MFA)

The system supports multiple authentication factors to enhance security:

#### Password Requirements
```php
// Password policy configuration
$password_policy = [
    'min_length' => 8,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_special_chars' => true,
    'disallow_common_passwords' => true,
    'password_history' => 5,  // Remember last 5 passwords
    'expiry_days' => 90       // Force change every 90 days
];
```

#### Account Lockout Protection
```php
function checkAccountLockout($username) {
    $user = getUserByUsername($username);
    
    if ($user['account_locked_until'] && 
        strtotime($user['account_locked_until']) > time()) {
        throw new SecurityException('Account temporarily locked');
    }
    
    return true;
}

function handleFailedLogin($user_id) {
    $attempts = incrementFailedAttempts($user_id);
    
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        lockAccount($user_id, LOCKOUT_DURATION);
        logSecurityEvent('account_locked', $user_id);
    }
}
```

### Role-Based Access Control (RBAC)

#### User Roles Hierarchy
```
Superadmin
    ├── Full system access
    ├── User management
    ├── System configuration
    └── All module access

Admin
    ├── Gatepass management
    ├── User approval
    ├── Reports generation
    └── Limited system settings

Security
    ├── Gatepass verification
    ├── Physical inspection
    ├── Security reports
    └── Gate management

User
    ├── Gatepass creation
    ├── Own gatepass management
    ├── Profile management
    └── Basic reporting
```

#### Permission Matrix
```php
$permissions = [
    'superadmin' => [
        'users.create', 'users.read', 'users.update', 'users.delete',
        'gatepasses.create', 'gatepasses.read', 'gatepasses.update', 'gatepasses.delete',
        'settings.read', 'settings.update',
        'logs.read', 'reports.all'
    ],
    'admin' => [
        'users.read', 'users.approve',
        'gatepasses.read', 'gatepasses.approve', 'gatepasses.decline',
        'reports.admin'
    ],
    'security' => [
        'gatepasses.read', 'gatepasses.verify',
        'reports.security'
    ],
    'user' => [
        'gatepasses.create', 'gatepasses.read.own',
        'profile.update'
    ]
];
```

### Session Management

#### Secure Session Configuration
```php
// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Session configuration
session_set_cookie_params([
    'lifetime' => 3600,        // 1 hour
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
```

#### Session Validation
```php
function validateSession() {
    if (!isset($_SESSION['user_id'])) {
        throw new SecurityException('Invalid session');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        throw new SecurityException('Session expired');
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > SESSION_REGENERATE_TIME) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    return true;
}
```

## 🛡️ Input Validation & Sanitization

### SQL Injection Prevention

#### Prepared Statements
```php
// Safe database queries using prepared statements
function getGatepassById($id) {
    global $conn;
    
    $stmt = $conn->prepare(
        "SELECT g.*, u.full_name as created_by_name 
         FROM gatepasses g 
         JOIN users u ON g.user_id = u.id 
         WHERE g.id = ?"
    );
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}
```

#### Dynamic Query Builder
```php
class QueryBuilder {
    private $query = '';
    private $params = [];
    private $types = '';
    
    public function select($columns) {
        $this->query = "SELECT " . implode(', ', $columns);
        return $this;
    }
    
    public function from($table) {
        $this->query .= " FROM " . $table;
        return $this;
    }
    
    public function where($column, $operator, $value) {
        $this->query .= " WHERE " . $column . " " . $operator . " ?";
        $this->params[] = $value;
        $this->types .= $this->getParamType($value);
        return $this;
    }
    
    public function execute() {
        global $conn;
        $stmt = $conn->prepare($this->query);
        
        if (!empty($this->params)) {
            $stmt->bind_param($this->types, ...$this->params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
}
```

### Cross-Site Scripting (XSS) Prevention

#### Output Encoding
```php
function safeOutput($data, $context = 'html') {
    switch ($context) {
        case 'html':
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        case 'url':
            return urlencode($data);
        case 'js':
            return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        case 'css':
            return preg_replace('/[^a-zA-Z0-9\-_]/', '', $data);
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
```

#### Content Security Policy (CSP)
```php
function setSecurityHeaders() {
    // Content Security Policy
    header("Content-Security-Policy: " . 
           "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' https://cdn.jsdelivr.net; " .
           "connect-src 'self'; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'");
    
    // Additional security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}
```

### Cross-Site Request Forgery (CSRF) Protection

#### CSRF Token Implementation
```php
class CSRFProtection {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time();
        
        // Clean old tokens
        self::cleanExpiredTokens();
        
        return $token;
    }
    
    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        $tokenTime = $_SESSION['csrf_tokens'][$token];
        if (time() - $tokenTime > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Remove token after use (single-use)
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    private static function cleanExpiredTokens() {
        foreach ($_SESSION['csrf_tokens'] as $token => $time) {
            if (time() - $time > CSRF_TOKEN_LIFETIME) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
}
```

#### Form Protection
```html
<!-- CSRF token in forms -->
<form method="POST" action="create_gatepass.php">
    <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
    <!-- Other form fields -->
</form>
```

## 🔍 Security Monitoring & Logging

### Security Event Logging

#### Security Event Types
```php
define('SECURITY_EVENTS', [
    'login_success' => 'User logged in successfully',
    'login_failure' => 'Failed login attempt',
    'account_locked' => 'Account locked due to multiple failed attempts',
    'password_changed' => 'Password changed',
    'privilege_escalation' => 'Attempt to access unauthorized resource',
    'data_export' => 'Data exported from system',
    'suspicious_activity' => 'Suspicious activity detected',
    'csrf_violation' => 'CSRF token validation failed',
    'sql_injection_attempt' => 'Potential SQL injection detected',
    'xss_attempt' => 'Potential XSS attack detected'
]);
```

#### Security Logger
```php
class SecurityLogger {
    public static function logEvent($event_type, $user_id = null, $details = []) {
        global $conn;
        
        $ip_address = self::getRealIpAddress();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        $stmt = $conn->prepare(
            "INSERT INTO security_logs 
             (event_type, user_id, ip_address, user_agent, request_uri, details, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $details_json = json_encode($details);
        $stmt->bind_param("sissss", $event_type, $user_id, $ip_address, 
                         $user_agent, $request_uri, $details_json);
        
        $stmt->execute();
        
        // Alert on critical events
        if (in_array($event_type, CRITICAL_SECURITY_EVENTS)) {
            self::sendSecurityAlert($event_type, $details);
        }
    }
    
    private static function getRealIpAddress() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // CloudFlare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
```

### Intrusion Detection

#### Suspicious Activity Detection
```php
class IntrusionDetection {
    public static function checkSuspiciousActivity($user_id = null) {
        $ip_address = SecurityLogger::getRealIpAddress();
        
        // Check for multiple failed logins
        if (self::checkMultipleFailedLogins($ip_address)) {
            SecurityLogger::logEvent('suspicious_activity', $user_id, [
                'type' => 'multiple_failed_logins',
                'ip_address' => $ip_address
            ]);
            return true;
        }
        
        // Check for rapid requests
        if (self::checkRapidRequests($ip_address)) {
            SecurityLogger::logEvent('suspicious_activity', $user_id, [
                'type' => 'rapid_requests',
                'ip_address' => $ip_address
            ]);
            return true;
        }
        
        // Check for unusual access patterns
        if (self::checkUnusualAccessPatterns($user_id)) {
            SecurityLogger::logEvent('suspicious_activity', $user_id, [
                'type' => 'unusual_access_pattern'
            ]);
            return true;
        }
        
        return false;
    }
    
    private static function checkMultipleFailedLogins($ip_address) {
        global $conn;
        
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count 
             FROM security_logs 
             WHERE event_type = 'login_failure' 
               AND ip_address = ? 
               AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'] > SUSPICIOUS_LOGIN_THRESHOLD;
    }
}
```

### Rate Limiting

#### API Rate Limiting
```php
class RateLimiter {
    private static $redis = null;
    
    public static function checkLimit($identifier, $limit, $window) {
        $redis = self::getRedis();
        $key = "rate_limit:" . $identifier;
        
        $current = $redis->get($key);
        
        if ($current === false) {
            $redis->setex($key, $window, 1);
            return true;
        }
        
        if ($current >= $limit) {
            SecurityLogger::logEvent('rate_limit_exceeded', null, [
                'identifier' => $identifier,
                'limit' => $limit,
                'window' => $window
            ]);
            return false;
        }
        
        $redis->incr($key);
        return true;
    }
    
    public static function checkGlobalLimit($ip_address) {
        // 100 requests per minute per IP
        return self::checkLimit("ip:" . $ip_address, 100, 60);
    }
    
    public static function checkUserLimit($user_id) {
        // 500 requests per hour per user
        return self::checkLimit("user:" . $user_id, 500, 3600);
    }
}
```

## 🔐 Data Protection & Encryption

### Data Encryption

#### Sensitive Data Encryption
```php
class EncryptionManager {
    private static $cipher = 'AES-256-GCM';
    private static $key;
    
    public static function encrypt($data) {
        $key = self::getEncryptionKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::$cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data, 
            self::$cipher, 
            $key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    public static function decrypt($encryptedData) {
        $key = self::getEncryptionKey();
        $data = base64_decode($encryptedData);
        
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $encrypted = substr($data, $ivLength + 16);
        
        return openssl_decrypt(
            $encrypted, 
            self::$cipher, 
            $key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );
    }
    
    private static function getEncryptionKey() {
        if (!self::$key) {
            self::$key = hash('sha256', ENCRYPTION_SECRET, true);
        }
        return self::$key;
    }
}
```

### Personal Data Protection (GDPR Compliance)

#### Data Anonymization
```php
class DataPrivacy {
    public static function anonymizeUser($user_id) {
        global $conn;
        
        $anonymized_data = [
            'username' => 'user_' . $user_id . '_deleted',
            'email' => 'deleted_' . $user_id . '@example.com',
            'full_name' => 'Deleted User',
            'phone' => null,
            'address' => null,
            'employee_id' => null
        ];
        
        $stmt = $conn->prepare(
            "UPDATE users SET 
             username = ?, email = ?, full_name = ?, 
             phone = ?, address = ?, employee_id = ?,
             status = 'deleted'
             WHERE id = ?"
        );
        
        $stmt->bind_param("ssssssi", 
            $anonymized_data['username'],
            $anonymized_data['email'],
            $anonymized_data['full_name'],
            $anonymized_data['phone'],
            $anonymized_data['address'],
            $anonymized_data['employee_id'],
            $user_id
        );
        
        $stmt->execute();
        
        SecurityLogger::logEvent('data_anonymized', null, [
            'user_id' => $user_id,
            'anonymized_fields' => array_keys($anonymized_data)
        ]);
    }
    
    public static function exportUserData($user_id) {
        // Export all user data for GDPR compliance
        $userData = self::collectUserData($user_id);
        
        SecurityLogger::logEvent('data_export', $user_id, [
            'export_type' => 'gdpr_request',
            'data_categories' => array_keys($userData)
        ]);
        
        return $userData;
    }
}
```

## 🔧 Security Configuration

### Environment Security

#### Secure Configuration
```php
// security_config.php
return [
    'password_policy' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special_chars' => true,
        'max_age_days' => 90,
        'history_count' => 5
    ],
    
    'session' => [
        'lifetime' => 3600,           // 1 hour
        'regenerate_interval' => 300, // 5 minutes
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ],
    
    'rate_limiting' => [
        'login_attempts' => 5,
        'lockout_duration' => 1800,   // 30 minutes
        'global_requests_per_minute' => 100,
        'user_requests_per_hour' => 500
    ],
    
    'encryption' => [
        'algorithm' => 'AES-256-GCM',
        'key_rotation_days' => 90
    ],
    
    'file_upload' => [
        'max_size' => '10M',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
        'scan_for_malware' => true,
        'quarantine_suspicious' => true
    ]
];
```

### File Upload Security

#### Secure File Handling
```php
class SecureFileUpload {
    private static $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf'
    ];
    
    public static function uploadFile($file, $uploadDir) {
        // Validate file
        if (!self::validateFile($file)) {
            throw new SecurityException('Invalid file upload');
        }
        
        // Generate secure filename
        $extension = self::getSecureExtension($file);
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        // Move file to secure location
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to upload file');
        }
        
        // Scan for malware (if configured)
        if (MALWARE_SCANNING_ENABLED) {
            self::scanForMalware($filepath);
        }
        
        SecurityLogger::logEvent('file_uploaded', $_SESSION['user_id'], [
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type']
        ]);
        
        return $filename;
    }
    
    private static function validateFile($file) {
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!array_key_exists($mimeType, self::$allowedTypes)) {
            return false;
        }
        
        // Additional validation for images
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return false;
            }
        }
        
        return true;
    }
}
```

## 🚨 Incident Response

### Security Incident Handling

#### Incident Response Workflow
```php
class IncidentResponse {
    public static function handleSecurityIncident($incident_type, $severity, $details) {
        // Log the incident
        $incident_id = self::logIncident($incident_type, $severity, $details);
        
        // Immediate response based on severity
        switch ($severity) {
            case 'critical':
                self::handleCriticalIncident($incident_id, $details);
                break;
            case 'high':
                self::handleHighSeverityIncident($incident_id, $details);
                break;
            case 'medium':
                self::handleMediumSeverityIncident($incident_id, $details);
                break;
            default:
                self::handleLowSeverityIncident($incident_id, $details);
        }
        
        return $incident_id;
    }
    
    private static function handleCriticalIncident($incident_id, $details) {
        // Immediate actions for critical incidents
        
        // 1. Alert security team
        self::alertSecurityTeam($incident_id, 'critical', $details);
        
        // 2. Block suspicious IP if applicable
        if (isset($details['ip_address'])) {
            self::blockIpAddress($details['ip_address'], 'security_incident');
        }
        
        // 3. Force logout affected users
        if (isset($details['user_id'])) {
            self::forceUserLogout($details['user_id']);
        }
        
        // 4. Enable enhanced monitoring
        self::enableEnhancedMonitoring();
    }
}
```

### Automated Response Actions

#### IP Blocking System
```php
class IpBlockingSystem {
    public static function blockIp($ip_address, $reason, $duration = 3600) {
        global $conn;
        
        $expires_at = date('Y-m-d H:i:s', time() + $duration);
        
        $stmt = $conn->prepare(
            "INSERT INTO blocked_ips (ip_address, reason, expires_at, created_at) 
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
             reason = VALUES(reason), 
             expires_at = VALUES(expires_at)"
        );
        
        $stmt->bind_param("sss", $ip_address, $reason, $expires_at);
        $stmt->execute();
        
        SecurityLogger::logEvent('ip_blocked', null, [
            'ip_address' => $ip_address,
            'reason' => $reason,
            'duration' => $duration
        ]);
    }
    
    public static function isIpBlocked($ip_address) {
        global $conn;
        
        $stmt = $conn->prepare(
            "SELECT id FROM blocked_ips 
             WHERE ip_address = ? 
               AND (expires_at IS NULL OR expires_at > NOW())
               AND is_active = 1"
        );
        
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }
}
```

## 📊 Security Metrics & Reporting

### Security Dashboard

#### Key Security Metrics
```php
class SecurityMetrics {
    public static function getSecurityDashboard() {
        return [
            'failed_login_attempts' => self::getFailedLoginAttempts(),
            'blocked_ips' => self::getBlockedIpCount(),
            'security_incidents' => self::getSecurityIncidents(),
            'user_sessions' => self::getActiveUserSessions(),
            'password_strength' => self::getPasswordStrengthStats(),
            'mfa_adoption' => self::getMfaAdoptionRate()
        ];
    }
    
    private static function getFailedLoginAttempts() {
        global $conn;
        
        $stmt = $conn->prepare(
            "SELECT 
                COUNT(*) as total,
                COUNT(DISTINCT ip_address) as unique_ips,
                DATE(created_at) as date
             FROM security_logs 
             WHERE event_type = 'login_failure' 
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC"
        );
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
```

## 📚 Related Documentation

- **[Installation Guide](Installation-Guide)** - Security setup during installation
- **[User Manual](User-Manual)** - Security features for end users
- **[Database Schema](Database-Schema)** - Security-related database tables
- **[API Documentation](API-Documentation)** - API security measures

---

This comprehensive security documentation ensures that the Gunayatan Gatepass System maintains the highest standards of data protection and system security.
