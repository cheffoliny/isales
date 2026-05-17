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

function hasLowStockWarnings(int $limit = 5): bool
{
    $db = db_connect('storage');

    $sql = "
        SELECT
            pe.id_nomenclature,
            n.is_calc,
            SUM(pe.count) AS ordered_qty

        FROM ppp_elements pe

        JOIN ppp p
            ON p.id = pe.id_ppp

        JOIN nomenclatures n
            ON n.id = pe.id_nomenclature

        WHERE
            DATE(p.source_date) = CURDATE()
            AND p.status != 'cancel'
            AND n.to_arc = 0
            AND n.is_calc > 0

        GROUP BY
            pe.id_nomenclature,
            n.is_calc

        HAVING
            (n.is_calc - ordered_qty) < ?

        LIMIT 1
    ";

    $stmt = $db->prepare($sql);

    $stmt->bind_param("i", $limit);

    $stmt->execute();

    $result = $stmt->get_result();

    $hasWarnings = $result->num_rows > 0;

    $stmt->close();

    return $hasWarnings;
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
            ($currentStatus === 'open' && $newStatus === 'wait') ||
            ($currentStatus === 'open' && $newStatus === 'cancel')
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
        elseif (($currentStatus === 'wait' && $newStatus === 'confirm') ||
                ($currentStatus === 'wait' && $newStatus === 'cancel')
            ) {

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