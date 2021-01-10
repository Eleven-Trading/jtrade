<?php
header('Access-Control-Allow-Origin: *');
error_log("url " . dirname(dirname(dirname(__FILE__))) . '/credentials.php');
require_once __DIR__ . '../../../credentials.php'; // is the same as require_once dirname(dirname(dirname(__FILE__))). '/credentials.php'; => you go from current directory and move up 3 times

$data = $finviz_crawler->quote('JE');
$finvizData = $data["snapshot"];
error_log("finviz ".json_encode($finvizData));


$str = $finvizData["Shs Float"];
$arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i',$str);                                                               
$shsFloatNumber = floatval($arr[0]);
$shsFloatMult = $arr[1];
if ($shsFloatMult == "M"){
    $shsFloat = $shsFloatNumber;
}
else if ($shsFloatMult == "B"){
    $shsFloat = $shsFloatNumber*1000;
}
else if ($shsFloatMult == "K"){
    $shsFloat = $shsFloatNumber/1000;
}
else {
    error_log(" -> Unrecognized multiplier ".$shsFloatMult);
}

$shortFloat = floatval(str_replace("%", "", $finvizData["Short Float"]))/100;

$relVolume = floatval($finvizData["Rel Volume"]);

$prevClose = floatval($finvizData["Prev Close"]);


$str = $finvizData["Avg Volume"];
$arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i',$str);                                                               
$avgVolumeNumber = floatval($arr[0]);
$avgVolumeMult = $arr[1];
if ($avgVolumeMult == "M"){
    $avgVolume = $avgVolumeNumber;
}
else if ($avgVolumeMult == "B"){
    $avgVolume = $avgVolumeNumber*1000;
}
else if ($avgVolumeMult == "K"){
    $avgVolume = $avgVolumeNumber/1000;
}
else {
    error_log(" -> Unrecognized multiplier ".$avgVolumeMult);
}

error_log("avgVolume ".$avgVolume);

?>