<?php
header('Access-Control-Allow-Origin: *');
error_log("url " . dirname(dirname(dirname(__FILE__))) . '/credentials.php');
require_once __DIR__ . '../../../credentials.php'; // is the same as require_once dirname(dirname(dirname(__FILE__))). '/credentials.php'; => you go from current directory and move up 3 times
/***********************************************
 * VARIABLES
 ***********************************************/

$conn = new mysqli($host, $user, $password, $mysqlDB, $port);
if ($conn->connect_error) {
    error_log("  ---> Mysql connection failed with message " . $conn->connect_error . "\n");
    die;
}

/***********************************************
 * GET DATE IN MYSQL
 ***********************************************/
$sql = "SELECT td FROM trades ORDER BY td DESC LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    error_log(" -> Latest date is ".$row["td"]);
    echo ($row["td"]);
  }
} else {
    error_log(" -> No result");
}
$conn->close();