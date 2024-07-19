<?php
require 'vendor/autoload.php';

use dekor\ArrayToTextTable;

// Fehler in Datei protokollieren
//wenn nur audit.php oder audit.php help aufgerufen wird dann hilfstxt anzeigen
ini_set('error_log', '/tmp/php_errors.log');
Main();
function Main(): void
{
    //$prompt = readline();
    print "audit - Gitlab authorization check";
    echo "-----------options--------" . PHP_EOL;
    $options = Input();
    var_dump($options);

    $token = GetToken($options);

    echo "-----------request--------" . PHP_EOL;
    $responseData = Request($options, $token);
    /*
     *Problem bei der Verbindung
     * 1. falsche URL
     * 2. Keine Berechtigung: 	"401 Unauthorized"
     *
     */
    if(empty($responseData)) {
        //Fehlermeldung was ist los?
        echo "somthing is wrong" . PHP_EOL;
        exit;
    }
    if (!$options["no-accessrole"]) {
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
            "csv-file",
            "pretty",
            "no-accessrole"
        ];
        $options = getopt($shortOpts, $longOpts);

        if ((isset($options["h"]) || (isset($options["help"])))) {
            // Check if the manpage is installed
            $output = shell_exec('man -w audit.php 2>/dev/null');
            if (empty($output)) {
                InstallLocalManPage();
            } else {
            // Execute local man page for audit.php
                system('man audit.php');
            }
            exit;
        }
    if (!isset($options["p"]) && !isset($options["u"]) && !isset($options["h"])){
        print "No correct Option. Please enter options: \n -p with Projekt ID \ -u with User ID \ -h for help \n";
      exit;
    }
//        if ((isset($options["p"]) || (isset($options["project"]))) || (isset($options["u"]) || (isset($options["user"])))) {
            if (isset($options["csv-file"])) {
                $options["csv-file"] = true;
            }
            if (isset($options["json-file"])) {
                $options["json-file"] = true;
            }
            if (isset($options["json"])) {
                $options["json"] = true;
            }
            if (isset($options["pretty"])) {
                $options["pretty"] = true;
            }
            if (isset($options["no-accessrole"])) {
                $options["no-accessrole"] = true;
            }
//        }

    return $options;
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
    if (empty(trim($token)) || $token == "<Put in here your personal access token>") {
        print "Error: No access token!\nGive it as flag -t/--token OR put it in the file 'accessToken.txt' \n";
        exit(1);
    }
    // Access Token formatieren
    $accessToken = "PRIVATE-TOKEN: $token";
    return $accessToken;
}

//Request to GitLab API
function Request($options, $token): array
{
    $idProjects = $options["p"];
    $idUser = $options["u"];

//URL GitLab API zusammen setzen
    if (isset($idProjects)) {
        //Netways URL
        //$URL = "https://git.netways.de/api/v4/projects/$id/members/all";
        //Testumgebung:
        $URL = "http://172.17.0.1:80/api/v4/projects/$idProjects/members/all";
    } elseif (isset($idUser)) {
        //Netways URL:
        //$URL = "https://git.netways.de/api/v4/users/$id/memberships";
        //Testumgebung:
        $URL = "http://172.17.0.1:801/api/v4/users/$idUser/memberships";
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
//    $errorMessage = file_get_contents('php://stderr');
//       echo "Fehlermeldung: " . $errorMessage;

//Anfrage ausführen und Rückgabe speichern (in json-Format)
    $response = curl_exec($ch);
    // var_dump(curl_getinfo($ch));

// Fehlerbehandlung wenn URL nicht korrekt
    if (curl_errno($ch)) {
        echo 'cURL Fehler: ' . curl_error($ch) . PHP_EOL;
    }

    curl_close($ch);
    //Antwort verarbeiten wenn Request erfolgriche war
    $responseData = [];

    if ($response !== false) {
        $responseData[] = json_decode($response, true);
    }
    return $responseData;
}

function InstallLocalManPage(){
    $manpage_path = __DIR__ . '/man/audit.php.1';
    if (file_exists($manpage_path)) {
        system("man $manpage_path");
    } else {
        echo "Manpage not found. Please run 'sudo ./installManPage.sh' to install it.\n";
    }
    exit(0);
}



/*
 * Fehlermeldungen:
 * unauthorized 	Der Nutzer ist zu dieser Anfrage nicht berechtigt.
 */
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

function UniqueFileName($options): string
{

    $projectId = $options["p"];
    $userId = $options["u"];

    if($options["json-file"]) {
        $baseFilename = "result_" . (isset($projectId) ? "project_$projectId" : "") . (isset($userId) ? "_user_$userId" : "") . ".json";
    } elseif ($options["csv-file"]) {
        $baseFilename = "result_" . (isset($projectId) ? "project_$projectId" : "") . (isset($userId) ? "_user_$userId" : "") . ".csv";
    }
    $pathInfo = pathinfo($baseFilename);
    $basename = $pathInfo['filename'];
    $extension = isset($pathInfo['extension']) ? "." . $pathInfo['extension'] : "";
    $counter = 1;

    $uniqueFileName = $basename . $extension;
    while (file_exists($uniqueFileName)) {
        $uniqueFileName = $basename . "_" . $counter . $extension;
        $counter++;
    }
    return $uniqueFileName;
}

function Output($responseData, $options)
{

//Print the input ID of project or user
    if (isset($options["p"])) print "ProjektID: " . $options["p"] . PHP_EOL;
    if (isset($options["u"])) print "Username: " . $options["u"] . PHP_EOL;

    //Output shown in human readable table
    if (isset($options["pretty"])) {
        foreach ($responseData as $member) {
            foreach ($member as $item) {
                echo "User: {$item['username']} - Role: {$item['access_level']}\n";
            }
        }
    }

//Output saved in JSON-file
    if (isset($options["json-file"])) {

        $file = UniqueFileName($options);
        // $file = "result.json" . $UserId;
        $bytesWritten = file_put_contents($file, json_encode($responseData, JSON_PRETTY_PRINT));

        if ($bytesWritten !== false) {
            $fileUrl = "file://" . realpath($file);
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
    if (isset($options["json"])) {
        print json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL;
    }


    //Output saved in csv-file
    if (isset($options["csv-file"])) {
        $file= UniqueFileName($options);
        $csvFile = fopen($file, "w");

        if ($csvFile !== false) {
            // Extract header
            $header = array_keys($responseData[0][0]);
            fputcsv($csvFile, $header);

            // Extract and write data rows
            foreach ($responseData[0] as $row) {
                $flatRow = [];
                foreach ($header as $column) {
                    $flatRow[] = is_array($row[$column]) ? json_encode($row[$column]) : $row[$column];
                }
                fputcsv($csvFile, $flatRow);
            }
        }
        fclose($csvFile);

        $fileUrl = "file://" . realpath($file);
            echo "\nData has been successfully saved to file $file\n";
            echo "You can access [$fileUrl]\n";
    } else {
            $redText = "\033[31m";
            $resetText = "\033[0m";
            echo "\n{$redText}Error:{$resetText} Failed to save data!\n";
        }

    if(!isset($options["json"]) && !isset($options["json-file"]) && !isset($options["csv-file"]) && !isset($options["pretty"])) {
            print "Data in pretty json:\n";
            print json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL;
    }

}
?>