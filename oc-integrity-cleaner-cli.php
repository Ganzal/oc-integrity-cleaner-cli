<?php

/**
 * Simple utility for removing extra files from ownCloud instance.
 *
 * @package oc-integrity-cleaner-cli
 *
 * @author Sergey D. Ivanov <me@ganzal.pro>
 * @copyright Copyright (c) 2017, Sergey D. Ivanov (https://ganzal.com/)
 * @license MIT
 *
 * Trick with password request over bash was found at
 * https://www.sitepoint.com/interactive-cli-password-prompt-in-php/
 * (c) 2009, Troels Knak-Nielsen
 *
 * @version 0.1.0   2017-02-07
 * @date    2017-02-07
 *
 * @since   0.1.0   2017-02-07
 */
// Checking current SAPI
if ('cli' != PHP_SAPI)
{
    trigger_error("This app should be run in CLI mode", E_USER_ERROR);
    exit(1);
}


// Detecting bash support (for blind password input)
$testBashCommand = "/usr/bin/env bash -c 'echo OK'";
if (rtrim(shell_exec($testBashCommand)) !== 'OK')
{
    trigger_error("Can't invoke bash", E_USER_ERROR);
    exit(1);
}


// Defining some constants
define('SELF_NAME', 'OC Integrity Cleaner');
define('SELF_VERSION', '0.1');
define('SELF_VERBOSE', isset(getopt('v')['v']));
define('WORKING_DIRECTORY', trim(getcwd()));

printf("\n%s, v%s\n\n", SELF_NAME, SELF_VERSION);
printf("Working directory : %s\n", WORKING_DIRECTORY);


// Checking working directory for OC Instance
echo "Checking working directory...", SELF_VERBOSE ? "\n" : '';

$simpleCheckSuccess = true;
$simpleCheckFiles = [
    '/occ',
    '/version.php',
    '/core/signature.json',
    '/config/config.php',
];

foreach ($simpleCheckFiles as $fileRelPath)
{
    $fileFullPath = WORKING_DIRECTORY . $fileRelPath;

    $fileExists = file_exists($fileFullPath);
    SELF_VERBOSE && printf("%s : %s\n", $fileRelPath,
                    $fileExists ? 'OK' : 'FAIL');

    if (!SELF_VERBOSE)
    {
        trigger_error("File not found: {$fileRelPath}");
    }

    $simpleCheckSuccess &= $fileExists;
}

if (!$simpleCheckSuccess)
{
    echo "\n";
    trigger_error("This app should be run in OC Instance root directory",
            E_USER_ERROR);
    exit(1);
}


// Checking OC Instance
echo "OK\nReading OC Config...";

require WORKING_DIRECTORY . '/config/config.php';

if (!isset($CONFIG))
{
    trigger_error("Failed to read configuration", E_USER_ERROR);
    exit(1);
}

if (!isset($CONFIG['installed']))
{
    trigger_error("Failed to read configuration value 'installed'", E_USER_ERROR);
    exit(1);
}

if (!$CONFIG['installed'])
{
    trigger_error("This OC Instance is not installed yet", E_USER_ERROR);
    exit(1);
}

if (!isset($CONFIG['trusted_domains']) || !isset($CONFIG['trusted_domains'][0]))
{
    trigger_error("Failed to read configuration value for 'trusted_domains'",
            E_USER_ERROR);
    exit(1);
}


// Defining integrity report URL
$tlsEnabled = false;
$curlOptFollowLocation = true;

if (isset($CONFIG['forcessl']))
{
    $tlsEnabled = true;
    $curlOptFollowLocation = false;
}

if (isset($CONFIG['overwrite.cli.url']))
{
    $integrityReportUrl = rtrim($CONFIG['overwrite.cli.url'], '/');

    if (false !== stripos($CONFIG['overwrite.cli.url'], 'https://'))
    {
        $curlOptFollowLocation = false;
    }
}
else
{
    $integrityReportUrl = 'htt' . ($tlsEnabled ? 's' : '') . '://' . $CONFIG['trusted_domains'][0];
}

$integrityReportUrl .= '/index.php/settings/integrity/failed';
$targetHostname = explode('/', $integrityReportUrl)[2];

printf("Host : %s\n", $targetHostname);
printf("Report URL : %s\n", $integrityReportUrl);


// Request for admin credentials
echo "\n\nProvide your admin credentials:\n";

$adminUsername = readline("\n  username: ");

$adminPasswordBashCommand = "/usr/bin/env bash -c 'read -s -p \""
        . addslashes('  password: ')
        . "\" mypassword && echo \$mypassword'";
