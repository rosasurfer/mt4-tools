<?php
namespace rosasurfer\rt\lib\synthetic;

use rosasurfer\core\Object;
use rosasurfer\rt\lib\IHistoryProvider;
use rosasurfer\rt\model\RosaSymbol;


/**
 * AbstractSynthesizer
 */
abstract class AbstractSynthesizer extends Object implements ISynthesizer {


    /** @var RosaSymbol */
    protected $symbol;

    /** @var string */
    protected $symbolName;

    /** @var string[][] - one or more sets of component names */
    protected $components = [];

    /** @var RosaSymbol[] - loaded symbols */
    protected $loadedSymbols = [];


    /**
     * {@inheritdoc}
     */
    public function __construct(RosaSymbol $symbol) {
        $this->symbol     = $symbol;
        $this->symbolName = $symbol->getName();
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartTick($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * {@inheritdoc}
     */
    public function getHistoryStartM1($format = 'Y-m-d H:i:s') {
        return '0';
    }


    /**
     * Calculate history for the specified bar period and time.
     *
     * @param  int  $period               - bar period identifier: PERIOD_M1 | PERIOD_M5 | PERIOD_M15 etc.
     * @param  int  $time                 - FXT time to return prices for. If 0 (zero) the oldest available history for the
     *                                      requested bar period is returned.
     * @param  bool $optimized [optional] - returned bar format (see notes)
     *
     * @return array - An empty array if history for the specified bar period and time is not available. Otherwise a
     *                 timeseries array with each element describing a single price bar as follows:
     *
     * <pre>
     * $optimized => FALSE (default):
     * ------------------------------
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (float),          // open value in real terms
     *     'high'  => (float),          // high value in real terms
     *     'low'   => (float),          // low value in real terms
     *     'close' => (float),          // close value in real terms
     *     'ticks' => (int),            // number of synthetic ticks
     * )
     *
     * $optimized => TRUE:
     * -------------------
     * Array(
     *     'time'  => (int),            // bar open time in FXT
     *     'open'  => (int),            // open value in point
     *     'high'  => (int),            // high value in point
     *     'low'   => (int),            // low value in point
     *     'close' => (int),            // close value in point
     *     'ticks' => (int),            // number of synthetic ticks
     * )
     * </pre>
     */
    abstract public function calculateHistory($period, $time, $optimized = false);


    /**
     * Get the components required to calculate the synthetic instrument.
     *
     * @param string[] $names
     *
     * @return RosaSymbol[] - array of symbols or an empty value if at least one of the symbols was not found
     */
    protected function getComponents(array $names) {
        $symbols = [];
        foreach ($names as $name) {
            if (isset($this->loadedSymbols[$name])) {
                $symbol = $this->loadedSymbols[$name];
            }
            else if (!$symbol = RosaSymbol::dao()->findByName($name)) {
                echoPre('[Error]   '.str_pad($this->symbolName, 6).'  required symbol '.$name.' not available');
                return [];
            }
            $symbols[] = $symbol;
        }
        return $symbols;
    }


    /**
     * Look-up the oldest available common history for all specified symbols.
     *
     * @param RosaSymbol[] $symbols
     *
     * @return int - history start time for all symbols (FXT) or 0 (zero) if no common history is available
     */
    protected function findCommonHistoryStartM1(array $symbols) {
        $day = 0;
        foreach ($symbols as $symbol) {
            $historyStart = (int) $symbol->getHistoryStartM1('U');      // 00:00 FXT of the first stored day
            if (!$historyStart) {
                echoPre('[Error]   '.str_pad($this->symbolName, 6).'  required M1 history for '.$symbol->getName().' not available');
                return 0;                                               // no history stored
            }
            $day = max($day, $historyStart);
        }
        echoPre('[Info]    '.str_pad($this->symbolName, 6).'  available M1 history for all sources starts at '.gmdate('D, d-M-Y', $day));
        return $day;
    }


    /**
     * Get the history of all components for the specified day.
     *
     * @param RosaSymbol[] $symbols
     * @param int          $day
     *
     * @return array[] - array of history timeseries per symbol:
     *
     * <pre>
     * Array(
     *     {symbol-name} => {timeseries},
     *     {symbol-name} => {timeseries},
     *     ...
     * )
     * </pre>
     */
    protected function getComponentsHistory($symbols, $day) {
        $quotes = [];
        foreach ($symbols as $symbol) {
            $name = $symbol->getName();
            if (!$quotes[$name] = $symbol->getHistoryM1($day)) {
                echoPre('[Error]   '.str_pad($this->symbolName, 6).'  required '.$name.' history for '.gmdate('D, d-M-Y', $day).' not available');
                return [];
            }
        }
        return $quotes;
    }


    /**
     * Implementation of {@link IHistoryProvider}::getHistory().
     *
     * {@inheritdoc}
     */
    public final function getHistory($period, $time, $optimized = false) {
        return $this->calculateHistory($period, $time, $optimized);
    }
}
