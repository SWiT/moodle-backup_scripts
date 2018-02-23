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


// Remove old out of range [SERVICE][DATESTAMP].sql.gz or [SERVICE][DATESTAMP] DB archive files.
echo "*** Delete out of range DB archive files ***\n";
foreach (scandir(DB_ARCHIVE, SCANDIR_SORT_DESCENDING) as $f) {
    // Use a regular expression to check the files datestamp is out of range
    $pattern = "/^".SERVICE."([0-9]{8}).sql.gz$/"; // service name folowed by a 8 digit datestamp.
    $matches = array();
    preg_match($pattern, $f, $matches);
    if (!empty($matches)) {
        if ($matches[1] < $olddatestamp) {
            $p = DB_ARCHIVE."/".$f;
            // Check if instance is not in the keep list.
            if (!keepInstance(SERVICE.$matches[1])) {
                // Delete the old filedir.
                echo "unlink $p\n";
                if (RUN) {
                    unlink($p);
                }
            } else {
                echo "KEEP $p\n";
            }
        }
    }

    // Use a regular expression to check the files datestamp is out of range
    $pattern = "/^".SERVICE."([0-9]{8})$/"; // service name folowed by a 8 digit datestamp.
    $matches = array();
    preg_match($pattern, $f, $matches);
    if (!empty($matches)) {
        if ($matches[1] < $olddatestamp) {
            $p = DB_ARCHIVE."/".$f;
            // Check if instance is not in the keep list.
            if (!keepInstance(SERVICE.$matches[1])) {
                // Delete the old filedir.
                $command = "rm -rf $p";
                run($command);
            } else {
                echo "KEEP $p\n";
            }
        }
    }
}
echo "\n";


// Download the 0 backup directory from the Production DB Replicant and rename it to [SERVICE][DATESTAMP].sql.gz.
echo "*** Download directory 0 from production and rename to ".SERVICE."$newdatestamp ***\n";
$command = "scp -r ".PROD_SERVER.":".PROD_DB_ARCHIVE."/0 ".DB_ARCHIVE."/".SERVICE."$newdatestamp";
run($command);
echo "\n";


// Drop old out of range databases from the DB.
echo "*** Drop old out of range databases from the DB ***\n";
try {
    $db = new PDO("pgsql:dbname=postgres;host=localhost", DB_USER, DB_PASS);
    if ($db) {
        $sql = "SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname DESC";
        echo "$sql\n";
        $stmt = $db->query($sql);
        foreach ($stmt as $row) {
            $dbname = $row["datname"];
            $pattern = "/^".SERVICE."([0-9]{8})$/"; // service name followed by a 8 digit datestamp.
            $matches = array();
            preg_match($pattern, $dbname, $matches);
            if (!empty($matches)) {
                if ($matches[1] < $olddatestamp) {
                    // Check if instance is not in the keep list.
                    if (!keepInstance(SERVICE.$matches[1])) {
                        // Delete the old database.
                        $sql = "DROP DATABASE $dbname";
                        echo "$sql\n";
                        if (RUN) {
                            $result = $db->exec($sql);
                            if ($result === False) {
                                die('ERROR: dropping table.');
                            }
                        }
                    } else {
                        echo "KEEP DATABASE $dbname\n";
                    }
                }
            }
        }
    }
} catch(Exception $e) {
    echo "ERROR: ".$e->getMessage();
}
echo "\n";


// Create a new [SERVICE][DATESTAMP] DB
$dbname = SERVICE.$newdatestamp;
echo "*** Create new $dbname database ***\n";
$sql = "CREATE DATABASE $dbname ENCODING = 'UTF-8'";
echo "$sql\n";
if (RUN) {
    try {
        if ($db) {
            $result = $db->exec($sql);
        }
    } catch(Exception $e) {
        echo "ERROR: ".$e->getMessage();
    }
}
echo "\n";


// Extract the new [SERVICE][DATESTAMP] backup directory to the new [SERVICE][DATESTAMP] DB
echo "*** Extract directory backup $dbname to $dbname ***\n";
$command = "pg_restore -j 7 -d $dbname ".DB_ARCHIVE."/$dbname";
run($command);
echo "\n";


// Purge Caches
echo "*** Purge all caches for $dbname ***\n";
$command = "php ".WWW_ROOT."/$dbname/admin/cli/purge_caches.php";
run($command);
echo "\n";

// Print date & time for logging.
$endtime = new DateTime();
echo $endtime->format('Y-m-d H:i:s')."\n";
$dur = $starttime->diff($endtime);
echo "Duration: ".$dur->format("%h:%I:%S")."\n";
