<?php

date_default_timezone_set('America/Detroit');
ini_set("allow_url_fopen", 1);

class Patient
{
    protected $_accountIsComplete = false;
    protected $_dateOfBirth;
    protected $_age;
    protected $_serviceDate;
    protected $_patientId;

    /**
     * Patient constructor.
     * @param $dateOfBirth
     * @param $serviceDate
     * @param $patientId
     * @throws Exception
     */
    function __construct($dateOfBirth, $patientId, $serviceDate)
    {
        //we throw InvalidArgument if the data does not match expected input
        try {
            $this->_dateOfBirth = $this->parseDate($dateOfBirth);
            $this->_serviceDate = $this->parseDate($serviceDate);
            $this->_patientId = $this->validateId($patientId);
        } catch (InvalidArgumentException $e) {
            throw new Exception("Failed to parse required data");
        }
        $this->setCompletionStatus();
        $this->setAge();
    }


    /**
     * First attempts to parse the date using date_parse, if it fails
     * It will assume the type is excel and attempt to parse it.
     * Throws InvalidArgumentException if there is a failure to parse.
     * @param $strDate
     * @throws InvalidArgumentException
     * @return array
     */
    private function parseDate($strDate)
    {
        //Elected to use the associative array from date_parse
        $date = date_parse($strDate);
        //if month is true, it should always have been parsed correctly with date_parse
        if ($date['month']) {
            return $date;
        }
        $result = $this->getDateFromExcel($strDate);
        //Could be replaced by trying to determine if it's in excel format before trying to convert
        if (!$result['error']) {
            return $result['date'];
        } else {
            throw new InvalidArgumentException();
        }
    }

    /**
     * Ensures the ID only contains a leading optional
     * and digits
     * @param $id
     * @throws  InvalidArgumentException
     * @return string
     */
    private function validateId($id)
    {
        //Optional * only digits
        if (preg_match('/^\*?\d+$/', $id)) {
            return $id;
        } else {
            throw new InvalidArgumentException();
        }
    }

    /**
     * Converts excel serial date to unix timestamp
     * @param $strExcelDate
     * @return array
     */
    private function getDateFromExcel($strExcelDate)
    {
        //Conversion found at this link:
        //stackoverflow.com/questions/11172644/convert-the-full-excel-date-serial-format-to-unix-timestamp
        $convertedDate = ($strExcelDate - 25569) * 86400;

        $date = date_parse(date('r', $convertedDate));
        $error = ($date['month'] ? false : true);

        return array("date" => $date, "error" => $error);
    }

    /**
     * @return array
     */
    public function getDateOfBirth()
    {
        return $this->_dateOfBirth;
    }

    /**
     * @return array
     */
    public function getServiceDate()
    {
        return $this->_serviceDate;
    }

    /**
     * @return string
     */
    public function getPatientId()
    {
        return $this->_patientId;
    }

    /**
     * @return bool
     */
    public function isAccountComplete()
    {
        return $this->_accountIsComplete;
    }

    /**
     * Called from constructor
     * Sets class wide completion var
     */
    private function setCompletionStatus()
    {
        //incomplete are denoted with a *
        $this->_accountIsComplete = ($this->_patientId[0] == '*' ? false : true);
    }

    /**
     * @return array
     */
    public function getAge()
    {
        return $this->_age;
    }

    /**
     * Called from constructor
     * Sets class wide age var
     */
    private function setAge()
    {
        //convert days to julian days and find the difference.
        $dateOfBirth = gregoriantojd($this->_dateOfBirth['month'], $this->_dateOfBirth['day'], $this->_dateOfBirth['year']);
        $parsedCurrentDate = date_parse(date('Y-m-d H:i:s'));
        $currentDate = gregoriantojd($parsedCurrentDate['month'], $parsedCurrentDate['day'], $parsedCurrentDate['year']);

        $this->_age = (int)(($currentDate - $dateOfBirth) / 365);
    }

    /**
     * Uses date('w') to get day of week
     * @return string
     */
    public function getServiceDayOfWeek()
    {
        //for use in converting to days of week
        $day = date('l',
            mktime($this->_serviceDate['hour'],
                $this->_serviceDate['minute'],
                $this->_serviceDate['second'],
                $this->_serviceDate['month'],
                $this->_serviceDate['day'],
                $this->_serviceDate['year']));
        return $day;
    }
}

/**
 * Fetches JSON from URL using file_get_contents
 * Requires ini change.
 * @param $url
 * @return array
 */
function fetchJsonFromEndpoint($url)
{
    $json = file_get_contents($url);
    return json_decode($json, true);
}

/**
 * Builds completed patient json
 * @param Patient $patient
 * @return array
 */
function buildPatientJson(Patient $patient)
{
    return array('age' => $patient->getAge(),
        'isOver21' => ($patient->getAge() >= 21 ? 1 : 0),
        'isComplete' => $patient->isAccountComplete(),
        'serviceDayOfWeek' => $patient->getServiceDayOfWeek());
}

/**
 * Test function for output
 * @return string
 * @throws Exception
 */
function run()
{
    $api = 'removed';
    $json = fetchJsonFromEndpoint($api);
    $patientDataArray = [];
    foreach ($json as $patientData) {
        $patient = null;
        try {
            $patient = new Patient($patientData["dob"], $patientData["patient-id"], $patientData["service-date"]);
        } catch (Exception $e) {
            continue;
        }
        $patientDataArray[] = buildPatientJson($patient);
    }
    return json_encode(array("patients" => $patientDataArray));
}

var_dump(run());

//use PHPUnit\Framework\TestCase;
//
//class ApiTest extends TestCase {
//    function fetchJsonFromEndpoint($url) {
//        $json = file_get_contents($url);
//        return json_decode($json);
//    }
//    public function testApiResponse() {
//        $api = 'http://portal.glpg.net/tom/';
//        $json = $this->fetchJsonFromEndpoint($api);
//        $this->assertArrayHasKey('patients', $json);
//    }
//}
