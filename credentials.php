<?php
require __DIR__. '/vendor/autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use FinvizCrawler\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/***********************************************
 * MYSQL
 ***********************************************/
// Variables
$host = $_ENV["mysqlhost"];
$port = $_ENV["mysqlport"];
$user = $_ENV["mysqluser"];
$password = $_ENV["mysqlpassword"];
$mysqlDB = $_ENV["mysqldb"];

/********************************
 * AWS S3
 ********************************/
$bucket = $_ENV['AWS_BUCKET'];
$s3 = new Aws\S3\S3Client([
    'version' => 'latest',
    'region' => 'eu-west-3',
    'credentials' => array(
        'key'    => $_ENV['AWS_KEY'],
        'secret' => $_ENV['AWS_SECRKET'],
    )
]);

/***********************************************
 * FINVIZ API
 ***********************************************/
$finviz_crawler = new Client();

?>