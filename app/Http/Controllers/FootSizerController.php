<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Services\FootMeasurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FootSizerController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    private function compressImage($sourcePath, $quality = 60)
    {
        $info = getimagesize($sourcePath);
        $mime = $info['mime'];
        $destinationPath = $sourcePath; // overwrite original file

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($sourcePath);
                imagejpeg($image, $destinationPath, $quality);
                break;

            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
                imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                imagejpeg($bg, $destinationPath, $quality); // save as jpeg to compress
                imagedestroy($bg);
                break;

            case 'image/webp':
                $image = imagecreatefromwebp($sourcePath);
                imagewebp($image, $destinationPath, $quality);
                break;

            case 'image/heic':
            case 'image/heif':
                throw new \Exception("HEIC format not supported in PHP GD. Please convert it before uploading.");
                break;

            default:
                throw new \Exception("Unsupported image format: $mime");
        }

        if (isset($image)) {
            imagedestroy($image);
        }
    }

    public function process(Request $request)
    {
        Log::info('Foot size measurement process started.');
        Log::info('Content-Type: ' . $request->header('Content-Type'));
        Log::info('Has file check: ' . ($request->hasFile('photo_path') ? 'yes' : 'no'));
        Log::info('File in request: ' . ($request->file('photo_path') ? 'exists' : 'null'));

        if ($request->hasFile('photo_path')) {
            $file = $request->file('photo_path');

            Log::info('File uploaded', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        } else {
            Log::warning('No file uploaded in "photo_path".');
        }

        Log::info('Incoming request data:', $request->all());

        // Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required|numeric|min:1',
            'photo_path' => 'required|image|mimes:jpg,jpeg,png,webp,heic|max:5120',
        ]);
        Log::info('Validation passed. Name: ' . $request->name);

        $photo = $request->file('photo_path');
        Log::info('Photo received: ' . $photo->getClientOriginalName()  . '. Name: ' . $request->name);

        try {
            // Store image
            $path = $photo->store('public/foot_photos');
            $imagePath = storage_path('app/' . $path);
            Log::info('Photo stored at: ' . $imagePath . '. Name: ' . $request->name);

            try {
                $this->compressImage($imagePath, 60);
                Log::info("Image compressed successfully. Name: " . $request->name);
            } catch (\Exception $compressEx) {
                Log::warning("Image compression skipped or failed: " . $compressEx->getMessage() . '. Name: ' . $request->name);
            }

            // Measure foot using local PHP service
            Log::info("Measuring foot locally with image: " . basename($imagePath) . '. Name: ' . $request->name);
            
            $measurementService = new FootMeasurementService();
            $data = $measurementService->measureFoot($imagePath);
            
            Log::info("Measurement result: " . json_encode($data) . '. Name: ' . $request->name);

            $footSize = $data['foot_size_cm'] ?? null;
            if (!$footSize) {
                Log::error("No foot size returned from measurement service. Name: " . $request->name);
                throw new \Exception("Foot size not returned");
            }

            $nigerianSize = round(($footSize * 1.5) + 1.5);
            Log::info("Calculated Nigerian shoe size: $nigerianSize. Name: " . $request->name);

            // Save to DB
            $record = Child::create([
                'name' => $request->name,
                'age' => $request->age,
                'photo_path' => $path,
                'shoe_size' => $nigerianSize,
                'foot_size_cm' => $footSize,
            ]);
            Log::info("Record saved to DB with ID: {$record->id}. Name: " . $request->name);

            $this->writeRecordToCsv($record);

            return response()->json([
                'name' => $record->name,
                'age' => $record->age,
                'shoe_size' => $record->shoe_size,
                'foot_size_cm' => $record->foot_size_cm,
                'photo_url' => asset(str_replace('public', 'storage', $path)),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in foot size process: ' . $e->getMessage() . '. Name: ' . $request->name);
            return response()->json(['error' => 'Processing failed. Please try again later.'], 500);
        }
    }

    private function writeRecordToCsv($record)
    {
        $csvPath = storage_path('app/foot_records/' . now()->toDateString() . '.csv');

        // Ensure directory exists
        if (!file_exists(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0755, true);
        }

        $headers = ['Name', 'Age', 'Foot Size (cm)', 'Shoe Size', 'Photo Path', 'Timestamp'];
        $row = [
            $record->name,
            $record->age,
            $record->foot_size_cm,
            $record->shoe_size,
            $record->photo_path,
            now()->toDateTimeString(),
        ];

        $writeHeaders = !file_exists($csvPath);
        $file = fopen($csvPath, 'a');

        if ($writeHeaders) {
            fputcsv($file, $headers);
        }

        fputcsv($file, $row);
        fclose($file);

        Log::info("Record written to daily CSV: $csvPath");
    }

    public function getAllFootSizeRecords()
    {
        $records = Child::orderBy('created_at', 'desc')->get();

        return response()->json($records);
    }

    public function analytics()
    {
        $records = Child::all();

        $totalChildren = $records->count();
        $averageAge = $records->avg('age');
        $averageShoeSize = $records->avg('shoe_size');
        $averageFootSizeCm = $records->avg('foot_size_cm');

        // Calculate priority based on age and shoe size
        $priorities = $records->map(function ($record) {
            return [
                'name' => $record->name,
                'age' => $record->age,
                'shoe_size' => $record->shoe_size,
                'priority' => $this->calculatePriority($record->age, $record->shoe_size),
            ];
        })->sortByDesc('priority');

        // Additional analytics data
        $minAge = $records->min('age');
        $maxAge = $records->max('age');
        $minShoeSize = $records->min('shoe_size');
        $maxShoeSize = $records->max('shoe_size');
        $minFootSizeCm = $records->min('foot_size_cm');
        $maxFootSizeCm = $records->max('foot_size_cm');

        return response()->json([
            'status' => 'success',
            'message' => 'Shoa a Child foundation shoe sizing initiative.',
            'total_foot_size_recorded' => $totalChildren,
            'average_age' => round($averageAge, 2),
            'average_shoe_size' => round($averageShoeSize, 2),
            'average_foot_size_cm' => round($averageFootSizeCm, 2),
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'min_shoe_size' => $minShoeSize,
            'max_shoe_size' => $maxShoeSize,
            'min_foot_size_cm' => $minFootSizeCm,
            'max_foot_size_cm' => $maxFootSizeCm,
            'priorities' => $priorities,
        ]);
    }

    private function convertToShoeSize($cm)
    {
        return round(($cm + 1.5) * 1.5);
    }

    private function calculatePriority($age, $shoeSize)
    {
        return max(0, 10 - $age) + (in_array($shoeSize, [18, 19, 34]) ? 5 : 0);
    }
}
