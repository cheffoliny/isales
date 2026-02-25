<?php
include_once __DIR__ . '/../core/init.php';
include_once __DIR__ . '/../config/config.php';

function checkEmail($str) {
	return preg_match("/^[\.A-z0-9_\-\+]+[@][A-z0-9_\-]+([.][A-z0-9_\-]+)+[A-z]{1,4}$/", $str);
}


function send_mail($from,$to,$subject,$body)
{
	$headers = '';
	$headers .= "From: $from\n";
	$headers .= "Reply-to: $from\n";
	$headers .= "Return-Path: $from\n";
	$headers .= "Message-ID: <" . md5(uniqid(time())) . "@" . $_SERVER['SERVER_NAME'] . ">\n";
	$headers .= "MIME-Version: 1.0\n";
	$headers .= "Date: " . date('r', time()) . "\n";

	mail($to,$subject,$body,$headers);
}

function update_geo_data( $person, $geo_data, $geo_acc, $geo_time, $geo_source ) {
    global $db_sod;

    $aQuery  = "INSERT INTO work_card_geo_log ( `id_person`, `geo_time`, `geo_data`, `geo_acc`, `geo_source`, `server_time` ) VALUES ( $person, '{$geo_time}', '{$geo_data}', '{$geo_acc}', '{$geo_source}', NOW() )";
    $aResult = mysqli_query( $db_sod, $aQuery ) or die( print "ВЪЗНИКНА ГРЕШКА ПРИ ОПИТ ЗА ЗАПИС! ОПИТАЙТЕ ПО–КЪСНО!".$aQuery );
}

function getPersonNameByID( $pID ) {

    global $db_personnel;

    $aQuery  = "SELECT CONCAT( fname, ' ', lname ) AS pName FROM personnel WHERE id = ". $pID ." ";
    $aResult = mysqli_query( $db_personnel, $aQuery ) or die( print "ГРЕШКА...! ОПИТАЙТЕ ПО–КЪСНО!" );

    while( $aRow = mysqli_fetch_assoc( $aResult ) ) {

        $strName	= isset( $aRow['pName'] ) ? $aRow['pName'] : '';

    }

    return $strName;

}

function getObjectByID($oID) {

    $db = db_connect('sod'); // или правилната база

    $stmt = $db->prepare("SELECT name FROM objects WHERE id = ?");
    $stmt->bind_param("i", $oID);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();
    $db->close();

    return $name ?? '';
}

?>