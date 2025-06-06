<?php

namespace App\Http\Controllers;

use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Http;

class FootSizerController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function process(Request $request)
    {
        Log::info('Foot size measurement process started.');

        // Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required|numeric|min:1',
            'photo_path' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);
        Log::info('Validation passed.');

        $photo = $request->file('photo_path');
        Log::info('Photo received: ' . $photo->getClientOriginalName());

        try {
            // Store image
            $path = $photo->store('public/foot_photos');
            $imagePath = storage_path('app/' . $path);
            Log::info('Photo stored at: ' . $imagePath);

            // Send image to Flask API
            $flaskUrl = rtrim(env('FOOT_MEASURE_API_URL'), '/') . '/measure-foot';
            Log::info("Calling Flask API: $flaskUrl");

            $response = Http::timeout(15)
                ->attach('image', file_get_contents($imagePath), basename($imagePath))
                ->post($flaskUrl);

            if (!$response->successful()) {
                Log::error("Flask API failed: " . $response->body());
                throw new \Exception("Flask API failed");
            }

            $data = $response->json();
            Log::info("Flask API response: " . json_encode($data));

            $footSize = $data['foot_size_cm'] ?? null;
            if (!$footSize) {
                Log::error("No foot size returned from Flask API.");
                throw new \Exception("Foot size not returned");
            }

            $nigerianSize = round(($footSize * 1.5) + 1.5);
            Log::info("Calculated Nigerian shoe size: $nigerianSize");

            // Save to DB
            $record = Child::create([
                'name' => $request->name,
                'age' => $request->age,
                'photo_path' => $path,
                'shoe_size' => $nigerianSize,
                'foot_size_cm' => $footSize,
            ]);
            Log::info("Record saved to DB with ID: {$record->id}");

            $this->writeRecordToCsv($record);

            return response()->json([
                'name' => $record->name,
                'age' => $record->age,
                'shoe_size' => $record->shoe_size,
                'foot_size_cm' => $record->foot_size_cm,
                'photo_url' => asset(str_replace('public', 'storage', $path)),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in foot size process: ' . $e->getMessage());
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

    private function convertToShoeSize($cm)
    {
        return round(($cm + 1.5) * 1.5);
    }

    private function calculatePriority($age, $shoeSize)
    {
        return max(0, 10 - $age) + (in_array($shoeSize, [18, 19, 34]) ? 5 : 0);
    }
}
