<?php

require_once ROOT_PATH.'lib/sql.php';



function post_kunde_neu($path_data, $data) 
{
    print_r($data);
}


function post_kunde_pruefen($pathdata, $data)
{
    /*
        Wenn Name und Telefonnummer in der Datenbank vorhanden sind, kann dem
        Kunde angezeigt werden, dass wir ihn kennen. Er muss dann keine Adresse 
        angeben.
        Zurückgegeben wird dann eine Kundennummer und der Basis-Kunden-Datensatz 
        alle vorhandenen Felder konvertiert zu "bekannt", außer dem übergebenen 
        Namen. Weitere Informationen werden nicht ausgegeben und damit kann auch 
        lediglich ein neuer Auftrag erstellt werden, keine Einsicht in die 
        Auftragshistorie.
    */
    if (!isset($data->telefon) or !isset($data->name)) {
        return array("status" => "error", "message" => "insufficient data");
    }
    $ret = array();
    $ret["status"] = "error";
    $ret["message"] = "not found";
    $ret["kundennr"] = null;

    $result = db_query("SELECT k.kundennr, k.json FROM kunde AS k LEFT JOIN kundenkontakt AS kk ON (kk.kunde=k.id) WHERE k.aktuell = 1 AND (k.nachname LIKE ? OR k.firma LIKE ?) AND kk.wert = ?",
        array($data->name, $data->name, $data->telefon));
    if ($result->rowCount() > 0) {
        /* Wenn es mehr als einen passenden Kunden gibt, ist es eine Duplette, wir nehmen immer den ersten. */
        $line = $result->fetch();
        $kunde = json_decode($line["json"], true);
        $ret["status"] = "success";
        unset($ret["message"]);
        $ret["kundennr"] = $kunde["kundennr"];
        foreach (array("vorname", "nachname", "firma", "adresse", "plz", "ort") as $field) {
            if ($kunde[$field]) {
                $ret[$field] = 'bekannt';
            }
        }
        if (strtolower($kunde["nachname"]) == strtolower($data->name)) {
            $ret["nachname"] = $kunde["nachname"];
        }
        if (strtolower($kunde["firma"]) == strtolower($data->name)) {
            $ret["firma"] = $kunde["firma"];
        }
    }

    return $ret;
}



function post_kunde_identifizieren($pathdata, $data)
{
    /*
        Bei Angabe von Name, Telefonnummer und Presstermin aus dem letzten Jahr 
        wird dem Kunde der komplette Kunden-Datensatz mitgeteilt mit UUID. Mit 
        diesem Datensatz ist eine Auftragshistorie und das Bearbeiten der 
        Kundendaten möglich.
    */
    if (!isset($data->telefon) or !isset($data->name) or !isset($data->datum)) {
        return array("status" => "error", "message" => "insufficient data");
    }

    $c = post_kunde_pruefen($pathdata, $data);
    $kundennr = $c["kundennr"];
    if (! $kundennr) {
        return array("status" => "error", "message" => "customer not found");
    }

    $result = db_query(/* */);

    return array("kundennr" => null);
}


