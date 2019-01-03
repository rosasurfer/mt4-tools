<?php
namespace rosasurfer\rost\model;

use rosasurfer\config\Config;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\UnimplementedFeatureException;

use rosasurfer\rost\FXT;
use rosasurfer\rost\Rost;
use rosasurfer\rost\RT;
use rosasurfer\rost\synthetic\Synthesizer;

use function rosasurfer\rost\fxTime;


/**
 * Represents a Rosatrader symbol.
 *
 * @method string          getType()            Return the instrument type (forex|metals|synthetic).
 * @method string          getName()            Return the symbol name, i.e. the actual symbol.
 * @method string          getDescription()     Return the symbol description.
 * @method int             getDigits()          Return the number of fractional digits of symbol prices.
 * @method bool            isAutoUpdate()       Whether automatic history updates are enabled.
 * @method string          getFormula()         Return a synthetic instrument's calculation formula (LaTeX).
 * @method DukascopySymbol getDukascopySymbol() Return the Dukascopy symbol mapped to this RosaTrader symbol.
 */
class RosaSymbol extends RosatraderModel {


    /** @var string */
    const TYPE_FOREX = 'forex';

    /** @var string */
    const TYPE_METAL = 'metals';

    /** @var string */
    const TYPE_SYNTHETIC = 'synthetic';


    /** @var string - instrument type (forex|metals|synthetic) */
    protected $type;

    /** @var string - symbol name */
    protected $name;

    /** @var string - symbol description */
    protected $description;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var bool - whether automatic history updates are enabled */
    protected $autoUpdate;

    /** @var string - LaTeX formula for calculation of synthetic instruments */
    protected $formula;

    /** @var string - start time of the available tick history (FXT) */
    protected $historyTicksStart;

    /** @var string - end time of the available tick history (FXT) */
    protected $historyTicksEnd;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyM1Start;

    /** @var string - end time of the available M1 history (FXT) */
    protected $historyM1End;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyD1Start;

    /** @var string - end time of the available D1 history (FXT) */
    protected $historyD1End;

    /** @var DukascopySymbol [transient] - the Dukascopy symbol mapped to this RosaTrader symbol */
    protected $dukascopySymbol;


    /**
     * Return the instrument's quote resolution (the value of 1 point).
     *
     * @return double
     */
    public function getPoint() {
        return 1/pow(10, $this->digits);
    }


    /**
     * Return the start time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryTicksStart($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyTicksStart) || $format=='Y-m-d H:i:s')
            return $this->historyTicksStart;
        return gmDate($format, strToTime($this->historyTicksStart.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryTicksEnd($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyTicksEnd) || $format=='Y-m-d H:i:s')
            return $this->historyTicksEnd;
        return gmDate($format, strToTime($this->historyTicksEnd.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryM1Start($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyM1Start) || $format=='Y-m-d H:i:s')
            return $this->historyM1Start;
        return gmDate($format, strToTime($this->historyM1Start.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryM1End($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyM1End) || $format=='Y-m-d H:i:s')
            return $this->historyM1End;
        return gmDate($format, strToTime($this->historyM1End.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryD1Start($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyD1Start) || $format=='Y-m-d H:i:s')
            return $this->historyD1Start;
        return gmDate($format, strToTime($this->historyD1Start.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as used for <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryD1End($format = 'Y-m-d H:i:s') {
        if (!isSet($this->historyD1End) || $format=='Y-m-d H:i:s')
            return $this->historyD1End;
        return gmDate($format, strToTime($this->historyD1End.' GMT'));
    }


    /**
     * Get the M1 history for the specified day.
     *
     * @param  int $fxDay - FXT timestamp
     *
     * @return array[] - If history for the specified day is not available an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a M1 bar as following:
     * <pre>
     * Array [
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (double),         // open value
     *     'high'  => (double),         // high value
     *     'low'   => (double),         // low value
     *     'close' => (double),         // close value
     *     'ticks' => (int),            // ticks or volume (if available)
     * ]
     * </pre>
     */
    public function getHistoryM1($fxDay) {
        $dataDir  = Config::getDefault()['app.dir.data'];
        $dataDir .= '/history/rost/'.$this->type.'/'.$this->name;
        $dir      = $dataDir.'/'.gmDate('Y/m/d', $fxDay);

        if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar'))
            return RT::readBarFile($file, $this);
        return [];
    }


    /**
     * Whether the symbol is a Forex symbol.
     *
     * @return bool
     */
    public function isForex() {
        return $this->type === self::TYPE_FOREX;
    }


    /**
     * Whether the symbol is a metals symbol.
     *
     * @return bool
     */
    public function isMetal() {
        return $this->type === self::TYPE_METAL;
    }


    /**
     * Whether the symbol is a synthetic symbol.
     *
     * @return bool
     */
    public function isSynthetic() {
        return $this->type === self::TYPE_SYNTHETIC;
    }


    /**
     * Whether the specified day is a trading day for the instrument.
     *
     * @param  int $fxTime - FXT timestamp
     *
     * @return bool
     */
    public function isTradingDay($fxTime) {
        if (!is_int($fxTime)) throw new IllegalTypeException('Illegal type of parameter $fxTime: '.getType($fxTime));

        $dow = (int) gmDate('w', $fxTime);
        return ($dow!=SATURDAY && $dow!=SUNDAY);
    }


