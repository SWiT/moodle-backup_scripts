<?php  // [SERVICE] configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = '127.0.0.1';
$CFG->dbname    = '[SERVICE][DATESTAMP]';
$CFG->dbuser    = '[DB_USER]';
$CFG->dbpass    = '[DB_PASS]';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbsocket' => '',
);


$CFG->wwwroot   = 'https://moodlebackup.oakland.edu/[SERVICE][DATESTAMP]';
$CFG->sslproxy = true;
$CFG->dataroot  = '/usr/local/[SERVICE]/[SERVICE]data[DATESTAMP]';
$CFG->admin     = 'admin';

$CFG->loginhttps = true;
$CFG->sslproxy = false;

$CFG->directorypermissions = 0777;

$CFG->defaultblocks = 'participants,news_items,activity_modules';

$CFG->defaultblocks_topics = 'participants,news_items,activity_modules';
$CFG->defaultblocks_weeks = 'participants,news_items,activity_modules';

$CFG->extramemorylimit = '4096M';

//$CFG->debug = 32767;
//$CFG->debugdisplay = 1;
$CFG->noemailever = true;
$CFG->auth = ''; // Disables LDAP and CAS authentication.

$CFG->debugusers = '[DEBUG_USERS]';

$CFG->sessioncookie='[SERVICE][DATESTAMP]';

$CFG->pathtounoconv = '/usr/bin/unoconv';

require_once(dirname(__FILE__) . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
