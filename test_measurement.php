<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\FootMeasurementService;

// Check for image path argument
if ($argc < 2) {
    echo "Usage: php test_measurement.php <path_to_image>\n";
    echo "Example: php test_measurement.php storage/app/public/foot_photos/test.jpg\n";
    exit(1);
}

$imagePath = $argv[1];

if (!file_exists($imagePath)) {
    echo "Error: Image file not found: $imagePath\n";
    exit(1);
}

echo "Testing Foot Measurement Service\n";
echo "================================\n";
echo "Image: $imagePath\n";
echo "GD Extension: " . (extension_loaded('gd') ? 'Available' : 'Not Available') . "\n";
echo "Imagick Extension: " . (extension_loaded('imagick') ? 'Available' : 'Not Available') . "\n";
echo "\nProcessing...\n";

try {
    $service = new FootMeasurementService();
    $result = $service->measureFoot($imagePath);
    
    echo "\n✓ Success!\n";
    echo "Foot Size: {$result['foot_size_cm']} cm\n";
    
    // Calculate Nigerian shoe size
    $nigerianSize = round(($result['foot_size_cm'] * 1.5) + 1.5);
    echo "Estimated Nigerian Shoe Size: $nigerianSize\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
