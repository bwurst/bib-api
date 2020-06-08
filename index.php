<?php

/* API für mosterei wurst */

/* für includes */
define("ROOT_PATH", __DIR__ . '/');

include ROOT_PATH . "vendor/autoload.php";
require_once ROOT_PATH."lib/api.php";
require_once ROOT_PATH."lib/sql.php";

$available_modules = array("auth", "kunde", "auftrag");

/* routing */

if (false) {
    print_r($_SERVER);
    die();
}

$BASE="/api/v1";
$target = substr($_SERVER['REQUEST_URI'], strlen($BASE) + 1);
if ($target === false) {
    echo 'invalid request';
    die();
}

$uri = null;
$query_string = null;
$components = explode('?', $target, 2);
$uri = $components[0];
if (count($components) > 1) {
    $query_string = $components[1];
}

$components = explode('/', $uri, 3);

$method = $_SERVER['REQUEST_METHOD'];
if (! in_array($method, array('GET', 'POST', 'DELETE'))) {
    api_send_error('500', 'invalid method');
}
$action = null;
$pathdata = null;
$module = $components[0];
if (count($components) > 1) {
    $action = $components[1];
}
if (count($components) > 2) {
    $pathdata = $components[2];
}

$data = null;
if (isset($_SERVER["CONTENT_TYPE"]) && $_SERVER["CONTENT_TYPE"] == 'application/json') {
    $data = json_decode(file_get_contents('php://input'), true);
}


/* prüfe authtoken */
$role = api_check_authtoken($data);


$route = null;

if (in_array($module, $available_modules)) {
    require_once "lib/".$module.".php";
    $func = strtolower($method) . '_' . $module;
    if ($action) {
        $func .= '_' . $action;
    }
    if (function_exists($func)) {
        $route = $func;
    }
}

if ($route === null) {
    api_send_error('500', "illegal query");
}

/*
 $route enthält einen funktionsnamen, der aufgerufen werden soll
 dieser wird immer mit dem restlichen Pfad und mit dem request-body aufgerufen
 */

$response = array();
try {
    $response = $route($pathdata, $data);
} catch (Exception $e) {
    api_send_error('1', $e->getMessage());
}

api_send($response);

