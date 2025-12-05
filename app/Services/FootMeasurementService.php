<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class FootMeasurementService
{
    private const A4_WIDTH_CM = 21.0;
    private const MIN_CONTOUR_AREA = 1000; // Minimum area to consider a contour

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

        // Check if Imagick is available, otherwise use GD
        if (extension_loaded('imagick')) {
            return $this->measureWithImagick($imagePath);
        } elseif (extension_loaded('gd')) {
            return $this->measureWithGD($imagePath);
        } else {
            throw new Exception("Neither Imagick nor GD extension is available");
        }
    }

    /**
     * Measure using Imagick (more powerful, better edge detection)
     */
    private function measureWithImagick(string $imagePath): array
    {
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
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Convert to grayscale
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            
            // Apply Gaussian blur to reduce noise
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
            
            // Edge detection using simple gradient method
            $edges = $this->detectEdgesGD($image, $width, $height);
            
            // Find contours from edge map
            $contours = $this->findContoursFromEdgeMap($edges, $width, $height);
            
            imagedestroy($image);
            
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
            
            Log::info("GD - Contour 1 dimensions: {$contour1['width']}x{$contour1['height']}, area: {$contour1['area']}");
            Log::info("GD - Contour 2 dimensions: {$contour2['width']}x{$contour2['height']}, area: {$contour2['area']}");
            
            // Determine which is A4 and which is foot
            if ($contour1['width'] > $contour2['width']) {
                $a4Contour = $contour1;
                $footContour = $contour2;
            } else {
                $a4Contour = $contour2;
                $footContour = $contour1;
            }
            
            // Calculate pixels per cm using A4 width
            $pixelsPerCm = $a4Contour['width'] / self::A4_WIDTH_CM;
            
            Log::info("GD - A4 width in pixels: {$a4Contour['width']}, Pixels per cm: $pixelsPerCm");
            
            // Calculate foot length (use height as length)
            $footLengthPx = $footContour['height'];
            $footLengthCm = round($footLengthPx / $pixelsPerCm, 1);
            
            Log::info("GD - Foot length in pixels: $footLengthPx, Foot length in cm: $footLengthCm");
            
            if ($footLengthCm < 5 || $footLengthCm > 50) {
                throw new Exception("Calculated foot size ($footLengthCm cm) is unrealistic. Please retake photo.");
            }
            
            return ['foot_size_cm' => $footLengthCm];
            
        } catch (Exception $e) {
            throw new Exception("GD processing error: " . $e->getMessage());
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
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($binaryMap[$y][$x] === 1 && !isset($visited["$x,$y"])) {
                    $contour = $this->floodFill($binaryMap, $visited, $x, $y, $width, $height);
                    
                    if ($contour['area'] > self::MIN_CONTOUR_AREA) {
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
        
        while (!empty($stack)) {
            [$x, $y] = array_pop($stack);
            
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
        }
        
        return [
            'x' => $minX,
            'y' => $minY,
            'width' => $maxX - $minX + 1,
            'height' => $maxY - $minY + 1,
            'area' => $area
        ];
    }
}
