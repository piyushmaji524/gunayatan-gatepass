<?php
// Generate a simple placeholder logo for the system
header('Content-Type: image/png');

// Create a blank image
$image = imagecreatetruecolor(200, 60);

// Define colors
$bg = imagecolorallocate($image, 41, 128, 185); // Blue
$text_color = imagecolorallocate($image, 255, 255, 255); // White
$accent_color = imagecolorallocate($image, 243, 156, 18); // Orange

// Fill background
imagefill($image, 0, 0, $bg);

// Add a decorative element
imagefilledrectangle($image, 0, 0, 10, 60, $accent_color);

// Add text
$font = 5; // Built-in font
imagestring($image, $font, 20, 10, "GUNAYATAN", $text_color);
imagestring($image, $font, 20, 30, "GATEPASS SYSTEM", $text_color);

// Output the image
imagepng($image);

// Free memory
imagedestroy($image);
?>
