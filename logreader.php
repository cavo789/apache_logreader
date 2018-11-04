<?php

/**
 * Author: AVONTURE Christophe.
 *
 * Description: Apache log reader
 *
 * Interface to allow easier reading of a .log file of Apache
 * 
 * GitHub repository: https://github.com/cavo789/apache_logreader
 */

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

define('DEBUG', true);

define('REPO', 'https://github.com/cavo789/apache_logreader');

class logReader
{
    public $_tooltip           = '<span data-toggle="popover" title="Tip" data-content="%s">%s</span>';
    public $_tooltipNumberHost = '<span data-toggle="popover" title="Number of connections made by this host" data-content="<strong>%s</strong>" class="glyphicon glyphicon-eye-open"></span>';
    public $_tooltipNumberURL  = '<span data-toggle="popover" title="Number of connections made on this URL " data-content="<strong>%s</strong>" class="glyphicon glyphicon-eye-open"></span>';
    public $_iconURLDecode     = '<span data-toggle="popover" title="Decoded url" data-content="%s" class="glyphicon glyphicon-screenshot"></span>';
    public $_urlFilter         = '<span data-task="url" data-url="%s" data-toggle="popover" title="Filter" data-content="<strong>Click here to restart the processing of the logfile but only for lines concerning %s</strong>" class="glyphicon glyphicon-filter"></span>';
    public $_urlMethod         = '<span data-task="url" data-url="%s" data-toggle="popover" title="Filter" data-content="<strong>Filtrer sur la méthode [%s]</strong>" class="glyphicon glyphicon-filter"></span>';
    public $_urlRemoveFilter   = '<span data-task="url" data-url="%s" data-toggle="popover" title="<strong style=\'color:red;\'>Remove filter</strong>" data-content="<strong style=\'color:red;\'>Click here to remove this filter</strong>" class="glyphicon glyphicon-remove"></span>';
    public $_urlStatus         = '<span data-task="url" data-url="%s" data-toggle="popover" title="Filter" data-content="<strong>Filtrer sur le statut [%s]</strong>" class="glyphicon glyphicon-filter"></span>';
    public $_NotBlocked        = '&nbsp;&nbsp;<span class="notBlockedlogReader label label-danger">This request wasn\'t blocked. If this is an attack, the logReader\'s rules can therefore be improved.</span>';
    public $_HasBeenHacked     = '&nbsp;&nbsp;<span class="notBlockedlogReader label label-danger">Status code 200 means that the server has successfully processed the request => please verify carefully !!!.</span>';

    public $aeSession = null;

    public $json     = null;
    public $root     = null;
    public $template = null;
    public $logFile  = null;

    public $entry         = null;
    public $arrHTTPErrors = [];

    // Entry that are not (yet) taken as errors, skipped, ...
    // These entries are displayed on the html page
    public $arrLogEntry = [];

    public $arrMonitoredHTTPErrors = [];
    public $arrMethodPOSTLines     = [];
    public $arrMethodPUTLines      = [];
    public $arrMethodTRACELines    = [];
    public $arrMethodHEADLines     = [];
    public $arrComponents          = [];
    public $arrMonitoredURLs       = [];
    public $arrHoneyPot            = [];
    public $arrGone                = [];
    public $arrHTTPCodes           = [];
    public $arrBadLines            = [];
    public $arrErrorsLines         = [];
    public $arrRemoteHost          = [];
    public $arrURLs                = [];

    public $SettingsFullInfo = false;

    public $totalRow          = 0;
    public $totalProcessedRow = 0;
    public $bad               = 0;
    public $skipped           = 0;

    // Time taken
    public $startProcess        = 0;
    public $endProcess          = 0;
    public $startDisplay        = 0;
    public $endDisplay          = 0;
    public $canBeSkippedSeconds = 0;

    public $errorHandler = null;

    public $_arrfilterDate       = null;
    public $_arrfilterMethod     = null;
    public $_arrfilterReferrer   = null;
    public $_arrfilterRemoteHost = null;
    public $_arrfilterStatus     = null;
    public $_arrfilterUserAgent  = null;
    public $_arrfilterURL        = null;

    public function __construct($logFile)
    {
        $this->root    =dirname(__FILE__);
        $this->template=$this->root . '/templates/';

        // Include the helper
        require_once $this->root . '/helpers/functions.php';
        require_once $this->root . '/helpers/session.php';

        if (('' == trim($logFile)) || ((is_file($logFile)) && (file_exists($logFile)))) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 1200);

            $this->logFile=$logFile;

