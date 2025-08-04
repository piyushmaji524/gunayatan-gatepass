# Database Schema

The Gunayatan Gatepass System uses a well-structured MySQL database design optimized for security, performance, and scalability. This documentation provides comprehensive details about all database tables, relationships, and indexes.

## 🗄️ Database Overview

**Database Name**: `gunayatan_gatepass`  
**Engine**: InnoDB (for ACID compliance and foreign key support)  
**Character Set**: utf8mb4 (full Unicode support including Hindi characters)  
**Collation**: utf8mb4_unicode_ci  

### Design Principles

- **Normalization**: Third Normal Form (3NF) to minimize data redundancy
- **Referential Integrity**: Foreign key constraints ensure data consistency
- **Security**: Proper data types and constraints prevent injection attacks
- **Performance**: Strategic indexing for optimal query performance
- **Audit Trail**: Comprehensive logging for compliance and debugging

## 📊 Core Tables

### 1. Users Table

Manages all system users across different roles.

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'security', 'superadmin') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'pending',
    department VARCHAR(100) NULL,
    employee_id VARCHAR(50) UNIQUE NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NULL,
    address TEXT NULL,
    profile_image VARCHAR(255) NULL,
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    account_locked_until TIMESTAMP NULL,
    password_reset_token VARCHAR(100) NULL,
    password_reset_expires TIMESTAMP NULL,
    email_verification_token VARCHAR(100) NULL,
    email_verified_at TIMESTAMP NULL,
    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
    notification_preferences JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_employee_id (employee_id),
    INDEX idx_last_login (last_login),
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

**Key Features:**
- Secure password storage with bcrypt hashing
- Account lockout mechanism for security
- Email verification system
- Timezone support for international users
- JSON field for flexible notification preferences

### 2. Gatepasses Table

Core table storing all gatepass information.

```sql
CREATE TABLE gatepasses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gatepass_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    from_location VARCHAR(100) NOT NULL,
    to_location VARCHAR(100) NOT NULL,
    material_type VARCHAR(100) NOT NULL,
    purpose TEXT NOT NULL,
    departure_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    return_date DATE NULL,
    return_time TIME NULL,
    vehicle_number VARCHAR(20) NULL,
    driver_name VARCHAR(100) NULL,
    driver_license VARCHAR(50) NULL,
    status ENUM('pending', 'approved', 'declined', 'verified', 'completed', 'cancelled') DEFAULT 'pending',
    admin_remarks TEXT NULL,
    security_remarks TEXT NULL,
    approval_date TIMESTAMP NULL,
    approved_by INT NULL,
    verification_date TIMESTAMP NULL,
    verified_by INT NULL,
    completion_date TIMESTAMP NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    is_emergency BOOLEAN DEFAULT FALSE,
    estimated_value DECIMAL(12,2) NULL,
    insurance_required BOOLEAN DEFAULT FALSE,
    special_instructions TEXT NULL,
    pdf_path VARCHAR(255) NULL,
    qr_code_path VARCHAR(255) NULL,
    validity_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_gatepass_number (gatepass_number),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_departure_date (departure_date),
    INDEX idx_approval_date (approval_date),
    INDEX idx_priority (priority),
    INDEX idx_is_emergency (is_emergency),
    INDEX idx_validity_expires (validity_expires),
    INDEX idx_created_at (created_at),
    INDEX idx_composite_status_date (status, departure_date),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);
```

**Key Features:**
- Unique gatepass numbering system
- Comprehensive status tracking
- Priority and emergency handling
- File path storage for PDFs and QR codes
- Expiry mechanism for validity control

### 3. Gatepass Items Table

Detailed item information for each gatepass.

```sql
CREATE TABLE gatepass_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gatepass_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'piece',
    weight DECIMAL(10,2) NULL,
    weight_unit VARCHAR(10) DEFAULT 'kg',
    dimensions VARCHAR(50) NULL,
    material VARCHAR(50) NULL,
    brand VARCHAR(50) NULL,
    model VARCHAR(50) NULL,
    serial_number VARCHAR(100) NULL,
    barcode VARCHAR(100) NULL,
    estimated_value DECIMAL(12,2) NULL,
    condition_notes TEXT NULL,
    is_returnable BOOLEAN DEFAULT TRUE,
    category VARCHAR(50) NULL,
    subcategory VARCHAR(50) NULL,
    supplier VARCHAR(100) NULL,
    purchase_date DATE NULL,
    warranty_expires DATE NULL,
    image_path VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_gatepass_id (gatepass_id),
    INDEX idx_item_name (item_name),
    INDEX idx_serial_number (serial_number),
    INDEX idx_barcode (barcode),
    INDEX idx_category (category),
    INDEX idx_is_returnable (is_returnable),
    INDEX idx_sort_order (sort_order),
    
    FOREIGN KEY (gatepass_id) REFERENCES gatepasses(id) ON DELETE CASCADE
);
```

