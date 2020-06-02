<?php

require_once ROOT_PATH.'lib/config.php';
$debugmode = (isset($_GET['debug']) && config('enable_debug'));

function DEBUG($str)
{
    global $debugmode;
    if ($debugmode) {
        if (is_array($str)) {
            array_walk_recursive($str, function (&$v) {
                $v = htmlspecialchars($v);
            });
            echo "<pre>".print_r($str, true)."</pre>\n";
        } elseif (is_object($str)) {
            echo "<pre>".print_r($str, true)."</pre>\n";
        } else {
            echo htmlspecialchars($str) . "<br />\n";
        }
    }
}


function system_failure($reason) 
{
    echo $reason;
    die();
}
