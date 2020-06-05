<?php

require_once ROOT_PATH.'lib/sql.php';


function telefonnummer($number, $country = "DE")
{
    $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
    try {
        $phoneNumber = $phoneNumberUtil->parse($number, $country);
    } catch (Exception $e) {
        return null;
    }
    if ($phoneNumberUtil->isValidNumber($phoneNumber)) {
        return $phoneNumberUtil->format($phoneNumber, 1);
    }
    return null;
}




function post_kunde_neu($path_data, $data) 
{
    /* 
    Legt einen neuen Kunden an.
    */
    if (!isset($data["kunde"])) {
        return array("status" => "error", "message" => "insufficient data");
    }
    $kdata = $data["kunde"];
    if (!(isset($kdata["nachname"]) || isset($kdata["firma"])) || !isset($data["uuid"])) {
        return array("status" => "error", "message" => "insufficient data");
    }
    $kunde = array(
        "kundennr" => null,
        "revision" => 0,
        "uuid" => null,
        "firma" => null,
        "vorname" => null,
        "nachname" => null,
        "adresse" => null,
        "plz" => null,
        "ort" => null,
        "bio" => 0,
        "biokontrollstelle" => null,
        "erstellt" => date("Y-m-d"),
        "notizen" => "erstellt über die API!",
        "kontakt" => array());
        //array("id" => null, "typ" => "telefon", "wert" => null, "notizen" => null)));

    $kunde["uuid"] = $data["uuid"];
    if (!isset($kdata["kontakt"]) || !is_array($kdata["kontakt"]) || !isset($kdata["kontakt"][0]["wert"])) {
        return array("status" => "error", "message" => "insufficient data");
    }
    
    foreach (array("firma", "vorname", "nachname", "adresse", "plz", "ort", "bio") as $field) {
        if (isset($kdata[$field]) && $kdata[$field]) {
            $kunde[$field] = $kdata[$field];
        }
    }
    foreach ($kdata["kontakt"] as $kontakt) {
        $k = array("id" => null, "typ" => "telefon", "wert" => null, "notizen" => null);
        if (isset($kontakt["typ"]) && in_array($kontakt["typ"], array("telefon", "mobile"))) {
            $k["typ"] = $kontakt["typ"];
            $k["wert"] = telefonnummer($kontakt["wert"]);
        }
        if ($kontakt["typ"] == "email") {
            $k["typ"] = "email";
            $k["wert"] = filter_var($kontakt["wert"], FILTER_VALIDATE_EMAIL);
        }
        if (isset($kontakt["notizen"])) {
            $k["notizen"] = $kontakt["notizen"];
        }
        $kunde["kontakt"][] = $k;
    }


    db_query("START TRANSACTION"); 
    db_query("INSERT INTO kunde (uuid, json) VALUES (?, '')", array($kunde["uuid"]));
    $id = db_insert_id();
    $result = db_query("SELECT MAX(kundennr)+1 AS kundennr FROM kunde");
    $res = $result->fetch();
    $kunde["kundennr"] = $res["kundennr"];

    foreach ($kunde["kontakt"] as $k) {
        db_query("INSERT INTO kundenkontakt (kunde, typ, wert, notizen) VALUES (?, ?, ?, ?)", array($id, $k["typ"], $k["wert"], $k["notizen"]));
        $k["id"] = db_insert_id();
    }

    db_query("UPDATE kunde SET kundennr=?, vorname=?, nachname=?, firma=?, json=? WHERE id=?", array($kunde["kundennr"], $kunde["vorname"], $kunde["nachname"], $kunde["firma"], json_encode($kunde), $id));
    db_query("COMMIT");

    return $kunde;

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
    if (!isset($data["telefon"]) or !isset($data["name"])) {
        return array("status" => "error", "message" => "insufficient data");
    }
    $ret = array();
    $ret["status"] = "error";
    $ret["message"] = "not found";
    $ret["kundennr"] = null;

    $result = db_query("SELECT k.kundennr, k.json FROM kunde AS k LEFT JOIN kundenkontakt AS kk ON (kk.kunde=k.id) WHERE k.aktuell = 1 AND (k.nachname LIKE ? OR k.firma LIKE ?) AND kk.wert = ?",
        array($data["name"], $data["name"], telefonnummer($data["telefon"])));
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
        if (strtolower($kunde["nachname"]) == strtolower($data["name"])) {
            $ret["nachname"] = $kunde["nachname"];
        }
        if (strtolower($kunde["firma"]) == strtolower($data["name"])) {
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
    if (!isset($data["telefon"]) or !isset($data["name"]) or !isset($data["datum"])) {
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


