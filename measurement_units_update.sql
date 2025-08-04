-- Add measurement_units table to existing installations
-- This script can be executed manually by administrators to add the measurement units feature

-- Create measurement_units table if it doesn't exist
CREATE TABLE IF NOT EXISTS measurement_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(50) NOT NULL UNIQUE,
    unit_symbol VARCHAR(20),
    unit_type ENUM('length', 'weight', 'volume', 'quantity', 'other') DEFAULT 'other',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default measurement units for movable/tangible items (50 units arranged alphabetically)
INSERT INTO measurement_units (unit_name, unit_symbol, unit_type, is_active) 
SELECT * FROM (
    SELECT 'Bags' as name, 'bag' as symbol, 'quantity' as type, 1 as active UNION ALL
    SELECT 'Barrels', 'bbl', 'volume', 1 UNION ALL
    SELECT 'Bottles', 'btl', 'quantity', 1 UNION ALL
    SELECT 'Boxes', 'box', 'quantity', 1 UNION ALL
    SELECT 'Buckets', 'bucket', 'quantity', 1 UNION ALL
    SELECT 'Bundles', 'bdl', 'quantity', 1 UNION ALL
    SELECT 'Cans', 'can', 'quantity', 1 UNION ALL
    SELECT 'Cartons', 'ctn', 'quantity', 1 UNION ALL
    SELECT 'Cases', 'case', 'quantity', 1 UNION ALL
    SELECT 'Centimeters', 'cm', 'length', 1 UNION ALL
    SELECT 'Coils', 'coil', 'quantity', 1 UNION ALL
    SELECT 'Containers', 'container', 'quantity', 1 UNION ALL
    SELECT 'Crates', 'crate', 'quantity', 1 UNION ALL
    SELECT 'Cubic Feet', 'ft³', 'volume', 1 UNION ALL
    SELECT 'Cubic Meters', 'm³', 'volume', 1 UNION ALL
    SELECT 'Cylinders', 'cyl', 'quantity', 1 UNION ALL
    SELECT 'Dozens', 'doz', 'quantity', 1 UNION ALL
    SELECT 'Drums', 'drum', 'quantity', 1 UNION ALL
    SELECT 'Each', 'ea', 'quantity', 1 UNION ALL
    SELECT 'Feet', 'ft', 'length', 1 UNION ALL
    SELECT 'Gallons', 'gal', 'volume', 1 UNION ALL
    SELECT 'Grams', 'g', 'weight', 1 UNION ALL
    SELECT 'Gross', 'gr', 'quantity', 1 UNION ALL
    SELECT 'Inches', 'in', 'length', 1 UNION ALL
    SELECT 'Jars', 'jar', 'quantity', 1 UNION ALL
    SELECT 'Jerricans', 'jerry', 'quantity', 1 UNION ALL
    SELECT 'Kilograms', 'kg', 'weight', 1 UNION ALL
    SELECT 'Kits', 'kit', 'quantity', 1 UNION ALL
    SELECT 'Length', 'length', 'length', 1 UNION ALL
    SELECT 'Liters', 'L', 'volume', 1 UNION ALL
    SELECT 'Meters', 'm', 'length', 1 UNION ALL
    SELECT 'Milligrams', 'mg', 'weight', 1 UNION ALL
    SELECT 'Milliliters', 'ml', 'volume', 1 UNION ALL
    SELECT 'Millimeters', 'mm', 'length', 1 UNION ALL
    SELECT 'Numbers', 'nos', 'quantity', 1 UNION ALL
    SELECT 'Ounces', 'oz', 'weight', 1 UNION ALL
    SELECT 'Packages', 'pkg', 'quantity', 1 UNION ALL
    SELECT 'Pairs', 'pair', 'quantity', 1 UNION ALL
    SELECT 'Pallets', 'pallet', 'quantity', 1 UNION ALL
    SELECT 'Pieces', 'pcs', 'quantity', 1 UNION ALL
    SELECT 'Pints', 'pt', 'volume', 1 UNION ALL
    SELECT 'Plates', 'plate', 'quantity', 1 UNION ALL
    SELECT 'Pounds', 'lb', 'weight', 1 UNION ALL
    SELECT 'Quarts', 'qt', 'volume', 1 UNION ALL
    SELECT 'Reams', 'ream', 'quantity', 1 UNION ALL
    SELECT 'Rolls', 'roll', 'quantity', 1 UNION ALL
    SELECT 'Sacks', 'sack', 'quantity', 1 UNION ALL
    SELECT 'Sets', 'set', 'quantity', 1 UNION ALL
    SELECT 'Sheets', 'sht', 'quantity', 1 UNION ALL
    SELECT 'Tons', 'ton', 'weight', 1 UNION ALL
    SELECT 'Tubes', 'tube', 'quantity', 1 UNION ALL
    SELECT 'Units', 'unit', 'quantity', 1 UNION ALL
    SELECT 'Yards', 'yd', 'length', 1
) AS tmp
WHERE NOT EXISTS (
    SELECT 1 FROM measurement_units
);

-- If you want to log this upgrade in the logs table, run the following command
-- Replace 1 with the ID of the user who is performing the upgrade
INSERT INTO logs (user_id, action, details, ip_address) 
VALUES (1, 'SYSTEM_UPGRADE', 'Added measurement_units table and default units', '127.0.0.1');
