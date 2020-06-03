<?php

require_once ROOT_PATH.'lib/sql.php';

function post_auftrag($path, $data)
{
    /* 
        Erstellt einen neuen Auftrag 
        Die UUID muss vom Client übertragen werden, damit Übermittlungsdoppel erkannt werden
        
    */
    $ret = array("status" => "error");
    if (!isset($data->uuid)) {
        $ret["message"] = "missing uuid";
        return $ret;
    }
    if (!isset($data->customerno)) {
        $ret["message"] = "missing customerno";
        return $ret;
    }

    
}




