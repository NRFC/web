<?php

$srcDir = realpath(__DIR__ . '/../skunk');
$srcFileName = 'fixtures-youth-2026-7.csv';
if (count($argv) >= 2) {
	$srcFileName = $argv[1];
}

$srcFile = $srcDir . '/' . $srcFileName;
$outputFile = $srcDir . '/transformed-' . $srcFileName;

$fh = fopen($srcFile, "r");
$outputFh = fopen($outputFile, "w");

// Write headers for new CSV
fputcsv($outputFh, [
	'date',
	'team',
	'opposing_club',
	'opposing_team',
	'competition_type',
	'kick_off_time',
	'venue',
	'notes'
]);

$headers = fgetcsv($fh);
$teamColumns = array_slice($headers, 1); // Skip the Date column

// Function to convert date from DD/MM/YYYY to YYYY-MM-DD
function convertDate($dateStr) {
	$dateStr = trim($dateStr);
	// Skip non-date values like "Easter", "Mothering Sunday", etc.
	if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateStr)) {
		$parts = explode('/', $dateStr);
		return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
	}
	return $dateStr; // Return as-is for non-date values (will be handled by validation)
}

while ($row = fgetcsv($fh)) {
	$date = isset($row[0]) ? trim($row[0]) : '';

	// Skip empty date rows
	if (empty($date)) {
		continue;
	}

	$convertedDate = convertDate($date);

	// Skip if date is not a valid date (e.g., "Easter", "Mothering Sunday")
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $convertedDate)) {
		continue;
	}

	// Loop through each team column (index 1 onwards)
	foreach ($teamColumns as $index => $team) {
		$cell = isset($row[$index + 1]) ? trim($row[$index + 1]) : '';

		// Skip empty cells
		if (empty($cell)) {
			continue;
		}

		// Skip Training
		if (stripos($cell, 'Training') !== false) {
			continue;
		}

		// Initialize all fields as empty
		$opposingClub = '';
		$opposingTeam = '';
		$competitionType = '';
		$kickOffTime = '';
		$venue = '';
		$notes = '';

		// Check if it's a match (contains (H) or (A))
		if (preg_match('/^(.*?)\s*\((H|A)\)$/i', $cell, $matches)) {
			$opponentText = trim($matches[1]);
			$homeAway = $matches[2];

			// Try to extract opposing_team (if there's an "x" or "vs" pattern)
			// For now, put the whole opponent in opposing_club
			// Could be enhanced to split club name from team name (e.g., "U15B" or similar)
			$opposingClub = $opponentText;

			// Determine venue based on Home/Away
			$venue = ($homeAway == 'H') ? 'Home' : 'Away';

			// Check if there's additional info like "x2" for multiple teams
			if (preg_match('/x\d+/', $opponentText)) {
				$notes = $opponentText;
				$opposingClub = preg_replace('/\s*x\d+/', '', $opponentText);
			}
		} else {
			// Arbitrary text event - put in notes
			$notes = $cell;
		}

		// Write the row (all fields are either populated or empty strings)
		fputcsv($outputFh, [
			$convertedDate,
			$team,
			$opposingClub,
			$opposingTeam,
			$competitionType,
			$kickOffTime,
			$venue,
			$notes
		]);
	}
}

fclose($fh);
fclose($outputFh);

echo "Transformation complete! Output saved to: " . $outputFile . "\n";
echo "Total rows processed.\n";