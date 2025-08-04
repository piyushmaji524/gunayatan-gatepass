<?php
require_once 'includes/config.php';

// Check PHP timezone settings
echo "<h2>PHP Timezone Settings</h2>";
echo "Current date_default_timezone_set(): " . date_default_timezone_get() . "<br>";
echo "Current PHP time: " . date('Y-m-d H:i:s') . "<br>";
echo "Current PHP time (format used in displays): " . date('d M Y, h:i A') . "<br>";

// Check MySQL timezone settings
echo "<h2>MySQL Timezone Settings</h2>";
$conn = connectDB();

// Get MySQL timezone and time
$stmt = $conn->query("SELECT 
    @@global.time_zone AS 'Global Timezone',
    @@session.time_zone AS 'Session Timezone',
    NOW() AS 'MySQL NOW()',
    CURRENT_TIMESTAMP AS 'MySQL CURRENT_TIMESTAMP'
");

$result = $stmt->fetch_assoc();
echo "MySQL global timezone: " . $result['Global Timezone'] . "<br>";
echo "MySQL session timezone: " . $result['Session Timezone'] . "<br>";
echo "MySQL NOW(): " . $result['MySQL NOW()'] . "<br>";
echo "MySQL CURRENT_TIMESTAMP: " . $result['MySQL CURRENT_TIMESTAMP'] . "<br>";

// Get a record from the database with both PHP-created and MySQL-created timestamps
echo "<h2>Sample Gatepasses with Timestamps</h2>";
$stmt = $conn->query("SELECT 
    gatepass_number, 
    created_at, 
    admin_approved_at, 
    security_approved_at 
FROM gatepasses 
ORDER BY created_at DESC LIMIT 5");

echo "<table border='1'>
<tr>
    <th>Gatepass #</th>
    <th>Created At (Raw)</th>
    <th>Created At (Formatted)</th>
    <th>Admin Approved (Raw)</th>
    <th>Admin Approved (Formatted)</th>
    <th>Security Approved (Raw)</th>
    <th>Security Approved (Formatted)</th>
</tr>";

while ($row = $stmt->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['gatepass_number']) . "</td>";
    echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
    echo "<td>" . htmlspecialchars(formatDateTime($row['created_at'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['admin_approved_at']) . "</td>";
    echo "<td>" . htmlspecialchars(formatDateTime($row['admin_approved_at'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['security_approved_at']) . "</td>";
    echo "<td>" . htmlspecialchars(formatDateTime($row['security_approved_at'])) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check database table structure
echo "<h2>Gatepass Table Structure</h2>";
$stmt = $conn->query("DESCRIBE gatepasses");
echo "<table border='1'>
<tr>
    <th>Field</th>
    <th>Type</th>
    <th>Null</th>
    <th>Key</th>
    <th>Default</th>
    <th>Extra</th>
</tr>";
while ($row = $stmt->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>
