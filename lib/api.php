<?php


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


function api_send_error($errno, $message) {
    $response = array();
    $response['status'] = 'error';
    $response['errno'] = $errno;
    $response['message'] = $message;
    
    api_send($response);
}


function api_send($response) 
{
    if (! isset($response['status'])) {
        $response['status'] = 'success';
    }
    if (! isset($response['errno'])) {
        $response['errno'] = '0';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}



