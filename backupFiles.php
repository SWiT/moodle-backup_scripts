<?php
if (php_sapi_name() != "cli") {die("this script is CLI only.");}

require("moodlebackup.config.php");

// Get the command line options.
$shortoptions = "s:y";
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

// Connect to the DB Server
try {
    $PDO = new PDO("pgsql:dbname=moodlebackup;host=localhost", DB_USER, DB_PASS);
    $STMT = False;
} catch(Exception $e) {
    echo "ERROR: ".$e->getMessage();
}

function run($command) {
    echo $command."\n";
    $retvar = 0;
    if (RUN) {
        system($command, $retvar);
    }
    if ($retvar !== 0) {
        echo "ERROR: running \"$command\"\n";
    }
}

function keepInstance($instance) {
    global $PDO, $STMT;
    $keep = True; // If anything goes wrong, keep it by default.
    try {
        if ($PDO) {
            if ($STMT == False) {
                $sql = "SELECT id, instance, notes FROM keep_instance WHERE instance=:instance";
                $STMT = $PDO->prepare($sql);
            }
            if ($STMT) {
                $STMT->execute(array("instance"=>$instance));
                $data = $STMT->fetchAll(PDO::FETCH_ASSOC);
                if (empty($data)) {
                    return False;
                }
            }
        }
    } catch(Exception $e) {
        echo "ERROR: ".$e->getMessage();
    }
    return $keep;
}


/************
 * Main
 ***********/
// Print the service name.
echo "SERVICE: ".SERVICE."\n";
// Print date & time for logging.
$starttime = new DateTime();
echo $starttime->format('Y-m-d H:i:s')."\n";
echo "\n";


// Print the Date Range
$date = new DateTime();
$newdatestamp = $date->format('Ymd');
$date->sub(new DateInterval(DATE_RANGE));
$olddatestamp = $date->format('Ymd');
echo "*** Date Range ***\n";
echo "$newdatestamp - $olddatestamp\n";
echo "\n";


// rsync the Production (Replicant) server to Temporary local location (SERVICEtmp).
echo "*** rsync ".SERVICE." production to temporary location ***\n";
$command = "rsync -a --delete --exclude-from=".FILEDATA_EXCLUDES." ".PROD_SERVER.":".PROD_FILEDATA." ".FILEDATA_TEMP;
run($command);
echo "\n";


// Create the moodlebackup log files.
echo "*** Create the moodlebackup log files ***\n";
$command = "date > ".FILEDATA_TEMP."/moodlebackup.log";
run($command);
$command = "du -sh ".FILEDATA_TEMP." > ".FILEDATA_TEMP."/totalfilesize.log";
run($command);
echo "\n";


// Delete old file repos that are out of range.
echo "*** Delete out of range file repositories ***\n";
foreach (scandir(FILEDATA_ROOT, SCANDIR_SORT_DESCENDING) as $f) {
    $p = FILEDATA_ROOT."/".$f;
    // Check the file reference is a directory
    if (is_dir($p)) {
        // Use a regular expression to check the folders datestamp is out of range
        $pattern = "/^".SERVICE."data([0-9]{8})$/"; // service name folowed by a 8 digit datestamp.
        $matches = array();
        preg_match($pattern, $f, $matches);
        if (!empty($matches)) {
            if ($matches[1] < $olddatestamp) {
                // Check if instance is not in the keep list.
                if (!keepInstance(SERVICE.$matches[1])) {
                    //Delete the old filedir.
                    $command = "rm -rf $p";
                    run($command);
                } else {
                    echo "KEEP $p\n";
                }
            }
        }
    }
}
echo "\n";