$adminPassword = rtrim(shell_exec($adminPasswordBashCommand));


// Fetching integrity report
echo "\n\nFetching integrity report...";

$curlHandler = curl_init();
curl_setopt($curlHandler, CURLOPT_URL, $integrityReportUrl);
curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curlHandler, CURLOPT_USERPWD, "$adminUsername:$adminPassword");
curl_setopt($curlHandler, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curlHandler, CURLOPT_FOLLOWLOCATION, $curlOptFollowLocation);

$curlExecOutput = curl_exec($curlHandler);
$curlExecInfo = curl_getinfo($curlHandler);
curl_close($curlHandler);

if (!$curlExecOutput)
{
    echo " Something went wrong.\ncURL output\n===========\n";
    var_export($curlExecOutput);
    echo "\n===========\n\ncURL info\n=========\n";
    var_export($curlExecInfo);
    echo "\n=========\n";

    trigger_error("Failed to fetch integrity report", E_USER_ERROR);
    exit(1);
}


// Parsing integrity report
echo " OK\nParsing integrity report...";

if (!preg_match("~Results.+[=]+$(.+)Raw output~Usm", $curlExecOutput,
                $resultsMatch))
{
    trigger_error("Failed to parse integrity report [preg_match step]",
            E_USER_ERROR);
    exit(1);
}

$extraFiles = [];
$extraFilesCnt = 0;
$resultsLines = explode("\n", trim($resultsMatch[1]));

$currentApp = null;
$currentReason = null;
$currentLine = 0;

foreach ($resultsLines as $resultLine)
{
    $currentLine++;
    if (!preg_match('~(\t+)?- (.+)~', $resultLine, $resultMatch))
    {
        trigger_error(sprintf("preg_match failed at line %d '%s'", $currentLine,
                        $resultLine), E_USER_WARNING);
        continue;
    }

    switch ($resultMatch[1])
    {
        case '':
            $currentApp = trim($resultMatch[2]);
            $currentReason = null;

            SELF_VERBOSE && printf("\n+ app : %s", $currentApp);

            break;

        case "\t":
            $currentReason = trim($resultMatch[2]);

            SELF_VERBOSE && printf("\n+ resaon : %s", $currentReason);

            break;

        case "\t\t":
            $extraFile = trim($resultMatch[2]);

            if ('EXTRA_FILE' != $currentReason)
            {
                SELF_VERBOSE && printf("\nskip : %s", $file);

                continue;
            }

            $extraFiles[$currentApp][] = $extraFile;
            $extraFilesCnt++;

            SELF_VERBOSE && printf("\n+ file : %s", $extraFile);

            break;
    }
}

SELF_VERBOSE && print("\n");

echo "OK\n";

if (!$extraFilesCnt)
{
    echo "Nothing to clean up\n";
    exit;
}

printf("Cleaning up %d file(s) from %d app(s)\n", $extraFilesCnt,
        count($extraFiles));


define('BACKUP_DIRECTORY',
        sprintf("%s/oc-integrity-cleaner-backup/%s-%s", $_SERVER['HOME'],
                $targetHostname, date('Y-m-d-H-i-s')));

printf("All files will move under '%s'\n\n", BACKUP_DIRECTORY);
readline("Press ENTER when ready\n");


// Moving extra files
mkdir(BACKUP_DIRECTORY, 0751, true);

echo "\nWork in progress...";

$progress = 0;
$successCnt = 0;
$failedCnt = 0;
$failedFiles = [];
foreach ($extraFiles as $appName => $appExtraFiles)
{
    $pathPrefix = ('core' == $appName ? '/' : '/apps/' . $appName . '/');

    foreach ($appExtraFiles as $file)
    {
        $from = WORKING_DIRECTORY . $pathPrefix . $file;
        $to = BACKUP_DIRECTORY . $pathPrefix . $file;
        $toDir = dirname($to);

        if (!file_exists($toDir))
        {
            mkdir($toDir, 0751, true);
        }

        SELF_VERBOSE && printf("\n< %s\n> %s\n", $from, $to);

        $result = rename($from, $to);
        if ($result)
        {
            $successCnt++;
        }
        else
        {
            $failedCnt++;
            $failedFiles[] = $pathPrefix . $file;
        }

        printf("\rWork in progress... %d done", ++$progress);
    }
}

echo "\rWork complete.", str_repeat(' ', 42);

if ($failedCnt)
{
    printf("\nSuccesses : %d\nFailed to move next %d file(s) :\n  - ",
            $successCnt, $failedCnt);
    echo implode("\n  - ", $failedFiles);
}

echo "\n";

# eof
