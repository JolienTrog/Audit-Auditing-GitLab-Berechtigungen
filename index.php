#!/usr/bin/env php

//GitLab Berechtigungen

<?php
require 'vendor/autoload.php';

use dekor\ArrayToTextTable;

/**
 * @param string $prompt message to display to the user as a prompt.
 * @return string The trimmed user input.
 * Übergabeparameter sind nicht richtig gesetzt, löst eine erneuten Userinput aus
 */
function getInput($prompt)
{
    echo $prompt;
    return trim(fgets(STDIN));
}

do {
    $checkArg = true;

    if (!isset($argv) || count($argv) < 3) {
        print "Bitte gib 2 Argumente ein: 1.Argument: p / u  2.Argument: Id \n";

        $argv[1] = getInput("1. Argument (p / u): ");
        $argv[2] = getInput("2. Argument (ID): \n");
    }
    if ((strlen($argv[1])== 1 && ($argv[1] == 'p' || $argv[1] == 'u') && preg_match('/^\d{4}$/', $argv[2]))) {
        $id = $argv[2];
        $checkArg = false;
    } else {
        echo "Ungültige Eingabe. Bitte versuche es erneut.\n";
        unset($argv[1], $argv[2]);
    }
} while ($checkArg === true);

//URL GitLab API zusammen setzen
if ($argv[1] === 'p') {
    $URL = "https://git.netways.de/api/v4/projects/$id/members/all";
} else {
    $URL = "https://git.netways.de/api/v4/users/$id/memberships";
}

//Persönlicher Access Token wird ausgelesen und Übergaben
$token = file_get_contents("accessToken.txt");
$accessToken = "PRIVATE-TOKEN: $token ";

//cURL-Handle initialisieren
$ch = curl_init();

//Curl Optionen festlegen
curl_setopt($ch, CURLOPT_URL, $URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('PRIVATE-TOKEN: ', $accessToken));

//Anfrage ausführen und Rückgabe speichern (in json-Format)
$response = curl_exec($ch);

// Fehlerbehandlung wenn URL nicht korrekt
if (curl_errno($ch)) {
    echo 'cURL Fehler: ' . curl_error($ch);
} else {
    // Rückgabe von String zu Array
    $responseData[] = json_decode($response, true);

    //Output aus Daten selektieren
    $memberData = [];
    foreach ($responseData as $member) {
        foreach ($member as $item) {
            //Access Role von Nummer zu Bezeichnung ändern
            $num = $item['access_level'];
            switch ($num) {
                case 0:
                    $accessRole = "No access";
                    break;
                case 5:
                    $accessRole = "Minimal access";
                    break;
                case 10:
                    $accessRole = "Guest";
                    break;
                case 20:
                    $accessRole = "Reporter";
                    break;
                case 30:
                    $accessRole = "Developer";
                    break;
                case 40:
                    $accessRole = "Maintainer";
                    break;
                case 50:
                    $accessRole = "Owner";
                    break;
                default:
                    $accessRole = "Unknown";
                    break;

            }

            $memberData[] =
                [
                    'UserID' => $item['id'],
                    'Username' => $item['username'],
                    'Name' => $item['name'],
                    'Access-Role' => $accessRole
                ];
        }
    }
}
//Output Table
if($argv[1] === 'p'){
    print "ProjektID: " . $id . PHP_EOL;
} else{
    print "UserID: " . $id . PHP_EOL;
}
echo (new ArrayToTextTable($memberData))->render();
echo PHP_EOL;
curl_close($ch);
?>