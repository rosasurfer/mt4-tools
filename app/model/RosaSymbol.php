<?php
namespace rosasurfer\rt\model;

use rosasurfer\console\io\Output;
use rosasurfer\exception\IllegalTypeException;
use rosasurfer\exception\RuntimeException;
use rosasurfer\process\Process;

use rosasurfer\rt\lib\IHistoryProvider;
use rosasurfer\rt\lib\RT;
use rosasurfer\rt\lib\dukascopy\Dukascopy;
use rosasurfer\rt\lib\synthetic\DefaultSynthesizer;
use rosasurfer\rt\lib\synthetic\SynthesizerInterface as Synthesizer;

use function rosasurfer\rt\fxTime;
use function rosasurfer\rt\isGoodFriday;
use function rosasurfer\rt\isHoliday;
use function rosasurfer\rt\isWeekend;

use const rosasurfer\rt\PERIOD_M1;


/**
 * Represents a Rosatrader symbol.
 *
 * @method string          getType()            Return the instrument type (forex|metals|synthetic).
 * @method int             getGroup()           Return the symbol's group id.
 * @method string          getName()            Return the symbol name, i.e. the actual symbol.
 * @method string          getDescription()     Return the symbol description.
 * @method int             getDigits()          Return the number of fractional digits of symbol prices.
 * @method int             getUpdateOrder()     Return the symbol's update order value.
 * @method string          getFormula()         Return a synthetic instrument's calculation formula (LaTeX).
 * @method DukascopySymbol getDukascopySymbol() Return the {@link DukascopySymbol} mapped to this Rosatrader symbol.
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

    /** @var int - grouping id for view separation */
    protected $group;

    /** @var string - symbol name */
    protected $name;

    /** @var string - symbol description */
    protected $description;

    /** @var int - number of fractional digits of symbol prices */
    protected $digits;

    /** @var int - on multi-symbol updates required symbols are updated before dependent ones */
    protected $updateOrder = 9999;

    /** @var string - LaTeX formula for calculation of synthetic instruments */
    protected $formula;

    /** @var string - start time of the available tick history (FXT) */
    protected $historyStartTick;

    /** @var string - end time of the available tick history (FXT) */
    protected $historyEndTick;

    /** @var string - start time of the available M1 history (FXT) */
    protected $historyStartM1;

    /** @var string - end time of the available M1 history (FXT) */
    protected $historyEndM1;

    /** @var string - start time of the available D1 history (FXT) */
    protected $historyStartD1;

    /** @var string - end time of the available D1 history (FXT) */
    protected $historyEndD1;

    /** @var DukascopySymbol [transient] - the Dukascopy symbol mapped to this Rosatrader symbol */
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
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartTick) || $format=='Y-m-d H:i:s')
            return $this->historyStartTick;
        return gmdate($format, strtotime($this->historyStartTick.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored tick history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndTick($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyEndTick) || $format=='Y-m-d H:i:s')
            return $this->historyEndTick;
        return gmdate($format, strtotime($this->historyEndTick.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartM1) || $format=='Y-m-d H:i:s')
            return $this->historyStartM1;
        return gmdate($format, strtotime($this->historyStartM1.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored M1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndM1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyEndM1) || $format=='Y-m-d H:i:s')
            return $this->historyEndM1;
        return gmdate($format, strtotime($this->historyEndM1.' GMT'));
    }


    /**
     * Return the start time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - start time based on an FXT timestamp
     */
    public function getHistoryStartD1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyStartD1) || $format=='Y-m-d H:i:s')
            return $this->historyStartD1;
        return gmdate($format, strtotime($this->historyStartD1.' GMT'));
    }


    /**
     * Return the end time of the symbol's stored D1 history (FXT).
     *
     * @param  string $format [optional] - format as accepted by <tt>date($format, $timestamp)</tt>
     *
     * @return string - end time based on an FXT timestamp
     */
    public function getHistoryEndD1($format = 'Y-m-d H:i:s') {
        if (!isset($this->historyEndD1) || $format=='Y-m-d H:i:s')
            return $this->historyEndD1;
        return gmdate($format, strtotime($this->historyEndD1.' GMT'));
    }


    /**
     * Get the M1 history for a given day.
     *
     * @param  int $time - FXT timestamp
     *
     * @return array[] - If history for the specified day is not available an empty array is returned. Otherwise a timeseries
     *                   array is returned with each element describing a M1 bar as follows:
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
    public function getHistoryM1($time) {
        $storageDir  = $this->di('config')['app.dir.storage'];
        $storageDir .= '/history/rosatrader/'.$this->type.'/'.$this->name;
        $dir         = $storageDir.'/'.gmdate('Y/m/d', $time);

        if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar'))
            return RT::readBarFile($file, $this);

        /** @var Output $output */
        $output = $this->di(Output::class);
        $output->error('[Error]   '.str_pad($this->name, 6).'  Rosatrader history for '.gmdate('D, d-M-Y', $time).' not found');
        return [];
    }


    /**
     * Whether the symbol is a Forex symbol.
     *
     * @return bool
     */
    public function isForex() {
        return ($this->type === self::TYPE_FOREX);
    }


    /**
     * Whether the symbol is a metals symbol.
     *
     * @return bool
     */
    public function isMetal() {
        return ($this->type === self::TYPE_METAL);
    }


    /**
     * Whether the symbol is a synthetic symbol.
     *
     * @return bool
     */
    public function isSynthetic() {
        return ($this->type === self::TYPE_SYNTHETIC);
    }


    /**
     * Whether a time is a trading day for the instrument in the standard timezone of the timestamp.
     *
     * @param  int $time - Unix or FXT timestamp
     *
     * @return bool
     */
    public function isTradingDay($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));

        return ($time && !isWeekend($time) && !$this->isHoliday($time));
    }


    /**
     * Whether a time is on a Holiday for the instrument in the standard timezone of the timestamp.
     *
     * @param  int $time - Unix or FXT timestamp
     *
     * @return bool
     */
    public function isHoliday($time) {
        if (!is_int($time)) throw new IllegalTypeException('Illegal type of parameter $time: '.gettype($time));
        if (!$time)
            return false;

        if (isHoliday($time))                           // check for common Holidays
            return true;
        if ($this->isMetal() && isGoodFriday($time))    // check for specific Holidays
            return true;
        return false;
    }


    /**
     * Show history status information.
     *
     * @return bool - success status
     */
    public function showHistoryStatus() {
        /** @var Output $output */
        $output     = $this->di(Output::class);
        $start      = $this->getHistoryStartM1('D, d-M-Y');
        $end        = $this->getHistoryEndM1  ('D, d-M-Y');
        $paddedName = str_pad($this->name, 6);

        if ($start) $output->out('[Info]    '.$paddedName.'  M1 local history from '.$start.' to '.$end);
        else        $output->out('[Info]    '.$paddedName.'  M1 local history empty');
        return true;
    }


    /**
     * Synchronize start/end times in the database with the files in the file system.
     *
     * @return bool - success status
     */
    public function synchronizeHistory() {
        /** @var Output $output */
        $output      = $this->di(Output::class);
        $storageDir  = $this->di('config')['app.dir.storage'];
        $storageDir .= '/history/rosatrader/'.$this->type.'/'.$this->name;
        $paddedName  = str_pad($this->name, 6);
        $startDate   = $endDate = null;
        $missing     = [];

        // find the oldest existing history file
        $years = glob($storageDir.'/[12][0-9][0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
        foreach ($years as $year) {
            $months = glob($year.'/[0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
            foreach ($months as $month) {
                $days = glob($month.'/[0-9][0-9]', GLOB_ONLYDIR|GLOB_NOESCAPE|GLOB_ERR) ?: [];
                foreach ($days as $day) {
                    if (is_file($file=$day.'/M1.bin') || is_file($file.='.rar')) {
                        $startDate = strtotime(strRight($day, 10).' GMT');
                        break 3;
                    }
                }
            }
        }

        // iterate over the whole time range and check existing files
        if ($startDate) {                                                               // 00:00 FXT of the first day
            $today     = ($today=fxTime()) - $today%DAY;                                // 00:00 FXT of the current day
            $delMsg    = '[Info]    '.$paddedName.'  deleting non-trading day M1 file: ';
            $yesterDay = fxTime() - fxTime()%DAY - DAY;

            $missMsg = function($missing) use ($output, $paddedName, $yesterDay) {
                if ($misses = sizeof($missing)) {
                    $first     = first($missing);
                    $last      = last($missing);
                    $output->out('[Error]   '.$paddedName.'  '.$misses.' missing history file'.pluralize($misses)
                                             .($misses==1 ? ' for '.gmdate('D, Y-m-d', $first)
                                                          : ' from '.gmdate('D, Y-m-d', $first).' until '.($last==$yesterDay? 'now' : gmdate('D, Y-m-d', $last))));
                }
            };

            for ($day=$startDate; $day < $today; $day+=1*DAY) {
                $dir = $storageDir.'/'.gmdate('Y/m/d', $day);

                if ($this->isTradingDay($day)) {
                    if (is_file($file=$dir.'/M1.bin') || is_file($file.='.rar')) {
                        $endDate = $day;
                        if ($missing) {
                            $missMsg($missing);
                            $missing = [];
                        }
                    }
                    else {
                        $missing[] = $day;
                    }
                }
                else {
                    is_file($file=$dir.'/M1.bin'    ) && true($output->out($delMsg.RT::relativePath($file))) && unlink($file);
                    is_file($file=$dir.'/M1.bin.rar') && true($output->out($delMsg.RT::relativePath($file))) && unlink($file);
                }
            }
            $missing && $missMsg($missing);
        }

        // update the database
        if ($startDate != $this->getHistoryStartM1('U')) {
            $output->out('[Info]    '.$paddedName.'  updating start time to '.($startDate ? gmdate('Y-m-d', $startDate) : '(empty)'));
            $this->historyStartM1 = $startDate ? gmdate('Y-m-d H:i:s', $startDate) : null;
            $this->modified();
        }

        if ($endDate) {
            $endDate += 23*HOURS + 59*MINUTES;          // adjust to the last minute as the database always holds full days
        }
        if ($endDate != $this->getHistoryEndM1('U')) {
            $output->out('[Info]    '.$paddedName.'  updating end time to: '.($endDate ? gmdate('D, Y-m-d H:i', $endDate) : '(empty)'));
            $this->historyEndM1 = $endDate ? gmdate('Y-m-d H:i:s', $endDate) : null;
            $this->modified();
        }
        $this->save();

        !$missing && $output->out('[Info]    '.$paddedName.'  '.($startDate ? 'ok':'empty'));
        $output->out('---------------------------------------------------------------------------------------');
        return true;
    }


    /**
     * Update the symbol's history.
     *
     * @param  int $period [optional]
     *
     * @return bool - success status
     */
    public function updateHistory($period = PERIOD_M1) {
        /** @var Output $output */
        $output = $this->di(Output::class);

        $provider = $this->getHistoryProvider();
        if (!$provider) return false($output->error('[Error]   '.str_pad($this->name, 6).'  no history provider found'));

        $historyEnd = (int) $this->getHistoryEndM1('U');
        $updateFrom = $historyEnd ? $historyEnd + 1*DAY : 0;                        // the next day
        $output->out('[Info]    '.str_pad($this->name, 6).'  updating M1 history since '.gmdate('D, d-M-Y', $historyEnd));

        for ($day=$updateFrom, $now=fxTime(); $day < $now; $day+=1*DAY) {
            if (!$this->isTradingDay($day))                                         // skip non-trading days
                continue;
            $bars = $provider->getHistory($period, $day);
            if (!$bars) return false($output->error('[Error]   '.str_pad($this->name, 6).'  M1 history sources'.($day ? ' for '.gmdate('D, d-M-Y', $day) : '').' not available'));
            RT::saveM1Bars($bars, $this);                                           // store the quotes

            if (!$day) {                                                            // if $day is zero (full update since start)
                $day = $bars[0]['time'];                                            // adjust it to the first available history
                $this->historyStartM1 = gmdate('Y-m-d H:i:s', $bars[0]['time']);    // update metadata *after* history was successfully saved
            }
            $this->historyEndM1 = gmdate('Y-m-d H:i:s', $bars[sizeof($bars)-1]['time']);
            $this->modified()->save();                                              // update the database
            Process::dispatchSignals();                                             // process signals
        }

        $output->out('[Ok]      '.$this->name);
        return true;
    }


    /**
     * Return a {@link IHistoryProvider} for the symbol.
     *
     * @return IHistoryProvider|null
     */
    public function getHistoryProvider() {
        if ($this->isSynthetic())
            return $this->getSynthesizer();
        return $this->getDukascopySymbol();
    }


    /**
     * Look-up and instantiate a {@link Synthesizer} to calculate quotes of a synthetic instrument.
     *
     * @return Synthesizer
     */
    protected function getSynthesizer() {
        if (!$this->isSynthetic()) throw new RuntimeException('Cannot create Synthesizer for non-synthetic instrument');

        $customClass = strLeftTo(Synthesizer::class, '\\', -1).'\\index\\'.$this->name;
        if (is_class($customClass) && is_a($customClass, Synthesizer::class, $allowString=true))
            return new $customClass($this);
        return new DefaultSynthesizer($this);
    }
}
