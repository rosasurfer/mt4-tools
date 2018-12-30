#!/usr/bin/env php
<?php
/**
 * Aktualisiert die MetaTrader-History der angegebenen Instrumente im globalen MT4-Serververzeichnis.
 */
namespace rosasurfer\rost\metatrader\update_history;

use rosasurfer\config\Config;

use rosasurfer\rost\Rost;
use rosasurfer\rost\metatrader\HistorySet;
use rosasurfer\rost\metatrader\MT4;
use rosasurfer\rost\model\RosaSymbol;

use function rosasurfer\rost\fxtTime;
use function rosasurfer\rost\isFxtWeekend;

require(dirName(realPath(__FILE__)).'/../../app/init.php');
date_default_timezone_set('GMT');


// -- Konfiguration ---------------------------------------------------------------------------------------------------------


$verbose = 0;                                                                       // output verbosity


// -- Start -----------------------------------------------------------------------------------------------------------------


// (1) Befehlszeilenargumente einlesen und validieren
/** @var string[] $args */
$args = array_slice($_SERVER['argv'], 1);

// Optionen parsen
foreach ($args as $i => $arg) {
    if ($arg == '-h'  )   exit(1|help());                                            // Hilfe
    if ($arg == '-v'  ) { $verbose = max($verbose, 1); unset($args[$i]); continue; } // verbose output
    if ($arg == '-vv' ) { $verbose = max($verbose, 2); unset($args[$i]); continue; } // more verbose output
    if ($arg == '-vvv') { $verbose = max($verbose, 3); unset($args[$i]); continue; } // very verbose output
}

/** @var RosaSymbol[] $symbols */
$symbols = [];

// Symbole parsen
foreach ($args as $i => $arg) {
    /** @var RosaSymbol $symbol */
    $symbol = RosaSymbol::dao()->findByName($arg);
    if (!$symbol) exit(1|stderror('error: unknown symbol "'.$args[$i].'"'));
    $symbols[$symbol->getName()] = $symbol;                                         // using the name as index removes duplicates
}
$symbols = $symbols ?: RosaSymbol::dao()->findAll();                                // ohne Angabe werden alle Instrumente verarbeitet


// (2) History aktualisieren
foreach ($symbols as $symbol) {
    updateHistory($symbol) || exit(1);
}
exit(0);


// --- Funktionen -----------------------------------------------------------------------------------------------------------


/**
 * Aktualisiert die MT4-History eines Instruments.
 *
 * @param  RosaSymbol $symbol
 *
 * @return bool - Erfolgsstatus
 */
function updateHistory(RosaSymbol $symbol) {
    $symbolName   = $symbol->getName();
    $symbolDigits = $symbol->getDigits();

    global $verbose;
    $directory    = Config::getDefault()->get('app.dir.data').'/history/mt4/XTrade-Testhistory';
    $lastSyncTime = null;
    echoPre('[Info]    '.$symbolName);

    // HistorySet oeffnen bzw. neues Set erstellen
    if ($history = HistorySet::get($symbolName, $directory)) {
        if ($verbose) echoPre('[Info]    lastSyncTime: '.(($lastSyncTime=$history->getLastSyncTime()) ? gmDate('D, d-M-Y H:i:s', $lastSyncTime) : 0));
    }
    !$history && $history=HistorySet::create($symbolName, $symbolDigits, $format=400, $directory);

    // History beginnend mit dem letzten synchronisierten Tag aktualisieren
    $startTime = $lastSyncTime ?: (int)$symbol->getHistoryStartM1('U');                             // FXT
    $startDay  = $startTime - $startTime%DAY;                                                       // 00:00 FXT der Startzeit
    $today     = ($time=fxtTime()) - $time%DAY;                                                     // 00:00 FXT des aktuellen Tages
    $lastMonth = -1;

    for ($day=$startDay; $day < $today; $day+=1*DAY) {
        $shortDate = gmDate('D, d-M-Y', $day);
        $month     = (int) gmDate('m', $day);
        if ($month != $lastMonth) {
            echoPre('[Info]    '.gmDate('M-Y', $day));
            $lastMonth = $month;
        }
        if (!isFxtWeekend($day, 'FXT')) {                                                           // nur an Handelstagen
            if      (is_file($file=Rost::getVar('rostFile.M1.compressed', $symbolName, $day))) {}   // wenn komprimierte Rost-Datei existiert
            else if (is_file($file=Rost::getVar('rostFile.M1.raw'       , $symbolName, $day))) {}   // wenn unkomprimierte Rost-Datei existiert
            else {
                echoPre('[Error]   '.$symbolName.'  Rosatrader history for '.$shortDate.' not found');
                return false;
            }
            if ($verbose > 0) echoPre('[Info]    synchronizing '.$shortDate);

            $bars = Rost::readBarFile($file, $symbolName);
            $history->synchronize($bars);
        }
        if (!WINDOWS) pcntl_signal_dispatch();                                                      // Auf Ctrl-C pruefen, um bei Abbruch den
    }                                                                                               // Schreibbuffer der History leeren zu koennen.
    $history->close();

    echoPre('[Ok]      '.$symbolName);
    return true;
}


/**
 * Hilfefunktion: Zeigt die Syntax des Aufrufs an.
 *
 * @param  string $message [optional] - zusaetzlich zur Syntax anzuzeigende Message (default: keine)
 */
function help($message = null) {
    if (is_null($message))
        $message = 'Updates the MetaTrader history of the specified symbols.';
    $self = baseName($_SERVER['PHP_SELF']);

echo <<<HELP
$message

  Syntax:  $self [symbol ...] [OPTIONS]

  Options:  -h   This help screen.


HELP;
}
