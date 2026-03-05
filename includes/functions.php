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

function update_ppp_status($pppID, $newStatus, $idUser)
{
    $db = db_connect('storage');

    $allowed = ['open','wait','confirm','cancel'];
    if (!in_array($newStatus, $allowed)) {
        return false;
    }

    $pppID = (int)$pppID;
    $idUser = (int)$idUser;

    /* ===== ВЗИМАМЕ ТЕКУЩИЯ СТАТУС ===== */
    $stmt = $db->prepare("SELECT status FROM ppp WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $pppID);
    $stmt->execute();
    $stmt->bind_result($currentStatus);
    $stmt->fetch();
    $stmt->close();

    if (!$currentStatus) {
        return false;
    }

    $db->begin_transaction();

    try {

        /* =======================================================
           OPEN ⇄ WAIT
        ======================================================= */
        if (
            ($currentStatus === 'wait' && $newStatus === 'open') ||
            ($currentStatus === 'open' && $newStatus === 'wait')
        ) {

            $stmt = $db->prepare("
                UPDATE ppp
                SET status = ?,
                    updated_user = ?,
                    updated_time = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $newStatus, $idUser, $pppID);
            $stmt->execute();
            $stmt->close();
        }

        /* =======================================================
           WAIT → CONFIRM
        ======================================================= */
        elseif ($currentStatus === 'wait' && $newStatus === 'confirm') {

            $stmt = $db->prepare("
                UPDATE ppp
                SET status = 'confirm',
                    dest_user = ?,
                    dest_date = NOW(),
                    updated_user = ?,
                    updated_time = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("iii", $idUser, $idUser, $pppID);
            $stmt->execute();
            $stmt->close();

            /* ===== Обновяваме ppp_elements ===== */
            $stmt2 = $db->prepare("
                UPDATE ppp_elements
                SET client_own = 1
                WHERE id_ppp = ?
            ");
            $stmt2->bind_param("i", $pppID);
            $stmt2->execute();
            $stmt2->close();
        }

        /* =======================================================
           CONFIRM → WAIT
        ======================================================= */
        elseif ($currentStatus === 'confirm' && $newStatus === 'wait') {

            $zeroDate = '0000-00-00 00:00:00';

            $stmt = $db->prepare("
                UPDATE ppp
                SET status = 'wait',
                    dest_user = 0,
                    dest_date = ?,
                    updated_user = ?,
                    updated_time = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $zeroDate, $idUser, $pppID);
            $stmt->execute();
            $stmt->close();
        }

        else {
            $db->rollback();
            return false;
        }

        $db->commit();
        $db->close();
        return true;

    } catch (Exception $e) {

        $db->rollback();
        $db->close();
        return false;
    }
}
?>