**Key Features:**
- Flexible quantity and unit management
- Comprehensive item tracking
- Barcode and serial number support
- Category-based organization
- Image storage capabilities

### 4. System Logs Table

Comprehensive audit trail for all system activities.

```sql
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(128) NULL,
    request_method VARCHAR(10) NULL,
    request_uri TEXT NULL,
    response_code INT NULL,
    execution_time DECIMAL(8,4) NULL,
    memory_usage INT NULL,
    error_message TEXT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    module VARCHAR(50) NOT NULL,
    additional_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_severity (severity),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    INDEX idx_composite_user_action (user_id, action, created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Key Features:**
- Complete change tracking with before/after values
- Performance monitoring metrics
- Security audit capabilities
- Flexible JSON data storage
- Comprehensive indexing for analysis

## 🔄 Supporting Tables

### 5. Notifications Table

Real-time notification system.

```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'gatepass') DEFAULT 'info',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    is_read BOOLEAN DEFAULT FALSE,
    is_delivered BOOLEAN DEFAULT FALSE,
    delivery_method ENUM('web', 'email', 'sms', 'push') DEFAULT 'web',
    related_entity_type VARCHAR(50) NULL,
    related_entity_id INT NULL,
    action_url VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_priority (priority),
    INDEX idx_is_read (is_read),
    INDEX idx_is_delivered (is_delivered),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at),
    INDEX idx_composite_user_unread (user_id, is_read, created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 6. Push Subscriptions Table

Web push notification management.

```sql
CREATE TABLE push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    error_count INT DEFAULT 0,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_last_used (last_used),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 7. Translation Cache Table

Hindi translation caching system.

```sql
CREATE TABLE translation_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    english_text VARCHAR(500) NOT NULL,
    hindi_text VARCHAR(500) NOT NULL,
    source VARCHAR(20) DEFAULT 'google',
    confidence_score DECIMAL(3,2) NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT NULL,
    usage_count INT DEFAULT 1,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_english_text (english_text),
    INDEX idx_hindi_text (hindi_text),
    INDEX idx_source (source),
    INDEX idx_is_verified (is_verified),
    INDEX idx_usage_count (usage_count),
    INDEX idx_last_used (last_used),
    
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 8. Settings Table

System configuration management.

```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    data_type ENUM('string', 'integer', 'float', 'boolean', 'json', 'text') DEFAULT 'string',
    description TEXT NULL,
    is_editable BOOLEAN DEFAULT TRUE,
    is_sensitive BOOLEAN DEFAULT FALSE,
    validation_rules JSON NULL,
    default_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    
    UNIQUE INDEX idx_category_key (category, setting_key),
    INDEX idx_category (category),
    INDEX idx_is_editable (is_editable),
    INDEX idx_is_sensitive (is_sensitive),
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);
```

## 🔗 Database Relationships

### Entity Relationship Diagram

```
Users (1) -----> (*) Gatepasses (user_id)
Users (1) -----> (*) Gatepasses (approved_by)
Users (1) -----> (*) Gatepasses (verified_by)
Users (1) -----> (*) System_Logs (user_id)
Users (1) -----> (*) Notifications (user_id)
Users (1) -----> (*) Push_Subscriptions (user_id)

Gatepasses (1) -----> (*) Gatepass_Items (gatepass_id)

Users (1) -----> (*) Translation_Cache (verified_by)
Users (1) -----> (*) Settings (updated_by)
```

### Key Relationships

1. **Users → Gatepasses**: One-to-many (creator, approver, verifier)
2. **Gatepasses → Gatepass_Items**: One-to-many (master-detail)
3. **Users → Notifications**: One-to-many (recipient)
4. **Users → System_Logs**: One-to-many (actor)

## 🚀 Performance Optimization

### Indexing Strategy

#### Primary Indexes
- **Primary Keys**: Auto-increment integers for optimal performance
- **Unique Indexes**: Username, email, gatepass_number for data integrity
- **Foreign Key Indexes**: All foreign key columns for join performance

#### Composite Indexes
```sql
-- Frequently used query patterns
INDEX idx_composite_status_date (status, departure_date)
INDEX idx_composite_user_unread (user_id, is_read, created_at)
INDEX idx_composite_user_action (user_id, action, created_at)
```

#### Covering Indexes
```sql
-- For dashboard queries
INDEX idx_gatepass_dashboard (user_id, status, created_at, gatepass_number)

-- For notification counts
INDEX idx_notification_count (user_id, is_read, type, created_at)
```

### Query Optimization Examples

#### Efficient Gatepass Listing
```sql
-- Optimized query for user dashboard
SELECT 
    g.id, g.gatepass_number, g.status, g.departure_date,
    g.from_location, g.to_location, g.created_at
FROM gatepasses g
WHERE g.user_id = ? 
    AND g.status IN ('pending', 'approved', 'verified')
ORDER BY g.created_at DESC
LIMIT 10 OFFSET ?;
```

#### Fast Status Counts
```sql
-- Dashboard status summary
SELECT 
    status,
    COUNT(*) as count
FROM gatepasses 
WHERE user_id = ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY status;
```

### Maintenance Procedures

#### Regular Cleanup
```sql
-- Clean old system logs (keep 6 months)
DELETE FROM system_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Clean old notifications (keep 3 months)
DELETE FROM notifications 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
    AND is_read = TRUE;

-- Clean expired gatepasses
UPDATE gatepasses 
SET status = 'expired' 
WHERE validity_expires < NOW() 
    AND status NOT IN ('completed', 'cancelled', 'expired');
```

#### Index Maintenance
```sql
-- Analyze table statistics
ANALYZE TABLE users, gatepasses, gatepass_items, system_logs;

-- Optimize tables
OPTIMIZE TABLE translation_cache, notifications;

-- Check index usage
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'gunayatan_gatepass'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

## 🔒 Security Considerations

### Data Protection

#### Sensitive Data Handling
```sql
-- Password hashing (handled in application)
-- Store bcrypt hashes, never plain text

-- Email verification tokens
-- Random 32-character strings with expiry

-- Session management
-- Store session IDs, not sensitive data
```

#### Access Control
```sql
-- Role-based queries
SELECT * FROM gatepasses 
WHERE (
    user_id = ? OR  -- Own gatepasses
    ? IN (SELECT id FROM users WHERE role = 'admin') OR  -- Admin access
    (? IN (SELECT id FROM users WHERE role = 'security') AND status = 'approved')  -- Security access
);
```

### Audit Requirements

#### Change Tracking
All critical operations are logged in `system_logs` table:
- User login/logout
- Gatepass creation/modification
- Status changes
- Administrative actions

#### Compliance Features
- Immutable audit log (no DELETE permissions for application user)
- Timestamp precision to milliseconds
- IP address and user agent tracking
- Complete before/after value storage in JSON format

## 📊 Database Views

### Common Query Views

#### User Dashboard View
```sql
CREATE VIEW user_dashboard_view AS
SELECT 
    g.id,
    g.gatepass_number,
    g.status,
    g.departure_date,
    g.from_location,
    g.to_location,
    g.created_at,
    u.full_name as created_by_name,
    COUNT(gi.id) as item_count,
    COALESCE(SUM(gi.estimated_value), 0) as total_value
FROM gatepasses g
JOIN users u ON g.user_id = u.id
LEFT JOIN gatepass_items gi ON g.id = gi.gatepass_id
GROUP BY g.id, g.gatepass_number, g.status, g.departure_date, 
         g.from_location, g.to_location, g.created_at, u.full_name;
```

#### Admin Summary View
```sql
CREATE VIEW admin_summary_view AS
SELECT 
    DATE(g.created_at) as date,
    g.status,
    COUNT(*) as count,
    COUNT(DISTINCT g.user_id) as unique_users,
    AVG(TIMESTAMPDIFF(HOUR, g.created_at, g.approval_date)) as avg_approval_hours
FROM gatepasses g
WHERE g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(g.created_at), g.status;
```

## 🛠️ Migration Scripts

### Version Updates

#### Schema Migration Template
```sql
-- Migration: Add new column to gatepasses table
-- Version: 2.1.0
-- Date: 2024-01-15

-- Add backup table
CREATE TABLE gatepasses_backup_20240115 AS SELECT * FROM gatepasses;

-- Add new column
ALTER TABLE gatepasses 
ADD COLUMN emergency_contact VARCHAR(100) NULL AFTER driver_license;

-- Add index
CREATE INDEX idx_emergency_contact ON gatepasses(emergency_contact);

-- Update version
INSERT INTO settings (category, setting_key, setting_value) 
VALUES ('system', 'schema_version', '2.1.0')
ON DUPLICATE KEY UPDATE setting_value = '2.1.0';
```

### Data Seeding

#### Default Settings
```sql
INSERT INTO settings (category, setting_key, setting_value, data_type, description) VALUES
('system', 'site_name', 'Gunayatan Gatepass System', 'string', 'System display name'),
('system', 'timezone', 'Asia/Kolkata', 'string', 'Default system timezone'),
('email', 'smtp_host', '', 'string', 'SMTP server hostname'),
('email', 'smtp_port', '587', 'integer', 'SMTP server port'),
('security', 'max_login_attempts', '5', 'integer', 'Maximum failed login attempts'),
('security', 'account_lockout_duration', '30', 'integer', 'Account lockout duration in minutes'),
('notifications', 'email_enabled', 'true', 'boolean', 'Enable email notifications'),
('notifications', 'push_enabled', 'true', 'boolean', 'Enable push notifications');
```

## 📚 Related Documentation

- **[Installation Guide](Installation-Guide)** - Database setup instructions
- **[API Documentation](API-Documentation)** - Database interaction endpoints
- **[Security Features](Security-Features)** - Security implementation details
- **[Performance Tuning](Performance-Tuning)** - Optimization guidelines

---

This schema documentation provides a complete reference for developers and database administrators working with the Gunayatan Gatepass System database.
