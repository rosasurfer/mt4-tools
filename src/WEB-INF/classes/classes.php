<?php
// Klassendefinitionen
// -------------------
$dir = dirName(__FILE__).DIRECTORY_SEPARATOR;

$__classes['DownloadFTPConfigurationAction'    ] = $dir.'actions/DownloadFTPConfigurationAction';
$__classes['DownloadFTPConfigurationActionForm'] = $dir.'actions/DownloadFTPConfigurationActionForm';
$__classes['UploadAccountHistoryAction'        ] = $dir.'actions/UploadAccountHistoryAction';
$__classes['UploadAccountHistoryActionForm'    ] = $dir.'actions/UploadAccountHistoryActionForm';
$__classes['UploadFTPConfigurationAction'      ] = $dir.'actions/UploadFTPConfigurationAction';
$__classes['UploadFTPConfigurationActionForm'  ] = $dir.'actions/UploadFTPConfigurationActionForm';

$__classes['Dukascopy'                         ] = $dir.'dukascopy/Dukascopy';

$__classes['HistorySet'                        ] = $dir.'metatrader/HistorySet';
$__classes['MT4'                               ] = $dir.'metatrader/MT4';

$__classes['MyFX'                              ] = $dir.'myfx/MyFX';

$__classes['DataNotFoundException'             ] = $dir.'simpletrader/DataNotFoundException';
$__classes['SimpleTrader'                      ] = $dir.'simpletrader/SimpleTrader';

$__classes['LZMA'                              ] = $dir.'helper/LZMA';
$__classes['ImportHelper'                      ] = $dir.'helper/ImportHelper';
$__classes['ReportHelper'                      ] = $dir.'helper/ReportHelper';
$__classes['Validator'                         ] = $dir.'helper/Validator';
$__classes['ViewHelper'                        ] = $dir.'helper/ViewHelper';

$__classes['Account'                           ] = $dir.'model/Account';
$__classes['AccountDAO'                        ] = $dir.'model/AccountDAO';
$__classes['ClosedPosition'                    ] = $dir.'model/ClosedPosition';
$__classes['ClosedPositionDAO'                 ] = $dir.'model/ClosedPositionDAO';
$__classes['OpenPosition'                      ] = $dir.'model/OpenPosition';
$__classes['OpenPositionDAO'                   ] = $dir.'model/OpenPositionDAO';
$__classes['Signal'                            ] = $dir.'model/Signal';
$__classes['SignalDAO'                         ] = $dir.'model/SignalDAO';

unset($dir);
