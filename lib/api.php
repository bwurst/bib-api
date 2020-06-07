<?php
require_once ROOT_PATH.'lib/sql.php';
require_once ROOT_PATH.'lib/debug.php';

function api_ratelimit() {
    /* lösche alle was älter als 10 Minuten ist */
    db_query("DELETE FROM ratelimit WHERE ts < CURRENT_TIMESTAMP() - 600");
    $remote = $_SERVER['REMOTE_ADDR'];
    // aktuellen Zugriff eintragen
    db_query("INSERT INTO ratelimit (ipaddr) VALUES (?)", array($remote));
    db_query("COMMIT");
    // Inklusive aktuellem Zugriff
    $result = db_query("SELECT COUNT(*) AS `count` FROM ratelimit WHERE ipaddr = ?", array($remote));
    $row = $result->fetch();
    /* Wie viel ist "zu viel" in 10 Minuten? */
    if ($row["count"] > 10) {
        api_send_error("429", "too many connections");
    }
}

function api_check_authtoken(&$data)
{
    if (!isset($data['authtoken'])) {
        api_send_error('401', 'authtoken missing');
    }
    $ip = $_SERVER['REMOTE_ADDR'];
    $result = db_query("SELECT role FROM api_tokens WHERE authtoken=?", array($data['authtoken']));
    if ($result->rowCount() < 1) {
        api_send_error('403', 'unauthorized');
    }
    $res = $result->fetch();
    $role = $res['role'];

    db_query("UPDATE api_tokens SET lastuse=CURRENT_TIMESTAMP(), lastip=? WHERE authtoken=?", array($ip, $data['authtoken']));
    db_query("COMMIT");
    unset($data['authtoken']);
    return $role;
}


function api_send_error($errno, $message) {
    $response = array();
    $response['status'] = 'error';
    $response['errno'] = $errno;
    $response['message'] = $message;
    
    api_send($response);
}


function api_send($response) 
{
    global $debug_content;
    if (! isset($response['status'])) {
        $response['status'] = 'success';
    }
    if (! isset($response['errno'])) {
        $response['errno'] = '0';
    }
    if ($debug_content) {
        $response['debug_content'] = $debug_content;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}



