-- Enhanced Notification System Database Tables
-- Run this SQL to add notification capabilities to your gatepass system

-- User preferences table for notification settings
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone_number VARCHAR(20),
    whatsapp_number VARCHAR(20),
    notification_preferences JSON,
    push_tokens JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);

-- In-app notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    urgency ENUM('low', 'medium', 'high') DEFAULT 'medium',
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, read_at),
    INDEX idx_created (created_at)
);

-- Notification delivery log
CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    success_channels JSON,
    failed_channels JSON,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_type (notification_type)
);

-- Update users table to include phone number if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) AFTER email,
ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) AFTER phone_number;

-- Insert default notification preferences for existing users
INSERT IGNORE INTO user_preferences (user_id, notification_preferences) 
SELECT id, JSON_OBJECT(
    'email', true,
    'sms', true,
    'whatsapp', true,
    'push', true,
    'in_app', true,
    'urgent_only', false
) 
FROM users;

-- Create notification settings in system_settings if not exists
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_description) VALUES
('notifications_enabled', '1', 'Enable multi-channel notifications'),
('sms_provider', 'textbelt', 'SMS provider (textbelt, twilio, etc.)'),
('whatsapp_provider', 'callmebot', 'WhatsApp provider (callmebot, ultramsg, etc.)'),
('push_enabled', '1', 'Enable browser push notifications'),
('notification_retry_attempts', '3', 'Number of retry attempts for failed notifications'),
('urgent_notification_all_channels', '1', 'Send urgent notifications via all available channels');

-- Sample trigger to auto-notify admins when new gatepass is created
DELIMITER //
CREATE TRIGGER IF NOT EXISTS notify_admin_new_gatepass
    AFTER INSERT ON gatepasses
    FOR EACH ROW
BEGIN
    -- Insert notification for all active admins
    INSERT INTO notifications (user_id, type, title, message, data, urgency)
    SELECT 
        u.id,
        'new_gatepass',
        CONCAT('New Gatepass #', NEW.gatepass_number, ' Requires Approval'),
        CONCAT('A new gatepass request has been submitted by ', creator.name, ' and requires your approval.'),
        JSON_OBJECT(
            'gatepass_id', NEW.id,
            'gatepass_number', NEW.gatepass_number,
            'creator_name', creator.name,
            'action_url', CONCAT('/admin/view_gatepass.php?id=', NEW.id)
        ),
        'high'
    FROM users u
    JOIN users creator ON creator.id = NEW.created_by
    WHERE u.role = 'admin' AND u.status = 'active';
END//

-- Trigger to notify security when gatepass is approved by admin
CREATE TRIGGER IF NOT EXISTS notify_security_gatepass_approved
    AFTER UPDATE ON gatepasses
    FOR EACH ROW
BEGIN
    -- Only trigger when status changes to approved_by_admin
    IF NEW.status = 'approved_by_admin' AND OLD.status != 'approved_by_admin' THEN
        INSERT INTO notifications (user_id, type, title, message, data, urgency)
        SELECT 
            u.id,
            'gatepass_approved',
            CONCAT('Gatepass #', NEW.gatepass_number, ' Ready for Verification'),
            CONCAT('Gatepass #', NEW.gatepass_number, ' has been approved by admin and requires security verification.'),
            JSON_OBJECT(
                'gatepass_id', NEW.id,
                'gatepass_number', NEW.gatepass_number,
                'action_url', CONCAT('/security/verify_gatepass.php?id=', NEW.id)
            ),
            'high'
        FROM users u
        WHERE u.role = 'security' AND u.status = 'active';
    END IF;
END//

-- Trigger to notify user when gatepass is verified by security
CREATE TRIGGER IF NOT EXISTS notify_user_gatepass_verified
    AFTER UPDATE ON gatepasses
    FOR EACH ROW
BEGIN
    -- Only trigger when status changes to approved_by_security
    IF NEW.status = 'approved_by_security' AND OLD.status != 'approved_by_security' THEN
        INSERT INTO notifications (user_id, type, title, message, data, urgency)
        VALUES (
            NEW.created_by,
            'gatepass_verified',
            CONCAT('Gatepass #', NEW.gatepass_number, ' Verified'),
            CONCAT('Your gatepass #', NEW.gatepass_number, ' has been verified by security. You may now proceed with material exit.'),
            JSON_OBJECT(
                'gatepass_id', NEW.id,
                'gatepass_number', NEW.gatepass_number,
                'action_url', CONCAT('/user/view_gatepass.php?id=', NEW.id)
            ),
            'high'
        );
    END IF;
END//

-- Trigger to notify user when gatepass is declined
CREATE TRIGGER IF NOT EXISTS notify_user_gatepass_declined
    AFTER UPDATE ON gatepasses
    FOR EACH ROW
BEGIN
    -- Only trigger when status changes to declined
    IF NEW.status = 'declined' AND OLD.status != 'declined' THEN
        INSERT INTO notifications (user_id, type, title, message, data, urgency)
        VALUES (
            NEW.created_by,
            'gatepass_declined',
            CONCAT('Gatepass #', NEW.gatepass_number, ' Declined'),
            CONCAT('Your gatepass #', NEW.gatepass_number, ' has been declined. Reason: ', COALESCE(NEW.decline_reason, 'No reason provided')),
            JSON_OBJECT(
                'gatepass_id', NEW.id,
                'gatepass_number', NEW.gatepass_number,
                'decline_reason', COALESCE(NEW.decline_reason, 'No reason provided'),
                'action_url', CONCAT('/user/view_gatepass.php?id=', NEW.id)
            ),
            'high'
        );
    END IF;
END//

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_gatepasses_status ON gatepasses(status);
CREATE INDEX IF NOT EXISTS idx_gatepasses_created_by ON gatepasses(created_by);
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);

-- Sample notification preferences data
UPDATE user_preferences SET notification_preferences = JSON_OBJECT(
    'email', true,
    'sms', true,
    'whatsapp', true,
    'push', true,
    'in_app', true,
    'urgent_only', false,
    'quiet_hours_start', '22:00',
    'quiet_hours_end', '08:00',
    'weekend_notifications', true
) WHERE notification_preferences IS NULL OR notification_preferences = '{}';
