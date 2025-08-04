-- Database structure for Gunayatan Gatepass System

-- Drop existing tables if they exist
DROP TABLE IF EXISTS logs;
DROP TABLE IF EXISTS gatepass_items;
DROP TABLE IF EXISTS gatepasses;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS measurement_units;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'security', 'user') NOT NULL,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create gatepasses table
CREATE TABLE gatepasses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gatepass_number VARCHAR(20) UNIQUE NOT NULL,
    from_location VARCHAR(255) NOT NULL,
    to_location VARCHAR(255) NOT NULL,
    material_type VARCHAR(100) NOT NULL,
    purpose TEXT,
    requested_date DATE NOT NULL,
    requested_time TIME NOT NULL,
    status ENUM('pending', 'approved_by_admin', 'approved_by_security', 'declined') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_approved_by INT,
    admin_approved_at DATETIME,
    security_approved_by INT,
    security_approved_at DATETIME,
    declined_by INT,
    declined_at DATETIME,
    decline_reason TEXT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (admin_approved_by) REFERENCES users(id),
    FOREIGN KEY (security_approved_by) REFERENCES users(id),
    FOREIGN KEY (declined_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create gatepass_items table
CREATE TABLE gatepass_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gatepass_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    FOREIGN KEY (gatepass_id) REFERENCES gatepasses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create logs table
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create measurement_units table
CREATE TABLE measurement_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(50) NOT NULL UNIQUE,
    unit_symbol VARCHAR(20),
    unit_type ENUM('length', 'weight', 'volume', 'quantity', 'other') DEFAULT 'other',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default users
INSERT INTO users (username, password, name, email, role, status) VALUES 
('admin', '$2y$10$EJtKWR5DNNa5KRCrbHfvB.vjTIa1SR5FQxO.Y2eU/hN.B/lROYq.C', 'Admin User', 'admin@your_domain_name', 'admin', 'active'),
('security', '$2y$10$HyF9Xb3UEoQOJQgd3YQkJOfYqeJfu1gvqchaF2QLZeqUVDmbDrJhm', 'Security Officer', 'security@your_domain_name', 'security', 'active'),
('user', '$2y$10$bODL34713YFVSFsg5TJGieh3jJJykVDNJKRXVak5Bnd6n28focpHO', 'Regular User', 'user@your_domain_name', 'user', 'active');

-- Insert sample gatepass with items
INSERT INTO gatepasses (gatepass_number, from_location, to_location, material_type, purpose, requested_date, requested_time, status, created_by)
VALUES ('GP-2025-00001', 'Warehouse A', 'Building B', 'Office Equipment', 'Moving office supplies to new location', CURDATE(), CURTIME(), 'pending', 3);

-- Get the ID of the inserted gatepass
SET @gatepass_id = LAST_INSERT_ID();

-- Insert sample items for the gatepass
INSERT INTO gatepass_items (gatepass_id, item_name, quantity, unit) VALUES
(@gatepass_id, 'Office Chair', 5, 'Pieces'),
(@gatepass_id, 'Computer Monitors', 3, 'Units');

-- Sample logs
INSERT INTO logs (user_id, action, details, ip_address) VALUES
(3, 'GATEPASS_CREATED', 'Created gatepass GP-2025-00001', '127.0.0.1'),
(3, 'USER_LOGIN', 'User logged in successfully', '127.0.0.1');

-- Insert default measurement units
INSERT INTO measurement_units (unit_name, unit_symbol, unit_type, is_active) VALUES
('Pieces', 'pcs', 'quantity', 1),
('Kilograms', 'kg', 'weight', 1),
('Grams', 'g', 'weight', 1),
('Liters', 'L', 'volume', 1),
('Milliliters', 'ml', 'volume', 1),
('Meters', 'm', 'length', 1),
('Centimeters', 'cm', 'length', 1),
('Feet', 'ft', 'length', 1),
('Inches', 'in', 'length', 1),
('Boxes', 'box', 'quantity', 1),
('Pairs', 'pair', 'quantity', 1),
('Units', 'unit', 'quantity', 1),
('Sets', 'set', 'quantity', 1),
('Tons', 'ton', 'weight', 1),
('Square Meters', 'm²', 'other', 1),
('Cubic Meters', 'm³', 'volume', 1);
