<?php

require_once(dirname(__FILE__) . '/../common.php');

$SRV_API_KIND = '/user/activate';
$res = array(
	'status' => 'OK'
);

// パラメータ取得
$cfgPostDefs = array(
	'userId' => array(NULL, 1, 20),
	'uuid' => array(FALSE, 1, 100),
	'cordova' => array(FALSE, 1, 20),
	'deviceModel' => array(FALSE, 1, 100),
	'deviceVersion' => array(FALSE, 1, 20),
	'devicePlatform' => array(FALSE, 1, 20),
	'isVirtual' => array('', 1, 1),
	'versionCode' => array('', 1, 20),
	'versionNum' => array('', 1, 20),
);
$cfgPost = checkPost($cfgPostDefs, $errMsgs);
try {
	$dbh = new PDO("mysql:host=$DBHOST;dbname=$DBNAME", $DBUSER, $DBPASS);
	if(count($errMsgs) == 0) {

		// 送信されてきたユーザ情報が存在するか調べる
		$cfgCur = NULL;
		if(!empty($cfgPost['userId'])) {
			$sthSel = $dbh->prepare(''
				. "SELECT *\n"
				. "FROM m_user\n"
				. "WHERE user_id=:user_id\n"
				. " AND expired_at IS NULL\n");
			$sthSel->bindParam(':user_id', $cfgPost['userId']);
			if($sthSel->execute()) {
				while($rsh = $sthSel->fetch()) {
					$cfgCur = arraySnakeToCamel($rsh);
				}
			}
		}

		$nowStr = date('Y-m-d H:i:s');
		if($cfgCur) {
			// 既存のユーザー
			$sthUpd = $dbh->prepare(''
				. "UPDATE m_user\n"
				. "SET\n"
				. " uuid=:uuid,\n"
				. " cordova=:cordova,\n"
				. " device_model=:device_model,\n"
				. " device_version=:device_version,\n"
				. " device_platform=:device_platform,\n"
				. " is_virtual=:is_virtual,\n"
				. " version_code=:version_code,\n"
				. " version_num=:version_num,\n"
				. " updated_at=:updated_at\n"
				. "WHERE user_id=:user_id\n"
				. " AND expired_at IS NULL\n");
			$sthUpd->bindParam(':user_id', $cfgPost['userId']);
			$sthUpd->bindParam(':uuid', $cfgPost['uuid']);
			$sthUpd->bindParam(':cordova', $cfgPost['cordova']);
			$sthUpd->bindParam(':device_model', $cfgPost['deviceModel']);
			$sthUpd->bindParam(':device_version', $cfgPost['deviceVersion']);
			$sthUpd->bindParam(':device_platform', $cfgPost['devicePlatform']);
			$sthUpd->bindParam(':is_virtual', $cfgPost['isVirtual']);
			$sthUpd->bindParam(':version_code', $cfgPost['versionCode']);
			$sthUpd->bindParam(':version_num', $cfgPost['versionNum']);
			$sthUpd->bindParam(':updated_at', $nowStr);
			$sthUpd->execute();

			$cfg = $cfgPost;

			$res['data'] = array(
					'userId' => $cfgPost['userId']
				);
		} else {
			// 新規ユーザー
			$userId = genUserId();

			$sthIns = $dbh->prepare(''
				. "INSERT INTO m_user(\n"
				. " user_id,\n"
				. " uuid,\n"
				. " cordova,\n"
				. " device_model,\n"
				. " device_version,\n"
				. " device_platform,\n"
				. " is_virtual,\n"
				. " version_code,\n"
				. " version_num,\n"
				. " created_at,\n"
				. " updated_at\n"
				. ")\n"
				. "VALUES(\n"
				. " :user_id,\n"
				. " :uuid,\n"
				. " :cordova,\n"
				. " :device_model,\n"
				. " :device_version,\n"
				. " :device_platform,\n"
				. " :is_virtual,\n"
				. " :version_code,\n"
				. " :version_num,\n"
				. " :created_at,\n"
				. " :updated_at\n"
				. ")");
			$sthIns->bindParam(':user_id', $userId);
			$sthIns->bindParam(':uuid', $cfgPost['uuid']);
			$sthIns->bindParam(':cordova', $cfgPost['cordova']);
			$sthIns->bindParam(':device_model', $cfgPost['deviceModel']);
			$sthIns->bindParam(':device_version', $cfgPost['deviceVersion']);
			$sthIns->bindParam(':device_platform', $cfgPost['devicePlatform']);
			$sthIns->bindParam(':is_virtual', $cfgPost['isVirtual']);
			$sthIns->bindParam(':version_code', $cfgPost['versionCode']);
			$sthIns->bindParam(':version_num', $cfgPost['versionNum']);
			$sthIns->bindParam(':created_at', $nowStr);
			$sthIns->bindParam(':updated_at', $nowStr);
			$sthIns->execute();

			$cfg = $cfgPost;
			$cfg['userId'] = $userId;

			$res['data'] = array(
					'userId' => $userId
				);
		}

		insertExecApi($cfg, $SRV_API_KIND, json_encode($cfgPost), $res['status'], json_encode($res['data']), $dbh);
	} else {
		error_log('Not POST method.');
		$res['status'] = 'ERROR';
		$res['messages'] = $errMsgs;

		insertExecApi($cfg, $SRV_API_KIND, json_encode($cfgPost), $res['status'], json_encode($res['messages']), $dbh);
	}

	$dbh = null;
} catch(PDOException $ex) {
	error_log($ex->getMessage());
	$res['status'] = 'ERROR';
	$res['messages'] = array("Database error.");
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$json = json_encode($res);
echo $json;
exit;

function genUserId() {
	$SRC = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$len = strlen($SRC);
	$userId = '';
	for($idx = 0; $idx < 20; $idx++) {
		$userId .= $SRC[mt_rand() % $len];
	}

	return $userId;
}
