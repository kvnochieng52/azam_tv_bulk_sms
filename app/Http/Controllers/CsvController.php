<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CsvController extends Controller
{
    /**
     * Validate and process a CSV file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validateCsv(Request $request)
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first('csv_file'),
            ], 422);
        }

        try {
            // Process the CSV file
            $file = $request->file('csv_file');
            
            // Generate a unique filename
            $fileName = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.csv';
            
            // Store the file in a temporary location
            $path = $file->storeAs('csv_uploads', $fileName, 'public');
            $fullPath = storage_path('app/public/' . $path);
            
            // Read CSV header row to get column names
            $handle = fopen($fullPath, 'r');
            $headerRow = fgetcsv($handle);
            fclose($handle);
            
            if (!$headerRow) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The CSV file is empty or has an invalid format.',
                ], 422);
            }
            
            // Convert header row to lowercase for case-insensitive comparison
            $headers = array_map('strtolower', $headerRow);
            
            // Check if any of the required columns exist
            $requiredColumns = ['phone', 'mobile', 'telephone', 'contact'];
            $foundColumns = array_intersect($requiredColumns, $headers);
            
            if (empty($foundColumns)) {
                // No required columns found, delete the file and return error
                Storage::disk('public')->delete($path);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'The CSV file must contain at least one of these columns: phone, mobile, telephone, or contact.',
                ], 422);
            }
            
            // Success - return file info and columns
            return response()->json([
                'status' => 'success',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'columns' => array_values($headerRow), // Ensure columns are returned as indexed array
                'columns_debug' => $headerRow, // For debugging
                'contact_column' => reset($foundColumns), // Get the first matching column
            ]);
            
        } catch (\Exception $e) {
            // Handle any errors
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the CSV file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
