<?php

/* API für mosterei wurst */

/* für includes */
define("ROOT_PATH", __DIR__ . '/');

$available_modules = array("auth", "customer", "order");

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
if (count($components) > 1) {
    list($uri, $query_string) = $components;
} else {
    $uri = $components[0];
}

$components = explode('/', $uri, 3);

$method = $_SERVER['REQUEST_METHOD'];
if (! in_array($method, array('GET', 'POST', 'DELETE'))) {
    echo 'invalid method';
    die();
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
    $data = json_decode(file_get_contents('php://input'));
}


$route = null;

if (in_array($module, $available_modules)) {
    require_once "lib/".$module.".php";
    $func = strtolower($method) . '_' . $module . '_' . $action;
    if (function_exists($func)) {
        $route = $func;
    }
}

if ($route === null) {
    echo "illegal query";
    die();
}

/*
 $route enthält eine funktion, die benutzt werden soll
 diese wird immer mit dem restlichen Pfad und mit dem request-body aufgerufen
 */

$response = array();
try {
    $response = $route($pathdata, $data);
} catch (Exception $e) {
    $msg = $e->getMessage();
    $response['status'] = 'error';
    $response['message'] = $msg;
}
if (! isset($response['status'])) {
    $response['status'] = 'success';
}

header('Content-Type: application/json');
echo json_encode($response);


