-- Enhanced Notification System Database Tables
-- Run this SQL to add tables for enhanced mobile notifications

-- Table for browser notifications (real-time)
CREATE TABLE IF NOT EXISTS browser_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, read_at),
    INDEX idx_created (created_at)
);

-- Table for system alerts (dashboard display)
CREATE TABLE IF NOT EXISTS system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('success', 'info', 'warning', 'danger') NOT NULL DEFAULT 'info',
    icon VARCHAR(50) NOT NULL DEFAULT 'fas fa-bell',
    message TEXT NOT NULL,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    dismissed_by JSON COMMENT 'Array of user IDs who dismissed this alert',
    INDEX idx_active (expires_at, created_at),
    INDEX idx_type (type)
);

-- Enhanced user preferences table for mobile notifications
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    whatsapp_number VARCHAR(20),
    telegram_chat_id VARCHAR(50),
    fcm_token TEXT COMMENT 'Firebase Cloud Messaging token',
    notification_preferences JSON DEFAULT '{}',
    quiet_hours_start TIME DEFAULT '22:00:00',
    quiet_hours_end TIME DEFAULT '08:00:00',
    timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for notification delivery tracking
CREATE TABLE IF NOT EXISTS notification_delivery_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL COMMENT 'email, sms, whatsapp, push, telegram',
    status ENUM('sent', 'delivered', 'failed', 'pending') NOT NULL DEFAULT 'pending',
    attempts INT DEFAULT 0,
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, notification_type),
    INDEX idx_status (status, created_at),
    INDEX idx_channel (channel, status)
);

-- Table for real-time notification queue
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    channels JSON NOT NULL COMMENT 'Array of channels to try',
    payload JSON NOT NULL,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    next_retry TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status, scheduled_at),
    INDEX idx_user (user_id, status),
    INDEX idx_priority (priority, scheduled_at)
);

-- Insert default notification preferences for existing users
INSERT IGNORE INTO user_preferences (user_id, notification_preferences)
SELECT id, JSON_OBJECT(
    'email', true,
    'sms', true,
    'whatsapp', true,
    'push', true,
    'telegram', false,
    'urgent_only', false,
    'quiet_hours_enabled', true
) FROM users;

-- Sample notification settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_description) VALUES
('instant_notifications_enabled', '1', 'Enable instant mobile notifications'),
('telegram_bot_token', '', 'Telegram bot token for instant notifications'),
('firebase_server_key', '', 'Firebase Cloud Messaging server key'),
('twilio_account_sid', '', 'Twilio Account SID for WhatsApp'),
('twilio_auth_token', '', 'Twilio Auth Token for WhatsApp'),
('notification_retry_interval', '300', 'Retry interval in seconds for failed notifications'),
('browser_notification_cleanup_days', '7', 'Days to keep browser notifications'),
('system_alert_cleanup_days', '3', 'Days to keep system alerts');

-- Enhanced triggers for instant notifications
DELIMITER //

-- Trigger for new gatepass creation
CREATE TRIGGER IF NOT EXISTS instant_notify_new_gatepass
    AFTER INSERT ON gatepasses
    FOR EACH ROW
BEGIN
    -- Add to notification queue for instant processing
    INSERT INTO notification_queue (user_id, notification_type, priority, channels, payload)
    SELECT 
        u.id,
        'new_gatepass',
        'high',
        JSON_ARRAY('email', 'sms', 'whatsapp', 'push', 'telegram'),
        JSON_OBJECT(
            'gatepass_id', NEW.id,
            'gatepass_number', NEW.gatepass_number,
            'creator_id', NEW.created_by,
            'from_location', NEW.from_location,
            'to_location', NEW.to_location,
            'material_type', NEW.material_type,
            'action_url', CONCAT('/admin/view_gatepass.php?id=', NEW.id)
        )
    FROM users u
    WHERE u.role = 'admin' AND u.status = 'active';
END//

-- Trigger for admin approval
CREATE TRIGGER IF NOT EXISTS instant_notify_gatepass_approved
    AFTER UPDATE ON gatepasses
    FOR EACH ROW
