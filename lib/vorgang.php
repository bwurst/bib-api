<?php

require_once ROOT_PATH.'lib/sql.php';
require_once ROOT_PATH.'lib/kunde.php';
require_once ROOT_PATH.'lib/filter.php';


function post_vorgang_lesen($path, $data)
{
    /* Liest den Vorgang aus mit dem übergebenen Handle */
    if (!isset($data["handle"])) {
        return array("status" => "error", "message" => "missing handle");
    }
    $result = db_query("SELECT json FROM vorgang WHERE handle=? AND aktuell=1", array($data['handle']));
    if ($result->rowCount() < 1) {
        $new = array("vorgang" => neuer_vorgang());
        $new['vorgang']['handle'] = $data["handle"];
        return $new;
    }
    $row = $result->fetch();
    return array("vorgang" => json_decode($row['json'], true));
}



function post_vorgang_produktion($path, $data)
{
    /*
        Ein Vorgang kann einem Produktionsschritt zugewiesen werden
    */
    if (!isset($data['handle'])) {
        api_send_error(500, "no handle");
    }
    if (!isset($data['ziel'])) {
        api_send_error(500, "kein ziel");
    }
    if (!isset($data['aktiv']) || !is_numeric($data['aktiv'])) {
        api_send_error(500, "keine aktion");
    }
    if ($data['ziel'] == 'anlieferung' && $data['aktiv'] == 1) {
        // Anlieferung kann der Kunde anonym machen
    } else {
        // Alles andere kann nur vom Admin-Terminal gemacht werden.
        api_require_role(4); // FIXME: Konstanten!
    }
    $result = db_query("SELECT MAX(position) AS position FROM vorgang_produktion WHERE ziel=? AND handle!=? AND ausgebucht IS NULL", 
        array($data['ziel'], $data['handle']));
    $row = $result->fetch();
    $maxposition = $row['position'];
    if (!isset($data['position'])) {
        $data['position'] = $maxposition + 1;
    }
    if ($data['position'] > $maxposition + 1) {
        $data['position'] = $maxposition + 1;
    }
    $result = db_query("SELECT id, position FROM vorgang_produktion WHERE handle=? AND ziel=? AND ausgebucht IS NULL", 
        array($data['handle'], $data['ziel']));
    if ($result->rowCount() > 1) {
        api_send_error(500, 'Datenbank-Fehler: Vorgang mehrfach im Schritt '.$data['ziel'].' eingebucht.');
    }
    if ($data['aktiv'] == 0 && $result->rowCount() == 0) {
        api_send_error(500, "Ausbuchung gefordert, Vorgang ist aber nicht eingebucht!");
    }
    $row = $result->fetch();
    if ($data['aktiv'] == 0) {
        // ausbuchen
        db_query("UPDATE vorgang_produktion SET position=NULL, ausgebucht=CURRENT_TIMESTAMP() WHERE handle=? AND ziel=? AND ausgebucht IS NULL",
            array($data['handle'], $data['ziel']));
        // folgende Aufträge zusammen rücken
        db_query("UPDATE vorgang_produktion SET position=position-1 WHERE ziel=? AND position >= ? AND ausgebucht IS NULL",
            array($data['ziel'], $row['position']));
    } elseif ($result->rowCount() == 0) {
        if ($data['position'] <= $maxposition) {
            // Wir müssen Platz schaffen!
            db_query("UPDATE vorgang_produktion SET position = position + 1 WHERE ziel=? AND position >= ? AND ausgebucht IS NULL",
                array($data['ziel'], $data['position']));
        }
        // Neu einbuchen
        db_query("INSERT INTO vorgang_produktion (handle, ziel, position, eingebucht) VALUES (?, ?, ?, CURRENT_TIMESTAMP())",
            array($data['handle'], $data['ziel'], $data['position']));
    } elseif ($row['position'] != $data['position']) {
        // position ändern
        // zuerst die anderen Einträge zusammen schieben.
        db_query("UPDATE vorgang_produktion SET position=position-1 WHERE ziel=? AND position > ? AND ausgebucht IS NULL",
            array($data['ziel'], $row['position']));
        $result = db_query("SELECT MAX(position) AS position FROM vorgang_produktion WHERE ziel=? AND handle!=? AND ausgebucht IS NULL",
            array($data['ziel'], $data['handle']));
        $myrow = $result->fetch();
        $maxposition = $myrow['position'] + 1;
        if ($data['position'] > $maxposition) {
            $data['position'] = $maxposition;
        }
        // an der neuen Position Platz schaffen
        if ($data['position'] <= $maxposition) {
            db_query("UPDATE vorgang_produktion SET position = position + 1 WHERE ziel=? AND position >= ? AND ausgebucht IS NULL",
                array($data['ziel'], $data['position']));
        }
        // dann auf die neue position schieben
        db_query("UPDATE vorgang_produktion SET position=? WHERE handle=? AND ziel=? AND ausgebucht IS NULL",
            array($data['position'], $data['handle'], $data['ziel']));
    }
    db_query("COMMIT");
    return array();
}


