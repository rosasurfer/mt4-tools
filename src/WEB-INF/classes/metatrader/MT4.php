<?php
/**
 * MT4/MQL related functionality
 */
class MT4 extends StaticClass {

   /**
    * Erweiterter History-Header:
    *
    * typedef struct _HISTORY_HEADER {
    *   int  version;            //     4      => hh[ 0]    // database version
    *   char description[64];    //    64      => hh[ 1]    // copyright info
    *   char symbol[12];         //    12      => hh[17]    // symbol name
    *   int  period;             //     4      => hh[20]    // symbol timeframe
    *   int  digits;             //     4      => hh[21]    // amount of digits after decimal point
    *   int  syncMark;           //     4      => hh[22]    // server database sync marker (timestamp)
    *   int  prevSyncMark;       //     4      => hh[23]    // previous server database sync marker (timestamp)
    *   int  periodFlag;         //     4      => hh[24]    // whether hh.period is a minutes or a seconds timeframe
    *   int  timezone;           //     4      => hh[25]    // timezone id
    *   int  reserved[11];       //    44      => hh[26]
    * } HISTORY_HEADER, hh;      // = 148 byte = int[37]
    */

   /**
    * typedef struct _RATEINFO {
    *   int    time;             //     4      =>  ri[0]    // bar time
    *   double open;             //     8      =>  ri[1]
    *   double low;              //     8      =>  ri[3]
    *   double high;             //     8      =>  ri[5]
    *   double close;            //     8      =>  ri[7]
    *   double vol;              //     8      =>  ri[9]
    * } RATEINFO, ri;            //  = 44 byte = int[11]
    */

   private static $tpl_HistoryHeader = array('version'      => 400,
                                             'description'  => 'mt4.rosasurfer.com',
                                             'symbol'       => "\0",
                                             'period'       => 0,
                                             'digits'       => 0,
                                             'syncMark'     => 0,
                                             'prevSyncMark' => 0,
                                             'periodFlag'   => 0,
                                             'timezone'     => 0,
                                             'reserved'     => "\0");

   private static $tpl_RateInfo = array('time'  => 0,
                                        'open'  => 0,
                                        'high'  => 0,
                                        'low'   => 0,
                                        'close' => 0,
                                        'vol'   => 0);

   /**
    * Erzeugt eine mit Defaultwerten gefüllte HistoryHeader-Struktur und gibt sie zurück.
    *
    * @return array - struct HISTORY_HEADER
    */
   public static function createHistoryHeader() {
      return self ::$tpl_HistoryHeader;
   }


   /**
    * Schreibt einen HistoryHeader mit den angegebenen Daten in die zum Handle gehörende Datei.
    *
    * @param  resource $hFile - File-Handle eines History-Files, muß Schreibzugriff erlauben
    * @param  mixed[]  $hh    - zu setzende Headerdaten (fehlende Werte werden ggf. durch Defaultwerte ergänzt)
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function writeHistoryHeader($hFile, array $hh) {
      if (getType($hFile) != 'resource') throw new IllegalTypeException('Illegal type of parameter $hFile: '.$hFile.' ('.getType($hFile).')');
      if (!$hh)                          throw new plInvalidArgumentException('Invalid parameter $hh: '.print_r($hh, true));

      $hh = array_merge(self::$tpl_HistoryHeader, $hh);
      $hh['timezone'] = 0;
      // TODO: Struct-Daten validieren

      fSeek($hFile, 0);
      return fWrite($hFile, pack('Va64a12VVVVVVa44', $hh['version'     ],     // V
                                                     $hh['description' ],     // a64
                                                     $hh['symbol'      ],     // a12
                                                     $hh['period'      ],     // V
                                                     $hh['digits'      ],     // V
                                                     $hh['syncMark'    ],     // V
                                                     $hh['prevSyncMark'],     // V
                                                     $hh['periodFlag'  ],     // V
                                                     $hh['timezone'    ],     // V
                                                     $hh['reserved'    ]));   // a44
   }


   /**
    * Fügt eine einzelne Bar an die zum Handle gehörende Datei an.
    *
    * @param  resource $hFile - File-Handle eines History-Files, muß Schreibzugriff erlauben
    * @param  int      $time  - Timestamp der Bar
    * @param  float    $open
    * @param  float    $high
    * @param  float    $low
    * @param  float    $close
    * @param  int      $vol
    *
    * @return int - Anzahl der geschriebenen Bytes
    */
   public static function addHistoryBar($hFile, $time, $open, $high, $low, $close, $vol) {
      if (getType($hFile) != 'resource') throw new IllegalTypeException('Illegal type of parameter $hFile: '.$hFile.' ('.getType($hFile).')');

      return fWrite($hFile, pack('Vddddd', $time, $open, $low, $high, $close, $vol));
   }


   /**
    * Aktualisiert die CSV-Files des angegebenen Signals (Datenbasis für MT4-Terminals).
    *
    * @param  Signal $signal  - Signalalias
    * @param  bool   $updates - ob beim letzten Abgleich der Datenbank Änderungen festgestellt wurden
    */
   public static function updateCSVFiles(Signal $signal, $updates) {
      if (!is_bool($updates)) throw new IllegalTypeException('Illegal type of parameter $updates: '.getType($updates));

      // (1) Datenverzeichnis bestimmen
      static $dataDirectory = null;
      if (is_null($dataDirectory))
         $dataDirectory = MyFX ::getConfigPath('myfx.data_directory');


      // (2) Prüfen, ob die Datei existiert
      $fileName = $dataDirectory.'/simpletrader/'.$signal->getAlias().'_open_trades.csv';
      $isFile   = is_file($fileName);


      // (3) Datei neu schreiben, wenn DB aktualisiert wurde oder die Datei nicht existiert
      if ($updates || !$isFile) {
         $positions = OpenPosition ::dao()->listBySignal($signal);

         // Verzeichnis ggf. erzeugen
         $directory = dirName($fileName);
         if (is_file($directory))                                   throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'" (is a file)');
         if (!is_dir($directory) && !mkDir($directory, 0755, true)) throw new plInvalidArgumentException('Cannot create directory "'.$directory.'"');
         if (!is_writable($directory))                              throw new plInvalidArgumentException('Cannot write to directory "'.$directory.'"');

         // Datei schreiben
         $hFile = $ex = null;
         try {
            $hFile = fOpen($fileName, 'wb');
            // Header schreiben
            // Orderdaten schreiben
            fWrite($hFile, (string)$positions);
            fClose($hFile);
         }
         catch (Exception $ex) {
            if (is_resource($hFile))            fClose($hFile);   // Unter Windows kann die Datei u.U. nicht im Exception-Handler gelöscht werden (gesperrt).
         }                                                        // Das File-Handle muß innerhalb UND außerhalb des Exception-Handlers geschlossen werden,
         if ($ex) {                                               // erst dann läßt sich die Datei unter Windows löschen.
            if (is_resource($hFile))            fClose($hFile);
            if (!$isFile && is_file($fileName)) unlink($fileName);
            throw $ex;
         }


         echoPre("\n");
         echoPre('file "'.$fileName.'" rewritten');
      }
   }
}
?>
