#!/usr/bin/php
<?php
/**
 * Save a test and its trade history in the database.
 */
use rosasurfer\myfx\metatrader\model\City;
use rosasurfer\myfx\metatrader\model\Country;
use rosasurfer\myfx\metatrader\model\Test;

require(__DIR__.'/../../app/init.php');
date_default_timezone_set('GMT');




/*
$db->query("drop table if exists t_test");
$db->query("create table t_test (name varchar(100) not null)");
$db->query("create table t_test (
               id   int not null auto_increment,
               name varchar(100) not null,
               primary key (id)
            )");
$db->query("insert into t_test (name) values
               ('a'),
               ('b'),
               ('c')");
$db->query("select * from t_test where name = 'a'");
$db->query("select * from t_test where name in ('a','b')");
$db->query("update t_test set name='aa' where name in ('a') limit 1");
$db->query("select count(*) from t_test");
$db->query("delete from t_test where name like 'b%'");
$db->query("describe t_test");
$db->query("explain select count(*) from t_test");

$signalDao  = Signal::dao();                 // MySQL
$signalDb   = Signal::db();

$countryDao = Country::dao();                // PostgreSQL
$countryDb  = Country::db();

$cityDao    = City::dao();                   // SQLite
$cityDb     = City::db();

$sql    = "select * from t_signal";
$result = $signalDb->query($sql);
$value  = $signalDao->findMany($sql);
$sql    = "select * from t_signal limit 3";
$result = $signalDb->query($sql);
$value  = $signalDb->query("select last_insert_id()")->fetchField();

$sql    = "select * from public.country";
$result = $countryDb->query($sql);
$value  = $countryDao->findOne($sql);

$sql    = "select * from city";
$result = $cityDb->query($sql);
$value  = $cityDao->findOne($sql);
*/


$db = City::db();

$db->execute("drop table if exists t_test");
$db->execute("create table t_test (name varchar(100) not null)");
$db->execute("insert into t_test (name) values ('a'), ('b'), ('c')");
$db->execute("update t_test set name='c' where name in ('c')");
$db->execute("select * from t_test where name = 'a'");
$db->execute("select * from t_test where name in ('a','b')");
$db->execute("update t_test set name='aa' where name in ('c')");
$db->execute("select count(*) from t_test");
$db->execute("delete from t_test where name = 'a' or name = 'b'");
$db->execute("explain select count(*) from t_test");
$db->execute("select count(*) from t_test");



exit();



echoPre(str_pad(explode(' ', $sql, 2)[0].':', 9).' affectedRows='.mysql_affected_rows($this->connection).'  real='.$this->affectedRows().'  info="'.mysql_info($this->connection).'"');
echoPre(str_pad(explode(' ', $sql, 2)[0].':', 9).' lastChanges='.$this->lastChanges.'  changes='.$this->handler->changes().'  real='.$affectedRows);

$db->execute("begin; insert into t_test (name) values ('baz'); commit");


// --- Configuration --------------------------------------------------------------------------------------------------------


$verbose         = 0;                                                // output verbosity
$testConfigFile  = null;                                             // test configuration file
$testResultsFile = null;                                             // test results file


// --- Start ----------------------------------------------------------------------------------------------------------------


// (1) read and validate command line arguments
$args = array_slice($_SERVER['argv'], 1);

// parse options
foreach ($args as $i => $arg) {
   if ($arg == '-h')   exit(1|help());                                              // help
   if ($arg == '-v') { $verbose = max($verbose, 1); unset($args[$i]); continue; }   // verbose output
}

// (1.1) the remaining argument must be a file
sizeOf($args)!=1 && exit(1|help());
$fileName = array_shift($args);
!is_file($fileName) && exit(1|echoPre('file not found: "'.$fileName.'"'));

// (1.2) fileName must be a test configuration or a test result file
$ext = (new SplFileInfo($fileName))->getExtension();
if ($ext == 'ini') {
   $testConfigFile  = $fileName;
   $testResultsFile = strLeft($fileName, -strLen($ext)).'log';
   !is_file($testResultsFile) && exit(1|echoPre('test results file not found: "'.$testResultsFile.'"'));
}
elseif ($ext == 'log') {
   $testConfigFile  = strLeft($fileName, -strLen($ext)).'ini';
   $testResultsFile = $fileName;
   !is_file($testConfigFile) && exit(1|echoPre('test config file not found: "'.$testConfigFile.'"'));
}
else exit(1|echoPre('unsupported file: "'.$fileName.'" (see -h for help)'));

// (1.3) check read access
!is_readable($testConfigFile ) && exit(1|echoPre('file not readable: "'.$testConfigFile .'"'));
!is_readable($testResultsFile) && exit(1|echoPre('file not readable: "'.$testResultsFile.'"'));


// (2) install SIGINT handler (catches Ctrl-C)                                      // To execute destructors it is enough to
if (!WINDOWS) pcntl_signal(SIGINT, create_function('$signal', 'exit();'));          // call exit() in the handler.


// (3) process the files
processTestFiles() || exit(1);

exit(0);


// --- Functions ------------------------------------------------------------------------------------------------------------


/**
 * Process the test files.
 *
 * @return bool - success status
 */
function processTestFiles() {
   global $testConfigFile, $testResultsFile, $verbose;

   $test = Test::create($testConfigFile, $testResultsFile);
   $test->save();

   //echoPre('Test of "'.$test->getStrategy().'" with '.$test->countTrades().' trades saved.');
   return true;
}


/**
 * Show help screen.
 *
 * @param  string $message - additional message to display (default: none)
 */
function help($message = null) {
   if (is_null($message))
      $message = 'Save a test with its trade history in the database.';
   $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP_MESSAGE
$message

  Syntax:  $self  [OPTIONS] FILE

  Options:  -v  Verbose output.
            -h  This help screen.

  FILE - test config (.ini) or test result (.log) file


HELP_MESSAGE;
}
