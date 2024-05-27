#!/usr/bin/env php

// GitLab Berechtigungen

<?php
/*Übergabeparameter
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
        print "Bitte gib 2 Argumente ein\n 1.Argument: p / u \n 2.Argument: Id \n";

        $argv[1] = getInput("1. Argument (p / u): ");
        $argv[2] = getInput("2. Argument (ID): ");
    }
    if ($argv[1] == 'p') {
        $project_id = $argv[2];
        $checkArg = false;
    } elseif ($argv[1] == 'u') {
        $user_id = $argv[2];
        $checkArg = false;
    } else {
        echo "Ungültige Eingabe. Bitte versuche es erneut.\n";
        unset($argv[1], $argv[2]);
    }
} while ($checkArg === true);

//URL der Api zusammen setzen
if (isset($project_id)) {
    $URL = "https://git.netways.de/api/v4/projects/$project_id";
} elseif (isset($user_id)) {
    $URL = "https://git.netways.de/api/v4/users/$user_id";
}

//persönlicher Token
//$token =  implode(include 'accessToken.txt');
$accessToken = "PRIVATE-TOKEN: <personal-access-token>";

//cURL-Handle initialisieren
$ch = curl_init();

//curl_setopt($ch, CURLOPT_URL, $URL_user_id);
curl_setopt($ch, CURLOPT_URL, $URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('PRIVATE-TOKEN: ', $accessToken));

//Anfrage ausführen und Rückgabe speichern (in json-Format)
$response = curl_exec($ch);

// Fehlerbehandlung wenn URL nicht korrekt
if (curl_errno($ch)) {
    echo 'cURL Fehler: ' . curl_error($ch);
} else {
    // Antwort anzeigen
    //$responseData = json_encode(json_decode($response, true), JSON_PRETTY_PRINT);
    $responseData[] = json_decode($response, true);


//URL für Members eines Projekts
    $URL = "https://git.netways.de/api/v4/projects/$project_id/members/all";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('PRIVATE-TOKEN: ', $accessToken));

    $response = curl_exec($ch);
    $membersData = json_decode($response, true);

    // Projektdaten auslesen
    $responseDataArray = [];
    foreach ($responseData as $project) {
        // Projektinformationen
        $projectData = [
            'ProjektID' => $project['id'],
            'Projektname' => $project['name'],
            'Members' => []
        ];

        // Mitgliedsdaten
        foreach ($membersData as $member) {
            //Access Role von Nummer zu Bezeichnung
            $num = $member['access_level'];
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
            $projectData['Members'][] = [
                'UserID' => $member['id'],
                'Username' => $member['username'],
                'Name' => $member['name'],
                'Access-Role' => $accessRole
            ];
        }
    $responseDataArray[] = $projectData;
 }
//Ansicht für Projekt ID mit Mitgliedsdaten
    include 'viewProject.php';

}

// cURL-Handle schließen
curl_close($ch);
curl_close($ch);

?>
