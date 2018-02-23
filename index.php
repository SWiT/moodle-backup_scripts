<html>
<head>
	<style>
	td {padding: 3px;}
	</style>
</head>
<body>
<h1>SWiT's Live Daily Moodle Backups</h1>
<p>This server contains multiple full backups of Moodle. 
It is only accessible to Admin and Manager accounts, LDAP is disabled. 
Do not alter the data on this server if at all possible.  
</p>

<?php
require("moodlebackup.config.php");

// These defines are only here to keep errors from being thrown when including some Moodle files.
define("MOODLE_INTERNAL", "true");  
define("MATURITY_STABLE", "true");
define("MATURITY_ALPHA", "true");

$DB = NULL;
$DB_MOODLEBACKUP = NULL;

try {
    $DB_MOODLEBACKUP = new PDO("pgsql:dbname=moodlebackup;host=localhost", DB_USER, DB_PASS);
} catch(Exception $e) {
    die("ERROR: ".$e->getMessage());
}


function connectDB($dbname) {
    try {
        global $DB;
        $DB = new PDO("pgsql:dbname=".$dbname.";host=localhost", DB_USER, DB_PASS);
        $success = true;
    } catch(Exception $e) {
        echo "<tr>";
        echo "<td>";
        echo("ERROR: ".$e->getMessage());
        echo "</td>";
        echo "</tr>";
        $success = false;
    }
    return $success;
}


function getKeepInstance($instance) {
    global $DB_MOODLEBACKUP;
    try {
        $sql = "SELECT id, instance, notes FROM keep_instance WHERE instance=:instance";
        $stmt = $DB_MOODLEBACKUP->prepare($sql);
        if ($stmt) {
            $stmt->execute(array("instance"=>$instance));
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($data)) {
                return $data[0];
            }
        }
    } catch(Exception $e) {
        echo "ERROR: ".$e->getMessage();
    }
    return False;
}

function printlastcron($dbname){
    global $DB;
    try{
        $sql = "SELECT max(lastruntime) as lastcron FROM mdl_task_scheduled TS";
        $stmt = $DB->query($sql);
        if($stmt){
            $data = $stmt->fetch();
            if(!empty($data['lastcron'])){
                echo date("m/d/Y g:ia (D)", $data['lastcron']);
                return;
            }
        }
    }
    catch(Exception $e)
    {
        echo "ERROR:";
        echo $e->getMessage();
    }
    echo "Error";
    return;
}

function getDBsize($dbname) {
    global $DB;
    try{
        $sql = "SELECT pg_database_size('".$dbname."') as dbsize";
        $stmt = $DB->query($sql);
        if($stmt){
            $data = $stmt->fetch();
            $dbsize = round($data['dbsize']/pow(1024,3))."G";
            return $dbsize;
        }
    }
    catch(Exception $e)
    {
        echo "ERROR:";
        echo $e->getMessage()." ";
    }
    echo "Error";
    return;
}

function printDBversion($dbname) {
    global $DB;
    try{
        $sql = "SELECT id, name, value FROM mdl_config WHERE name = 'release'";
        $stmt = $DB->query($sql);
        if($stmt){
            $data = $stmt->fetch();
            echo $data['value'];
            return;
        }
    }
    catch(Exception $e)
    {
        echo "ERROR:";
        echo $e->getMessage()." ";
    }
    echo "Error";
    return;
}

function printfreespace($location) {
    echo round(disk_free_space($location)/pow(1024,3))."G";
    echo "&#160;(";
    echo round(disk_free_space($location)/disk_total_space($location)*100);
    echo "%)";
}

?>

<h2>Disk Usage</h2>
<p><a href="moodlelogger">Disk Usage Log</a></p>
<table>
<tr><th></th><th>Location</th><th>Free Space</th></tr>
<?php
function printfreespacerow($name, $location) {
	echo "<tr>";
		echo "<td>$name</td>";
		echo "<td>";
			echo $location;
		echo "</td>";
		echo "<td>";
			printfreespace($location);
		echo "</td>";
	echo "</tr>";	
}
printfreespacerow("System/DB", "/");
printfreespacerow("RAID6", "/media/raid");
?>
</table>


<?php

function printtable($service, &$numinstances = 0) {
    echo "<h1>".ucwords($service)."</h1>";
    echo "<div style='height: 450px; width: 1150px;  overflow: scroll; background-color: lightgray;'>";
    echo "<table>";
    echo "<tr>";
        echo "<th>DB</th>";
        echo "<th>DB Backup Time</th>";
        echo "<th>DB Version</th>";
    echo "<th>DB Size</th>";
        echo "<th>Files Size</th>";
        echo "<th>Files Backup Time</th>";
        echo "<th>Code Version</th>";
        echo "<th>Commit</th>";
    echo "</tr>";

    foreach (scandir(WWW_ROOT, SCANDIR_SORT_DESCENDING) as $f) {
        // Check the file reference is a directory
        $p = WWW_ROOT."/".$f;
        if (is_dir($p)) {
            // Use a regular expression to check the folder has a datestamp
            $pattern = "/^".$service."([0-9]{8})$/"; // service name folowed by a 8 digit datestamp.
            $matches = array();
            preg_match($pattern, $f, $matches);
            if (!empty($matches)) {
                $instance = $matches[0];
                $datestamp = $matches[1];
                $filerepo = SERVICES_ROOT."/".$service."/".$service."data".$datestamp;
                if (connectDB($instance)) {
                    include(WWW_ROOT."/$instance/version.php");

                    echo "<tr>";
                    echo "<td>";
                        if ($data = getKeepInstance($instance)) {
                            echo "<img src='keep.png' style='height: 16px; width: 16px;' title=\"".$data['notes']."\" />";
                        }
                        echo "<a href=\"/$instance\">".ucwords($instance)."</a>";
                    echo "</td>";
                    echo "<td>";
                            printlastcron($instance);
                    echo "</td>";
                    echo "<td>";
                            printDBversion($instance);
                    echo "</td>";
                    echo "<td>";
                            $logfile = $filerepo."/totaldbsize.log";
                            if (file_exists($logfile)) {
                                echo file_get_contents($logfile);
                            } else {
                                $dbsize = getDBsize($instance);
                                echo $dbsize;
                                file_put_contents($logfile, $dbsize);
                            }

                    echo "</td>";
                    echo "<td>";
                            $logfile = $filerepo."/totalfilesize.log";
                            if (file_exists($logfile)) {
                                $filessize = preg_split('/\s+/', file_get_contents($logfile));
                                echo $filessize[0];
                            } else {
                                echo "?";
                            }
                    echo "</td>";
                    echo "<td>";
                        $logfile = $filerepo."/moodlebackup.log";
                        if (file_exists($logfile)) {
                            $datestring = file_get_contents($logfile);
                            $date = new DateTime($datestring);
                            echo $date->format("m/d/Y g:ia (D)");
                        } else {
                            echo "?";
                        }
                    echo "</td>";
                    echo "<td>";
                            echo $release;
                    echo "</td>";
                    echo "<td>";
                        $logfile = $filerepo."/commitatbackup.log";
                        if (file_exists($logfile)) {
                            $commit = file_get_contents($logfile);
                            echo $commit;
                        } else {
                            echo "?";
                        }

                    echo "</td>";
                    echo "</tr>";
                }
                $numinstances++;
            }
        }
    }
    echo "</table>";
    echo "</div>";
}

foreach (explode(",", SERVICES) as $s) {
    $servicename = trim($s);
    $numinstances = 0;
    printtable($servicename, $numinstances);
    echo "$numinstances $servicename instances<br/>";
}

?>
</body>
</html>
