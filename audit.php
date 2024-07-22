<?php
require 'vendor/autoload.php';
use dekor\ArrayToTextTable;
ini_set('error_log', '/tmp/php_errors.log');
Main();
/**
 * Main function to get user input, send request to GitLab API, and process the response.
 * This function orchestrates the flow of the script, including input parsing, API request sending,
 * and output formatting based on the provided options.
 *
 * @return void
 */
function Main(): void
{
    print PHP_EOL . "audit - Gitlab authorization check" . PHP_EOL;
    echo "-----------options--------" . PHP_EOL;
    $options = Input();
    //var_dump($options);

    $token = GetToken($options);

    echo "-----------request--------" . PHP_EOL;
    $responseData = Request($options, $token);
    /*
     *Problem bei der Verbindung
     * 1. falsche URL
     * 2. Keine Berechtigung: 	"401 Unauthorized"
     */
//    if(empty($responseData)) {
//        //error message
//        echo "somthing is wrong" . PHP_EOL;
//        exit;
//    }
    if (!$options["no-accessrole"]) {
        $responseData = AccessRole($responseData);
    }
    Output($responseData, $options);
}
/**
 * Parse the input options from the user to get the project ID, user ID, and other options.
 * @return array $options Array of parsed input options from the user to get the project ID, user ID, and other options.
 *
 */
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
            $output = shell_exec('man -w audit 2>/dev/null');
            if (empty($output)) {
                echo "The man page for audit.php is not installed.\n";
                echo "Create the man page with the following command:\n";
                echo "Create a directory: mkdir -p /usr/local/man/man1/ \n";
                echo "Copy the man page from doc/audit.1 to the directory: cp docs/audit.1 /usr/local/man/man1 \n";
                echo "Update man page database: mandb \n\n";
            } else {
            // Execute local man page for audit.php
                system('man audit');
            }
            exit;
        }
    if (!isset($options["p"]) && !isset($options["u"]) && !isset($options["h"])){
        print "No correct Option. Please enter options: \n -p with Projekt ID \ -u with User ID \ -h for help \n";
      exit;
    }
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
    return $options;
}
/**
 * Get the access token from the user input or from the file.
 * @param array $options Array of parsed input, here only used to set access token manually.
 * @return string $accessToken Access token to authenticate the user with the GitLab API.
 */
function GetToken($options): string
{
    // Access Token from input
    if (isset($options["t"])) {
        $token = $options["t"];
    } elseif (isset($options["token"])) {
        $token = $options["token"];
    } else {
        // Access Token from file
        $token = file_get_contents("accessToken.txt");
    }
    if (empty(trim($token)) || $token == "<Put in here your personal access token>") {
        print "Error: No access token!\nGive it as flag -t/--token OR put it in the file 'accessToken.txt' \n";
        exit(1);
    }
    $accessToken = "PRIVATE-TOKEN: $token";
    return $accessToken;
}
/**
 * Send a request to the GitLab API to get the members of a project or the projects of a user.
 * @param array $options Array of parsed input options from the user to get the project ID, user ID, and other options.
 * @param string $token Access token to authenticate the user with the GitLab API.
 * @return array $responseData Array of response data from the GitLab API.
 */
function Request($options, $token): array
{
    $idProjects = $options["p"];
    $idUser = $options["u"];

//URL GitLab API for projects or users
    if (isset($idProjects)) {
        //Netways URL
        //$URL = "https://git.netways.de/api/v4/projects/$id/members/all";
        //URL for test environment:
        $URL = "http://172.17.0.1:80/api/v4/projects/$idProjects/members/all";
    } elseif (isset($idUser)) {
        //Netways URL:
        //$URL = "https://git.netways.de/api/v4/users/$id/memberships";
        //URL for test environment:
        $URL = "http://172.17.0.1:801/api/v4/users/$idUser/memberships";
    } else {
        print "ID is unknown \n";
    }

//start cURL-Handle
    $ch = curl_init();

//Set curl options
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($token, "ACCEPT: application/json"));

    //extract and save error message in a temp. file
    $tempFile = tmpfile();
    if ($tempFile === false) {
        echo "Error: Fail to create a temporarily file for error-messages.";
        exit;
    }
    $metaData = stream_get_meta_data($tempFile);
    $tempFileName = $metaData['uri'];
    curl_setopt($ch, CURLOPT_STDERR, $tempFile);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

//execute curl request
    $response = curl_exec($ch);

//Error handling
    if (curl_errno($ch)) {
        $stderrOutput = file_get_contents($tempFileName);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlInfo = curl_getinfo($ch);
        echo 'cURL Fehler: ' . curl_error($ch) . PHP_EOL;
        echo "HTTP-Statuscode: " . $httpStatusCode. PHP_EOL;
        echo "cURL-Info: " . print_r($curlInfo, true) . PHP_EOL;
        echo "STDERR Ausgabe: " . $stderrOutput;
        echo $stderrOutput . PHP_EOL;
        exit;
    }
    curl_close($ch);
    fclose($tempFile);

    $responseData = [];

    if ($response !== false) {
        $responseData[] = json_decode($response, true);
    }
    return $responseData;
}
/*
 * Fehlermeldungen:
 * unauthorized 	Der Nutzer ist zu dieser Anfrage nicht berechtigt.
 */
/**
 * Convert the access level number to a human-readable access role.
 * @param $responseData Array of response data from the GitLab API.
 * @return array $accessRole Array of response data from the GitLab API with human-readable access roles.
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
/**
 * Generate a unique filename for the output file.
 * @param array $options Assign what kind of output file should be created.
 * @return string $uniqueFileName Unique filename for the output file.
 */
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
/**
 * Output the response data in a human-readable table, JSON, or CSV format.
 * @param array $responseData Array of response data from the GitLab API.
 * @param array $options Array of parsed input options from the user to get Project Data, User Data and authorization level.
 * @return void
 */
function Output($responseData, $options) : void
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