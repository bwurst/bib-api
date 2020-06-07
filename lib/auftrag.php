<?php

require_once ROOT_PATH.'lib/sql.php';
require_once ROOT_PATH.'lib/kunde.php';


function post_auftrag_lesen($path, $data)
{
    /* Liest den Auftrag aus mit dem übergebenen Handle */
    if (!isset($data["handle"])) {
        return array("status" => "error", "message" => "missing handle");
    }
    $result = db_query("SELECT json FROM auftrag WHERE handle=? AND aktuell=1", array($data['handle']));
    if ($result->rowCount() < 1) {
        $new = array("auftrag" => neuer_auftrag());
        $new['auftrag']['handle'] = $data["handle"];
        return $new;
    }
    $row = $result->fetch();
    return array("auftrag" => json_decode($row['json'], true));
}


function post_auftrag_anlieferung($path, $data)
{
    /* 
        Erstellt einen neuen Auftrag oder ändert einen bestehenden
        Das Handle muss vom Client übertragen werden, damit Übermittlungsdoppel erkannt werden, 
        das gilt auch als Authentifizierung bei Änderungen für diesen Auftrag.
    */
    if (!isset($data["handle"])) {
        return array("status" => "error", "message" => "missing handle");
    }
    $auftrag = neuer_auftrag();
    $auftrag['handle'] = $data['handle'];
    $mode = 'new';
    db_query("LOCK TABLE auftrag WRITE, kunde READ");
    $result = db_query("SELECT id, revision, status, json FROM auftrag WHERE handle=? AND aktuell=1", array($data['handle']));
    if ($result->rowCount() > 0) {
        $row = $result->fetch();
        if (!$row['status'] || $row['status'] == 'bestellt') {
            // Produktion noch nicht begonnen, Update noch möglich.
            $mode = 'update';
            $auftrag = json_decode($row['json'], true);
        } else {
            db_query("UNLOCK TABLES");
            api_send_error(403, 'keine Änderung mehr möglich');
        }
    }

    // Kundennr kann geändert werden, dann aber revision neu auslesen
    if (isset($data['kundennr']) && $auftrag['kundennr'] != $data['kundennr']) {
        $auftrag['kundennr'] = $data['kundennr'];
        $auftrag['kundenrevision'] = 0;
    }
    if (! $auftrag['kundenrevision']) {
        if ($auftrag['kundennr']) {
            // Kunden-Revision bezieht sich auf die aktuelle Version des Kunden-Datensatzes wenn der Kunde festgelegt wird.
            $result = db_query("SELECT MAX(revision) AS revision FROM kunde WHERE kundennr=?", array($auftrag['kundennr']));
            $line = $result->fetch();
            $auftrag['kundenrevision'] = $line['revision'];
        } else {
            $auftrag['kundennr'] = null;
            $auftrag['kundenrevision'] = 0;
        }
    }

    $fields = array("firma", "vorname", "nachname", "adresse", "plz", "ort", "telefon");
    foreach ($fields as $f) {
        if (isset($data['kundendaten'][$f])) {
            $auftrag['kundendaten'][$f] = $data['kundendaten'][$f];
        }
    }
    
    $auftrag["status"] = $data['status'];
    $auftrag['bestellung'] = $data['bestellung'];
    $auftrag['originale'] = $data['originale'];
    $auftrag['name'] = $data['name'];
    $auftrag['telefon'] = $data['telefon'];
    $auftrag['abholung'] = $data['abholung'];
    $auftrag['paletten'] = $data['paletten'];
    $auftrag['bio'] = $data['bio'];
    $auftrag['biokontrollstelle'] = $data['biokontrollstelle'];
    $auftrag['summe_betrag'] = $data['summe_betrag'];
    $auftrag['summe_liter'] = $data['summe_liter'];
    $auftrag['posten'] = $data['posten'];

    /* FIXME: Syntax- und Plausibilitätsprüfungen gehören hier hin. */

    $auftrag['revision'] += 1;

    db_query("START TRANSACTION");
    db_query("INSERT INTO auftrag (handle, revision, status, kundennr, kundenrevision, bestellung_json, json) VALUES (?, ?, ?, ?, ?, ?, ?)", array(
            $auftrag['handle'], $auftrag['revision'], $auftrag['status'], $auftrag['kundennr'], $auftrag['kundenrevision'], json_encode($auftrag['bestellung']), json_encode($auftrag)));
    $id = db_insert_id();
    db_query("UPDATE auftrag SET aktuell=0 WHERE handle=? AND id != ?", array($auftrag['handle'], $id));
    db_query("COMMIT");
    db_query("UNLOCK TABLES");
    
}





function neuer_auftrag() {
    $ret = array(
        "handle" => null,
        "revision" => 0,
        "aktuell" => 1,
        "erstellt" => time(),
        "geaendert" => time(),
        "user" => null,
        "kundennr" => null,
        "kundenrevision" => 0,
        "kundendaten" => array(
            "vorname" => null,
            "nachname" => null,
            "adresse" => null,
            "plz" => null,
            "ort" => null,
            "telefon" => null,
            "email" => null,
            ),
        "status" => "",
        "bestellung" => array(
            array(
                "obstart" => array('apfel'),
                "menge" => "",
                "termin" => null,
                "gitterbox" => array(
                    //array("id" => 0)
                    ),
                "anhaenger" => array(
                    //array("kennz" => "")
                    ),
                "gebrauchte" => null,
                "neue" => array(
                    // "5er" => "50%",
                    // "10er" => "50%",
                    // "sonstiges" => "Freitext"
                    ),
                "frischsaft" => null,
                "anmerkungen" => null,
                )
            ),
        "originale" => array(
            // array("handle" => "...", "revision" => 1)
            ),
        "name" => null,
        "telefon" => null,
        "abholung" => null,
        "paletten" => null,
        "bio" => false,
        "biokontrollstelle" => null,
        "summe_betrag" => 0,
        "summe_liter" => 0,
        "posten" => array(
            /*array(
                "preislisten_id": "string",
                "anzahl": "string",
                "beschreibung": "string",
                "einzelpreis": "string",
                "liter_pro_einheit": "string",
                "einheit": "string",
                "steuersatz": "string",
                "datum": "string"
                )*/
            )
        );
    return $ret;
}


