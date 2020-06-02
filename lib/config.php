<?php


function config($key) 
{
    include ROOT_PATH.'config.php';
    if (isset($config[$key])) {
        return $config[$key];
    } else {
        return null;
    }

}

