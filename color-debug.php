<?php
/**
 * Color Variation Debug Script
 * 
 * This script helps debug why new colors don't save variation data properly.
 * Upload this to your public_html root and access it via browser.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "<h1>Color Variation Debug Report</h1>";
echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #4CAF50; color: white; }</style>";

// 1. Check all colors in database
echo "<h2>1. Colors in Database</h2>";
$colors = \Illuminate\Support\Facades\DB::table('colors')->orderBy('id', 'desc')->limit(10)->get();
echo "<table><tr><th>ID</th><th>Name</th><th>Code</th><th>Created At</th></tr>";
foreach ($colors as $color) {
    echo "<tr><td>{$color->id}</td><td>{$color->name}</td><td>{$color->code}</td><td>{$color->created_at}</td></tr>";
}
echo "</table>";

// 2. Test color lookup with different cases
echo "<h2>2. Color Lookup Test</h2>";
$testCodes = ['#9ACD32', '#9acd32', '#F5F5F5', '#f5f5f5'];
echo "<table><tr><th>Test Code</th><th>Found?</th><th>Color Name</th></tr>";
foreach ($testCodes as $code) {
    $color = \App\Models\Color::where('code', $code)->first();
    $found = $color ? 'YES' : 'NO';
    $name = $color ? $color->name : 'N/A';
    echo "<tr><td>{$code}</td><td>{$found}</td><td>{$name}</td></tr>";
}
echo "</table>";

// 3. Test with uppercase conversion
echo "<h2>3. Color Lookup with strtoupper()</h2>";
$testCodes = ['#9acd32', '#f5f5f5', '#ff0000'];
echo "<table><tr><th>Original Code</th><th>Uppercase Code</th><th>Found?</th><th>Color Name</th></tr>";
foreach ($testCodes as $code) {
    $upperCode = strtoupper($code);
    $color = \App\Models\Color::where('code', $upperCode)->first();
    $found = $color ? 'YES' : 'NO';
    $name = $color ? $color->name : 'N/A';
    echo "<tr><td>{$code}</td><td>{$upperCode}</td><td>{$found}</td><td>{$name}</td></tr>";
}
echo "</table>";

// 4. Check recent Laravel logs for color-related errors
echo "<h2>4. Recent Laravel Logs (Color-related)</h2>";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $lines = explode("\n", $logs);
    $colorLogs = array_filter($lines, function($line) {
        return stripos($line, 'color') !== false || stripos($line, 'variation') !== false;
    });
    $recentLogs = array_slice(array_reverse($colorLogs), 0, 10);
    echo "<pre style='background: #f4f4f4; padding: 10px; overflow-x: auto;'>";
    foreach ($recentLogs as $log) {
        echo htmlspecialchars($log) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p>Log file not found</p>";
}

echo "<hr><p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>Check if your newly created colors appear in section 1</li>";
echo "<li>Verify that color codes are stored in UPPERCASE in the database</li>";
echo "<li>Check section 2 to see if case-sensitive lookup is the issue</li>";
echo "<li>Section 3 shows if strtoupper() fixes the lookup</li>";
echo "<li>Section 4 shows any color-related errors from logs</li>";
echo "</ol>";
echo "<p><strong>After reviewing, delete this file for security!</strong></p>";
