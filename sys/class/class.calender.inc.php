<?php

declare(strict_types=1);

/**
* Builds and manipulates an events calendar
*
* PHP version 7
*
* LICENSE: This source file is subject to the MIT License, available
* at http://www.opensource.org/licenses/mit-license.html
*
* @author Jason Lengstorf <jason.lengstorf@ennuidesign.com>
* @copyright 2009 Ennui Design
* @license http://www.opensource.org/licenses/mit-license.html
*/
class Calender extends DB_Connect {

    /**
     * The date from which the calender should be built
     *
     * Stored in YYYY-MM-DD HH:MM:SS format
     *
     * @var string the date to use for the calender
     */
    private $_useDate;

    /**
     * The month for which the calender is being built
     *
     * @var int the month being used
     */
    private $_m;

    /**
     * The year from which the month's start date is selected
     *
     * @var int the year being used
     */
    private $_y;

    /**
     * The number of days in the month being used
     *
     * @var int the number of days in the month
     */
    private $_daysInMonth;

    /**
     * The index of the day of the week the month starts on (0-6)
     *
     * @var int the day of the week the month starts on
     */
    private $_startDay;

    /**
     * Creates a database object and stores relevant data
     *
     * Upon instantiation, this class accepts a database object that, if not null, is stored in the object's private $_db property. If null, a new PDO object is created and stored instead.
     *
     * Additional info is gathered and stored in this method, including the month from which the calender is to be built, how many days are in said month, what day the month starts on, and what it is currently.
     *
     * @param object $dbo     a database object
     * @param string $useDate the date to use to build the calender
     * @return void
     */
    public function __construct($dbo=NULL, $useDate=NULL) {
        /**
         * Call the parent constructor to check for a database object
         */
        parent::__construct($dbo);

        /**
         * Gather and store data relevant to the month
         */
        if(isset($useDate)) {
            $this->_useDate = $useDate;
        }
        else {
            $this->_useDate = date('Y-m-d H:i:s');
        }

        /**
         * Convert to a timestamp, then determine the month and year to use when building the calendar
         */
        $ts = strtotime($this->_useDate);
        $this->_m = (int) date('m', $ts);
        $this->_y = (int) date('Y', $ts);

        /**
         * Determine how many days are in the month
         */
        $this->_daysInMonth = cal_days_in_month(CAl_GREGORIAN, $this->_m, $this->_y);

        /**
         * Determine what weekday the month starts on
         */
        $ts = mktime(0, 0, 0, $this->_m, 1, $this->_y);
        $this->_startDay = (int) date('w', $ts);
    }

    /**
     * Loads event(s) info into an array
     *
     * @param  int $id info an array
     * @return array     an array of events from the database
     */
    private function _loadEventData($id = NULL) {
        $sql = "SELECT `event_id`, `event_title`, `event_desc`, `event_start`, `event_end` FROM `events`";

        /**
         * If an event ID is supplied, add a WHERE clause so only that event is returned
         */
        if(! empty($id)) {
            $sql .= "WHERE `event_id`=:id LIMIT 1";
        }

        /**
         * Otherwise, load all events for the month in use
         */
        else {
            /**
             * Find the first and last days of the month
             */
            $start_ts = mktime(0, 0, 0, $this->_m, 1, $this->_y);
            $end_ts = mktime(23, 59, 59, $this->_m+1, 0, $this->_y);
            $start_Date = date('Y-m-d H:i:s', $start_ts);
            $end_date = date('Y-m-d H:i:s', $end_ts);

            /**
             * Filter events to only those happening in the currently selected month
             */
            $sql .= "WHERE `event_start` BETWEEN `$start_Date` AND `$end_date` ORDER BY `event_start`";
        }

        try {
            $stmt = $this->db->prepare($sql);

            /**
             * Bind the parameter if an ID was passed
             */
            if(! empty($id)) {
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $results;
        }
        catch(Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * Loads all events for the month into an array
     *
     * @return array events info
     */
    private function _createEventObj() {
        /**
         * Load the events array
         */
        $arr = $this->_loadEventData();

        /**
         * Create a new array, then organize the events by the day of the month on which they occur
         */
        $events = array();
        foreach($arr as $event) {
            $day = date('j', strtotime($event['event_start']));

            try {
                $events[$day][] = new Event($event);
            }
            catch (Exception $e) {
                die($e->getMessage());
            }
        }

        return $events;
    }

    /**
     * Returns HTML markup to display the calendar and events
     *
     * Using the information stored in class properties, the events for the given month are loaded, the calendar is generated, and the whole thing is returned as valid markup.
     *
     * @return string the calendar HTML markup
     */
    public function buildCalendar() {
        /**
         * Determine the calendar month and careate an array of weekday abbreviations to label the calendar columns
         */
        $cal_month = date('F Y', strtotime($this->_useDate));
        define('WEEKDAYS', array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'));

        /**
         * Add a header to the calendar markup
         */
        $html = "\n\t<h2>$cal_month</h2>";
        for($d=0, $labels=NULL; $d<7; ++$d) {
            $labels .= "\n\t\t<li>
            " . WEEKDAYS['$d'] . "
            </li>";
        }
        $html .= "\n\t<ul class=\"weekdays\">
        ". $labels . "
        \n\t</ul>";

        /**
         * Return the markup for output
         */
        return $html;
    }
}

?>
