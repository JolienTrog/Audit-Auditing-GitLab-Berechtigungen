<?php
function drawLine($length) {
    echo str_repeat('-', $length) . PHP_EOL;
}

function drawCell($text, $width) {
    echo '| ' . str_pad($text, $width - 2) . ' ';
}

// Bestimme die maximale Breite für jede Spalte
$projektIdWidth = max(array_map(function($row) { return strlen((string)$row['ProjektID']); }, $responseDataArray));
$projektnameWidth = max(array_map(function($row) { return strlen($row['Projektname']); }, $responseDataArray));
$userIdWidth = max(array_map(function($row) { return max(array_map(function($member) { return strlen((string)$member['UserID']); }, $row['Members'])); }, $responseDataArray));
$usernameWidth = max(array_map(function($row) { return max(array_map(function($member) { return strlen($member['Username']); }, $row['Members'])); }, $responseDataArray));
$nameWidth = max(array_map(function($row) { return max(array_map(function($member) { return strlen($member['Name']); }, $row['Members'])); }, $responseDataArray));
$accessRoleWidth = max(array_map(function($row) { return max(array_map(function($member) { return strlen($member['Access-Role']); }, $row['Members'])); }, $responseDataArray));

// Berücksichtige die Kopfzeilenbreite
$projektIdWidth = max($projektIdWidth, strlen('ProjektID'));
$projektnameWidth = max($projektnameWidth, strlen('Projektname'));
$userIdWidth = max($userIdWidth, strlen('UserID'));
$usernameWidth = max($usernameWidth, strlen('Username'));
$nameWidth = max($nameWidth, strlen('Name'));
$accessRoleWidth = max($accessRoleWidth, strlen('Access-Role'));

// Zeichne die Überschriften für ProjektID und Projektname
drawLine($projektIdWidth + $projektnameWidth + 5);
drawCell('ProjektID', $projektIdWidth + 2);
drawCell('Projektname', $projektnameWidth + 2);
echo '|' . PHP_EOL;
drawLine($projektIdWidth + $projektnameWidth + 5);

foreach ($responseDataArray as $project) {
    // Zeichne ProjektID und Projektname
    drawCell((string)$project['ProjektID'], $projektIdWidth + 2);
    drawCell($project['Projektname'], $projektnameWidth + 2);
    echo '|' . PHP_EOL;
    drawLine($projektIdWidth + $projektnameWidth + 5);

    // Zeichne die Überschriften für UserID, Username, Name und Access-Role
    drawLine($userIdWidth + $usernameWidth + $nameWidth + $accessRoleWidth + 11);
    drawCell('UserID', $userIdWidth + 2);
    drawCell('Username', $usernameWidth + 2);
    drawCell('Name', $nameWidth + 2);
    drawCell('Access-Role', $accessRoleWidth + 2);
    echo '|' . PHP_EOL;
    drawLine($userIdWidth + $usernameWidth + $nameWidth + $accessRoleWidth + 11);

    // Zeichne die Mitgliederdaten
    foreach ($project['Members'] as $member) {
        drawCell((string)$member['UserID'], $userIdWidth + 2);
        drawCell($member['Username'], $usernameWidth + 2);
        drawCell($member['Name'], $nameWidth + 2);
        drawCell($member['Access-Role'], $accessRoleWidth + 2);
        echo '|' . PHP_EOL;
        drawLine($userIdWidth + $usernameWidth + $nameWidth + $accessRoleWidth + 11);
    }
}
?>