    /**
     * Discard existing history and reload or recreate it.
     *
     * @return bool - success status
     */
    public function refreshHistory() {
        return false;
    }


    /**
     * Synchronize the existing history in the file system with the database.
     *
     * @return bool - success status
     */
    public function synchronizeHistory() {
        $dataDir  = Config::getDefault()['app.dir.data'];
        $dataDir .= '/history/rost/'.$this->type.'/'.$this->name;

        $startDate = $endDate = null;
        $firstFile = $lastFile = null;

        // find the oldest existing history file
        $years = glob($dataDir.'/[12][0-9][0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
        foreach ($years as $year) {
            $months = glob($year.'/[0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
            foreach ($months as $month) {
                $days = glob($month.'/[0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
                foreach ($days as $day) {
                    if (is_file($file=$day.'/M1.bin') || is_file($file.='.rar')) {
                        $startDate = strToTime(strRight($day, 10).' GMT');
                        $firstFile = $file;
                        break 3;
                    }
                }
            }
        }

        // iterate over the whole time range and track the last existing file
        if ($startDate) {
            $today = ($today=fxTime()) - $today%DAY;                                // 00:00 FXT of the current day
            for ($day=$startDate; $day < $today; $day+=1*DAY) {
                $dir = $dataDir.'/'.gmDate('Y/m/d', $day);
                if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar')) {      // TODO: handle missing data
                    $endDate  = $day;
                    $lastFile = $file;
                }
            }
        }

        // update the database
        if ($startDate != $this->getHistoryM1Start('U')) {
            echoPre('[Info]    '.$this->name.'  updating start time to '.($startDate ? gmDate('Y-m-d', $startDate) : '(empty)'));
            $this->historyM1Start = $startDate ? gmDate('Y-m-d H:i:s', $startDate) : null;
            $this->modified();
        }
        if ($endDate != $this->getHistoryM1End('U')) {
            echoPre('[Info]    '.$this->name.'  updating end time to '.($endDate ? gmDate('Y-m-d', $endDate) : '(empty)'));
            $this->historyM1End = $endDate ? gmDate('Y-m-d H:i:s', $endDate) : null;
            $this->modified();
        }
        if (!$this->isModified()) {
            echoPre('[Info]    '.$this->name.' ok');
        }
        else {
            $this->save();
        }
        return true;
    }


    /**
     * Update the symbol's history (atm only M1 history is processed).
     *
     * @return bool - success status
     */
    public function updateHistory() {
        $updatedTo  = (int) $this->getHistoryM1End('U');                            // end time FXT
        $updateFrom = $updatedTo ? $updatedTo - $updatedTo%DAY + 1*DAY : 0;         // 00:00 FXT of the first day to update
        $today      = ($today=fxTime()) - $today%DAY;                               // 00:00 FXT of the current day
        echoPre('[Info]    '.$this->getName().'  updating M1 history '.($updateFrom ? 'since '.gmDate('D, d-M-Y', $updateFrom) : 'from start'));

        if ($this->isSynthetic()) {
            $synthesizer = new Synthesizer($this);

            for ($day=$updateFrom; $day < $today; $day+=1*DAY) {
                if ($day && !$this->isTradingDay($day))                             // skip non-trading days
                    continue;

                $bars = $synthesizer->calculateQuotes($day);
                if (!$bars) return true(echoPre('[Error]   '.$this->getName().'  M1 history sources '.($day ? 'for '.gmDate('D, d-M-Y', $day).' ':'').'not available'));
                if (!$day) {                                                        // if $day is zero (no prices have been stored before)
                    $opentime = $bars[0]['time'];                                   // adjust it to the first available history
                    $day = $opentime - $opentime%DAY;
                    echoPre('[Info]    '.$this->getName().'  available M1 history starts at '.gmDate('D, d-M-Y', $day));
                }
            }

            /*
            $availableFrom = (int) $synthesizer->getHistoryM1Start('U');            // latest start time FXT of all components
            if (!$availableFrom)
                return false(echoPre('[Error]   '.$this->getName().': history of components not available'));
            if ($part = $availableFrom%DAY)
                $availableFrom -= $part - 1*DAY;                                    // 00:00 FXT of the first completely available day
            $updateFrom = max($availableFrom, $updateFrom);
            $today = ($today=fxTime()) - $today%DAY;                                // 00:00 FXT of the current day

            echoPre('updatedTo: '.$updatedTo.'  availableFrom: '.gmDate('Y-m-d H:i:s', $availableFrom).'  updateFrom: '.gmDate('Y-m-d H:i:s', $updateFrom).'  today: '.gmDate('Y-m-d H:i:s', $today));

            for ($day=$updateFrom; $day < $today; $day+=1*DAY) {
                $bars = $synthesizer->calculateQuotes($day);
                //store bars
                //store the new updatedTo value
            }
            */
        }
        else {
            // request price updates from a mapped symbol's data source
            throw new UnimplementedFeatureException('RosaSymbol::updateHistory() not yet implemented for regular instruments ('.$this->getName().')');
        }
        return true;
    }
}
