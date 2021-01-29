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
 * UPLOAD TO MYSQL
 ***********************************************/

error_log("\nINSERTING QUOTES AND SYMBOLS INTO MYSQL");
foreach ($quotes as $response) {

    foreach ($response as $resp) {

        $table = 'symbols';
        //Check if symbol exists
        $sql = 'SELECT * FROM ' . $table . ' WHERE id = "' . $resp["symbol"] . '"';
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            error_log(" -> Symbol/id exists");
        } else {
            error_log(" -> Symbol/id does not exists so we create/insert");
            $sql = 'INSERT INTO ' . $table . ' (id, name) VALUES ("' . $resp["symbol"] . '", "' . $resp["symbol"] . '")';
            $result = $conn->query($sql);

            if ($result === TRUE) {
                error_log(" -> Inserted symbol " . $resp["symbol"] . " to MYSQL");
            } else {
                $conn->error . "\n";
                error_log(" -> mysql symbol  " . $resp["symbol"] . " failed with error " . $conn->error);
                //error_log(" -> mysql query failed with message " . $sql . "<br>'");
                array_push($errors, "Symbols error: " . $conn->error);
            }
        }

        error_log(" -> Saving image to S3");
        $chartImage = $resp['chartImage'];
        $chartImageUrl = null;
        if ($chartImage) {
            $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chartImage));
            $subFolder = 'chartimages';
            $imageName = $resp['date'] . "-" . $resp['symbol'] . ".png";
            $imageType = 'image/png';

            try {
                $result = $s3->putObject(array(
                    'Bucket' => $bucket,
                    'Body' => $image,
                    'Key'    => $subFolder . "/" . $imageName,
                    'ContentType' => $imageType
                ));
                $chartImageUrl = $result['ObjectURL'];
                error_log(" -> Image saved under " . $chartImageUrl);
            } catch (S3Exception $e) {
                //$errorMsg = array("status" => "error", "message" => "" . $e->getMessage() . "\n");
                error_log("the json encode message is: " . json_encode($errorMsg));
                //die(json_encode($errorMsg));
                //echo $e->getMessage() . "\n";
                array_push($errors, "S3 error: " . $e->getMessage());
            }
        } else {
            error_log(" -> No image to store");
        }


        //PREPARING FINVIZ DATA
        $finvizCrawler = $finviz_crawler->quote($resp["symbol"]);
        $finvizData = $finvizCrawler["snapshot"];
        //error_log("finviz " . json_encode($finvizData));
        
        $shsFloat = 0;
        $str = $finvizData["Shs Float"];
        if ($str != "-") {
            $arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $str);
            $shsFloatNumber = floatval($arr[0]);
            $shsFloatMult = $arr[1];
            if ($shsFloatMult == "M") {
                $shsFloat = $shsFloatNumber;
            } else if ($shsFloatMult == "B") {
                $shsFloat = $shsFloatNumber * 1000;
            } else if ($shsFloatMult == "K") {
                $shsFloat = $shsFloatNumber / 1000;
            } else {
                error_log(" -> Unrecognized multiplier " . $shsFloatMult);
            }
        }

        $shortFloat = floatval(str_replace("%", "", $finvizData["Short Float"])) / 100;

        $relVolume = floatval($finvizData["Rel Volume"]);

        $prevClose = floatval($finvizData["Prev Close"]);


        $str = $finvizData["Avg Volume"];
        $arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $str);
        $avgVolumeNumber = floatval($arr[0]);
        $avgVolumeMult = $arr[1];
        $avgVolume = null;
        if ($avgVolumeMult == "M") {
            $avgVolume = $avgVolumeNumber;
        } else if ($avgVolumeMult == "B") {
            $avgVolume = $avgVolumeNumber * 1000;
        } else if ($avgVolumeMult == "K") {
            $avgVolume = $avgVolumeNumber / 1000;
        } else {
            error_log(" -> Unrecognized multiplier " . $avgVolumeMult);
        }

        $shortable = $finvizData["Shortable"];

        $volume = floatval($finvizData["Volume"]);

        $optionable = $finvizData["Optionable"];

        //UPLOAD TO MYSQL
        $table = "quotes";

        $sql = 'INSERT INTO ' . $table . ' (id, date, symbol, shsFloat, shortFloat, relVolume, prevClose, avgVolume, Shortable, Optionable, Volume, chartImage) VALUES ("' . $resp["id"] . '", FROM_UNIXTIME(' . $resp["date"] . '), "' . $resp["symbol"] . '", "' . $shsFloat . '", "' . $shortFloat . '", "' . $relVolume . '", "' . $prevClose . '", "' . $avgVolume . '", "' . $shortable . '", "' . $optionable . '", "' . $volume . '", "' . $chartImageUrl . '")';
        $result = $conn->query($sql);

        if ($result === TRUE) {
            error_log(" -> Inserted quote " . $resp["id"] . " to MYSQL");
        } else {
            $conn->error . "\n";
            error_log(" -> mysql quote " . $resp["id"] . " failed with error " . $conn->error);
            //error_log(" -> mysql query failed with message " . $sql . "<br>'");
            array_push($errors, "Quotes error: " . $conn->error);
        }
    }
}