function post_vorgang_anlieferung($path, $data)
{
    /* 
        Erstellt einen neuen Vorgang oder ändert einen bestehenden
        Das Handle muss vom Client übertragen werden, damit Übermittlungsdoppel erkannt werden, 
        das gilt auch als Authentifizierung bei Änderungen für diesen Vorgang.
    */
    // FIXME: hier sollten nicht alle Änderungen erlaubt sein
    vorgang_aendern($data);
}


function post_vorgang_aendern($path, $data)
{
    api_require_role(4); // FIXME: Konstanten
    vorgang_aendern($data);
}


function vorgang_aendern($data, $admin = false) 
{
    /*
        Ändern eines Vorgangs
    */
    if (!isset($data["handle"])) {
        return array("status" => "error", "message" => "missing handle");
    }
    $vorgang = neuer_vorgang();
    $vorgang['handle'] = $data['handle'];
    $mode = 'new';
    db_query("LOCK TABLE vorgang WRITE, kunde READ");
    $result = db_query("SELECT id, revision, status, json FROM vorgang WHERE handle=? AND aktuell=1", array($data['handle']));
    if ($result->rowCount() > 0) {
        $row = $result->fetch();
        $vorgang = json_decode($row['json'], true);
        if ($admin || !$vorgang['status']['gepresst']) {
            // Produktion noch nicht begonnen, Update noch möglich.
            $mode = 'update';
        } else {
            db_query("UNLOCK TABLES");
            api_send_error(403, 'keine Änderung mehr möglich');
        }
    }

    // Kundennr kann geändert werden, dann aber revision neu auslesen
    if (isset($data['kundennr']) && $vorgang['kundennr'] != $data['kundennr']) {
        $vorgang['kundennr'] = $data['kundennr'];
        $vorgang['kundenrevision'] = 0;
    }
    if (! $vorgang['kundenrevision']) {
        if ($vorgang['kundennr']) {
            // Kunden-Revision bezieht sich auf die aktuelle Version des Kunden-Datensatzes wenn der Kunde festgelegt wird.
            $result = db_query("SELECT MAX(revision) AS revision FROM kunde WHERE kundennr=?", array($vorgang['kundennr']));
            $line = $result->fetch();
            $vorgang['kundenrevision'] = $line['revision'];
        } else {
            $vorgang['kundennr'] = null;
            $vorgang['kundenrevision'] = 0;
        }
    }

    $fields = array("firma", "vorname", "nachname", "adresse", "plz", "ort", "telefon");
    foreach ($fields as $f) {
        if (isset($data['kundendaten'][$f])) {
            $vorgang['kundendaten'][$f] = $data['kundendaten'][$f];
        }
    }
    
    //$vorgang["status"] = $data['status'];
    $status = array();
    if (is_array($data["status"])) {
        foreach (array_keys($vorgang["status"]) as $key) {
            if (isset($data["status"][$key])) {
                $status[] = $key;
                $vorgang["status"][$key] = $data["status"][$key];
            } else {
                $vorgang["status"][$key] = null;
            }
        }
    } else {
        api_send_error(500, 'invalid status object');
    }
    $status = implode(',', $status);
    $vorgang['bestellung'] = $data['bestellung'];
    $vorgang['originale'] = $data['originale'];
    $vorgang['name'] = $data['name'];
    if (! $data['name']) {
        if ($data['kundendaten']['firma']) {
            $vorgang['name'] = $data['kundendaten']['firma'];
        } else {
            $vorgang['name'] = $data['kundendaten']['nachname'];
            if ($data['kundendaten']['vorname']) {
                $vorgang['name'] .= ', '.$data['kundendaten']['vorname'];
            }
        }
    }
    $vorgang['telefon'] = $data['telefon'];
    if (! $data['telefon']) {
        $vorgang['telefon'] = $data['kundendaten']['telefon'];
    }
    $vorgang['abholung'] = $data['abholung'];
    $vorgang['paletten'] = $data['paletten'];
    $vorgang['bio'] = $data['bio'];
    $vorgang['biokontrollstelle'] = $data['biokontrollstelle'];
    $vorgang['summe_betrag'] = $data['summe_betrag'];
    $vorgang['summe_liter'] = $data['summe_liter'];
    $vorgang['posten'] = $data['posten'];

    /* FIXME: Syntax- und Plausibilitätsprüfungen gehören hier hin. */

    $vorgang['revision'] += 1;

    db_query("START TRANSACTION");
    db_query("INSERT INTO vorgang (handle, revision, status, anlieferdatum, produktionsdatum, kundennr, kundenrevision, bestellung_json, json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", array(
            $vorgang['handle'], $vorgang['revision'], $status, $vorgang["status"]["angeliefert"], $vorgang["status"]["abgefuellt"], $vorgang['kundennr'], $vorgang['kundenrevision'], json_encode($vorgang['bestellung']), json_encode($vorgang)));
    $id = db_insert_id();
    db_query("UPDATE vorgang SET aktuell=0 WHERE handle=? AND id != ?", array($vorgang['handle'], $id));
    db_query("COMMIT");
    db_query("UNLOCK TABLES");
}


