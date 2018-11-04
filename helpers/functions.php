<?php

class logReaderFct
{
    public static function human_filesize($bytes, $decimals = 2)
    {
        return number_format($bytes);
    }

   /**
    * Custom error handler.  Display the first error/warning/notice/... and stop the execution.
    * Aim : error free script
    *
    * @param type $errno
    * @param type $errstr
    * @param type $errfile
    * @param type $errline
    * @return boolean
    */
    public static function logreader_ErrorHandler($errno, $errstr, $errfile, $errline)
    {
        
        echo '<html><head>';
        echo '<link href="assets/css/bootstrap.min.css" rel="stylesheet" />';
        echo '<style>'.
         'body{color:#fff;background-color:#851507;font:14px/1.5 Helvetica,Arial,sans-serif;}'.
         '.panel{padding:10px;white-space:pre-line;border-radius:10px;background-color:#b34334;}'.
         '.panel-heading{background-color:#FA9A8D !important;}'.
         '.panel-title{font-weight:bold;}'.
         'pre{padding:10px;border-radius:10px;background-color:#FA9A8D;}'.
         '</style>';
        echo '</head><body>';
        echo '<div class="container">';
        echo '<div id="accordion" class="panel-group">';      
        echo '<h1>'.__CLASS__.'::'.__FUNCTION__.'()</h1>';
        echo '<h2>Error in file '.$errfile.', line '.$errline.'</h2>';
        echo '<h3>'.$errstr.'</h3>';

        $arr=debug_backtrace();
        $i=0;

        foreach ($arr as $row) {
            $class=(isset($row['class'])?$row['class'].'::':'');
            $fct=$row['function'].'()';
            $line=(isset($row['line'])?' - line '.$row['line']:'');
            $panelTitle=$class.$fct.$line;

            echo '<div class="panel panel-default">';
            echo '   <div class="panel-heading">';
            echo '      <h4 class="panel-title">';
            echo '         <a data-toggle="collapse" data-parent="#accordion" href="#collapse'.$i.'">'.$i.' - '.$panelTitle.'</a>';
            echo '      </h4>';
            echo '   </div>';
            echo '   <div id="collapse'.$i.'" class="panel-collapse collapse '.($i==1?'in':'').'">';
            echo '      <div class="panel-body">';
            echo '          <pre>'.print_r($row, true).'</pre>';
            echo '      </div>';
            echo '   </div>';
            echo '</div>';

            $i++;
        }

        echo '   </div>';
        echo '</div>';

        echo '<script type="text/javascript" src="assets/js/jquery.min.js"></script>';
        echo '<script type="text/javascript" src="assets/js/bootstrap.min.js"></script>';
        echo '</body></html>';

        die();

        // Don't run PHP normal error handler
        return true;
    }

    private static function SimplifyURLs($url, &$arrSimpiflyURLs = null)
    {

        if ($arrSimpiflyURLs==null) {
            return $url;
        }
      
        if (count($arrSimpiflyURLs) > 0) {
            foreach ($arrSimpiflyURLs as $row) {
                foreach ($row as $regex => $value) {
                    // Make the control case insensitive
                    $regex='/'.$regex.'/i';

                    $matches = array();
                    preg_match($regex, $url, $matches);

                    if (isset($matches[0])) {
                        $url=preg_replace($regex, '', $url);
                    }
                }
            }
        }

        return $url;
    }

