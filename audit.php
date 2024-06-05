#!/usr/bin/env php
//audit - GitLab Berechtigungsprüfung

<?php
require 'vendor/autoload.php';

use dekor\ArrayToTextTable;

ini_set('error_log', '/tmp/php_errors.log'); // Fehler in Datei protokollieren
Main();
function Main() :void
{
    //$prompt = readline();
    echo "-----------options--------" . PHP_EOL;
    $options = Input();
    var_dump($options);
    echo "-----------token--------" . PHP_EOL;
    $token = GetToken($options);
    var_dump($token);
    echo "-----------request--------" . PHP_EOL;
    $responseData = Request($options, $token);
    Output($responseData, $options);

}

function Input(): array
{
    $shortOpts = "p:u:ht:";
    $longOpts = [
        "--project:",
        "--user:",
        "--help",
        "--token:",
        "--json",
        "--pretty"
    ];
    //hier fehlt die wiederholung der eingabe dann erst do-while aktivieren
    //do {
  /*  if (isset($options["h"])) {
        //hier muss die manpage aufgerufen werden
    }*/
    $options = getopt($shortOpts, $longOpts);
    if (isset($options["p"]) || isset($options["u"])) {
        return $options;
    } else {
        print "No correct Option. \n -p with Projekt ID \ -u with User ID \ -h for help \n";
        print "Enter one Option. \n";
        exit(1);
        /*$input = readline("Please enter options: ");

        $_SERVER['argv'] = array_merge([$_SERVER['argv'][0]], explode(" ", $input));
        $options = getopt($shortOpts, $longOpts);*/
    }
    // } while (!isset($options["p"]) || !isset($options["u"]));

}

function GetToken($options): string
{
    //Persönlicher Access Token wird abgerufen über Flagg --token oder aus file
    if (isset($options["t"])) {
       file_put_contents("accessToken.txt", $options["t"]);
        $token = file_get_contents("accessToken.txt");
        $accessToken = "PRIVATE-TOKEN: $token ";
//        $accessToken = $options["t"]; hier anders parameter auslesen!!!
    } else {
        $token = file_get_contents("accessToken.txt");
        $accessToken = "PRIVATE-TOKEN: $token ";
    }
    if (empty($accessToken)) {
        print "No access token!\nGive it as flag -t/--token OR put it in the file 'accessToken.txt' \n";
        exit(1);
    }
    return $accessToken;
}

//Request to GitLab API
function Request($options, $token): array
{
    $idProjects = $options["p"];
    $idUser = $options["u"];

//URL GitLab API zusammen setzen
    if (isset($idProjects)) {
        $URL = "http://127.0.0.1:8081/api/v4/projects/$idProjects/members/all";
    } elseif (isset($idUser)) {
        $URL = "http://127.0.0.1:8081/api/v4/users/$idUser/memberships";
    } else {
        print "ID is unknown \n";
    }
    var_dump($URL);
    var_dump($token);
/*    var_dump($URL);*/
//cURL-Handle initialisieren
    $ch = curl_init();
    //curl_multi_init — Liefert ein cURL-Mehrfach-Handle
    var_dump($ch);
//Curl Optionen festlegen
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($token, "ACCEPT: application/json"));
//error erkennen
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR,fopen('php://stderr', 'w'));
 /*   $errorMessage = file_get_contents('php://stderr');
    echo "Fehlermeldung: " . $errorMessage;*/

//Anfrage ausführen und Rückgabe speichern (in json-Format)
    $response = curl_exec($ch);
   // var_dump(curl_getinfo($ch));
    curl_close($ch);
    $responseData = [];
    // Fehlerbehandlung wenn URL nicht korrekt
    if (curl_errno($ch)) {
        echo 'cURL Fehler: ' . curl_error($ch) . PHP_EOL;
    } else {
        $responseData[] = json_decode($response, true);

        var_dump($responseData);
    }


    return $responseData;
}

/**
 *
 * https://docs.gitlab.com/ee/api/access_requests.html#valid-access-levels
 */
function AccessRole($responseData): array
{
    foreach ($responseData as $member) {
        foreach ($member as $item) {
            $num = $item["access_level"];
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
            $item["access_level"] = $accessRole;
        }
    }
    return $responseData;
}

function Output($responseData, $options)
{
    var_dump($responseData);

    //Output saved in JSON-file
    if($options["json"]){
    $jsonFile = 'responseData.json';
    file_put_contents($jsonFile, $responseData);
    echo "file has been saved to $jsonFile \n";
    }

    //Default Output saved in CSV-file

    $csvFile = fopen("responseData.csv", "a");
    foreach($responseData as $row){
    fputcsv($csvFile, $row);
    }
    $pointer = fgets($csvFile);
    echo $pointer;
    fclose($csvFile);
    // Setting the file pointer to 0th
    // position using rewind() function
    //rewind($myfile);

    //Output in a human readable table
   /*  if($options["pretty"]){
        //Output Table
   if ($options["p"]) {
        print "ProjektID: " . $id . PHP_EOL;
    } else {
        print "UserID: " . $id . PHP_EOL;
    }*/
    echo (new ArrayToTextTable($responseData))->render();
    echo PHP_EOL;
//}

}
?>