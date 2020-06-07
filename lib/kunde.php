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
    api_ratelimit();
    /* 
    Legt einen neuen Kunden an.
    */
    if (!isset($data["kunde"])) {
        return array("status" => "error", "message" => "insufficient data 1");
    }
    $kdata = $data["kunde"];
    if (!(isset($kdata["nachname"]) || isset($kdata["firma"])) || !isset($data["uuid"])) {
        return array("status" => "error", "message" => "insufficient data 2");
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
        return array("status" => "error", "message" => "insufficient data 3");
    }
    
    foreach (array("firma", "vorname", "nachname", "adresse", "plz", "ort", "bio") as $field) {
        if (isset($kdata[$field]) && $kdata[$field]) {
            $kunde[$field] = $kdata[$field];
        }
    }
    foreach ($kdata["kontakt"] as $kontakt) {
        $k = array("id" => null, "typ" => "telefon", "wert" => null, "notizen" => null);
        if (isset($kontakt["typ"]) && in_array($kontakt["typ"], array("telefon", "mobil"))) {
            $k["typ"] = $kontakt["typ"];
            $k["wert"] = telefonnummer($kontakt["wert"]);
            if (! $k["wert"]) {
                return array("status" => "error", "message" => "invalid phone number: ".$kontakt["wert"]);
            }
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

    $result = db_query("SELECT kundennr FROM kunde AS k LEFT JOIN kundenkontakt AS kk ON (k.id=kk.kunde) WHERE (k.nachname LIKE ? OR k.firma LIKE ?) AND kk.wert=?", array($kunde["nachname"], $kunde["firma"], $kunde["kontakt"][0]["wert"]));
    if ($result->rowCount() > 0) {
        return array("status" => "error", "message" => "duplicate");
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
    api_ratelimit();
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

    $vorname = null;
    $nachname = null;
    $query = "SELECT k.kundennr, k.json FROM kunde AS k LEFT JOIN kundenkontakt AS kk ON (kk.kunde=k.id) WHERE k.aktuell = 1 AND (k.nachname LIKE ? OR k.firma LIKE ?) AND kk.wert = ?";
    $query_params = array($data["name"], $data["name"], telefonnummer($data["telefon"]));
    if (strpos($data["name"], ' ') !== false) {
        // Wenn Leerzeichen enthalten, dann kann es auch Vorname und Nachname sein
        $parts = explode(" ", $data['name']);
        $nachname = array_pop($parts);
        $vorname = implode(" ", $parts);
        $query = "SELECT k.kundennr, k.json FROM kunde AS k LEFT JOIN kundenkontakt AS kk ON (kk.kunde=k.id) WHERE k.aktuell = 1 AND (k.nachname LIKE ? OR k.firma LIKE ? OR (k.vorname LIKE ? AND k.nachname LIKE ?)) AND kk.wert = ?";
        $query_params = array($data["name"], $data["name"], $vorname, $nachname, telefonnummer($data["telefon"]));
    }
    $result = db_query($query, $query_params);
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
        if (strtolower($kunde["vorname"]) == strtolower($vorname)) {
            $ret["vorname"] = $kunde["vorname"];
        }
        if (strtolower($kunde["nachname"]) == strtolower($data["name"]) || strtolower($kunde["nachname"]) == strtolower($nachname)) {
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
    api_ratelimit();
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


function kunde_laden($kundennr) 
{
    $result = db_query("SELECT json FROM kunde WHERE kundennr=? AND aktuell=1", array($neu['kundennr']));
    $row = $result->fetch();
    $kunde = json_decode($row, true);
    return $kunde;
}



function kunde_aendern($neu) 
{
    $aenderungen = false;
    $kunde = kunde_laden($neu['kundennr']);
    $fields = array("firma", "vorname", "nachname", "adresse", "plz", "ort", "bio", "biokontrollstelle", "notizen", "uuid");
    foreach ($fields as $key) {
        if ($neu[$field] != $kunde[$field]) {
            $kunde[$field] = $neu[$field];
            $aenderungen = true;
        }
    }
    if (count($neu['kontakt']) != count($kunde['kontakt'])) {
        $aenderungen = true;
    }
    foreach ($neu['kontakt'] as $idx => $kontakt) {
        foreach (array("typ", "wert", "notizen") as $field) {
            if ($kunde['kontakt'][$idx][$field] != $kontakt[$field]) {
                $aenderungen = true;
            }
        }
    }

    if ($aenderungen) {
        $neu['revision'] = $kunde['revision'] + 1;
        db_query("START TRANSACTION");
        db_query("INSERT INTO kunde (uuid, kundennr, revision, vorname, nachname, firma, json) VALUES (?, ?, ?, ?, ?, ?, ?)", array($neu["uuid"], $neu['kundennr'], $neu['revision'], $neu['vorname'], $neu['nachname'], $neu['firma'], json_encode($neu)));
        $id = db_insert_id();

        foreach ($neu["kontakt"] as $k) {
            db_query("INSERT INTO kundenkontakt (kunde, typ, wert, notizen) VALUES (?, ?, ?, ?)", array($id, $k["typ"], $k["wert"], $k["notizen"]));
            $k["id"] = db_insert_id();
        }

        db_query("UPDATE kunde SET aktuell=0 WHERE kundennr=? AND id!=?", array($neu["kundennr"], $id));
        db_query("COMMIT");
    }
}



