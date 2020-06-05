<?php

require_once ROOT_PATH.'lib/sql.php';

function auth($pathdata, $data)
{
    if (!isset($data["client"])) {
        throw new Exception('No client type specified');
    }
    if (isset($data["authtoken"])) {
        /* app will prüfen, ob das authtoken schon registriert ist (unter diesem client) */
        $result = db_query("SELECT role, name FROM api_tokens WHERE authtoken=? AND client=?", array($data["authtoken"], $data["client"]));
        if ($result->rowCount() > 0) {
            /* authtoken bereits registriert */
            $row = $result->fetch();
            return array("authtoken" => $data["authtoken"], "role" => $row['role'], "name" => $row['name'], "client" => $data["client"]);
        }
        return array("status" => "error", "message" => "token not registered");
    }
    $client = $data["client"];
    $name = 'anonymous';
    if (isset($data["name"])) {
        $name = $data["name"];
    }
    $authtoken = strtr(substr(base64_encode(random_bytes(64)), 0, 64), '+/', '-_');
    
    db_query("INSERT INTO api_tokens (authtoken, role, client, name, lastip) VALUES (?, ?, ?, ?, ?)", array($authtoken, 'anonymous', $client, $name, $_SERVER['REMOTE_ADDR']));
    
    return array('authtoken' => $authtoken, 'role' => 'anonymous', 'name' => $name, 'client' => $client);
}


