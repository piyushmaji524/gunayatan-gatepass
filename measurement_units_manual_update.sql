-- Manual update script for measurement_units table
-- This script will clear existing units and insert only movable/tangible item units suitable for gatepass system
-- Execute this script manually in your database to update the measurement units

-- First, clear all existing measurement units
DELETE FROM measurement_units;

-- Reset auto increment counter
ALTER TABLE measurement_units AUTO_INCREMENT = 1;

-- Insert only movable/tangible item measurement units (53 units arranged alphabetically)
INSERT INTO measurement_units (unit_name, unit_symbol, unit_type, is_active) VALUES
('Bags', 'bag', 'quantity', 1),
('Barrels', 'bbl', 'volume', 1),
('Bottles', 'btl', 'quantity', 1),
('Boxes', 'box', 'quantity', 1),
('Buckets', 'bucket', 'quantity', 1),
('Bundles', 'bdl', 'quantity', 1),
('Cans', 'can', 'quantity', 1),
('Cartons', 'ctn', 'quantity', 1),
('Cases', 'case', 'quantity', 1),
('Centimeters', 'cm', 'length', 1),
('Coils', 'coil', 'quantity', 1),
('Containers', 'container', 'quantity', 1),
('Crates', 'crate', 'quantity', 1),
('Cubic Feet', 'ft³', 'volume', 1),
('Cubic Meters', 'm³', 'volume', 1),
('Cylinders', 'cyl', 'quantity', 1),
('Dozens', 'doz', 'quantity', 1),
('Drums', 'drum', 'quantity', 1),
('Each', 'ea', 'quantity', 1),
('Feet', 'ft', 'length', 1),
('Gallons', 'gal', 'volume', 1),
('Grams', 'g', 'weight', 1),
('Gross', 'gr', 'quantity', 1),
('Inches', 'in', 'length', 1),
('Jars', 'jar', 'quantity', 1),
('Jerricans', 'jerry', 'quantity', 1),
('Kilograms', 'kg', 'weight', 1),
('Kits', 'kit', 'quantity', 1),
('Length', 'length', 'length', 1),
('Liters', 'L', 'volume', 1),
('Meters', 'm', 'length', 1),
('Milligrams', 'mg', 'weight', 1),
('Milliliters', 'ml', 'volume', 1),
('Millimeters', 'mm', 'length', 1),
('Numbers', 'nos', 'quantity', 1),
('Ounces', 'oz', 'weight', 1),
('Packages', 'pkg', 'quantity', 1),
('Pairs', 'pair', 'quantity', 1),
('Pallets', 'pallet', 'quantity', 1),
('Pieces', 'pcs', 'quantity', 1),
('Pints', 'pt', 'volume', 1),
('Plates', 'plate', 'quantity', 1),
('Pounds', 'lb', 'weight', 1),
('Quarts', 'qt', 'volume', 1),
('Reams', 'ream', 'quantity', 1),
('Rolls', 'roll', 'quantity', 1),
('Sacks', 'sack', 'quantity', 1),
('Sets', 'set', 'quantity', 1),
('Sheets', 'sht', 'quantity', 1),
('Tons', 'ton', 'weight', 1),
('Tubes', 'tube', 'quantity', 1),
('Units', 'unit', 'quantity', 1),
('Yards', 'yd', 'length', 1);

-- Log this manual update in the logs table
-- Replace 1 with the actual user ID who is performing this update
INSERT INTO logs (user_id, action, details, ip_address) 
VALUES (1, 'MANUAL_UPDATE', 'Manually updated measurement_units table with movable item units only', '127.0.0.1');

-- Display success message
SELECT 'Measurement units table updated successfully with 53 movable item units!' as status;

-- Show all inserted units for verification
SELECT id, unit_name, unit_symbol, unit_type, is_active 
FROM measurement_units 
ORDER BY unit_name;