error_log("\nINSERTING TRADES INTO MYSQL");
foreach ($trades as $response) {
    //error_log("trades " . json_encode($response));
    foreach ($response as $resp) {
        //error_log("resp " . json_encode($resp));
        $implodeExecutions = null;
        $implodeSetups = null;
        $implodeMistakes = null;
        $setupsArray = [];
        $mistakesArray = [];

        if (!empty($resp["executions"])) {
            $implodeExecutions = implode(",", $resp["executions"]);
        }

        if (!empty($resp["setups"])) {
            foreach ($resp["setups"] as $item) {
                if (isset($item['id'])) {
                    array_push($setupsArray, $item['id']);
                } else {
                    //first we check if the name already exists - case when the same new name is used on new upload
                    $sql = 'SELECT id FROM setupsMistakes WHERE name = "' . $item['value'] . '"';
                    $result = $conn->query($sql);
                    if ($result->num_rows > 0) {
                        error_log(" -> Setup name already exists");
                        while ($row = $result->fetch_assoc()) {
                            array_push($setupsArray, $row['id']);
                        }
                    } else {
                        error_log(" -> Setup name is new so we create/insert");

                        $sql = 'INSERT INTO setupsMistakes (name) VALUES ("' . $item['value'] . '")';
                        $result = $conn->query($sql);

                        if ($result === TRUE) {
                            error_log(" -> Inserted new setupMistake " . $item["value"] . " to MYSQL and the new id is " . $conn->insert_id);
                            array_push($setupsArray, $conn->insert_id);
                        } else {
                            $conn->error . "\n";
                            error_log(" -> mysql new setupMistake " . $item["value"] . " failed with error " . $conn->error);
                            array_push($errors, "Journals error: " . $conn->error);
                        }
                    }
                }
                //error_log("setups item ".json_encode($item));
            }
            $implodeSetups = implode(",", $setupsArray);
            error_log("implodeSetups " . $implodeSetups);
        }

        if (!empty($resp["mistakes"])) {
            foreach ($resp["mistakes"] as $item) {
                if (isset($item['id'])) {
                    array_push($mistakesArray, $item['id']);
                } else {
                    //first we check if the name already exists - case when the same new name is used on new upload
                    $sql = 'SELECT id FROM setupsMistakes WHERE name = "' . $item['value'] . '"';
                    $result = $conn->query($sql);
                    if ($result->num_rows > 0) {
                        error_log(" -> Mistake name already exists");
                        while ($row = $result->fetch_assoc()) {
                            array_push($mistakesArray, $row['id']);
                        }
                    } else {
                        error_log(" -> Mistake name is new so we create/insert");
                        $sql = 'INSERT INTO setupsMistakes (name) VALUES ("' . $item['value'] . '")';
                        $result = $conn->query($sql);

                        if ($result === TRUE) {
                            error_log(" -> Inserted new setupMistake " . $item["value"] . " to MYSQL and the new id is " . $conn->insert_id);
                            array_push($mistakesArray, $conn->insert_id);
                        } else {
                            $conn->error . "\n";
                            error_log(" -> mysql new setupMistake " . $item["value"] . " failed with error " . $conn->error);
                            array_push($errors, "Journals error: " . $conn->error);
                        }
                    }
                }
            }
            $implodeMistakes = implode(",", $mistakesArray);
            error_log("implodeMistakes " . $implodeMistakes);
        }
        $table = 'trades';
        $sql = 'INSERT INTO ' . $table . ' (id, account, td, currency, type, side, symbol, quantity, entryPrice, exitPrice, entryTime, exitTime, commission, sec, taf, nscc, nasdaq, ecnRemove, ecnAdd, grossProceeds, entryGrossProceeds, exitGrossProceeds, netProceeds, entryNetProceeds, exitNetProceeds, clrBroker, liq, note, strategy, executions, setups, mistakes, grossStatus, netStatus) VALUES ("' . $resp["id"] . '", "' . $resp["account"] . '", FROM_UNIXTIME(' . $resp["td"] . '), "' . $resp["currency"] . '", "' . $resp["type"] . '", "' . $resp["side"] . '", "' . $resp["symbol"] . '", "' . $resp["quantity"] . '", "' . $resp["entryPrice"] . '", "' . $resp["exitPrice"] . '", FROM_UNIXTIME(' . $resp["entryTime"] . '), FROM_UNIXTIME(' . $resp["exitTime"] . '), "' . $resp["commission"] . '", "' . $resp["sec"] . '", "' . $resp["taf"] . '", "' . $resp["nscc"] . '", "' . $resp["nasdaq"] . '", "' . $resp["ecnRemove"] . '", "' . $resp["ecnAdd"] . '", "' . $resp["grossProceeds"] . '", "' . $resp["entryGrossProceeds"] . '", "' . $resp["exitGrossProceeds"] . '", "' . $resp["netProceeds"] . '", "' . $resp["entryNetProceeds"] . '", "' . $resp["exitNetProceeds"] . '", "' . $resp["clrBroker"] . '", "' . $resp["liq"] . '", "' . $resp["note"] . '", "' . $resp["strategy"] . '", "' . $implodeExecutions . '", "' . $implodeSetups . '", "' . $implodeMistakes . '", "' . $resp["grossStatus"] . '", "' . $resp["netStatus"] . '")';
        $result = $conn->query($sql);

        if ($result === TRUE) {
            error_log(" -> Inserted trade " . $resp["id"] . " to MYSQL");
        } else {
            $conn->error . "\n";
            error_log(" -> mysql trade " . $resp["id"] . " failed with error " . $conn->error);
            //error_log(" -> mysql query failed with message " . $sql . "<br>'");
            array_push($errors, "Trades error: " . $conn->error);
        }


        $table = 'tradeSetupsMistakes';
        error_log("\nINSERTING SETUPS INTO MYSQL");
        if (!empty($setupsArray)) {
            foreach ($setupsArray as $item) {
                $sql = 'INSERT INTO ' . $table . ' (id, trade, smId, smType, account, td, currency, type, side, symbol, quantity, entryPrice, exitPrice, entryTime, exitTime, commission, sec, taf, nscc, nasdaq, ecnRemove, ecnAdd, grossProceeds, entryGrossProceeds, exitGrossProceeds, netProceeds, entryNetProceeds, exitNetProceeds, clrBroker, liq, note, strategy, executions, setups, mistakes, grossStatus, netStatus) VALUES ("sm' . $resp["entryTime"] . '_' . $resp["symbol"] . '_' . $item . '_s", "' . $resp["id"] . '", "' . $item . '", "setup", "' . $resp["account"] . '", FROM_UNIXTIME(' . $resp["td"] . '), "' . $resp["currency"] . '", "' . $resp["type"] . '", "' . $resp["side"] . '", "' . $resp["symbol"] . '", "' . $resp["quantity"] . '", "' . $resp["entryPrice"] . '", "' . $resp["exitPrice"] . '", FROM_UNIXTIME(' . $resp["entryTime"] . '), FROM_UNIXTIME(' . $resp["exitTime"] . '), "' . $resp["commission"] . '", "' . $resp["sec"] . '", "' . $resp["taf"] . '", "' . $resp["nscc"] . '", "' . $resp["nasdaq"] . '", "' . $resp["ecnRemove"] . '", "' . $resp["ecnAdd"] . '", "' . $resp["grossProceeds"] . '", "' . $resp["entryGrossProceeds"] . '", "' . $resp["exitGrossProceeds"] . '", "' . $resp["netProceeds"] . '", "' . $resp["entryNetProceeds"] . '", "' . $resp["exitNetProceeds"] . '", "' . $resp["clrBroker"] . '", "' . $resp["liq"] . '", "' . $resp["note"] . '", "' . $resp["strategy"] . '", "' . $implodeExecutions . '", "' . $implodeSetups . '", "' . $implodeMistakes . '", "' . $resp["grossStatus"] . '", "' . $resp["netStatus"] . '")';
                $result = $conn->query($sql);

                if ($result === TRUE) {
                    error_log(" -> Inserted setup " . $item . " to MYSQL");
                } else {
                    $conn->error . "\n";
                    error_log(" -> mysql setup " . $item . " failed with error " . $conn->error);
                    //error_log(" -> mysql query failed with message " . $sql . "<br>'");
                    array_push($errors, "Setups error: " . $conn->error);
                }
            }
        }

        error_log("\nINSERTING MISTAKES INTO MYSQL");
        if (!empty($mistakesArray)) {
            foreach ($mistakesArray as $item) {
                $sql = 'INSERT INTO ' . $table . ' (id, trade, smId, smType, account, td, currency, type, side, symbol, quantity, entryPrice, exitPrice, entryTime, exitTime, commission, sec, taf, nscc, nasdaq, ecnRemove, ecnAdd, grossProceeds, entryGrossProceeds, exitGrossProceeds, netProceeds, entryNetProceeds, exitNetProceeds, clrBroker, liq, note, strategy, executions, setups, mistakes, grossStatus, netStatus) VALUES ("sm' . $resp["entryTime"] . '_' . $resp["symbol"] . '_' . $item . '_m", "' . $resp["id"] . '", "' . $item . '", "mistake", "' . $resp["account"] . '", FROM_UNIXTIME(' . $resp["td"] . '), "' . $resp["currency"] . '", "' . $resp["type"] . '", "' . $resp["side"] . '", "' . $resp["symbol"] . '", "' . $resp["quantity"] . '", "' . $resp["entryPrice"] . '", "' . $resp["exitPrice"] . '", FROM_UNIXTIME(' . $resp["entryTime"] . '), FROM_UNIXTIME(' . $resp["exitTime"] . '), "' . $resp["commission"] . '", "' . $resp["sec"] . '", "' . $resp["taf"] . '", "' . $resp["nscc"] . '", "' . $resp["nasdaq"] . '", "' . $resp["ecnRemove"] . '", "' . $resp["ecnAdd"] . '", "' . $resp["grossProceeds"] . '", "' . $resp["entryGrossProceeds"] . '", "' . $resp["exitGrossProceeds"] . '", "' . $resp["netProceeds"] . '", "' . $resp["entryNetProceeds"] . '", "' . $resp["exitNetProceeds"] . '", "' . $resp["clrBroker"] . '", "' . $resp["liq"] . '", "' . $resp["note"] . '", "' . $resp["strategy"] . '", "' . $implodeExecutions . '", "' . $implodeSetups . '", "' . $implodeMistakes . '", "' . $resp["grossStatus"] . '", "' . $resp["netStatus"] . '")';
                $result = $conn->query($sql);

                if ($result === TRUE) {
                    error_log(" -> Inserted mistake " . $item . " to MYSQL");
                } else {
                    $conn->error . "\n";
                    error_log(" -> mysql mistake " . $item . " failed with error " . $conn->error);
                    //error_log(" -> mysql query failed with message " . $sql . "<br>'");
                    array_push($errors, "Mistakes error: " . $conn->error);
                }
            }
        }
    }
}