            if (is_readable($this->root . '/logreader.json')) {
                $this->json = json_decode(file_get_contents($this->root . '/logreader.json'), true);

                if ('true' === self::getJSONValue('settings', 'debug', null, null, 'false')) {
                    error_reporting(E_ALL);
                    $this->errorHandler = set_error_handler('logReaderFct::logreader_ErrorHandler');
                }

                $this->arrHTTPErrors         =['403'=>'Code 403 (not catched as an attack)', '404'=>'Code 404', '500'=>'Code 500 (not catched as an attack)'];
                $this->arrMonitoredHTTPErrors=[];

                foreach ($this->arrHTTPErrors as $key => $value) {
                    $this->arrMonitoredHTTPErrors[]=$key;
                }

                $this->arrMethodPOSTLines =[];
                $this->arrMethodPUTines   =[];
                $this->arrMethodTRACELines=[];
                $this->arrMethodHEADLines =[];

                // Get an associative array with the list of HTTP code and status
                $arr=self::getJSONValue('HTTPStatus', null, null, null, null);
                foreach ($arr as $key => $value) {
                    $this->arrHTTPCodess[key($value)]=$value[key($value)];
                }

                $this->SettingsFullInfo=('true' == self::getJSONValue('settings', 'full_info', null, null, 'false'));

                return true;
            } else {
                die('logreader.json not found');
            }
        } else {
            die('The file ' . $logFile . ' doesn\'t exists<br/><a href="' . logReaderFct::GetCurrentURL($path = true, $querystring = false) . '">0. Back</a>');
        }
    } // function __construct()

    public function __destruct()
    {
        // Restore the normal PHP error handler
        restore_error_handler();

        unset($this->json, $this->root, $this->arrBadLines, $this->arrSkipLines, $this->arrHTTPErrors, $this->arrMonitoredHTTPErrors, $this->arrMethodPOSTLines, $this->arrMethodPUTLines, $this->arrMethodTRACELines, $this->arrMethodHEADLines, $this->arrMonitoredURLs, $this->arrHoneyPot, $this->arrComponents, $this->arrGone);
    }

    public function setFilters($arrFilters)
    {
        if (isset($arrFilters)) {
            foreach ($arrFilters as $key => $value) {
                switch ($key) {
                    case 'date': {
                        // Get the interval : if an enddate has been specified, get the range
                        // f.i. 2015-12-01;2015-12-02;2015-12-03;2015-12-04;2015-12-05;2015-12-06
                        // if date was 2015-12-01 and enddate was 2015-12-06

                        if (isset($arrFilters['enddate']) && ($arrFilters['enddate'] != $arrFilters['date'])) {
                            $from     =ltrim(trim($arrFilters['date'], ';'));
                            $till     =ltrim(trim($arrFilters['enddate'], ';'));
                            $iDateFrom=mktime(1, 0, 0, substr($from, 5, 2), substr($from, 8, 2), substr($from, 0, 4));
                            $iDateTo  =mktime(1, 0, 0, substr($till, 5, 2), substr($till, 8, 2), substr($till, 0, 4));

                            if ($iDateTo >= $iDateFrom) {
                                while ($iDateFrom < $iDateTo) {
                                    $iDateFrom += 86400; // add 24 hours
                                    $value .= ';' . date('Y-m-d', $iDateFrom);
                                }
                            } // if()
                        } // if(isset())

                        $this->_arrfilterDate=explode(';', ltrim(trim($value), ';'));

                        break;
                    }
                    case 'method':
                        $this->_arrfilterMethod=explode('§', ltrim(trim($value), '§'));

                        break;
                    case 'remoteHost':
                        $this->_arrfilterRemoteHost=explode('§', ltrim(trim($value), '§'));

                        break;
                    case 'referrer':
                        $this->_arrfilterReferrer=explode('§', ltrim(trim($value), '§'));

                        break;
                    case 'status':
                        $this->_arrfilterStatus=explode('§', ltrim(trim($value), '§'));

                        break;
                    case 'URL':
                        $this->_arrfilterURL=explode('§', ltrim(trim($value), '§'));

                        break;
                    case 'userAgent':
                        $this->_arrfilterUserAgent=explode('§', ltrim(trim($value), '§'));

                        break;
                }
            }
        }

        return true;
    }

    /**
     * Run the processing of the file (called by the 3. Process action).
     *
     * @return bool
     */
    public function ProcessFile()
    {
        $this->aeSession=logReaderSession::getInstance(true);

        if ((is_readable($this->logFile)) && is_file(($this->logFile))) {
            $stopProcess=false;

            $this->startProcess = microtime(true);

            $log='';

            $file  = file_get_contents(realpath($this->root . '/' . $this->logFile));
            $lines = explode("\n", $file);

            $this->totalRow         =0;           // Number of lines in the file
            $this->totalProcessedRow=0;  // Counter, number of processed lines

            foreach ($lines as $line) {
                if (!$line) {
                    continue;
                }

                $this->totalRow++;

                // $stopProcess is set on true when lines shouldn't no more be processed.  The foreach will just
                // continue so we can count the number of lines in the logfile and only for that purpose.
                if (true === $stopProcess) {
                    continue;
                }

                // Process the entry from the Apache log and convert it into an associative array
                $this->entry=logReaderFct::processApacheLogLine($line);

                //if(!isset($this->entry['url'])) { $this->skipped++; $this->entry['logReader']['skip']=true; continue; }

                // Check if the url should be ignored i.e. not processed at all by the script.
                // This is order to speed up the process

                if (self::isIgnored()) {
                    $this->skipped++;

                    continue;
                }

                // -----------------------------------
                // Apply filters - start

                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterDate, $this->entry, 'date'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }

                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterMethod, $this->entry, 'method'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }

                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterURL, $this->entry, 'url'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }

                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterReferrer, $this->entry, 'referrer'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }

                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterUserAgent, $this->entry, 'userAgent'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }

                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterRemoteHost, $this->entry, 'remoteHost'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }
                if (false !== ($found=logReaderFct::applyFilter($this->_arrfilterStatus, $this->entry, 'status'))) {
                    if (-1 == $found) {
                        $this->skipped++;

                        continue;
                    }
                }

                // Apply filters - end
                // -----------------------------------

                $this->arrLogEntry[]=$this->entry;

                $this->totalProcessedRow++;

                // Is it a request to an url from the honey pot
                $isHoneyPot                               =false;
                list($isHoneyPot, $found, $reason, $regex)=self::HoneyPot();

                if (true === $isHoneyPot) {
                    // Don't mention the remoteHost again if already blacklisted
                    $arr=self::getJSONValue('bad', 'remoteHost', 'full', null, null);
                    if (null != $arr) {
                        if (isset($this->entry['remoteHost'])) {
                            if (!(in_array($this->entry['remoteHost'], $arr))) {
                                if (!isset($this->arrHoneyPot[$this->entry['url']])) {
                                    $this->arrHoneyPot[$this->entry['url']]=['value'=>$found, 'reason'=>$reason, 'regex'=>$regex, 'host'=>[]];
                                }
                                if (!isset($this->arrHoneyPot[$this->entry['url']]['host'][$this->entry['remoteHost']])) {
                                    $this->arrHoneyPot[$this->entry['url']]['host'][$this->entry['remoteHost']]=['#'=>1];
                                } else {
                                    $this->arrHoneyPot[$this->entry['url']]['host'][$this->entry['remoteHost']]['#']++;
                                }
                            }
                        }
                    }
                } // if ($isHoneyPot===true){

                // ----------------------------------------------------------------------------

                // Keep logging of each individual remote
                if (isset($this->entry['remoteHost'])) {
                    if (!isset($this->arrRemoteHost[$this->entry['remoteHost']])) {
                        $this->arrRemoteHost[$this->entry['remoteHost']]=['#'=>1, 'comment'=>''];
                    } else {
                        $this->arrRemoteHost[$this->entry['remoteHost']]['#']++;
                    }
                }

                // Keep logging of each individual url
                if (isset($this->entry['url'])) {
                    if (!isset($this->arrURLs[$this->entry['url']])) {
                        $this->arrURLs[$this->entry['url']]=['#'=>1, 'comment'=>''];
                    } else {
                        $this->arrURLs[$this->entry['url']]['#']++;
                    }
                }

                // First thing to do : based on the json rules, determine if the entry contains a possible attack
                $isBad=self::isBad();
                if (true === $isBad) {
                    $this->arrBadLines[]=$this->entry;
                }

                // If not flagged as bad request, is it an old url (now gone)
                list($isGone, $found, $reason, $regex)=self::isGone();
                if ($isGone) {
                    $this->entry['logReader']['Gone'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['url'=>$found]];
                    $this->arrGone[]                   =$this->entry;
                }

                // If not flagged as bad request, can we skip this url ? This is the case for urls that are supposed safe based on the json settings
                if ((false == $isGone) && (false == $isBad)) {
                    $skip=self::canbeSkipped();

                    if (false == $skip) {
                        if (isset($this->entry['status'])) {
                            if (in_array($this->entry['status'], $this->arrMonitoredHTTPErrors)) {
                                // Don't output errors, will be done later on.  Just record the entry
                                if (true != $isGone) {
                                    $this->arrErrorsLines[$this->entry['status']][]=$this->entry;
                                }
                            }
                        }
                    } else { // if ( (!in_array($this->entry['url'], ...
                        if ((false == $isBad) && (false == $isGone)) {
                            $this->skipped++;
                        }
                    } // if ( (!in_array($this->entry['url'], ...
                } // if (($isGone==false) && ($isBad==false))

               // Keep logging of monitored url
                if (isset($this->entry['url'])) {
                    list($isMonitored, $found, $reason, $regex)=self::isMonitoredURL($this->entry['url']);
                    if ($isMonitored) {
                        if (!isset($this->arrMonitoredURLs[$found])) {
                            $this->arrMonitoredURLs[$found]=[];
                        }
                        $this->arrMonitoredURLs[$found]['entry'][]=$this->entry;
                    }
                }

                // Keep logging of each POST, PUT, TRACE and HEAD records to allow to examine them afterward

                $methods=['POST', 'PUT', 'TRACE', 'HEAD'];

                foreach ($methods as $method) {
                    if (isset($this->entry['method']) && ($this->entry['method'] == $method)) {
                        // Skip a few request that are reputed safe

                        $skip=false;
                        $arr =isset($this->json['skip']['monitor' . $method]) ? $this->json['skip']['monitor' . $method]['urls']['regex'] : null;
                        if (null != $arr) {
                            list($skip, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($this->entry['url'], $arr);
                        }
                        if (!$skip) {
                            $arr=isset($this->json['skip']['monitor' . $method]) ? $this->json['skip']['monitor' . $method]['remoteHost']['regex'] : null;
                            if (null != $arr) {
                                list($skip, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($this->entry['remoteHost'], $arr);
                            }
                        }
                        if (!$skip) {
                            switch ($method) {
                                case 'POST':
                                    $this->arrMethodPOSTLines[]=$this->entry;

                                    break;
                                case 'PUT':
                                    $this->arrMethodPUTLines[]=$this->entry;

                                    break;
                                case 'TRACE':
                                    $this->arrMethodTRACELines[]=$this->entry;

                                    break;
                                case 'HEAD':
                                    $this->arrMethodHEADLines[]=$this->entry;

                                    break;
                            }
                        }
                    }
                } // foreach ($arr as $method)

                // ----------------------------------------------------------------------------
                // Keep logging of each accessed Joomla components/modules/plugins that are adressed
                // Once with ?option=, once with /components/com_xxxx

                $matches  = [];
                $component='';

                if (isset($this->entry['url'])) {
                    list($isFound, $found, $reason, $regex)=self::isJoomlaComponent($this->entry['url']);
                    if ($isFound) {
                        $component=$found;
                        $type     =$reason;
                        $url      =$this->entry['url'];
                    } // $isFound

                    if ('' != trim($component)) {
                        if (isset($this->arrComponents[$found])) {
                            ++$this->arrComponents[$found]['#'];
                        } else {
                            $this->arrComponents[$found]=['#'=>1, 'name'=>$component];
                        }
                    } // if (isset($matches[0]))
                }
            } // foreach($file as $line)

            if ($this->totalRow < 0) {
                $this->totalRow=0;
            }

            $this->endProcess = microtime(true);

            $this->aeSession->set('BadLines', $this->arrBadLines);
            $this->aeSession->set('Components', $this->arrComponents);
            $this->aeSession->set('Errors', $this->arrErrorsLines);
            $this->aeSession->set('Gone', $this->arrGone);
            $this->aeSession->set('HoneyPot', $this->arrHoneyPot);
            $this->aeSession->set('LogEntry', $this->arrLogEntry);
            $this->aeSession->set('MethodHEAD', $this->arrMethodHEADLines);
            $this->aeSession->set('MethodPOST', $this->arrMethodPOSTLines);
            $this->aeSession->set('MethodPUT', $this->arrMethodPUTLines);
            $this->aeSession->set('MethodTRACE', $this->arrMethodTRACELines);
            $this->aeSession->set('MonitoredURLs', $this->arrMonitoredURLs);
            $this->aeSession->set('RemoteHost', $this->arrRemoteHost);
            $this->aeSession->set('URLs', $this->arrURLs);

            unset($this->arrBadLines, $this->arrComponents, $this->arrErrorsLines, $this->arrGone, $this->arrHoneyPot, $this->arrLogEntry, $this->arrMethodHEADLines, $this->arrMethodPOSTLines, $this->arrMethodPUTLines, $this->arrMethodTRACELines, $this->arrMonitoredURLs, $this->arrRemoteHost, $this->arrURLs);

            return true;
        } else { // if (is_readable($log_file))
            return -1;
        } // if (is_readable($log_file))
    } // function Process()

    // --------------------------------------------------------------------------------------
    // --------------------------------------------------------------------------------------
    // --------------------------------------------------------------------------------------

    /**
     * 0 - Display the list of files in the log folder.
     *
     * @return bool
     */
    public function DisplayListOfFiles()
    {
        $url=self::removeQueryStringVar(self::curPageURL(), 'action');
        $url=self::removeQueryStringVar($url, 'file');

        $dir = dirname(__FILE__) . '/logs';

        $dh   = opendir($dir);
        $files=[];
        while (false !== ($filename = readdir($dh))) {
            if (!(in_array($filename, ['.', '..', '.htaccess', 'index.html']))) {
                if (is_file($dir . DS . $filename)) {
                    $files[] = ['name'=>$filename, 'filesize'=>logReaderFct::human_filesize(filesize(realpath($dir . DS . $filename)))];
                }
            }
        }

        if (count($files) > 0) {
            ksort($files);
        }

        // Get the GitHub corner
        $github = '';
        if (is_file($cat = $this->template . 'octocat.tmpl')) {
            $github = str_replace('%REPO%', REPO, file_get_contents($cat));
        }

        $html=file_get_contents($this->template . 'logreader_files.default.tmpl');
        $html = str_replace('%REPO%', $github, $html);
        $html = str_replace('%FOLDER%', str_replace('/', DS, $dir), $html);
        $html = str_replace('%CURRENT_URL%', dirname(logReaderFct::GetCurrentURL()). '/' .
            basename(logReaderFct::GetCurrentURL()), $html);

        $lines='';

        foreach ($files as $key => $arr) {
            $fileext=('' != trim($arr['name']) ? pathinfo($arr['name'], PATHINFO_EXTENSION) : '');

            // Don't take ZIP files in the list
            if (!in_array($fileext, ['7z', 'gz', 'zip'])) {
                $lines .= '<tr data-filename="logs/' . $arr['name'] . '">' .
                    '<td class="filename">' . $arr['name'] . '</td>' .
                    '<td class="filesize">' . $arr['filesize'] . '</td>' .
                    '<td class="fileactions">' .
                    '<span title="Automatically deletes all requests to static files (images, css, js, web fonts, ...)" class="task" data-task="purge">1. Automatic cleaning</span>&nbsp;(<span class="task" data-task="purgeMAX">Max</span>)&nbsp;->&nbsp;' .
                    '<span title="Displays an interface that groups requests and allows you to manually simplify the file" class="task" data-task="diet">2. Manual cleaning</span>&nbsp;->&nbsp;' .
                    '<span title="Process the file" class="task" data-task="process">3. Process</span>&nbsp;->&nbsp;' .
                    '<span title="Deleting the log file on disk" class="task" data-task="kill">4. Remove the file</span>' .
                    '</td></tr>';
            }
        }

        echo str_replace('%LINES%', $lines, $html);

        return true;
    } // function DisplayListOfFiles()

    /**
     * 1 - Purge - Remove a lot of entries in the file.
     *
     * @return bool
     */
    public function Purge()
    {
        $filename = realpath($this->root . '/' . $this->logFile);

        if(!is_writable($filename)) {
            // If the file is read-only, the Purge action can't be done
            return sprintf('File %s is read-only', $this->logFile);
        }

        // A few querystring parameters like (&print=1, &start=10, &view=html, ...) can be safely removed from the querystring
        $arrSimplifyURLs = self::getJSONValue('simplify_urls', 'regex', null, null, null);

        $arrPurgeURLs = self::getJSONValue('purge', 'urls', 'regex', null, null);

        $arrPurgeStatus     = self::getJSONValue('purge', 'status', null, null, null);
        $arrSkipHost        = self::getJSONValue('skip', 'remoteHost', 'regex', null, null);
        $arrSkip            = self::getJSONValue('skip', 'urls', 'regex', null, null);
        $arrSkipIPwhitelist = self::getJSONValue('skip', 'remoteHost_whitelist', 'regex', null, null); 

        // &PurgeMAX is a querystring parameter to ask for the maximum purge
        $PurgeMAX='purgeMAX' === $_GET['action'] ? true : false;

        $arrPurgeMAXURLs  =$PurgeMAX ? self::getJSONValue('purgeMAX', 'urls', 'regex', null, null) : null;
        $arrPurgeMAXStatus=$PurgeMAX ? self::getJSONValue('purgeMAX', 'status', null, null, null) : null;

        if (null != $arrPurgeURLs) {
            $newfile='';

            $file          = file_get_contents($filename);
            $lines         = explode("\n", $file);
            $nbr           =0;
            $this->totalRow=0;

            foreach ($lines as $line) {
                $this->entry = logReaderFct::processApacheLogLine($line, $arrSimplifyURLs);

                if (null == $this->entry) {
                    continue;
                }

                if (null == $this->entry['simplified_url']) {
                    continue;
                }

                // Process the "Purge" entry (arrPurge)
                list($Purge, $found, $reason, $regex) = logReaderFct::CheckValueAgainstRegex($this->entry['simplified_url'], $arrPurgeURLs);

                if ((!$Purge) && (true == $PurgeMAX)) {
                    list($Purge, $found, $reason, $regex) = logReaderFct::CheckValueAgainstRegex($this->entry['simplified_url'], $arrPurgeMAXURLs);
                }

                // ... Based on the host
                if (!$Purge) {
                    list($Purge, $found, $reason, $regex) = logReaderFct::CheckValueAgainstRegex($this->entry['remoteHost'], $arrSkipHost);
                }

                if (!$Purge) {
                    list($Purge, $found, $reason, $regex) = logReaderFct::CheckValueAgainstRegex($this->entry['remoteHost'], $arrSkipIPwhitelist);
                }

                // ... Based on the HTTP status (f.i. like 304 or 403)
                if (!$Purge) {
                    $Purge = in_array($this->entry['status'], $arrPurgeStatus);
                }

                if ((!$Purge) && (true == $PurgeMAX)) {
                    $Purge=in_array($this->entry['status'], $arrPurgeMAXStatus);
                }

                if (!$Purge) {
                    list($Purge, $found, $reason, $regex) = logReaderFct::CheckValueAgainstRegex($this->entry['simplified_url'], $arrSkip);
                }

                // The line can stay
                if (!$Purge) {
                    $nbr+=1;
                    $newfile .= $line . "\n";
                }

            } // foreach

            if ($nbr > 0) {
                // Keep an archive of the file before starting to remove rows
                logReaderFct::MakeZip($this->root . '/' . $this->logFile . '.zip', $this->logFile);

                // Rewrite the file with its new content
                $fp = fopen($filename, 'wb');
                fwrite($fp, $newfile);
                fclose($fp);
            } // if ($nbr>0)

            $result=number_format(filesize($filename));

            $url=logReaderFct::GetCurrentURL($path = true, $querystring = true);
        }

        $result=logReaderFct::human_filesize(filesize($filename), 2);

        return $result;
    } // function Purge()

    /**
     * 2 - Diet.
     *
     * @return bool
     */
    public function Diet()
    {
        // A few querystring parameters like (&print=1, &start=10, &view=html, ...) can be safely removed from the querystring
        $arrSimplifyURLs=self::getJSONValue('simplify_urls', 'regex', null, null, null);

        $filename=realpath($this->root . '/' . $this->logFile);

        $result='';

        // "ids" is set by the interface with the list of URIs that should be deleted from the
        // file like f.i. "/style2.css" and "/reset.css" when these two files have been selected
        // from the interface.
        if (isset($_POST['ids'])) {
            if (is_writable($filename)) {
                // Only if the file is writable
                $ids=$_POST['ids'];

                $newfile='';

                $file  = file_get_contents($filename);
                $lines = explode("\n", $file);
                $nbr   =0;
                foreach ($lines as $line) {
                    $this->entry=logReaderFct::processApacheLogLine($line);

                    if (isset($this->entry['url'])) {
                        if (in_array(urlencode($this->entry['url']), $ids)) {
                            $nbr++;
                        } else {
                            $newfile .= $line . "\n";
                        }
                    }
                }

                // Keep an archive of the file before starting to remove rows
                logReaderFct::MakeZip($this->root . '/' . $this->logFile . '.zip', $this->logFile);

                //$this->logFile=rtrim($this->logFile,'_diet').'_diet';
                $result='<h2>' . $nbr . ' lines removed</h2>';

                $filename=$this->root . '/' . $this->logFile;
                $fp      = fopen($filename, 'wb');
                fwrite($fp, $newfile);
                fclose($fp);
            } 
        }

        $file  = file_get_contents($filename);
        $lines = explode("\n", $file);

        // Number of lines in the file
        $this->totalRow=0;

        $this->arrURLs=[];

        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }

            $this->totalRow++;

            // Process the entry from the Apache log and convert it into an associative array
            $this->entry=logReaderFct::processApacheLogLine($line, $arrSimplifyURLs);

            if (!isset($this->entry['url'])) {
                continue;
            }

            if (!isset($this->arrURLs[$this->entry['url']])) {
                $this->arrURLs[$this->entry['url']]=
                    [
                        '#' => 1, 
                        'status' => $this->entry['status'], 
                        'method' => $this->entry['method'],
                        //'date' => $this->entry['date'],
                        //'time' => $this->entry['time'],
                        //'timezone' => $this->entry['timezone'],
                        'simplified_url' => $this->entry['simplified_url'], 
                        'bytes' => $this->entry['bytes']
                    ];
            } else {
                $this->arrURLs[$this->entry['url']]['#']++;
                $this->arrURLs[$this->entry['url']]['bytes'] += $this->entry['bytes'];
            }
        } // foreach

        $totalSize=0;
        foreach ($this->arrURLs as $url => $entry) {
            $totalSize += $entry['bytes'];
        }

        $readOnly = '';
        if (!is_writable($filename)) {
            $readOnly = '&nbsp;<strong style="color:red;">FILE IS READ-ONLY</strong>';
        } 

        $result .= '<h3>' . str_replace('/', DS, $filename) . $readOnly. '</h3>' .
            '<strong>Filesize: ' . logReaderFct::ShowFriendlySize(filesize($filename)) . '&nbsp;-&nbsp;' .
            'Number of lines: <span id="nbrRowDisplay">' . number_format($this->totalRow, 0, ',', ' ') . '</span><span id="nbrRow" class="hidden">' . $this->totalRow . '</span>&nbsp;-&nbsp;' .
            'Selected number of lines: <span id="nbrSelected">0</span>&nbsp;(<span id="nbrSelectedPct">0%</span> | <span id="nbrBytesDisplay">0</span><span id="nbrBytesIntern" class="hidden">0</span>)&nbsp;' .
            'Bandwidth used : ' . logReaderFct::ShowFriendlySize($totalSize) . '</strong>';

        // Sort the array
        $occurence = [];
        $url       = [];
        foreach ($this->arrURLs as $key => $entry) {
            $occurence[$key] = $entry['#']; // Number of requests done on this url
            $url[$key]       = $key;        // Name of the host (mainly his IP address)
        }
        array_multisort($occurence, SORT_DESC, $url, SORT_ASC, $this->arrURLs);

        $i    =0;
        $lines='';

        foreach ($this->arrURLs as $url => $entry) {
            $i++;

            // Try to retrieve the file extension (.php, .png, .gif, ...)
            // $url is something like /media/com_proofreader/css/style.min.css?a_query_string

            // 1. Retrieve only the path (/media/com_proofreader/css/style.min.css)
            $url = parse_url($url, PHP_URL_PATH);

            // 2. Get only the last part (style.min.css)
            $arr = explode('/', $url);
            $filename = end($arr);

            // 3. Get all extensions so remove the first part "style" and keep "min.css"
            //$part = explode('.', $filename);
            //array_shift($part);
            //$ext = implode('.', $part);
            // OR only the past part (.css)
            $ext = pathinfo($filename)['extension']??'';

            $lines .=
            '<tr id="row' . $i . '">' .
                '<td class="chk"><input type="checkbox" id="chk' . $i . '" name="ids[]" value="' . urlencode($url) . '"/></td>' .
                '<td class="nbr">' . $i . '</td>' .
                //'<td class="date">' . $entry['date'] . '</td>' .
                //'<td class="time">' . $entry['time'] . '</td>' .
                '<td class="ext sorter-text filter-match">' . $ext . '</td>' .
                '<td class="max650 wrap" data-id="' . $i . '" title="Simplified :&#10;' . $entry['simplified_url'] . '">' . $url . '</td>' .
                '<td id="hits' . $i . '">' . $entry['#'] . '</td>' .
                '<td id="method' . $i . '">' . $entry['method'] . '</td>' .
                '<td id="status' . $i . '">' . $entry['status'] . '</td>' .
                '<td id="bytes' . $i . '">' . number_format($entry['bytes']) . '<span id="totalbytes' . $i . '" class="hidden">' . $entry['bytes'] . '</span></td>' .
                '<td id="humansize' . $i . '">' . logReaderFct::ShowFriendlySize($entry['bytes']) . '</td>' .
            '</tr>';
        } // foreach

        $html=file_get_contents($this->template . 'logreader_diet.default.tmpl');

        $action=$_SERVER['PHP_SELF'];

        $html=str_replace('%DIETRESULT%', $result, $html);

        $html=str_replace('%LOGFILE%', str_replace('/', DS, $this->logFile), $html);
        $html=str_replace('%FILENAME%', base64_encode(urlencode($this->logFile)), $html);
        $html=str_replace('%ACTION%', 'diet', $html);

        echo str_replace('%LINES%', $lines, $html);

        return true;
    }

    /**
     * 3 - Process.
     *
     * @return bool
     */
    public function Process()
    {
        $this->startDisplay = microtime(true);

        // Get the HTML template to use for the display
        $html=file_get_contents($this->template . '/logreader_process.default.tmpl', FILE_USE_INCLUDE_PATH);
        $html=str_replace('%LOGFILE%', $this->logFile, $html);
        $html=str_replace('%LOGROWNBR%', $this->totalRow, $html);

        // ----------------------------------
        // Display filters
        $filters='';

        $arr=[
            'date'       => '_arrfilterDate', 
            'method'     => '_arrfilterMethod',
            'referrer'   => '_arrfilterReferrer',
            'remoteHost' =>'_arrfilterRemoteHost',
            'status'     => '_arrfilterStatus', 
            'userAgent'  => '_arrfilterUserAgent',
            'URL'        => '_arrfilterURL'
        ];

        foreach ($arr as $key => $value) {
            $url=self::removeQueryStringVar(self::curPageURL(), $key);
            $url=sprintf($this->_urlRemoveFilter, $url, 'Re');
            if (!empty($this->$value)) {
                $filters .= '<li>' . $key . ' = ' . implode('§', str_replace(',', '§', $this->$value)) . '&nbsp;<span style="font-size:0.8em;">' . $url . '</span></li>';
            }
        }

        if ('' != trim($filters)) {
            $filters='<br/><br/><div class="bg-primary">Active querystring filters : <br/><ul>' . $filters . '</ul></div>';
        }
        $html=str_replace('%FILTERS%', $filters, $html);
        // ----------------------------------

        // Errors 403, 404, 500, ...
        foreach ($this->arrMonitoredHTTPErrors as $key => $value) {
            $html=str_replace('%HTTPERRORS' . $value . 'NBR%', isset($this->arrErrorsLines[$value]) ? count($this->arrErrorsLines[$value]) : 0, $html);
        }

        self::OutputLog($html);
        self::OutputHackAttempt($html);
        self::OutputHoneyPot($html);
        self::OutputGone($html);
        self::OutputHTTPErrors($html);
        self::OutputRemoteHost($html);
        self::OutputURLs($html);
        self::OutputJoomlaComp($html);
        self::OutputMonitoredURLs($html);
        self::OutputMethod($html, 'POST');
        self::OutputMethod($html, 'PUT');
        self::OutputMethod($html, 'TRACE');
        self::OutputMethod($html, 'HEAD');

        // Display a few statistics about time needed to built and display the page
        $this->endDisplay = microtime(true);

        $ProcessSeconds=round($this->endProcess - $this->startProcess, 4);
        $DisplaySeconds=round($this->endDisplay - $this->startDisplay, 4);

        $html=str_replace('%STATPAGEPROCESSSECONDS%', $ProcessSeconds, $html);
        $html=str_replace('%STATPAGEDISPLAYSECONDS%', $DisplaySeconds, $html);

        $tmp ='<br/><ul><li>canBeSkipped :' . round($this->canBeSkippedSeconds, 4) . '&nbsp;seconds</li></ul>';
        $html=str_replace('%STATMORE%', $tmp, $html);

        // Finally, display the HTML
        echo $html;

        return true;

    } // function Process

    /**
     * 4 - Kill the file.
     *
     * @return bool
     */
    public function Kill()
    {
        if ('' == trim($this->logFile)) {
            die('$this->logFile not initialized in ' . __METHOD__);
        }

        $filename=realpath($this->root . '/' . $this->logFile);
        if (file_exists($filename)) {
            if(is_writable($filename)) {
                unlink($filename);
            }
        }

        return true;
    }

    /**
     * Download the file (f.i. only logs entries, honeypot, ...).
     *
     * @param type $type
     */
    public function DownloadFile($type)
    {
        $return='';

        $this->aeSession=logReaderSession::getInstance(true);

        $group=null;

        switch ($type) {
            case 'full':
                // read the full logfile, process every row and split into ip, authUser, date, url, ... and download as an Excel file

                $file  = file_get_contents(realpath($this->root . '/' . $this->logFile));
                $lines = explode("\n", $file);

                foreach ($lines as $line) {
                    if ($line && ('' != trim($line))) {
                        $group[]=logReaderFct::processApacheLogLine($line);
                    }
                }

                break;
            case '403':
                $arr  =$this->aeSession->get('Errors', null);
                $group=isset($arr['403']) ? $arr['403'] : null;

                break;
            case '404':
                $arr  =$this->aeSession->get('Errors', null);
                $group=isset($arr['404']) ? $arr['404'] : null;

                break;
            case '500':
                $arr  =$this->aeSession->get('Errors', null);
                $group=isset($arr['500']) ? $arr['500'] : null;

                break;
            case 'hack':
                $group=$this->aeSession->get('BadLines', null);

                break;
            case 'methodPOST':
                $group=$this->aeSession->get('MethodPOST', null);

                break;
            case 'methodPUT':
                $group=$this->aeSession->get('MethodPUT', null);

                break;
            case 'methodTRACE':
                $group=$this->aeSession->get('MethodTRACE', null);

                break;
            case 'methodHEAD':
                $group=$this->aeSession->get('MethodHEAD', null);

                break;
            case 'monitoredURLs':
                $group=[];
                $arr  =$this->aeSession->get('MonitoredURLs', null);
                foreach ($arr as $url => $entries) {
                    foreach ($entries['entry'] as $entry) {
                        $group[]=$entry;
                    }
                }

                break;
            default:
                $group=$this->aeSession->get('LogEntry', null);
        }

        if (count($group) > 0) {
            // Get the name of each entries : will be the first line of the line; line with fieldname

            foreach ($group[0] as $key => $value) {
                if (!(in_array($key, ['logReader', 'simplified_url']))) {
                    $return .= $key . '§';
                }
            }
            $return=rtrim($return, '§') . PHP_EOL;

            // Output every entries

            foreach ($group as $entry) {
                if (isset($entry['logReader'])) {
                    unset($entry['logReader']);
                }
                if (isset($entry['simplified_url'])) {
                    unset($entry['simplified_url']);
                }

                if (isset($entry['referrer'])) {
                    if ('' == $entry['referrer']) {
                        $entry['referrer']='-';
                    } else {
                        $entry['referrer']=str_replace('%0A', '-', str_replace('%0D', '-', urldecode($entry['referrer'])));
                    }
                }
                if (isset($entry['userAgent'])) {
                    $entry['userAgent']=str_replace('§', ',', $entry['userAgent']);
                }
                $return .= str_replace(PHP_EOL, '', implode('§', $entry)) . PHP_EOL;
            } // foreach ($group as $entry)

            $return=rtrim($return, PHP_EOL);

            $temp   = tempnam('/tmp', 'FOO');
            $handle = fopen($temp, 'w');
            fwrite($handle, $return);
            fclose($handle);

            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $type . '_' . basename($this->logFile) . '.csv' . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($temp));
            die(readfile($temp));
        } // if(count($this->arrLogEntry)>0)
    } // function DownloadFile()

    private function var_dump($var, $title = null, $die = true)
    {
        return logReaderFct::var_dump($var, $title, $die);
    }

    /**
     * Return the current url, with all parameters.
     *
     * @return string
     */
    private function curPageURL()
    {
        $pageURL = 'http';
        if (isset($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'])) {
            $pageURL .= 's';
        }
        $pageURL .= '://';
        if ('80' != $_SERVER['SERVER_PORT']) {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        return $pageURL;
    } // function curPageURL()

    /**
     * Remove a variable from the querystring.
     *
     * @param type $url     f.i. 'http://.../logreader.php?file=logs/test.log&remoteHost=badHost&date=2014-08-15'
     * @param type $varname f.i. 'remoteHost' i.e. the name of the variable to remove
     *
     * @return type will return 'http://.../logreader.php?file=logs/test.log&date=2014-08-15'
     */
    private function removeQueryStringVar($url, $varname)
    {
        return preg_replace('([?&]' . $varname . '=[^&]*)', '$1', $url);
    }

    private function getJSONValue($node1, $node2 = null, $node3 = null, $node4 = null, $defaultValue = null)
    {
        return logReaderFct::getJSONValue($this->json, $node1, $node2, $node3, $node4, $defaultValue);
    }

    /**
     * Convert a host value (like '187.12.69.162' into an url to a WhoIs web service).
     *
     * @staticvar string $regex_IP
     * @staticvar string $whois_url
     *
     * @param type $remoteHost
     */
    private function WhoIs($remoteHost)
    {
        if ('' == trim($remoteHost)) {
            return '';
        }

        static $regex_IP ='';
        static $whois_url='';

        if ('' == trim($regex_IP)) {
            // Get the regular expression to match an IP address and the url to use for the "Who is" function
            $arr=self::getJSONValue('settings', 'regex', 'matchIP', null, null);
            if (null !== $arr) {
                $regex_IP =key($arr[0]);
                $whois_url=$arr[0][$regex_IP];
            }
        }

        $return=$remoteHost;

        if ('' != trim($regex_IP)) {
            $matches = [];
            preg_match('/' . $regex_IP . '/', $return, $matches);
            if (isset($matches[0])) {
                $return='<a style="font-weight:bold;" href="' . sprintf($whois_url, $matches[1]) . '" target="_blank">' . $remoteHost . '</a>';
            }
        }

        return $return;
    } // function WhoIs()

    /**
     * Simple function to display a log entry.
     *
     * @param type       $nbr
     * @param null|mixed $entry
     * @param null|mixed $title
     *
     * @return string
     */
    private function OutputEntry($nbr, $entry = null, $title = null)
    {
        if (null == $entry) {
            $entry = $this->entry;
        }

        $host   = isset($entry['remoteHost']) ? $entry['remoteHost'] : null;
        $url    = isset($entry['url']) ? $entry['url'] : null;
        $agent  = isset($entry['userAgent']) ? $entry['userAgent'] : null;
        $status = isset($entry['status']) ? $entry['status'] : null;

        // Do we need to highlight something before displaying the entry ?
        $arrType=['Bad'=>'BadHighlight', 'Skip'=>'GoodHighlight', 'Gone'=>'GoneHighlight'];
        foreach ($arrType as $type => $class) {
            if (isset($entry['logReader'][$type])) {
                foreach ($entry['logReader'][$type] as $id => $arr) {
                    if (isset($arr['highlight'])) {
                        foreach ($arr['highlight'] as $key => $value) {
                            $reason = isset($arr['reason']) ? $arr['reason'] : '';
                            $reason .= isset($arr['regex']) ? '<br/><br/>regex = ' . $arr['regex'] : '';
                            $reason .= isset($arr['regex']) ? '<br/><br/>' . $key . ' = ' . $value : '';

                            // It's possible that the entry has no "Bad/Good_reason".  This is the case when it's a honey pot url f.i. not flagged as bad
                            $entry[$key]=str_replace($value, sprintf($this->_tooltip, $reason, '<span class="' . $class . '">' . $value . '</span>'), $entry[$key]);
                        }
                        // Do we need to export all informations ?  The $entry['logReader'] key can be removed since fully processed here above as tooltip.
                        // $this->SettingsFullInfo is a json->settings entry and, if true, the $entry['logReader'] will be displayed
                        if (!$this->SettingsFullInfo) {
                            unset($entry['logReader'][$type][$id]);
                        }
                    } // if(isset($entry['logReader']['highlight']))
                } // foreach
                if (0 == count($entry['logReader'][$type])) {
                    unset($entry['logReader'][$type]);
                }
            } // if (isset($entry['logReader'][$type]))
        } // foreach

        if (isset($entry['logReader'])) {
            if ((!$this->SettingsFullInfo) && (0 == count($entry['logReader']))) {
                unset($entry['logReader']);
            }
        }

        // Perhaps should we not output every keys.  For instance, we can configure LogReader to not output the [protocol] entry
        $arr = self::getJSONValue('settings', 'output', 'hide', null, null);
        if (null != $arr) {
            foreach ($arr as $key) {
                if (isset($entry[$key])) {
                    unset($entry[$key]);
                }
            }
        }

        if (isset($this->arrHTTPCodess[$status])) {
            $filter          = self::removeQueryStringVar(self::curPageURL(), 'status');
            $filter          = sprintf($this->_urlStatus, $filter . '&status=' . $entry['status'], $entry['status']);
            $entry['status'] = $entry['status'] . ' ==> ' . $this->arrHTTPCodess[$status] . $filter;
        }

        $tmp = (isset($this->arrRemoteHost[$host]['#'])) ? sprintf($this->_tooltipNumberHost, $this->arrRemoteHost[$host]['#']) : '';

        $filter              = self::removeQueryStringVar(self::curPageURL(), 'remoteHost');
        $filter              = sprintf($this->_urlFilter, $filter . '&remoteHost=' . urlencode(base64_encode($host)), $host);
        $entry['remoteHost'] = self::WhoIs($entry['remoteHost']) . '&nbsp;<span style="font-size:0.8em;">******' . $tmp . $filter . '</span>';

        $method          = isset($entry['method']) ? $entry['method'] : null;
        $filter          = self::removeQueryStringVar(self::curPageURL(), 'method');
        $filter          = sprintf($this->_urlFilter, $filter . '&method=' . urlencode($method), $host);
        $entry['method'] = $method . '&nbsp;<span style="font-size:0.8em;">' . $filter . '</span>';

        $filter             = self::removeQueryStringVar(self::curPageURL(), 'userAgent');
        $filter             = sprintf($this->_urlFilter, $filter . '&userAgent=' . urlencode(base64_encode($agent)), $agent);
        $entry['userAgent'] = $entry['userAgent'] . '&nbsp;<span style="font-size:0.8em;">' . $filter . '</span>';

        $tmp          = (isset($this->arrURLs[$url])) ? sprintf($this->_tooltipNumberURL, $this->arrURLs[$url]['#']) : '';
        $filter       = self::removeQueryStringVar(self::curPageURL(), 'url');
        $filter       = sprintf($this->_urlFilter, $filter . '&URL=' . urlencode(base64_encode($url)), htmlentities($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $entry['url'] = $entry['url'] . '&nbsp;<span style="font-size:0.8em;">' . $tmp . $filter . sprintf($this->_iconURLDecode, htmlentities($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</span>';

        $filter        = self::removeQueryStringVar(self::curPageURL(), 'date');
        $filter        = sprintf($this->_urlFilter, $filter . '&date=' . urlencode($entry['date']), $entry['date']);
        $entry['date'] = $entry['date'] . '&nbsp;<span style="font-size:0.8em;">' . $filter . '</span>';

        $filter            = self::removeQueryStringVar(self::curPageURL(), 'referrer');
        $filter            = sprintf($this->_urlFilter, $filter . '&referrer=' . urlencode(base64_encode($entry['referrer'])), $entry['referrer']);
        $url               = ('-' != $entry['referrer']) ? '<a href="' . $entry['referrer'] . '" target="_blank">' . $entry['referrer'] . '</a>' : $entry['referrer'];
        $entry['referrer'] = $url . '&nbsp;<span style="font-size:0.8em;">' . $filter . '</span>';

        $class='';

        if (in_array($status, ['200', '403', '404', '500'])) {
            $entry['status'] = '<span class="status' . $status . '">' . $entry['status'] . '</span>';
        }

        if (isset($entry['logReader']['Skip'])) {
            if ('200' == $status) {
                $entry['status'] = '<span class="Status200">' . $entry['status'] . '</span>';
            }
            $class .= 'bg-success ';
        } else { // if(isset($entry['logReader']['Skip']))
            // Identified as bad request
            if (isset($entry['logReader']['Bad'])) {
                if (in_array($status, ['200', '206'])) {
                    // The status code is 200 or 206 and the request has been identified as bad
                    // Make a check on the URL : is it a dangerous one ?  If yes, damned!!!, something is really not cool
                    // If the url is OK (f.i. /fr/index.php), the request has probably be flagged as bad because the remote
                    // host has been banned.  In that case (url = safe, status=200 or 206), don't flag the entry has HackSuccess

                    $class .= 'bg-warning ';

                    if (false === strpos($entry['url'], '/logReader/accessdenied.php?')) {
                        $arr=self::getJSONValue('skip', 'urls', 'regex', null, null);
                        if (null != $arr) {
                            list($skip, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($url, $arr);
                            if (!$skip) {
                                $class .= 'bg-HackSuccess ';
                                $entry['status']=$entry['status'] . $this->_HasBeenHacked;
                            }
                        }
                    } // if (strpos($entry['url']
                } else {
                    $class .= (in_array($status, ['200', '206']) ? 'bg-warning' : 'bg-danger') . ' ';
                    if (($status >= 400) && (isset($entry['logReader']['Bad']))) {
                        $entry['status']=$entry['status'] . $this->_NotBlocked;
                    }
                }
            } // if(isset($entry['logReader']['Bad']))
        } //  // if(isset($entry['logReader']['Skip']))

        $html='<div class="%s">%s</div>';
        $html=sprintf($html, rtrim($class, ' '), sprintf('%06d', $nbr) . ' - ' . (null != $title ? $title . ' - ' : '') . print_r($entry, true));

        return $html;
    } // function OutputEntry()

    /**
     * Based on the $json rule, detect if the entry contains an attack or not.
     *
     * @return bool
     */
    private function isBad()
    {
        $_comment='the presence of pattern "%s" denotes a possible attack';

        $isBad        =false;
        $reason       ='';
        $remoteComment='';

        // ---------------------------------------------------------------------------------------------------------------------
        // Try to determine if the web access is bad based on

        // ... userAgent
        //if(!($isBad)) {
        $arr=self::getJSONValue('bad', 'userAgent', 'regex', null, null);
        if (null != $arr) {
            if (isset($this->entry['userAgent'])) {
                list($isFound, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($this->entry['userAgent'], $arr);
                if ($isFound) {
                    $isBad                            =true;
                    $this->entry['logReader']['Bad'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['userAgent'=>$found]];
                } // if ($isBad)
            }
        }
        //} // if(!($isBad)) {

        // No PUT method allowed
        if (isset($this->entry['method'])) {
            if ('PUT' == $this->entry['method']) {
                $isBad                            =true;
                $this->entry['logReader']['Bad'][]=['reason'=>'PUT method', 'highlight'=>['method'=>'PUT']];
            } // if ($isBad)
        }

        //  ... based on a regex of the url
        if (isset($this->entry['url'])) {
            list($isFound, $found, $reason, $regex)=self::isBadURL($this->entry['url']);
            if (true === $isFound) {
                $isBad                            =true;
                $this->entry['logReader']['Bad'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['url'=>$found]];
            }
        }

        // ... Joomla components/modules/plugins
        if (isset($this->entry['url'])) {
            list($isFound, $found, $reason, $regex)=self::isBadJoomlaComponent($this->entry['url']);
            if (true === $isFound) {
                $isBad                            =true;
                $this->entry['logReader']['Bad'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['url'=>$found]];
            }
        }

        // ... the remote host (bad ones)
        if (isset($this->entry['remoteHost'])) {
            list($isFound, $found, $reason, $regex)=self::isBadBot($this->entry['remoteHost']);
            if (true === $isFound) {
                $isBad                            =true;
                $this->entry['logReader']['Bad'][]=['reason'=>$found . ' is blacklisted', 'highlight'=>['remoteHost'=>$this->entry['remoteHost']]];
            }
        }

        // Add extra info in the remoteHost and arrULS table
        if ($isBad) {
            if ('' == trim($remoteComment)) {
                $remoteComment='First possible hack attempt retrieved for this host :&nbsp;';
            }

            if (isset($this->arrRemoteHost[$this->entry['remoteHost']])) {
                $this->arrRemoteHost[$this->entry['remoteHost']]['comment']='First attack done by this host : <br/><small><span class="BadHighlight">' . $found . ' => ' . $reason . '</span></small>';
            }

            if (isset($this->arrURLs[$this->entry['url']])) {
                $this->arrURLs[$this->entry['url']]['comment']=('' != trim($reason) ? '<small><span class="BadHighlight">' . $reason . '</small></span>' : '&nbsp;');
            }
        } // if ($isBad)

        return $isBad;
    } // function isBad()

    /**
     * Based on the $json rule, detect if the entry contains an old url (Redirect gone).
     *
     * @param null|mixed $url
     *
     * @return bool
     */
    private function isGone($url = null)
    {
        if (isset($this->entry['url'])) {
            if (null == $url) {
                $url=$this->entry['url'];
            }
        }

        $arr=self::getJSONValue('gone', 'urls', 'regex', null, null);

        return logReaderFct::CheckValueAgainstRegex($url, $arr);
    } // function isGone()

    /**
     * Check if the url should be ignored i.e. not processed at all by the script.
     * This is order to speed up the process.
     *
     * @return type
     */
    private function isIgnored()
    {
        $isIgnore=false;
        if (isset($this->entry['url'])) {
            $arr                                    =self::getJSONValue('ignore', 'urls', 'regex', null, null);
            list($isIgnore, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($this->entry['url'], $arr);
        }

        return $isIgnore;
    }

    /**
     * Based on the $json rule, detect if the entry contains an url mentionned in the Honey pot.  If so, it's an attack.
     *
     * @return bool
     */
    private function HoneyPot()
    {
        $HoneyPot=false;
        $reason  =null;
        $regex   =null;

        // White list, don't mention access done by these machines
        if (isset($this->entry['remoteHost'])) {
            list($skip, $reason, $regex)=self::isIPWhiteListed($this->entry['remoteHost']);
        } else {
            $skip=false;
        }

        if ($skip) {
            return [false, false, $reason, $regex];
        }

        // Try to determine if the url that is requested is in the Honey pot

        if (!($HoneyPot)) {
            $arr=self::getJSONValue('honeypot', 'urls', 'regex', null, null);
            if (null != $arr) {
                if (isset($this->entry['url'])) {
                    list($HoneyPot, $found, $reason, $regex) = logReaderFct::CheckValueAgainstRegex($this->entry['url'], $arr);
                    if ($HoneyPot) {
                        $this->entry['logReader']['HoneyPot'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['url'=>$found]];
                    }
                } else {
                    $found=false;
                }
            }
        }

        return [$HoneyPot, $found, $reason, $regex];
    } // function HoneyPot()

    /**
     * Based on the $json rule, detect if the entry can be skipped (=not displayed) or not.
     *
     * @return bool
     */
    private function canbeSkipped()
    {
        $startTime = microtime(true);

        // Should we skip this url, this is the case for urls that are supposed safe based on the json settings
        $skip=false;

        if (!($skip)) {
            // White list, don't mention access done by these machines
            if (isset($this->entry['remoteHost'])) {
                list($skip, $found, $reason, $regex)=self::isIPWhiteListed($this->entry['remoteHost']);
            } else {
                $skip=false;
            }

            if ($skip) {
                $this->entry['logReader']['Skip'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['remoteHost'=>$found]];
            }
        } // if(!($skip)) {

        // Skip based on the HTTP status code
        if (!($skip) && isset($this->entry['status'])) {
            $arr=self::getJSONValue('skip', 'status', null, null, null);
            if (null != $arr) {
                $skip=in_array($this->entry['status'], $arr);
                if ($skip) {
                    $this->entry['logReader']['Skip'][]=['reason'=>'HTTP status is"' . $this->entry['status'] . '"', 'highlight'=>['status'=>$this->entry['status']]];
                }
            }
        } // if(!($skip)) {

        // ... based on the bot (only if friendly bot)
        if (!($skip) && isset($this->entry['remoteHost'])) {
            list($skip, $found, $reason, $regex)=self::isGoodBot($this->entry['remoteHost']);
            if (true === $skip) {
                $this->entry['logReader']['Skip'][]=['reason'=>$reason, 'highlight'=>['remoteHost'=>$found]];
            }
        } // if(!($skip))
        //  ... based on a regex of the url
        if (!($skip) && isset($this->entry['url'])) {
            list($skip, $found, $reason, $regex)=self::isGoodURL($this->entry['url']);
            if ($skip) {
                $this->entry['logReader']['Skip'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['url'=>$found]];
            }
        } // if(!($skip)) {
        //  ... based on a regex of the remoteHost
        if (!($skip) && isset($this->entry['remoteHost'])) {
            $arr=self::getJSONValue('skip', 'remoteHost', 'regex', null, null);
            if (null != $arr) {
                list($skip, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($this->entry['remoteHost'], $arr);
                if ($skip) {
                    $this->entry['logReader']['Skip'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['remoteHost'=>$found]];
                } // if ($skip)
            }
        } // if(!($skip)) {

        // ... userAgent
        if (!($skip) && isset($this->entry['userAgent'])) {
            $arr=self::getJSONValue('skip', 'userAgent', 'regex', null, null);
            if (null != $arr) {
                list($skip, $found, $reason, $regex)=logReaderFct::CheckValueAgainstRegex($this->entry['userAgent'], $arr);
                if ($skip) {
                    $this->entry['logReader']['Skip'][]=['reason'=>$reason, 'regex'=>$regex, 'highlight'=>['userAgent'=>$found]];
                } // if ($skip)
            }
        } // if(!($isBad)) {

        $endTime = microtime(true);
        $this->canBeSkippedSeconds += $endTime - $startTime;

        return $skip;
    } // function canbeSkipped()

    private function isIPWhiteListed($remoteHost)
    {
        $arr=self::getJSONValue('skip', 'remoteHost_whitelist', 'regex', null, 0);

        return logReaderFct::CheckValueAgainstRegex($remoteHost, $arr);
    }

    private function isGoodBot($remoteHost)
    {
        $arr=self::getJSONValue('skip', 'remoteHost', 'regex', null, 0);

        return logReaderFct::CheckValueAgainstRegex($remoteHost, $arr);
    }

    private function isGoodURL($url)
    {
        $arr=self::getJSONValue('skip', 'urls', 'regex', null, null);

        return logReaderFct::CheckValueAgainstRegex($url, $arr);
    }

    private function isBadBot($remoteHost)
    {
        $arr=self::getJSONValue('bad', 'remoteHost', 'full', null, null);
        if (null != $arr) {
            if (in_array($this->entry['remoteHost'], $arr)) {
                return [true, $remoteHost, 'Bad bot', 'remoteHost->full'];
            }
        }
        $arr=self::getJSONValue('bad', 'remoteHost', 'regex', null, null);

        return logReaderFct::CheckValueAgainstRegex($remoteHost, $arr);
    }

    private function isBadURL($url)
    {
        $arr=self::getJSONValue('bad', 'urls', 'regex', null, null);

        return logReaderFct::CheckValueAgainstRegex($url, $arr);
    }

    private function isMonitoredURL($url)
    {
        $arr=self::getJSONValue('monitored', 'regex', null, null, null);

        return logReaderFct::CheckValueAgainstRegex($url, $arr);
    }

    private function isBadJoomlaComponent($url)
    {
        $arr=self::getJSONValue('bad', 'components', 'regex', null, null);

        return logReaderFct::CheckValueAgainstRegex($url, $arr);
    }

    private function isJoomlaComponent($url)
    {
        $arr=self::getJSONValue('settings', 'detectJoomlaComponent', 'regex', null, null);

        return logReaderFct::CheckValueAgainstRegex($url, $arr);
    }

    /**
     * Output the lines to analyze (i.e. not "identified" as bad requests, gone request, honey pot, ...).
     *
     * @param type $html
     */
    private function OutputLog(&$html)
    {
        $log='';

        $arr=$this->aeSession->get('LogEntry', null);
        if (count($arr) > 0) {
            $nbr=0;
            foreach ($arr as $entry) {
                $nbr++;
                $log .= self::OutputEntry($nbr, $entry, 'Lines to analyze');
            }
        }
        if ('' == trim($log)) {
            $log='After the processing, the log file is empty i.e. lines were skipped because safe and errors are listed below.';
        }

        $html=str_replace('%LOGNBR%', count($arr), $html);
        $html=str_replace('%LOGENTRIES%', $log, $html);

        return true;
    } // function OutputLog()

    /**
     * Output the lines that correspond to old urls; flagged as "Gone".
     *
     * @param type $html
     */
    private function OutputGone(&$html)
    {
        $log='';

        $arr=$this->aeSession->get('Gone', null);

        if (count($arr) > 0) {
            $nbr=0;
            foreach ($arr as $entry) {
                $nbr++;
                $log .= self::OutputEntry($nbr, $entry, 'Gone');
            }
        }

        $html=str_replace('%GONENBR%', count($arr), $html);
        $html=str_replace('%GONEENTRIES%', ('' != trim($log) ? $log : 'There is no access to old URLs.'), $html);

        return true;
    } // function OutputGone()

    /**
     * Output HTTP errors, one "topic" by errors like 403, 404, 500.
     *
     * @param type $html
     */
    private function OutputHTTPErrors(&$html)
    {
        $arr=$this->aeSession->get('Errors', null);

        $tip='&nbsp;<span data-toggle="popover" title="help" data-content="<span class=\'tooltiptext\'>' .
         'If an entry has already be processed as an hack attempt or an entry to skip, even if an HTTP error code was found, the entry won\'t be taken here anymore.<br/>So, it\'s well possible that the counter mention zero when there was well entry with this HTTP status.' .
         '" class="help glyphicon glyphicon-eye-open text-info"></span>' .
         '<span data-task="download" title="Télécharge le fichier au format texte" data-type="%CODE%" class="help text-info glyphicon glyphicon-download-alt" ' .
            'data-filename="' . $this->logFile . '">&nbsp;</span>';

        $log     ='';
        $errorNbr=0;

        foreach ($this->arrHTTPErrors as $key => $value) {
            $log .= '<div id="' . $key . '"><h3>' . $value . '&nbsp;' .
            '<span class="badge">' . (isset($this->arrErrorsLines[$key]) ? count($this->arrErrorsLines[$key]) : 0) . '</span>' .
               str_replace('%CODE%', $key, $tip) . '</h3><pre>';

            if (count($arr) > 0) {
                if (isset($arr[$key]) && (count($arr[$key]) > 0)) {
                    $errorNbr += count($arr[$key]);
                    $nbr=0;
                    foreach ($arr[$key] as $entry) {
                        $nbr++;
                        $log .= self::OutputEntry($nbr, $entry, 'HTTP errors');
                    }
                } // if (count($arrErrorsLines['403'])>0)
            } else { // if(count($this->arrHTTPErrors)>0)
                $log .= 'No entries';
            } // if(count($this->arrHTTPErrors)>0)

            $log .= '</pre></div>';
        } // foreach

        $html=str_replace('%HTTPERRORSNBR%', $errorNbr, $html);
        $html=str_replace('%HTTPERRORSENTRIES%', ('' != trim($log) ? $log : 'No entries'), $html);

        return true;
    } // function OutputHTTPErrors()

    /**
     * Output Honey pot.
     *
     * @param type $html
     */
    private function OutputHoneyPot(&$html)
    {
        // ----------------------------------------------------------------------------
        // Display the honey pot entries

        $log='';

        $more='';
        $arr =$this->aeSession->get('HoneyPot', null);

        if (count($arr) > 0) {
            $arrUnique=[];

            foreach ($arr as $url => $row) {
                $nbr   =0;
                $remote='';

                $value =$row['value'];
                $reason=$row['reason'];
                $url   =str_replace($value, '<span class="BadHighlight">' . $value . '</span>', $url);
                foreach ($row['host'] as $host => $tmp) {
                    $nbr += $tmp['#'];
                    $remote .= $host . ' ,';
                    if (!isset($arrUnique[$host])) {
                        $arrUnique[]=$host;
                    }
                }

                $remote=rtrim($remote, ', ');
                $log .= '<tr><td>' . $url . '</td><td><strong>' . $nbr . '</strong></td><td>' . $reason . '</td><td>' . $remote . '</td></tr>';
            } // foreach

            ksort($arrUnique, SORT_NUMERIC);
            foreach ($arrUnique as $key) {
                $more .= '"' . $key . '", ';
            }
            $more='<h4>Unique values :</h4>' . rtrim($more, ', ');
        } else { // if(count($this->arrHoneyPot)>0)
            $log .= '<tr><td colspan="4"><span style="font-style:italic">No entries in this table</span></td></tr>';
        }

        $html=str_replace('%HONEYPOTENTRIES%', $log, $html);
        $html=str_replace('%HONEYPOTMORE%', $more, $html);

        return true;
    } // function OutputHoneyPot()

    /**
     * Display the possible attacks.
     *
     * @param type $html
     */
    private function OutputHackAttempt(&$html)
    {
        $log='';

        $arr=$this->aeSession->get('BadLines', null);

        if (count($arr) > 0) {
            $nbr=0;
            foreach ($arr as $this->entry) {
                $nbr++;
                $log .= self::OutputEntry($nbr, $this->entry, '* Hack attempt *');
            } // foreach
        } // if (count($this->arrBadLines)>0)

        $html=str_replace('%POSSIBLEATTACKSNBR%', count($arr), $html);
        $html=str_replace('%POSSIBLEATTACKSENTRIES%', ('' != trim($log) ? $log : 'No entries'), $html);

        // The OutputEntry function will generate a 'bg-HackSuccess' css class when a hack attempt has succedeed i.e.
        // when the status code is 200 and the url is a bad one.
        // If this is the case, alert the user by putting a bg-HackSuccess class in the html result

        if (strpos($log, 'bg-HackSuccess') > 0) {
            $html=str_replace('%HACKSUCCESS%', 'bg-HackSuccess', $html);
        } else {
            $html=str_replace('%HACKSUCCESS%', '', $html);
        }

        return true;
    } // function OutputHackAttempt()

    /**
     * Output the number of connections by remote host.
     *
     * @param type $html
     *
     * @return type.
     */
    private function OutputRemoteHost(&$html)
    {
        $log='';

        $arr=$this->aeSession->get('RemoteHost', null);

        if (count($arr) > 0) {
            // Sort by number of request (descending) then by remote host / ip (asc)

            $occurence = [];
            $host      = [];
            foreach ($arr as $key => $row) {
                $occurence[$key] = $row['#']; // Number of requests done by this host
                $host[$key]      = $key;           // Name of the host (mainly his IP address)
            }
            array_multisort($occurence, SORT_DESC, $host, SORT_ASC, $arr);

            foreach ($arr as $host => $value) {
                $url=self::removeQueryStringVar(self::curPageURL(), 'remoteHost');
                $url=sprintf($this->_urlFilter, $url . '&remoteHost=' . urlencode(base64_encode($host)), $host);

                list($isBot, $found, $reason, $regex)=self::isIPWhiteListed($host);
                $BotType                             ='';
                if (!$isBot) {
                    list($isBot, $found, $reason, $regex)=self::isGoodBot($host);
                    if ($isBot) {
                        $BotType='Good';
                    }
                }
                if (!$isBot) {
                    list($isBot, $found, $reason, $regex)=self::isBadBot($host);
                    if ($isBot) {
                        $BotType='Bad';
                    }
                }
                if ($isBot) {
                    $bot=(in_array($BotType, ['Bad', 'Good']) ? '<span class="' . $BotType . 'Highlight">' . $found . ('Bad' == $BotType ? ' is banned' : ' is whitelisted') . '</span>' : $found);
                } else {
                    $bot='';
                }
                $log .= '<tr><td>' . self::WhoIs($host) . '&nbsp;<span style="font-size:0.8em;">' . $url . '</span></td><td>' . $value['#'] . '</td><td>' . ('' != trim($bot) ? $bot . '<br/>' : '') . $value['comment'] . '</td></td></tr>';
            }
        } else {
            $log .= '<tr><td colspan="3"><span style="font-style:italic">No entries in this table</span></td></tr>';
        }

        $html=str_replace('%REMOTEHOSTNBR%', count($arr), $html);
        $html=str_replace('%REMOTEHOSTENTRIES%', $log, $html);

        return true;
    } // function OutputRemoteHost()

    /**
     * Output the number of requests by URLs.
     *
     * @param type $html
     */
    private function OutputURLs(&$html)
    {
        $log='';

        $arr=$this->aeSession->get('URLs', null);
        if (count($arr) > 0) {
            // Sort by number of request (descending)

            $occurence = [];
            $url       = [];
            foreach ($arr as $key => $row) {
                $occurence[$key] = $row['#']; // Number of requests done for this url
                $url[$key]       = $key;            // url
            }
            array_multisort($occurence, SORT_DESC, $url, SORT_ASC, $arr);

            foreach ($arr as $key => $value) {
                $url=self::removeQueryStringVar(self::curPageURL(), 'URL');
                $url=sprintf($this->_urlFilter, $url . '&URL=' . urlencode(base64_encode($key)), $key);

                // Verify is it's a good, bad or gone URL
                list($isFound, $found, $reason, $regex)=self::isGoodURL($key);
                if (true === $isFound) {
                    $key=sprintf($this->_tooltip, $reason, '<span class="GoodHighlight">' . $key . '</span>');
                } else {
                    list($isFound, $found, $reason, $regex)=self::isBadURL($key);
                    if ($isFound) {
                        $key=sprintf($this->_tooltip, $reason, '<span class="BadHighlight">' . $key . '</span>');
                    } else {
                        list($isFound, $found, $reason, $regex)=self::isBadJoomlaComponent($key);
                        if (true === $isFound) {
                            $key=sprintf($this->_tooltip, $reason, '<span class="BadHighlight">' . $key . '</span>');
                        } else {
                            list($isFound, $found, $reason, $regex)=self::isGone($key);
                            if ($isFound) {
                                $key=sprintf($this->_tooltip, $reason, '<span class="GoneHighlight">' . $key . '</span>');
                            }
                        }
                    }
                }

                $log .= '<tr><td class="max650 wrap">' . $key . '&nbsp;<span style="font-size:0.8em;">' . $url . '</span></td><td><strong>' . $value['#'] . '</strong></td></tr>';
            } // foreach
        } else { // if (count($this->arrComponents)>0) {
            $log .= '<tr><td colspan="2"><span style="font-style:italic">No entries in this table</span></td></tr>';
        }

        $html=str_replace('%BYURLSNBR%', count($arr), $html);
        $html=str_replace('%BYURLSENTRIES%', $log, $html);

        return true;
    } // function OutputURLs()

    /**
     * Do we've monitored urls ?  If so, output the information.
     *
     * @param type $html
     *
     * @return bool
     */
    private function OutputMonitoredURLs(&$html)
    {
        $log     ='';
        $errorNbr=0;

        $arr=$this->aeSession->get('MonitoredURLs', null);

        if (count($arr) > 0) {
            $errorNbr=0;

            foreach ($arr as $key => $arr) {
                if (count($arr) > 0) {
                    $errorNbr += count($arr['entry']);
                    // Retrieve the reason why the url is monitored
                    list($isMonitored, $found, $reason, $regex)=self::isMonitoredURL($key);

                    $log .= '<h4>' . $key . '&nbsp; (' . $reason . ') <span class="badge">' . count($arr['entry']) . '</span></h4><pre class="max1280">';
                    $nbr=0;
                    foreach ($arr['entry'] as $entry) {
                        $nbr++;
                        $log .= self::OutputEntry($nbr, $entry, 'Monitored URLS');
                    }

                    $log .= '</pre>';
                }
            } // foreach
        } // if (count($arrMonitoredURLs)>0)

        if ('' == trim($log)) {
            $log='No access made on monitored URLs';
        }

        $html=str_replace('%MONITOREDURLSNBR%', $errorNbr, $html);
        $html=str_replace('%MONITOREDURLSENTRIES%', $log, $html);

        return true;
    }

    /**
     * Joomla! components requested.
     *
     * @param type $html
     */
    private function OutputJoomlaComp(&$html)
    {
        $log='';
        $arr=$this->aeSession->get('Components', null);

        if (count($arr) > 0) {
            // Sort by number of request (descending)

            $occurence = [];
            $url       = [];
            foreach ($arr as $key => $row) {
                $occurence[$key] = $row['#']; // Number of requests done for this url
                $url[$key]       = $key;            // url
            }
            array_multisort($occurence, SORT_DESC, $url, SORT_ASC, $arr);

            foreach ($arr as $key => $value) {
                $name                                =$value['name'];
                list($isBad, $found, $reason, $regex)=self::isBadJoomlaComponent($name);
                if ($isBad) {
                    $name=sprintf($this->_tooltip, $reason, '<span class="BadHighlight">' . $name . '</span>');
                }

                $url=self::removeQueryStringVar(self::curPageURL(), 'URL');
                $url=sprintf($this->_urlFilter, $url . '&URL=' . urlencode(base64_encode($value['name'])), $value['name']);

                $log .= '<tr><td class="max650 wrap">' . $name . '&nbsp;<span style="font-size:0.8em;">' . $url . '</span></td><td><strong>' . $value['#'] . '</strong></td></tr>';
            }
        } else { // if (count($this->arrComponents)>0) {
            $log .= '<tr><td colspan="2"><span style="font-style:italic">No entries in this table</span></td></tr>';
        } // if (count($this->arrComponents)>0) {

        $html=str_replace('%JOOMLACOMPNBR%', count($arr), $html);
        $html=str_replace('%JOOMLACOMPENTRIES%', $log, $html);

        return true;
    } // function OutputJoomlaComp()

    /**
     * Output all records concerning POST, PUT, TRACE or HEAD requests.
     *
     * @param type  $html
     * @param mixed $method
     *
     * @return bool
     */
    private function OutputMethod(&$html, $method)
    {
        $arr=null;

        switch ($method) {
            case 'POST':
                $arr=$this->aeSession->get('MethodPOST', null);

                break;
            case 'HEAD':
                $arr=$this->aeSession->get('MethodHEAD', null);

                break;
            case 'PUT':
                $arr=$this->aeSession->get('MethodPUT', null);

                break;
            case 'TRACE':
                $arr=$this->aeSession->get('MethodTRACEL', null);

                break;
        }

        $log='';

        if (!(empty($arr))) {
            $nbr=0;
            foreach ($arr as $entry) {
                $nbr++;
                $log .= self::OutputEntry($nbr, $entry, 'Method ' . $method);
            }
        } // if (count(arrMethodPostLines)>0)

        if ('' == trim($log)) {
            $log='No ' . $method . ' request made';
        }

        $html=str_replace('%METHOD' . $method . 'NBR%', (empty($arr)?0:count($arr)), $html);
        $html=str_replace('%METHOD' . $method . 'ENTRIES%', $log, $html);

        return true;
    } // function OutputMethod()

} // class logReader

// -----------------------------------------------------------------------------------------------------------
// ENTRY POINT
// -----------------------------------------------------------------------------------------------------------

   error_reporting(DEBUG ? -1 : 0);
   ini_set('display_errors', DEBUG ? 1 : 0);
   ini_set('display_startup_errors', DEBUG ? 1 : 0);
   ini_set('html_errors', DEBUG ? 1 : 0);

   // To initialize : filename of the log to analyze.
   $data=isset($_POST['file']) ? $_POST : $_GET;

   $action =isset($data['action']) ? (in_array($data['action'], ['diet', 'download', 'process', 'kill', 'purge', 'purgeMAX', 'notepad']) ? $data['action'] : 'diet') : 'diet';
   $logfile=isset($data['file']) ? urldecode(base64_decode(trim($data['file']))) : null;

if (!isset($logReader)) {
    $logReader=new logReader($logfile);
}

if (null != $logfile) {
    $arrFilters=[];
    $arr       =['date', 'enddate', 'method', 'notepad', 'referrer', 'remoteHost', 'userAgent', 'status', 'URL'];
    foreach ($arr as $key) {
        if ((isset($data[$key])) && ('' != trim($data[$key]))) {
            if (in_array($key, ['referrer', 'remoteHost', 'userAgent', 'URL'])) {
                // Decrypt first
                $arrFilters[$key]=urldecode(base64_decode($data[$key]));
            } else {
                $arrFilters[$key]=$data[$key];
            }
        }
    }

    $logReader->setFilters($arrFilters);

    switch ($action) {
        case 'purge': // 1. Purge
        case 'purgeMAX':
            echo $logReader->Purge();
            break;

        case 'diet':  // 2. Diet
            $logReader->Diet();
            break;

        case 'process': // 3. Process
            if (true === $logReader->ProcessFile()) {
                $logReader->Process();
            } else {
                echo sprintf('File not accessible: %s', $logfile);
            }
            break;

        case 'kill': // 4. Kill
            $logReader->Kill();

            break;
        case 'download':
            $logReader->DownloadFile($data['type']);
            die();
        case 'notepad': // Open the logfile with Notepad
            try {
                $cmd = 'Notepad++.exe "' . $logfile . '"';
                if ('Windows' == substr(php_uname(), 0, 7)) {
                    pclose(popen('start /B ' . $cmd, 'r'));
                }
            } catch (Exception $ex) {
            }

            break;
    } // switch ($action)
} else {
    // Step 0 : display the list of files of the logs folder
    $logReader->DisplayListOfFiles();
}
