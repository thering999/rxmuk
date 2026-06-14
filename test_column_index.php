<?php
/**
 * Test column index conversion
 */

function getCellColumnIndex($cell_ref) {
    // Extract column letters from cell reference (A1, B2, XFD1048576, etc.)
    preg_match('/^([A-Z]+)/', $cell_ref, $matches);
    if (!isset($matches[1])) {
        return -1;
    }
    
    $col_letters = $matches[1];
    $index = 0;
    foreach (str_split($col_letters) as $char) {
        $index = $index * 26 + (ord($char) - ord('A') + 1);
    }
    return $index - 1; // Convert to 0-based index
}

// Test cases
$test_cases = [
    'A1' => 0,
    'B1' => 1,
    'C1' => 2,
    'Z1' => 25,
    'AA1' => 26,
    'AB1' => 27,
    'AZ1' => 51,
    'BA1' => 52,
    'ZZ1' => 701,
    'AAA1' => 702,
];

echo "Testing Column Index Conversion:\n";
echo "================================\n";
foreach ($test_cases as $cell => $expected) {
    $result = getCellColumnIndex($cell);
    $status = ($result === $expected) ? "✓" : "✗";
    echo "$status $cell => $result (expected $expected)\n";
}
?>