BEGIN
    IF NEW.status = 'approved_by_admin' AND OLD.status != 'approved_by_admin' THEN
        -- Notify user
        INSERT INTO notification_queue (user_id, notification_type, priority, channels, payload)
        VALUES (
            NEW.created_by,
            'gatepass_approved',
            'medium',
            JSON_ARRAY('email', 'sms', 'whatsapp', 'push'),
            JSON_OBJECT(
                'gatepass_id', NEW.id,
                'gatepass_number', NEW.gatepass_number,
                'from_location', NEW.from_location,
                'to_location', NEW.to_location,
                'action_url', CONCAT('/user/view_gatepass.php?id=', NEW.id)
            )
        );
        
        -- Notify security personnel
        INSERT INTO notification_queue (user_id, notification_type, priority, channels, payload)
        SELECT 
            u.id,
            'gatepass_approved',
            'high',
            JSON_ARRAY('email', 'sms', 'whatsapp', 'push', 'telegram'),
            JSON_OBJECT(
                'gatepass_id', NEW.id,
                'gatepass_number', NEW.gatepass_number,
                'from_location', NEW.from_location,
                'to_location', NEW.to_location,
                'action_url', CONCAT('/security/verify_gatepass.php?id=', NEW.id)
            )
        FROM users u
        WHERE u.role = 'security' AND u.status = 'active';
    END IF;
END//

-- Trigger for security verification
CREATE TRIGGER IF NOT EXISTS instant_notify_gatepass_verified
    AFTER UPDATE ON gatepasses
    FOR EACH ROW
BEGIN
    IF NEW.status = 'approved_by_security' AND OLD.status != 'approved_by_security' THEN
        INSERT INTO notification_queue (user_id, notification_type, priority, channels, payload)
        VALUES (
            NEW.created_by,
            'gatepass_verified',
            'high',
            JSON_ARRAY('email', 'sms', 'whatsapp', 'push'),
            JSON_OBJECT(
                'gatepass_id', NEW.id,
                'gatepass_number', NEW.gatepass_number,
                'from_location', NEW.from_location,
                'to_location', NEW.to_location,
                'action_url', CONCAT('/user/view_gatepass.php?id=', NEW.id)
            )
        );
    END IF;
END//

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_gatepasses_status ON gatepasses(status, created_at);
CREATE INDEX IF NOT EXISTS idx_gatepasses_created_by ON gatepasses(created_by, status);
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);

-- Cleanup procedures for old notifications
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS CleanupOldNotifications()
BEGIN
    -- Clean up old browser notifications
    DELETE FROM browser_notifications 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL (
        SELECT setting_value FROM system_settings 
        WHERE setting_key = 'browser_notification_cleanup_days'
    ) DAY);
    
    -- Clean up expired system alerts
    DELETE FROM system_alerts 
    WHERE expires_at IS NOT NULL AND expires_at < NOW();
    
    -- Clean up old notification delivery logs (keep for 30 days)
    DELETE FROM notification_delivery_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean up completed notification queue items (keep for 7 days)
    DELETE FROM notification_queue 
    WHERE status = 'completed' AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END//

DELIMITER ;

-- Sample data for testing (optional)
-- INSERT INTO user_preferences (user_id, phone_number, whatsapp_number, notification_preferences) VALUES
-- (1, '+919876543210', '+919876543210', '{"email": true, "sms": true, "whatsapp": true, "push": true}'),
-- (2, '+919876543211', '+919876543211', '{"email": true, "sms": true, "whatsapp": true, "push": true}');

-- Create event scheduler for notification processing (optional)
-- SET GLOBAL event_scheduler = ON;
-- 
-- CREATE EVENT IF NOT EXISTS process_notification_queue
-- ON SCHEDULE EVERY 30 SECOND
-- DO
-- BEGIN
--     -- This would call a PHP script to process the notification queue
--     -- Example: Call external PHP script via MySQL UDF or use a cron job instead
-- END;
