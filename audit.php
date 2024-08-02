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
    $options = Input();

    $token = GetToken($options);
    $page = $options['page'] ?? 1;
    $perPage = $options['perPage'] ?? 20;
    $response = Request($options, $token);
    $responseData = $response['data'];
    $totalPages = $response['totalPages'];
    $totalItems = $response['totalItems'];

    if(isset($options['page'])) {
        if ($page > $totalPages) {
            printMessage("There is no page $page. Total Amount of pages: $totalPages");
            exit(1);
        }
    } else {
        // Loop through pages if totalPages is greater than 1
        while ($page <= $totalPages) {
            $options['page'] = $page;
            $response = Request($options, $token);
            $responseData = array_merge($responseData, $response['data']);
            $page++;
        }
    }

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
            "no-accessrole",
            "perPage:",
            "page:"
        ];
        $options = getopt($shortOpts, $longOpts);
    if (isset($options["project"])) {
        $options["p"] = $options["project"];
    }

    if (isset($options["user"])) {
        $options["u"] = $options["user"];
    }
    if ((isset($options["h"]) || (isset($options["help"])))) {
        // Check if the manpage is installed
        $output = shell_exec('man -w audit 2>/dev/null');
        if (empty($output)) {
            printMessage("The man page for audit.php is not installed.\n");
            echo "Create the man page with the following command:\n";
            echo "Create a directory: mkdir -p /usr/local/man/man1/ \n";
            echo "Copy the man page from doc/audit.1 to the directory: cp docs/audit.1 /usr/local/man/man1 \n";
            echo "Update man page database: mandb \n\n";
        } else {
            system('man audit');
        }
        exit(1);
    }
    if (!isset($options["p"]) && !isset($options["u"]) && !isset($options["h"])){
        printMessage("No correct Option.", "yellow");
        print "Please enter options: \n -p with Projekt ID \ -u with User ID \ -h for help \n";
      exit(1);
    }
    if (isset($options["p"]) && !is_numeric($options["p"])) {
        printMessage("Error: The projekt ID must be a number.", "yellow");
        exit(1);
    }

    if (isset($options["u"]) && !is_numeric($options["u"])) {
        printMessage("Error: The user ID must be a number.", "yellow");
        exit(1);
    }
    if (isset($options["perPage"]) && !is_numeric($options["perPage"])) {
        printMessage("Error: The amount of output per page must be a number.", "yellow");
        exit(1);
    }
    if (isset($options["page"]) && !is_numeric($options["page"])) {
        printMessage("Error: The page must be a number.", "yellow");
        exit(1);
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
        printMessage("Error: No access token!");
        print "Give it as flag -t/--token OR put it in the file 'accessToken.txt' \n";
        exit;
    }
   //Validate access token
    if (!preg_match('/^glpat-[\w-]{20}$/', $token)) {
        printMessage("The access token appears to be invalide. Check the format.". PHP_EOL, "yellow");
        exit;
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
function Request(array $options, string $token): array
{
    $idProjects = $options["p"];
    $idUser = $options["u"];
    $page = $options['page'];
    $perPage = $options['perPage'];

    if (isset($idProjects)) {
        //Netways URL
        //$URL = "https://git.netways.de/api/v4/projects/$id/members/all?per-page=$perPage&page=&page";
        //URL for test environment:
        $URL = "http://172.17.0.1:80/api/v4/projects/$idProjects/members/all?per_page=$perPage&page=$page";
    } elseif (isset($idUser)) {
        //Netways URL:
        //$URL = "https://git.netways.de/api/v4/users/$id/memberships?per-page=$perPage&page=&page";
        //URL for test environment:
        $URL = "http://172.17.0.1:80/api/v4/users/$idUser/memberships?per_page=$perPage&page=$page";
    } else {
        printMessage ("ID is unknown \n", "yellow");
        exit(1);
    }

//start cURL-Handle
    $ch = curl_init();

//Set curl options
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($token, "ACCEPT: application/json"));
    curl_setopt($ch, CURLOPT_HEADER, true);

//extract and save error message in a temp. file
    $tempFile = tmpfile();
    if ($tempFile === false) {
        //diese meldung nicht in standout
        echo "Error: Fail to create a temporarily file for error-messages.";
        exit(1);
    }
    $metaData = stream_get_meta_data($tempFile);
    $tempFileName = $metaData['uri'];
    curl_setopt($ch, CURLOPT_STDERR, $tempFile);
    curl_setopt($ch, CURLOPT_VERBOSE, true);

//execute curl request
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

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
        exit(1);
    }

    curl_close($ch);
    fclose($tempFile);

    // Extract total-items and total-pages from the header
    $totalItems = null;
    $totalPages = null;
    if (preg_match('/X-Total: (\d+)/', $header, $matches)) {
        $totalItems = (int)$matches[1];
    }
    if (preg_match('/X-Total-Pages: (\d+)/', $header, $matches)) {
        $totalPages = (int)$matches[1];
    }

    $responseData = [];
    if ($body !== false) {
        $responseData[] = json_decode($body, true);
    }

    // Return response data along with pagination info
    return [
        'data' => $responseData,
        'totalItems' => $totalItems,
        'totalPages' => $totalPages
    ];
}

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

//default output
    if(!isset($options["json"]) && !isset($options["json-file"]) && !isset($options["csv-file"]) && !isset($options["pretty"])) {
        print json_encode($responseData);
    }

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
            printMessage("Data has been successfully saved.", "green");
            echo "File result.json ($bytesWritten bytes)\n";
            echo "You can access [$fileUrl]\n";
        } else {
            printMessage("Error: Failed to save data in json-file!");
        }
    }
//Output as pretty JSON
    if (isset($options["json"])) {
        print json_encode($responseData, JSON_PRETTY_PRINT) . PHP_EOL;
    }

//Output saved in csv-file
    if (isset($options["csv-file"])) {
        $file = UniqueFileName($options);
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
        } else {
            printMessage("Error: Failed to save data in csv-file! \n");
        }
        fclose($csvFile);

        $fileUrl = "file://" . realpath($file);
        printMessage("\nData has been successfully saved.", "green");
        echo "\nFile $file\n";
        echo "You can access [$fileUrl]\n";
    }
}
/**
 * Print an error message in the specified color.
 *
 * @param string $message The error message to print.
 * @param string $color The color to print the message in (default is red).
 * @return void
 */
function printMessage(string $message, string $color = 'red'): void
{
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
    ];

    $colorCode = $colors[$color] ?? $colors['red'];
    $resetCode = "\033[0m";
    print "{$colorCode}{$message}{$resetCode}" . PHP_EOL;
}
?>