error_log("\nINSERTING EXECUTIONS INTO MYSQL");
foreach ($executions as $response) {

    $table = 'executions';
    //error_log("Each execution " . json_encode($response));
    foreach ($response as $resp) {
        error_log(" -> Each exec " . json_encode($resp));

        $sql = 'INSERT INTO ' . $table . ' (id, account, td, sd, currency, type, side, symbol, quantity, price, execTime, commission, sec, taf, nscc, nasdaq, ecnRemove, ecnAdd, grossProceeds, netProceeds, clrBroker, liq, note, trade) VALUES ("' . $resp["id"] . '", "' . $resp["account"] . '", FROM_UNIXTIME(' . $resp["td"] . '), FROM_UNIXTIME(' . $resp["sd"] . '), "' . $resp["currency"] . '", "' . $resp["type"] . '", "' . $resp["side"] . '", "' . $resp["symbol"] . '", "' . $resp["quantity"] . '", "' . $resp["price"] . '", FROM_UNIXTIME(' . $resp["execTime"] . '), "' . $resp["commission"] . '", "' . $resp["sec"] . '", "' . $resp["taf"] . '", "' . $resp["nscc"] . '", "' . $resp["nasdaq"] . '", "' . $resp["ecnRemove"] . '", "' . $resp["ecnAdd"] . '", "' . $resp["grossProceeds"] . '", "' . $resp["netProceeds"] . '", "' . $resp["clrBroker"] . '", "' . $resp["liq"] . '", "' . $resp["note"] . '", "' . $resp["trade"] . '")';
        $result = $conn->query($sql);

        if ($result === TRUE) {
            error_log(" -> Inserted execution " . $resp["id"] . " to MYSQL");
        } else {
            $conn->error . "\n";
            error_log(" -> mysql execution " . $resp["id"] . " failed with error " . $conn->error);
            //error_log(" -> mysql query failed with message " . $sql . "<br>'");
            array_push($errors, "Executions error: " . $conn->error);
        }
    }
}

error_log("\nINSERTING JOURNALS INTO MYSQL");
foreach ($journals as $response) {
    $table = 'journals';
    $implodeTrades = implode(",", $response["trades"]);

    $sql = 'INSERT INTO ' . $table . ' (id, date, trades, note, recording) VALUES ("' . $response["id"] . '", FROM_UNIXTIME(' . $response["date"] . '), "' . $implodeTrades . '", "' . $response["note"] . '", "' . $response["recording"] . '")';
    $result = $conn->query($sql);

    if ($result === TRUE) {
        error_log(" -> Inserted journal " . $response["id"] . " to MYSQL");
    } else {
        $conn->error . "\n";
        error_log(" -> mysql journal " . $response["id"] . " failed with error " . $conn->error);
        //error_log(" -> mysql query failed with message " . $sql . "<br>'");
        array_push($errors, "Journals error: " . $conn->error);
    }
}

$conn->close();

$responseBack = $errors;

echo json_encode($responseBack);
