-- Update users table to add superadmin role
ALTER TABLE users MODIFY role ENUM('superadmin', 'admin', 'security', 'user') NOT NULL;

-- Create a superadmin user
INSERT INTO users (username, password, name, email, role, status) VALUES 
('superadmin', '$2y$10$EJtKWR5DNNa5KRCrbHfvB.vjTIa1SR5FQxO.Y2eU/hN.B/lROYq.C', 'Super Admin', 'superadmin@your_domain_name', 'superadmin', 'active');

-- Update relevant permissions and system settings
UPDATE users SET status = 'active' WHERE role = 'superadmin';
