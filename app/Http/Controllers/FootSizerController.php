<?php

namespace App\Http\Controllers;

use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;

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

        // Store image
        $path = $photo->store('public/foot_photos');
        $imagePath = storage_path('app/' . $path);
        Log::info('Photo stored at: ' . $imagePath);

        // Run Python script
        $python = 'python3'; // or 'python'
        $scriptPath = base_path('foot_detect.py');
        Log::info("Running Python script at $scriptPath");

        try {
            $process = new Process([$python, $scriptPath, $imagePath]);
            $process->run();

            $outputRaw = $process->getOutput();
            $errorRaw = $process->getErrorOutput();

            Log::info('Python script stdout: ' . $outputRaw);
            Log::info('Python script stderr: ' . $errorRaw);

            if (!$process->isSuccessful()) {
                Log::error('Python script failed with error: ' . $process->getErrorOutput());
                throw new ProcessFailedException($process);
            }

            $outputRaw = $process->getOutput();
            Log::info('Python script output: ' . $outputRaw);

            $output = json_decode($outputRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: ' . json_last_error_msg());
                throw new \Exception('Invalid JSON output from Python script.');
            }

            $footSize = $output['foot_size_cm'] ?? null;
            Log::info('Foot size extracted: ' . $footSize);

            $nigerianSize = round(($footSize * 1.5) + 1.5);
            Log::info('Nigerian shoe size: ' . $nigerianSize);

            // Save to DB
            $record = Child::create([
                'name' => $request->name,
                'age' => $request->age,
                'photo_path' => $path,
                'shoe_size' => $nigerianSize,
                'foot_size_cm' => $footSize,
            ]);
            Log::info('Record saved to DB with ID: ' . $record->id);

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
            return response()->json(['error' => 'Load Failed'], 500);
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