// cp hardlinks from moodletmp to moodle[DATESTAMP]
echo "*** cp hardlinks and rsync from ".SERVICE."datatmp to ".SERVICE."data$newdatestamp ***\n";
if (RUN) {
    mkdir(FILEDATA_ROOT."/".SERVICE."data".$newdatestamp."/", 0777, True);
    mkdir(FILEDATA_ROOT."/".SERVICE."data".$newdatestamp."/filedir/", 0777, True);
    mkdir(FILEDATA_ROOT."/".SERVICE."data".$newdatestamp."/trashdir/", 0777, True);
}
// Check if the folder is empty before running a 'cp' command.
$folder = FILEDATA_TEMP."/filedir";
if (count(scandir($folder)) > 2) {
    run("cp -alf $folder/* ".FILEDATA_ROOT."/".SERVICE."data$newdatestamp/filedir/");
} else {
    echo "$folder was empty.\n";
}
$folder = FILEDATA_TEMP."/trashdir";
if (count(scandir($folder)) > 2) {
    run("cp -alf $folder/* ".FILEDATA_ROOT."/".SERVICE."data$newdatestamp/trashdir/");
} else {
    echo "$folder was empty.\n";
}
$command = "rsync -aH --delete ".FILEDATA_TEMP."/ ".FILEDATA_ROOT."/".SERVICE."data$newdatestamp";
run($command);
echo "\n";


// Remove old out of range Git repositories
echo "*** Delete out of range Git repositories ***\n";
foreach (scandir(WWW_ROOT, SCANDIR_SORT_DESCENDING) as $f) {
    $p = WWW_ROOT."/".$f;
    // Check the file reference is a directory
    if (is_dir($p)) {
        // Use a regular expression to check the folders datestamp is out of range
        $pattern = "/^".SERVICE."([0-9]{8})$/"; // service name folowed by a 8 digit datestamp.
        $matches = array();
        preg_match($pattern, $f, $matches);
        if (!empty($matches)) {
            if ($matches[1] < $olddatestamp) {
                // Check if instance is not in the keep list.
                if (!keepInstance(SERVICE.$matches[1])) {
                    // Delete the old gitrepo.
                    $command = "rm -rf $p";
                    run($command);
                } else {
                    echo "KEEP $p\n";
                }
            }
        }
    }
}
echo "\n";


// Create new instance of the git repo.
echo "*** Create new instance of the git repo ***\n";
if (chdir(WWW_ROOT)) {
    echo getcwd()."\n";
    $newrepo = SERVICE.$newdatestamp;
    $command = "git clone ".GIT_REPO." $newrepo";
    run($command);
} else {
    die("ERROR: changing dir to ".WWW_ROOT);
}
echo "\n";


// Get the current commit hash from a production front end.
echo "*** Get the current commit hash from a production front end. ***\n";
$logfile = FILEDATA_ROOT."/".SERVICE."data$newdatestamp/commitatbackup.log";
$commit = exec("ssh ".PROD_FRONTEND." git --git-dir=/var/www/html/moodle/.git log --pretty=format:'%h' -n 1");
echo "commit $commit\n";
if (RUN) {
    file_put_contents($logfile, $commit);
    echo "Wrote $commit to $logfile\n";
}


// Checkout the same commit as Production
echo "*** Checkout the same commit as Production ***\n";
if (file_exists(WWW_ROOT."/$newrepo")) {
    if (chdir(WWW_ROOT."/$newrepo")) {
        echo getcwd()."\n";
        $command = "git checkout $commit";
        run($command);
    } else {
        die("ERROR: changing dir to ".WWW_ROOT."/$newrepo");
    }
}
echo "\n";


// Generate the config.php file.
echo "*** Generate the config.php file ***\n";
$templatedir = dirname(__FILE__);
$configcontents = file_get_contents($templatedir."/config.template.php");
$search = array("[SERVICE]", "[DATESTAMP]", "[DB_USER]", "[DB_PASS]", "[DEBUG_USERS]");
$replace = array(SERVICE, $newdatestamp, DB_USER, DB_PASS, DEBUG_USERS);
$configcontents = str_replace($search, $replace, $configcontents);
if (RUN) {
    file_put_contents(WWW_ROOT."/$newrepo/config.php", $configcontents);
}
echo "Wrote ".WWW_ROOT."/$newrepo/config.php\n";
echo "\n";


// Print date & time for logging.
$endtime = new DateTime();
echo $endtime->format('Y-m-d H:i:s')."\n";
$dur = $starttime->diff($endtime);
echo "Duration: ".$dur->format("%h:%I:%S")."\n";

