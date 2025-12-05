<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class FootMeasurementService
{
    private const A4_WIDTH_CM = 21.0;
    private const MIN_CONTOUR_AREA = 5000; // Minimum area to consider a contour (increased for better filtering)
    
    private $binaryMapScale = 1; // Scale factor for binary map downscaling

    /**
     * Measure foot size from an image containing a foot on A4 paper
     *
     * @param string $imagePath Path to the image file
     * @return array Contains 'foot_size_cm' key with the measurement
     * @throws Exception If measurement fails
     */
    public function measureFoot(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: $imagePath");
        }

        // Try Imagick first if available, otherwise use GD
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                return $this->measureWithImagick($imagePath);
            } catch (Exception $e) {
                Log::warning("Imagick measurement failed, falling back to GD: " . $e->getMessage());
                // Fall through to GD
            }
        }
        
        if (extension_loaded('gd')) {
            return $this->measureWithGD($imagePath);
        }
        
        throw new Exception("Neither Imagick nor GD extension is available");
    }

    /**
     * Measure using Imagick (more powerful, better edge detection)
     */
    private function measureWithImagick(string $imagePath): array
    {
        if (!class_exists('Imagick')) {
            throw new Exception("Imagick class not available. Please use GD instead.");
        }
        
        try {
            $image = new \Imagick($imagePath);
            
            // Convert to grayscale
            $image->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
            
            // Apply Gaussian blur to reduce noise
            $image->gaussianBlurImage(2, 1);
            
            // Edge detection using Canny-like approach
            $image->edgeImage(1);
            
            // Enhance edges
            $image->normalizeImage();
            $image->thresholdImage(0.5 * \Imagick::getQuantum());
            
            // Get image dimensions
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            
            // Find contours by analyzing pixel data
            $contours = $this->findContoursFromImage($image, $width, $height);
            
            if (count($contours) < 2) {
                throw new Exception("Not enough objects detected in image. Ensure foot is on A4 paper.");
            }
            
            // Sort contours by area (largest first)
            usort($contours, function($a, $b) {
                return $b['area'] - $a['area'];
            });
            
            // Take the two largest contours
            $contour1 = $contours[0];
            $contour2 = $contours[1];
            
            Log::info("Contour 1 dimensions: {$contour1['width']}x{$contour1['height']}, area: {$contour1['area']}");
            Log::info("Contour 2 dimensions: {$contour2['width']}x{$contour2['height']}, area: {$contour2['area']}");
            
            // Determine which is A4 and which is foot
            // A4 is typically wider and more rectangular
            $aspectRatio1 = $contour1['width'] / $contour1['height'];
            $aspectRatio2 = $contour2['width'] / $contour2['height'];
            
            // A4 paper in landscape typically has aspect ratio close to 1.4 (21/29.7)
            // or in portrait close to 0.7 (21/29.7)
            if ($contour1['width'] > $contour2['width']) {
                $a4Contour = $contour1;
                $footContour = $contour2;
            } else {
                $a4Contour = $contour2;
                $footContour = $contour1;
            }
            
            // Calculate pixels per cm using A4 width
            $pixelsPerCm = $a4Contour['width'] / self::A4_WIDTH_CM;
            
            Log::info("A4 width in pixels: {$a4Contour['width']}, Pixels per cm: $pixelsPerCm");
            
            // Calculate foot length (use height as length, assuming vertical orientation)
            $footLengthPx = $footContour['height'];
            $footLengthCm = round($footLengthPx / $pixelsPerCm, 1);
            
            Log::info("Foot length in pixels: $footLengthPx, Foot length in cm: $footLengthCm");
            
            // Cleanup
            $image->clear();
            $image->destroy();
            
            if ($footLengthCm < 5 || $footLengthCm > 50) {
                throw new Exception("Calculated foot size ($footLengthCm cm) is unrealistic. Please retake photo.");
            }
            
            return ['foot_size_cm' => $footLengthCm];
            
        } catch (\ImagickException $e) {
            throw new Exception("Imagick processing error: " . $e->getMessage());
        }
    }

    /**
     * Measure using GD (fallback, simpler edge detection)
     */
    private function measureWithGD(string $imagePath): array
    {
        try {
            // Load image
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                throw new Exception("Unable to read image information");
            }
            
            $mimeType = $imageInfo['mime'];
            
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($imagePath);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($imagePath);
                    break;
                default:
                    throw new Exception("Unsupported image format: $mimeType");
            }
            
            if (!$image) {
                throw new Exception("Failed to load image");
            }
            
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
            
            // Downscale large images to reduce memory usage and processing time
            $maxDimension = 800; // Reduced from 1200 for better memory efficiency
            $scale = 1.0;
            
            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                $scale = min($maxDimension / $originalWidth, $maxDimension / $originalHeight);
                $newWidth = (int)($originalWidth * $scale);
                $newHeight = (int)($originalHeight * $scale);
                
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
                imagedestroy($image);
                $image = $resized;
                
                Log::info("GD - Image downscaled from {$originalWidth}x{$originalHeight} to {$newWidth}x{$newHeight}");
            }
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Enhanced preprocessing for complex backgrounds
            // 1. Convert to grayscale
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            
            // 2. Strong contrast to separate white paper from shadows
            imagefilter($image, IMG_FILTER_CONTRAST, -30);
            
            // 3. Brightness boost to make white paper very bright
            imagefilter($image, IMG_FILTER_BRIGHTNESS, 30);
            
            // 4. Apply blur for noise reduction
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            
            // Use thresholding instead of edge detection for better object separation
            $binaryMap = $this->thresholdImage($image, $width, $height);
            
            // Find contours from binary map
            $contours = $this->extractContours($binaryMap, $width, $height);
            
            imagedestroy($image);
            
            if (count($contours) < 2) {
                throw new Exception("Not enough objects detected in image. Ensure foot is on A4 paper.");
            }
            
            // Sort contours by area (largest first)
            usort($contours, function($a, $b) {
                return $b['area'] - $a['area'];
            });
            
            Log::info("GD - Found " . count($contours) . " contours");
            
            // Find the A4 paper - it should be a large rectangular contour
            $a4Contour = null;
            $footContour = null;
            
            // Look for A4 paper characteristics:
            // - Large area (one of the top contours)
            // - Aspect ratio close to A4 (0.707 for portrait or 1.414 for landscape)
            // - Must be rectangular, not square
            $topContours = array_slice($contours, 0, min(10, count($contours)));
            
            foreach ($topContours as $index => $contour) {
                $aspectRatio = $contour['width'] / $contour['height'];
                
                // A4 paper ratios with stricter tolerance
                $isA4Portrait = abs($aspectRatio - 0.707) < 0.15; // 21/29.7 = 0.707
                $isA4Landscape = abs($aspectRatio - 1.414) < 0.25; // 29.7/21 = 1.414
                
                // Reject nearly square shapes (likely shadows or merged objects)
                $isSquarish = abs($aspectRatio - 1.0) < 0.2;
                
                Log::info("GD - Contour $index: {$contour['width']}x{$contour['height']}, area: {$contour['area']}, aspect: " . round($aspectRatio, 2));
                
                if (($isA4Portrait || $isA4Landscape) && !$isSquarish) {
                    if ($a4Contour === null || $contour['area'] > $a4Contour['area']) {
                        $a4Contour = $contour;
                        Log::info("GD - Found A4 paper at index $index with aspect ratio " . round($aspectRatio, 2));
                    }
                }
            }
            
            if (!$a4Contour) {
                // Fallback: use the largest rectangular contour
                Log::warning("GD - No A4-shaped contour found, using largest rectangular contour");
                foreach ($topContours as $contour) {
                    $aspectRatio = $contour['width'] / $contour['height'];
                    // Avoid square shapes
                    if (abs($aspectRatio - 1.0) > 0.2) {
                        $a4Contour = $contour;
                        break;
                    }
                }
                
                if (!$a4Contour) {
                    $a4Contour = $contours[0];
                }
            }
            
            // Find foot contour - should be elongated (longer than wide)
            foreach ($topContours as $contour) {
                if ($contour === $a4Contour) {
                    continue;
                }
                
                $footAspectRatio = max($contour['width'], $contour['height']) / min($contour['width'], $contour['height']);
                
                // Foot should be:
                // 1. Smaller than A4
                // 2. Elongated (aspect ratio > 2 when considering longer/shorter dimension)
                // 3. Within or near A4 paper bounds
                if ($contour['area'] < $a4Contour['area'] * 0.8 && $footAspectRatio > 2.0) {
                    $isNearA4 = $this->isContourNearA4($contour, $a4Contour);
                    
                    if ($isNearA4) {
                        $footContour = $contour;
                        Log::info("GD - Found foot contour (aspect: " . round($footAspectRatio, 2) . ")");
                        break;
                    }
                }
            }
            
            if (!$footContour) {
                // Fallback: use second largest contour
                Log::warning("GD - No foot contour found near A4, using second largest");
                $footContour = $contours[1] ?? null;
                
                if (!$footContour) {
                    throw new Exception("Could not identify foot in image. Please ensure foot is clearly visible on A4 paper.");
                }
            }
            
            Log::info("GD - A4 dimensions: {$a4Contour['width']}x{$a4Contour['height']}, area: {$a4Contour['area']}");
            Log::info("GD - Foot dimensions: {$footContour['width']}x{$footContour['height']}, area: {$footContour['area']}");
            
            // Scale back contour dimensions if we downscaled the binary map
            $scale = $this->binaryMapScale;
            
            // Determine which dimension of A4 to use for calibration
            // Use the shorter dimension as width (21cm)
            $a4WidthPx = min($a4Contour['width'], $a4Contour['height']) * $scale;
            $pixelsPerCm = $a4WidthPx / self::A4_WIDTH_CM;
            
            Log::info("GD - A4 width in pixels: {$a4WidthPx}, Pixels per cm: $pixelsPerCm, Scale: $scale");
            
            // Calculate foot length - use the LONGER dimension of the foot contour
            $footLengthPx = max($footContour['width'], $footContour['height']) * $scale;
            $footLengthCm = round($footLengthPx / $pixelsPerCm, 1);
            
            Log::info("GD - Foot length in pixels: $footLengthPx, Foot length in cm: $footLengthCm");
            
            if ($footLengthCm < 5 || $footLengthCm > 50) {
                throw new Exception("Calculated foot size ($footLengthCm cm) is unrealistic. Please retake photo.");
            }
            
            return ['foot_size_cm' => $footLengthCm];
            
        } catch (Exception $e) {
            // Clean up error message
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'pattern') !== false || strpos($errorMsg, 'regex') !== false) {
                throw new Exception("Image processing failed. Please ensure the photo shows a foot clearly placed on white A4 paper.");
            }
            throw new Exception("GD processing error: " . $errorMsg);
        }
    }

    /**
     * Find contours from Imagick image
     */
    private function findContoursFromImage(\Imagick $image, int $width, int $height): array
    {
        // Export pixel data
        $pixels = $image->exportImagePixels(0, 0, $width, $height, "I", \Imagick::PIXEL_CHAR);
        
        // Create binary map (1 = edge, 0 = background)
        $binaryMap = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $index = $y * $width + $x;
                $binaryMap[$y][$x] = $pixels[$index] > 128 ? 1 : 0;
            }
        }
        
        return $this->extractContours($binaryMap, $width, $height);
    }

    /**
     * Detect edges using GD (Sobel-like operator)
     */
    private function detectEdgesGD($image, int $width, int $height): array
    {
        $edges = [];
        
        // Sobel operator kernels
        $sobelX = [[-1, 0, 1], [-2, 0, 2], [-1, 0, 1]];
        $sobelY = [[-1, -2, -1], [0, 0, 0], [1, 2, 1]];
        
        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $gx = 0;
                $gy = 0;
                
                // Apply Sobel operator
                for ($ky = -1; $ky <= 1; $ky++) {
                    for ($kx = -1; $kx <= 1; $kx++) {
                        $pixel = imagecolorat($image, $x + $kx, $y + $ky);
                        $gray = $pixel & 0xFF; // Get gray value
                        
                        $gx += $gray * $sobelX[$ky + 1][$kx + 1];
                        $gy += $gray * $sobelY[$ky + 1][$kx + 1];
                    }
                }
                
                $magnitude = sqrt($gx * $gx + $gy * $gy);
                $edges[$y][$x] = $magnitude > 100 ? 1 : 0; // Threshold
            }
        }
        
        return $edges;
    }

    /**
     * Threshold image to create binary map (white objects = 1, dark background = 0)
     * Memory-optimized version
     */
    private function thresholdImage($image, int $width, int $height): array
    {
        // Sample pixels to calculate average brightness (faster than scanning all)
        $sampleStep = max(1, (int)($width / 40)); // Reduced sampling for faster calculation
        $totalBrightness = 0;
        $pixelCount = 0;
        
        for ($y = 0; $y < $height; $y += $sampleStep) {
            for ($x = 0; $x < $width; $x += $sampleStep) {
                $pixel = imagecolorat($image, $x, $y);
                $gray = $pixel & 0xFF;
                $totalBrightness += $gray;
                $pixelCount++;
            }
        }
        
        // Use adaptive threshold (slightly above average to detect white paper)
        $avgBrightness = $totalBrightness / $pixelCount;
        // Higher threshold to focus on very bright objects (white paper)
        // This helps ignore shadows
        $threshold = max($avgBrightness + 35, 120); // At least 120 to ensure we get white objects
        
        Log::info("GD - Average brightness: " . round($avgBrightness) . ", Threshold: " . round($threshold));
        
        // Create binary map with reduced resolution for memory efficiency
        $downscale = 2; // Process every 2nd pixel to reduce memory by 4x
        $scaledWidth = (int)ceil($width / $downscale);
        $scaledHeight = (int)ceil($height / $downscale);
        $binaryMap = [];
        
        for ($y = 0; $y < $height; $y += $downscale) {
            $scaledY = (int)($y / $downscale);
            $binaryMap[$scaledY] = [];
            
            for ($x = 0; $x < $width; $x += $downscale) {
                $pixel = imagecolorat($image, $x, $y);
                $gray = $pixel & 0xFF;
                $scaledX = (int)($x / $downscale);
                $binaryMap[$scaledY][$scaledX] = $gray > $threshold ? 1 : 0;
            }
        }
        
        // Store scale factor for later use
        $this->binaryMapScale = $downscale;
        
        return $binaryMap;
    }

    /**
     * Find contours from edge map
     */
    private function findContoursFromEdgeMap(array $edges, int $width, int $height): array
    {
        $visited = [];
        $contours = [];
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (isset($edges[$y][$x]) && $edges[$y][$x] === 1 && !isset($visited["$x,$y"])) {
                    // Found new contour, flood fill to find its bounds
                    $contour = $this->floodFill($edges, $visited, $x, $y, $width, $height);
                    
                    if ($contour['area'] > self::MIN_CONTOUR_AREA) {
                        $contours[] = $contour;
                    }
                }
            }
        }
        
        return $contours;
    }

    /**
     * Extract contours from binary map using connected components
     */
    private function extractContours(array $binaryMap, int $width, int $height): array
    {
        $visited = [];
        $contours = [];
        $maxContours = 30; // Limit number of contours to process
        
        // Get actual dimensions of binary map (might be downscaled)
        $mapHeight = count($binaryMap);
        $mapWidth = isset($binaryMap[0]) ? count($binaryMap[0]) : 0;
        
        for ($y = 0; $y < $mapHeight && count($contours) < $maxContours; $y++) {
            for ($x = 0; $x < $mapWidth && count($contours) < $maxContours; $x++) {
                if (isset($binaryMap[$y][$x]) && $binaryMap[$y][$x] === 1 && !isset($visited["$x,$y"])) {
                    $contour = $this->floodFill($binaryMap, $visited, $x, $y, $mapWidth, $mapHeight);
                    
                    if ($contour['area'] > self::MIN_CONTOUR_AREA / ($this->binaryMapScale * $this->binaryMapScale)) {
                        $contours[] = $contour;
                    }
                }
            }
        }
        
        return $contours;
    }

    /**
     * Flood fill algorithm to find contour bounds
     */
    private function floodFill(array &$map, array &$visited, int $startX, int $startY, int $width, int $height): array
    {
        $stack = [[$startX, $startY]];
        $minX = $startX;
        $maxX = $startX;
        $minY = $startY;
        $maxY = $startY;
        $area = 0;
        $maxIterations = 500000; // Prevent infinite loops
        $iterations = 0;
        
        while (!empty($stack) && $iterations < $maxIterations) {
            [$x, $y] = array_pop($stack);
            $iterations++;
            
            $key = "$x,$y";
            if (isset($visited[$key]) || $x < 0 || $x >= $width || $y < 0 || $y >= $height) {
                continue;
            }
            
            if (!isset($map[$y][$x]) || $map[$y][$x] !== 1) {
                continue;
            }
            
            $visited[$key] = true;
            $area++;
            
            // Update bounds
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
            
            // Add neighbors (4-connectivity)
            $stack[] = [$x + 1, $y];
            $stack[] = [$x - 1, $y];
            $stack[] = [$x, $y + 1];
            $stack[] = [$x, $y - 1];
            
            // Periodically free memory
            if ($iterations % 1000 == 0) {
                gc_collect_cycles();
            }
        }
        
        return [
            'x' => $minX,
            'y' => $minY,
            'width' => $maxX - $minX + 1,
            'height' => $maxY - $minY + 1,
            'area' => $area
        ];
    }

    /**
     * Check if a contour is near or overlapping with A4 paper contour
     */
    private function isContourNearA4(array $contour, array $a4Contour): bool
    {
        $footCenterX = $contour['x'] + $contour['width'] / 2;
        $footCenterY = $contour['y'] + $contour['height'] / 2;
        
        // Check if foot center is within A4 bounds (with some margin)
        $margin = 50; // pixels
        $isWithinX = $footCenterX >= ($a4Contour['x'] - $margin) && 
                     $footCenterX <= ($a4Contour['x'] + $a4Contour['width'] + $margin);
        $isWithinY = $footCenterY >= ($a4Contour['y'] - $margin) && 
                     $footCenterY <= ($a4Contour['y'] + $a4Contour['height'] + $margin);
        
        return $isWithinX && $isWithinY;
    }
}
