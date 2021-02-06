<?php
header('Access-Control-Allow-Origin: *');
error_log("url " . dirname(dirname(dirname(__FILE__))) . '/credentials.php');
require_once __DIR__ . '../../../credentials.php'; // is the same as require_once dirname(dirname(dirname(__FILE__))). '/credentials.php'; => you go from current directory and move up 3 times

/***********************************************
 * VARIABLES
 ***********************************************/
$data = json_decode(file_get_contents("php://input"), true);

$executions = $data["executions"];
$trades = $data["trades"];
$quotes = $data["quotes"];
$journals = $data["journals"];
$errors = [];

//error_log("executions ".json_encode($executions));
//error_log(("executions " . json_encode($executions) . "\n\ntrades " . json_encode($trades) . "\n\nquotes " . json_encode($quotes) . "\n\njournals " . json_encode($journals)));

$conn = new mysqli($host, $user, $password, $mysqlDB, $port);
if ($conn->connect_error) {
    error_log("  ---> Mysql connection failed with message " . $conn->connect_error . "\n");
    die;
}

/***********************************************
 * GET DATE IN MYSQL
 ***********************************************/