    /**
     * Split a string coming from an Apache log file into an associative array
     *
     * Explanation of an Apache log file
     * @url : https://httpd.apache.org/docs/2.2/fr/logs.html (https://httpd.apache.org/docs/2.2/en/logs.html)
      *
     * @param type $line
     * @return type
     */
    public static function processApacheLogLine($line, &$arrSimplifyURLs = null)
    {

        $return=array();
        $matches = array();

        // Decode the url in case of accentuated
        $line=str_replace('\\', '#', urldecode($line));

        // process the string. This regular expression was adapted from
        // http://oreilly.com/catalog/perlwsmng/chapter/ch08.html
        $regex='/^(\S+) (\S+) (\S+) \[([^:]+):(\d+:\d+:\d+) ([^\]]+)\] "(\S+) (.+?) (\S+)" (\S+) (\S+) "([^"]*)" "(.+)"$/';

        preg_match($regex, html_entity_decode($line), $matches);
 
        if (isset($matches[0])) {
            $return=array(
            //'fullString' => $matches[0],
            
            // This is the IP address of the client (remote host) which made the request to the server
            'remoteHost' => $matches[1],
            
            // Unreliable information, identity of the remote user; highly unreliable; often not present (set to a hyphen sign "-")
            //'identUser' => $matches[2],
            
            // This is the userid of the person requesting the document as determined by HTTP authentication. User used through an .htpasswd f.i.
            'authUser' => $matches[3],
            
            // The time that the request was received. The format is:
            'date' => self::ConvertLogDate($matches[4]),
            //'time' => $matches[5],
            //'timezone' => $matches[6],
            
            // HTTP method (POST, GET, HEAD, ...)
            'method' => $matches[7],
            
            // Requested URL
            'url' => ltrim(rtrim($matches[8])),
            'simplified_url' => ltrim(rtrim(self::SimplifyURLs($matches[8], $arrSimplifyURLs))),
            
            // HTTP protocol  (often HTTP/1.0)
            'protocol' => $matches[9],
            
            // HTTP return code (200, 403, 404, 500, ...)
            'status' => $matches[10],
            
            // Size of the object returned to the client, not including the response headers.
            'bytes' => ($matches[11]=='-'?0:$matches[11]),
                        
            // Unreliable information, referrer; if not set/known, a hyphen sign "-" will be found
            'referrer' => $matches[12],
            
            // userAgent, Unreliable information
            // The User-Agent HTTP request header. This is the identifying information that the client browser reports about itself.
            'userAgent' => $matches[13]
            );
        }

        if (isset($return['url'])) {
            //$return['url']= htmlentities($return['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (isset($return['simplified_url'])) {
            $return['simplified_url']= htmlentities($return['simplified_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $return;
    } // function processApacheLogLine()

    /**
     * Convert a date in the 25/DEC/2014 notation into 2014-12-24 notation
     *
     * @param type $sDate
     * @return boolean
     */
    public static function ConvertLogDate($sDate)
    {
        $sDate = preg_replace('#:#', ' ', $sDate, 1);
        $sDate = str_replace('/', ' ', $sDate);
        if (!$t = strtotime($sDate)) {
            return false;
        }
        return date('Y-m-d', $t);
    }

    /**
     * Private var_dump function
     *
     * @param type $var
     * @param type $die   If true, the code will stop once the dump has been displayed
     * @return boolean
     */
    public static function var_dump($var, $title = null, $die = true)
    {
        $arr=debug_backtrace();
        $i=0;

        $dbg=1;

        $class=(isset($arr[$dbg]['class'])?$arr[$dbg+1]['class'].'::':'');
        $fct=$arr[$dbg+1]['function'].'()';
        $line=(isset($arr[$dbg]['line'])?' - line '.$arr[$dbg]['line']:'');
        $panelTitle=$class.$fct.$line;

        echo '<html><head>';
        echo '<link href="assets/css/bootstrap.min.css" rel="stylesheet">';
        echo '<style>'.
         'body{color:#fff;background-color:#851507;font:14px/1.5 Helvetica,Arial,sans-serif;}'.
         '.panel{padding:10px;white-space:pre-line;border-radius:10px;background-color:#b34334;}'.
         '.panel-heading{background-color:#FA9A8D !important;}'.
         '.panel-title{font-weight:bold;}'.
         'pre{padding:10px;border-radius:10px;background-color:#FA9A8D;}'.
         '</style>';
        echo '</head><body>';
        echo '<div class="container">';
        echo '   <div id="accordion" class="panel-group">';
        echo '   <h1>'.__CLASS__.'::'.__FUNCTION__.'()</h1>';
        if ($title!=null) {
            echo '   <h2>'.$title.'()</h2>';
        }
        echo '      <div class="panel panel-default">';
        echo '         <div class="panel-heading">';
        echo '            <h4 class="panel-title">';
        echo '               <a data-toggle="collapse" data-parent="#accordion" href="#collapse'.$i.'">'.$i.' - '.$panelTitle.'</a>';
        echo '            </h4>';
        echo '         </div>';
        echo '         <div id="collapse'.$i.'" class="panel-collapse collapse '.($i==0?'in':'').'">';
        echo '            <div class="panel-body">';
        echo '                <pre>'.print_r($var, true).'</pre>';
        echo '            </div>';
        echo '         </div>';
        echo '      </div>';
        echo '   </div>';
        echo '</div>';
        echo '<script src="assets/js/jquery.min.js"></script>';
        echo '<script src="assets/js/bootstrap.min.js"></script>';
        echo '</body></html>';

        if ($die) {
            die();
        }
        return true;
    }

    /**
     * Be sure that the accessed entry from the JSON file exists.  If so, return the value stored in that entry,
     * otherwise return a default value
     *
     * @param type $json
     * @param type $node1             For instance 'settings'
     * @param type $node2                          'ProcessFirstRow'
     * @param type $node3                          'SkipFirstLines'
     * @param type $defaultValue
     * @return type
     */
    public static function getJSONValue($json, $node1, $node2 = null, $node3 = null, $node4 = null, $defaultValue = null)
    {

        $return=$defaultValue;

        if (isset($json[$node1])) {
            if ($node2!=null) {
                if (isset($json[$node1][$node2])) {
                    if ($node3!=null) {
                        if (isset($json[$node1][$node2][$node3])) {
                            if ($node4!=null) {
                                if (isset($json[$node1][$node2][$node3][$node4])) {
                                    $return=$json[$node1][$node2][$node3][$node4];
                                } else {
                                    self::var_dump($json, 'JSON entry [\''.$node1.'\'][\''.$node2.'\'][\''.$node3.'\']\''.$node4.'\'] not found');
                                }
                            } else {
                                 $return=$json[$node1][$node2][$node3];
                            }
                        } else {
                            self::var_dump($json, 'JSON entry [\''.$node1.'\'][\''.$node2.'\'][\''.$node3.'\']\' not found');
                        }
                    } else {
                        $return=$json[$node1][$node2];
                    }
                } else {
                    self::var_dump($json, 'JSON entry [\''.$node1.'\'][\''.$node2.'\'] not found');
                }
            } else {
                $return=$json[$node1];
            }
        } else {
            self::var_dump($json, 'JSON entry [\''.$node1.'\'][\''.$node2.'\'] not found');
        }

        return $return;
    }

    /**
     * Simple function to format a figure
     * @param type $nbr
     * @return type
     */
    public static function ShowFigure($nbr)
    {
        return '&nbsp;<span class="badge">'.number_format($nbr).'</span>';
    }

    /**
     * Compress a file
     *
     *    If $filename is set to C:\site\logreader\logs\201411.log, the zip filename will be
     *       C:\site\logreader\logs\201411.log.zip (i.e. with ".zip" as suffix).
     *
     * @param type $filename
     * @return boolean
     */
    public static function MakeZip($ZIPFileName, $FileToZip, $overwrite = false)
    {
       // Keep an archive of the file before starting to remove rows
        if (($overwrite==true) || (!file_exists($ZIPFileName))) {
            $zip = new ZipArchive();
            $zip->open($ZIPFileName, ZipArchive::CREATE);
            $zip->addFile($FileToZip);
            $zip->close();
        }
        return true;
    }

    /**
     *
     *
     * $arr is an array like this one :
     *
     *  $arr=array();
     *  $arr['.*(/?(keyword|keyword2)?(/administrator/index.php).*)$'=>'Joomla backend'];
     *  $arr['/aesecure/setup.php'=>'aeSecure Interface']
     *
     * i.e. the regex expression as key and a comment as value
     *
     * @param type $checkValue
     * @param type $arr
     * @return type
     */
    public static function CheckValueAgainstRegex($checkValue, $arr)
    {

        $dbg=debug_backtrace(null);

        if (isset($dbg[1])) {
            $caller=(isset($dbg[1]['function'])?$dbg[1]['function'].'()&nbsp;-&nbsp;':'');
        } else {
            $caller='';
        }

        $isFound=false;
        $found=null;
        $reason=null;
        $regex=null;

        if (count($arr) > 0) {
            foreach ($arr as $row) {
                foreach ($row as $regex => $value) {
                    $tmp=str_replace('/', '\/', $regex);
                    $tmp='/'.rtrim(ltrim($tmp, '\/'), '\/').'/';

                    // Make the control case insensitive
                    $tmp.='i';

                    $matches = array();
                    preg_match($tmp, $checkValue, $matches);

                    if (isset($matches[0])) {
                        $isFound=true;

                        $found=isset($matches[1])?$matches[1]:'';
                        if (strpos($value, '@FOUND@')!==false) {
                            $value=str_replace('@FOUND@', '<span class=\'BadHighlight\'>'.$found.'</span>', $value);
                        }

                        $reason=$caller.$value;
                        break;
                    }
                }

                if ($isFound) {
                    break;
                }

            }
        }

        return array($isFound,$found,$reason,$regex);
    }

    /**
     * Return the current URL (without querystring)
     *
     * @param type $use_forwarded_host
     * @return type
     */
    public static function GetCurrentURL($path = true, $querystring = true, $use_forwarded_host = false)
    {
        $ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? true:false;
        $sp = strtolower($_SERVER['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
        $port = $_SERVER['SERVER_PORT'];
        $port = ((!$ssl && $port=='80') || ($ssl && $port=='443')) ? '' : ':'.$port;
        $host = ($use_forwarded_host && isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null);
        $host = isset($host) ? $host : $_SERVER['SERVER_NAME'] . $port;
        return $protocol . '://' . $host . ($path==true?$_SERVER['PHP_SELF']:'').($querystring==true?'?'.$_SERVER['QUERY_STRING']:'');
    }

    /**
     * Apply a filter and return FALSE when nothing should be filtered or -1 when the filter wasn't found
     *
     * called by logreader.php like this :
     *    if(($found=logReaderFct::applyFilter($this->_arrfilterURL, $this->entry, 'url'))!==FALSE) {
     *       if ($found==-1) continue;
     *    }
     *
     * @param type $arrFilters
     * @param type $entry
     * @param type $key
     * @return type    FALSE when there is no filter at all or not found (just like strpos function)
     *                 -1 when there are filters to look for and when none are found
     *                 otherwise return the position of the filter (can be 0 if the filter starts the string)
    */
    public static function applyFilter(&$arrFilters, &$entry, $key)
    {
        if ($arrFilters==[]) {
            return false;
        }

        static $i=0;

        $found=false;

        if (count($arrFilters)>0) {
            $found=-1;

            if (isset($entry[$key])) {
                foreach ($arrFilters as $filter) {
                   // Don't know why, the dot is stored as "&period;" in the string to search, the equal sign is "&equals;"
                    $filter=str_replace('=', '&equals;', str_replace('.', '&period;', 'task=user.reg'));
                    $value=$entry[$key];
                    if (stripos($value, $filter)!==false) {
                        $found=stripos($entry[$key], $filter);
                        break;
                    }
                }
            }
        }

        return $found;
    }

    public static function ShowFriendlySize($fsizebyte)
    {
        if ($fsizebyte < 1024) {
            $fsize = $fsizebyte."&nbsp;bytes";
        } elseif (($fsizebyte >= 1024) && ($fsizebyte < 1048576)) {
            $fsize = round(($fsizebyte/1024), 2);
            $fsize = $fsize."&nbsp;KB";
        } elseif (($fsizebyte >= 1048576) && ($fsizebyte < 1073741824)) {
            $fsize = round(($fsizebyte/1048576), 2);
            $fsize = $fsize."&nbsp;MB";
        } elseif ($fsizebyte >= 1073741824) {
            $fsize = round(($fsizebyte/1073741824), 2);
            $fsize = $fsize."&nbsp;GB";
        }

        return $fsize;
    }

}
