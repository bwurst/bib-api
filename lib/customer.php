<?php

require_once ROOT_PATH.'lib/sql.php';



function post_customer_create($path_data, $data) 
{
    print_r($data);
}


function post_customer_check($pathdata, $data)
{
    /*
        Wenn Name und Telefonnummer in der Datenbank vorhanden sind, kann dem
        Kunde angezeigt werden, dass wir ihn kennen. Er muss dann keine Adresse 
        angeben.
        Zurückgegeben wird dann eine Kundennummer. Mehr Informationen werden 
        aber nicht ausgegeben und damit kann auch lediglich ein neuer Auftrag 
        erstellt werden, keine Einsicht in die Auftragshistorie.
    */
    if (!isset($data->phone) or !isset($data->name)) {
        return array("status" => "error", "message" => "insufficient data");
    }
    $ret = array();
    $ret["status"] = "error";
    $ret["message"] = "not found";
    $ret["customerno"] = null;

    $result = db_query("SELECT customerno FROM customers AS c LEFT JOIN customer_contacts AS cc ON (cc.customer = c.id) WHERE c.is_current = 1 AND (c.lastname LIKE ? OR c.company LIKE ?) AND cc.value = ?",
        array($data->name, $data->name, $data->phone));
    if ($result->rowCount() > 0) {
        /* Wenn es mehr als einen passenden Kunden gibt, ist es eine Duplette, wir nehmen immer den ersten. */
        $line = $result->fetch();
        $ret["status"] = "success";
        unset($ret["message"]);
        $ret["customerno"] = $line["customerno"];
    }

    return $ret;
}



function post_customer_identify($pathdata, $data)
{
    /*
        Bei Angabe von Name, Telefonnummer und Presstermin aus dem letzten Jahr 
        wird dem Kunde der komplette Kunden-Datensatz mitgeteilt mit UUID. Mit 
        diesem Datensatz ist eine Auftragshistorie und das Bearbeiten der 
        Kundendaten möglich.
    */
    if (!isset($data->phone) or !isset($data->name) or !isset($data->date)) {
        return array("status" => "error", "message" => "insufficient data");
    }

    return array("customerno" => null);
}


