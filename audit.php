#!/usr/bin/env php
//audit - GitLab Berechtigungsprüfung

<?php
require 'vendor/autoload.php';

use dekor\ArrayToTextTable;

// Fehler in Datei protokollieren
//wenn nur audit oder audit help aufgerufen wird dann hilfstxt anzeigen
ini_set('error_log', '/tmp/php_errors.log');
Main();
function Main(): void
{
    //$prompt = readline();
    echo "-----------options--------" . PHP_EOL;
    $options = Input();
    var_dump($options);

    $token = GetToken($options);


    echo "-----------request--------" . PHP_EOL;
    $responseData = Request($options, $token);
    if ($options["no-accessrole"]) {
        $responseData = AccessRole($responseData);
    }
    Output($responseData, $options);

}

function Input(): array
{
    $shortOpts = "p:u:ht:";
    $longOpts = [
        "project:",
        "user:",
        "help",
        "token:",
        "json-file",
        "json",
        "csv",
        "pretty",
        "no-accessrole"
    ];

    //hier fehlt die wiederholung der eingabe dann erst do-while aktivieren
    //do {
    /*  if (isset($options["h"])) {
          //hier muss die manpage aufgerufen werden
      }*/

    $options = getopt($shortOpts, $longOpts);

    //accessrole wird per default von int wert in bezeichung laut gitlab umgewandelt. wenn zahlen benötigt werden kann diese funktion mit flag deaktivert werden

    if (!isset($options["no-accessrole"])) {
        $options["no-accessrole"] = true;
    }


    if ((isset($options["p"]) || (isset($options["project"]))) || (isset($options["u"]) || (isset($options["user"])))) {
        return $options;
    } else {
        print "No correct Option. \n -p with Projekt ID \ -u with User ID \ -h for help \n";
        print "Enter one Option. \n";
        exit(1);
        //erneuter Input plus hilfstext
        /*$input = readline("Please enter options: ");

        $_SERVER['argv'] = array_merge([$_SERVER['argv'][0]], explode(" ", $input));
        $options = getopt($shortOpts, $longOpts);*/
    }
    // } while (!isset($options["p"]) || !isset($options["u"]));

}

function GetToken($options): string
{
    // Persönlicher Access Token wird abgerufen über Flag -t/--token oder aus file
    if (isset($options["t"])) {
        $token = $options["t"];
    } elseif (isset($options["token"])) {
        $token = $options["token"];
    } else {
        $token = file_get_contents("accessToken.txt");
    }

    // Access Token formatieren
    $accessToken = "PRIVATE-TOKEN: $token";

    if (empty(trim($token))) {
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

//cURL-Handle initialisieren
    $ch = curl_init();
    //curl_multi_init — Liefert ein cURL-Mehrfach-Handle

//Curl Optionen festlegen
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($token, "ACCEPT: application/json"));
//error anzeigen lassen und in file schreiben
    // curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
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
    }
    return $responseData;
}

/**
 *
 * https://docs.gitlab.com/ee/api/access_requests.html#valid-access-levels
 */
function AccessRole($responseData): array
{
    foreach ($responseData as &$member) {
        foreach ($member as &$item) {
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

//Print the input ID of project or user
    if (isset($options["p"])) print "ProjektID: " . $options["p"] . PHP_EOL;
    if (isset($options["u"])) print "Username: " . $options["u"] . PHP_EOL;
//Output saved in JSON-file
    if (isset($options["json-file"])) {

        $ProjectId = $options["p"];
        $UserId = $options["u"];
        $file = "result.json" . $UserId;
        $bytesWritten = file_put_contents($file, json_encode($responseData, JSON_PRETTY_PRINT));

        if ($bytesWritten !== false) {
            $fileUrl = "file://" . realpath("result.json");
            echo "\nData has been successfully saved to file result.json ($bytesWritten bytes)\n";
            echo "You can access [$fileUrl]\n";
        } else {
            // ANSI-Escape-Sequenz für roten Text
            $redText = "\033[31m";
            $resetText = "\033[0m";
            echo "\n{$redText}Error:{$resetText} Failed to save data!\n";
        }
        }
//Output as pretty JSON
        if(isset($options["json"])){
            print json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL;
        }


    //Output saved in csv-file
    if (isset($options["csv"])) {
        $csvFile = fopen("responseData.csv", "x+");
        foreach ($responseData as $row) {
            fputcsv($csvFile, $row);
        }
        $pointer = fgets($csvFile);
        echo $pointer;
        fclose($csvFile);
    }
    //Output shown in human readable table
    if (isset($options["pretty"])) {
        $table = new ArrayToTextTable($responseData);
        echo $table->render() . PHP_EOL;
    }

    //echo json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL;
    //print_r($responseData);*/


}

?>