function post_vorgang_liste($path, $data)
{
    // FIXME: Konstanten wären gut
    api_require_role(4);

    $ret = array(
        "auftraege" => array(),
        "produktion" => array(),
        );
    if (isset($data['ziel'])) {
        $result = db_query("SELECT handle, position, eingebucht FROM vorgang_produktion WHERE ziel=? AND ausgebucht IS NULL ORDER BY position ASC",
            array($data['ziel']));
        while ($row = $result->fetch()) {
            $aresult = db_query("SELECT json FROM vorgang WHERE aktuell=1 AND handle=?",
                array($row['handle']));
            $a = $aresult->fetch();
            $ret["auftraege"][] = json_decode($a['json'], true);
            $ret["produktion"][$row['handle']] = array("position" => $row['position'], "eingebucht" => $row['eingebucht']);
        }

    } elseif (isset($data['filter'])) {
        list($sqlfilter, $sqlfilter_params) = filter($data['filter']);
        $sorting = sorting($data);

        $result = db_query("SELECT json FROM vorgang WHERE aktuell=1 AND ".$sqlfilter.' '.$sorting, $sqlfilter_params);
        while ($a = $result->fetch()) {
            $ret["auftraege"][] = json_decode($a['json'], true);
        }
    } else {
        
        api_send_error(500, 'no filter specified');
    }

    return $ret;

}



function neuer_vorgang() {
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
        "status" => array(
            // Jedes Feld kann einen Timestamp aufnehmen
            "bestellt" => null,
            "bestaetigt" => null,
            "angeliefert" => null,
            "gepresst" => null,
            "abgefuellt" => null,
            "gelagert" => null,
            "abgeholt" => null,
            "bezahlt" => null
            ),
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
        "paletten" => array(
            /*
            array( // einzelne palette
                "timestamp" => ...
                "3er" => 0,
                "3er_gebrauchte" => 0,
                "5er" => 0,
                "5er_gebrauchte" => 0,
                "10er" => 0,
                "10er_gebrauchte" => 0,
                "anmerkungen" => "...",
            )
            */
            ),
        "originale" => array(
            // array("handle" => "...", "revision" => 1)
            ),
        "name" => null,
        "telefon" => null,
        "abholung" => null,
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


