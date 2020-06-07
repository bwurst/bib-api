<?php

require_once ROOT_PATH.'lib/config.php';
require_once ROOT_PATH.'lib/api.php';
$debugmode = (isset($_GET['debug']) && config('enable_debug'));

$debug_content = array();

function DEBUG($str)
{
    global $debugmode;
    global $debug_content;
    if ($debugmode) {
        if (is_array($str)) {
            array_walk_recursive($str, function (&$v) {
                $v = htmlspecialchars($v);
            });
            $debug_content[] = $str;
        } elseif (is_object($str)) {
            $debug_content[] = $str;
        } else {
            $debug_content[] = htmlspecialchars($str);
        }
    }
}


function system_failure($reason) 
{
    api_send_error('1', $reason);
}
