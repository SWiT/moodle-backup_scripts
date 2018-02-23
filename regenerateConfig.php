<?php
if (php_sapi_name() != "cli") {die("this script is CLI only.");}

require("moodlebackup.config.php");

// Get the command line options.
$shortoptions = "s:d:cy";
$options = getopt($shortoptions);

// Validate the service option and include it's config.inc.php.
if (isset($options["s"])) {
    //Check option is in list of valid services.
    $service = False;
    foreach(explode(",", SERVICES) as $s) {
        $s = trim($s);
        if ($s == $options["s"]) {
            $service = $options["s"];
            break;
        }
    }
    if(!$service) {
        echo "SERVICES: ".SERVICES."\n";
        die("ERROR: invalid SERVICE '".$options["s"]."'.\n");
    }
} else {
    die("ERROR: -s \"SERVICE\" parameter is required.\n");
}
require($service."/config.inc.php");

// Validate the datestamp option
if (isset($options["d"])) {
    //Check option is valid.
    $datestamp = $options["d"];
    if(!$datestamp) {
        die("ERROR: invalid DATESTAMP '".$options["d"]."'.\n");
    }
} else {
    die("ERROR: -d \"DATESTAMP\" parameter is required.\n");
}

// Check for the -c argument for recloning the code.
if (isset($options["c"])) {
    $rebuildcode = True;
} else {
    $rebuildcode = False;
}

// Check for the -y argument to make changes.
if (isset($options["y"])) {
    define("RUN", True);
} else {
    echo "\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "This CLI script requires a '-y' argument to make changes.\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "\n";
    define("RUN", False);
}


function run($command) {
    echo $command."\n";
    $retvar = 0;
    if (RUN) {
        system($command, $retvar);
    }
    if ($retvar !== 0) {
        die("ERROR: running \"$command\"\n");
    }
}



/************
 * Main
 ***********/
// Print date & time for logging.
$starttime = new DateTime();
echo $starttime->format('Y-m-d H:i:s')."\n";
echo "\n";


// Print the service name.
echo "SERVICE: ".SERVICE."\n";


// Print the Datestamp
echo "DATESTAMP: $datestamp\n";


if ($rebuildcode) {

    // Remove the Git repository if it already exists
    echo "*** Delete old Git repository ***\n";
    $p = WWW_ROOT."/".$service.$datestamp;
    // Check the file reference is a directory
    if (is_dir($p)) {
        // Delete the old gitrepo.
        $command = "rm -rf $p";
        run($command);
    }
    echo "\n";


    // Create new instance of the git repo.
    echo "*** Create new instance of the git repo ***\n";
    if (chdir(WWW_ROOT)) {
        echo getcwd()."\n";
        $command = "git clone ".GIT_REPO." ".SERVICE.$datestamp;
        run($command);
    } else {
        die("ERROR: changing dir to ".WWW_ROOT);
    }
    echo "\n";


    // Get the current commit hash from a production front end.
    echo "*** Get the current commit hash from a production front end. ***\n";
    $logfile = FILEDATA_ROOT."/".SERVICE."data$datestamp/commitatbackup.log";
    $commit = exec("ssh ".PROD_FRONTEND." git --git-dir=/var/www/html/moodle/.git log --pretty=format:'%h' -n 1");
    echo "commit $commit\n";
    if (RUN) {
        file_put_contents($logfile, $commit);
        echo "Wrote $commit to $logfile\n";
    }


    // Checkout the same commit as Production
    echo "*** Checkout the same commit as Production ***\n";
    if (file_exists(WWW_ROOT."/".SERVICE.$datestamp)) {
        if (chdir(WWW_ROOT."/".SERVICE.$datestamp)) {
            echo getcwd()."\n";
            $command = "git checkout $commit";
            run($command);
        } else {
            die("ERROR: changing dir to ".WWW_ROOT."/".SERVICE.$datestamp);
        }
    }
    echo "\n";
}

// Generate the config.php file.
echo "*** Generate the config.php file ***\n";
$templatedir = dirname(__FILE__);
$configcontents = file_get_contents($templatedir."/config.template.php");
$search = array("[SERVICE]", "[DATESTAMP]", "[DB_USER]", "[DB_PASS]", "[DEBUG_USERS]");
$replace = array(SERVICE, $datestamp, DB_USER, DB_PASS, DEBUG_USERS);
$configcontents = str_replace($search, $replace, $configcontents);
if (RUN) {
    file_put_contents(WWW_ROOT."/".SERVICE.$datestamp."/config.php", $configcontents);
}
echo "Wrote ".WWW_ROOT."/".SERVICE.$datestamp."/config.php\n";
echo "\n";


// Print date & time for logging.
$endtime = new DateTime();
echo $endtime->format('Y-m-d H:i:s')."\n";
$dur = $starttime->diff($endtime);
echo "Duration: ".$dur->format("%h:%I:%S")."\n";