<?php

require_once 'vendor/autoload.php';

// Simple test to verify AlbumSeeder contains all 347 albums
$albumSeederPath = 'database/sqldump/seeders/AlbumSeeder.php';

if (!file_exists($albumSeederPath)) {
    echo "❌ AlbumSeeder.php not found!\n";
    exit(1);
}

$content = file_get_contents($albumSeederPath);

// Count the number of album entries
preg_match_all("/\['id' => (\d+),/", $content, $matches);
$albumIds = array_map('intval', $matches[1]);

echo "🔍 Testing AlbumSeeder...\n";
echo "📊 Found " . count($albumIds) . " albums in AlbumSeeder\n";

// Check if we have exactly 347 albums
if (count($albumIds) === 347) {
    echo "✅ Correct number of albums (347)\n";
} else {
    echo "❌ Expected 347 albums, found " . count($albumIds) . "\n";
    exit(1);
}

// Check if IDs are sequential from 1 to 347
$expectedIds = range(1, 347);
$missingIds = array_diff($expectedIds, $albumIds);
$extraIds = array_diff($albumIds, $expectedIds);

if (empty($missingIds) && empty($extraIds)) {
    echo "✅ All album IDs are present and sequential (1-347)\n";
} else {
    if (!empty($missingIds)) {
        echo "❌ Missing album IDs: " . implode(', ', $missingIds) . "\n";
    }
    if (!empty($extraIds)) {
        echo "❌ Extra album IDs: " . implode(', ', $extraIds) . "\n";
    }
    exit(1);
}

// Check first and last albums
if (strpos($content, "'For Those About To Rock We Salute You'") !== false) {
    echo "✅ First album found: 'For Those About To Rock We Salute You'\n";
} else {
    echo "❌ First album not found\n";
    exit(1);
}

if (strpos($content, "'Koyaanisqatsi (Soundtrack from the Motion Picture)'") !== false) {
    echo "✅ Last album found: 'Koyaanisqatsi (Soundtrack from the Motion Picture)'\n";
} else {
    echo "❌ Last album not found\n";
    exit(1);
}

echo "\n🎉 AlbumSeeder test passed! All 347 albums are present and correctly formatted.\n";
echo "✅ Issue resolved: AlbumSeeder now contains all albums from the sqldump\n";
