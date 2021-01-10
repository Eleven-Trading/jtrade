<?php
header('Access-Control-Allow-Origin: *');
error_log("url ".dirname(dirname(dirname(__FILE__))). '/credentials.php');
//require_once dirname(dirname(dirname(__FILE__))). '/credentials.php';
require_once __DIR__ . '../../../credentials.php'; // is the same as require_once dirname(dirname(dirname(__FILE__))). '/credentials.php'; => you go from current directory and move up 3 times
/***********************************************
 * VARIABLES
 ***********************************************/
error_log("host is ".$host);

$conn = new mysqli($host, $user, $password, $mysqlDB, $port);
if ($conn->connect_error) {
    error_log("  ---> Mysql connection failed with message " . $conn->connect_error . "\n");
    die;
}

/***********************************************
 * GET SETUPS
 ***********************************************/
error_log("\nGETTING SETUPS");

$sql = 'SELECT *  FROM setupsMistakes';
$result = $conn->query($sql);
if ($result->num_rows > 0) {
  // output data of each row
  $resultArray = Array();
  while($row = $result->fetch_assoc()) {
    $resultArray[] = array('id'=>$row['id'], 'value'=>$row['name']);
  }
} else {
    error_log(" -> No last date. This is a problem");
}

echo(json_encode($resultArray));


?>