<?php

$SRV_APP_VER = '2.0.2';

$WAT_VRECOG_API_KEY = '';
$WAT_VRECOG_API_URL = 'https://gateway.watsonplatform.net/visual-recognition/api/v3/detect_faces?version=2018-03-19';

$DBHOST = '127.0.0.1';
// $DBHOST = '192.168.67.2';
$DBNAME = 'vrecog';
$DBUSER = 'vrecog';
$DBPASS = '';

function checkPost(&$defs, &$errMsgs) {
	if(is_null($errMsgs)) {
		$errMsgs = array();
	}
	$data = array();

	if(empty($_POST)) {
		$errMsgs[] = "Not POST method.";
	} else {
		foreach($defs as $key => $def) {
			if(isset($_POST[$key])) {
				$len = strlen($_POST[$key]);
				if($len < $def[1]) {
					$errMsgs[] = "$key is too short.";
				} else if($len > $def[2]) {
					$errMsgs[] = "$key is too long.";
				} else {
					$data[$key] = $_POST[$key];
				}
			} else if($def[0] !== FALSE) {
				// 非必須ならばデフォルト値
				$data[$key] = $def[0];
			} else {
				$errMsgs[] = "$key is required.";
			}
		}
	}

	return $data;
}

function checkUpload($fileName, $sizeLimit, $allowedMimes, &$determinedMime, &$errMsgs) {
	if(is_null($errMsgs)) {
		$errMsgs = array();
	}

	if(empty($_FILES[$fileName]['name']) || !is_uploaded_file($_FILES[$fileName]['tmp_name'])) {
		$errMsgs[] = "No upload file $fileName.";
	} else if(!isset($_FILES[$fileName]['error']) || is_array($_FILES[$fileName]['error'])) {
		$errMsgs[] = "Invalid parameters in $fileName.";
	}

	if(count($errMsgs) == 0) {
		switch($_FILES[$fileName]['error']) {
		case UPLOAD_ERR_OK:
			break;
		case UPLOAD_ERR_NO_FILE:
			$errMsgs[] = "No file $fileName sent.";
			break;
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			$errMsgs[] = "$fileName is exceeded filesize limit.";
			break;
		default:
			$errMsgs[] = "$fileName has unknown errors.";
			break;
		}
	}

	if(count($errMsgs) == 0) {
		if($_FILES[$fileName]['size'] > $sizeLimit) {
			$errMsgs[] = "$fileName is exceeded filesize limit ($sizeLimit).";
		}
	}

	$determinedMime = NULL;
	if(count($errMsgs) == 0) {
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		if(FALSE === $ext = array_search(
			$finfo->file($_FILES[$fileName]['tmp_name']), $allowedMimes, TRUE
		)) {
			$errMsgs[] = "$fileName has invalid file format.";
		} else {
			$determinedMime = $allowedMimes[$ext];
		}
	}

	return $_FILES[$fileName];
}

function insertExecApi($cfg, $kind, $params, $resStatus, $resData, &$dbh) {
	global $SRV_APP_VER;

	$sthIns = $dbh->prepare(''
		. "INSERT INTO t_exec_api(\n"
		. " user_id,\n"
		. " cordova,\n"
		. " device_model,\n"
		. " device_version,\n"
		. " device_platform,\n"
		. " version_code,\n"
		. " version_num,\n"
		. " srv_app_ver,\n"
		. " kind,\n"
		. " params,\n"
		. " res_status,\n"
		. " res_data\n"
		. ")\n"
		. "VALUES(\n"
		. " :user_id,\n"
		. " :cordova,\n"
		. " :device_model,\n"
		. " :device_version,\n"
		. " :device_platform,\n"
		. " :version_code,\n"
		. " :version_num,\n"
		. " :srv_app_ver,\n"
		. " :kind,\n"
		. " :params,\n"
		. " :res_status,\n"
		. " :res_data\n"
		. ")");
	$sthIns->bindParam(':user_id', $cfg['userId']);
	$sthIns->bindParam(':cordova', $cfg['cordova']);
	$sthIns->bindParam(':device_model', $cfg['deviceModel']);
	$sthIns->bindParam(':device_version', $cfg['deviceVersion']);
	$sthIns->bindParam(':device_platform', $cfg['devicePlatform']);
	$sthIns->bindParam(':version_code', $cfg['versionCode']);
	$sthIns->bindParam(':version_num', $cfg['versionNum']);
	$sthIns->bindParam(':srv_app_ver', $SRV_APP_VER);
	$sthIns->bindParam(':kind', $kind);
	$sthIns->bindParam(':params', $params);
	$sthIns->bindParam(':res_status', $resStatus);
	$sthIns->bindParam(':res_data', $resData);
	$sthIns->execute();

	return $dbh->lastInsertId();
}

function insertExecApiFile($apiSeq, $userId, $fileSize, $fileMime, $filePath, &$dbh) {
	$sthIns = $dbh->prepare(''
		. "INSERT INTO t_exec_api_file(\n"
		. " api_seq,\n"
		. " user_id,\n"
		. " file_size,\n"
		. " file_mime,\n"
		. " file_data\n"
		. ")\n"
		. "VALUES(\n"
		. " :api_seq,\n"
		. " :user_id,\n"
		. " :file_size,\n"
		. " :file_mime,\n"
		. " :file_data\n"
		. ")");
	$sthIns->bindParam(':api_seq', $apiSeq);
	$sthIns->bindParam(':user_id', $userId);
	$sthIns->bindParam(':file_size', $fileSize);
	$sthIns->bindParam(':file_mime', $fileMime);
	$fileData = file_get_contents($filePath);
	$sthIns->bindParam(':file_data', $fileData);
	$sthIns->execute();

	return $dbh->lastInsertId();
}

function checkApiLimit($userId, $kind, $span, $limitCnt, &$dbh) {
	$sthSel = $dbh->prepare(''
		. "SELECT COUNT(*) AS cnt\n"
		. "FROM t_exec_api\n"
		. "WHERE user_id=:user_id\n"
		. " AND kind=:kind\n"
		. " AND res_status IN ('OK','ERROR')\n"
		. " AND created_at>:created_at\n");
	$sthSel->bindParam(':user_id', $userId);
	$sthSel->bindParam(':kind', $kind);
	$fromStr = date('Y-m-d H:i:s', time() - $span);
	$sthSel->bindParam(':created_at', $fromStr);
	$cnt = 0;
	if($sthSel->execute()) {
		while($rsh = $sthSel->fetch()) {
			$cnt = $rsh['cnt'];
		}
	}

	return $cnt > $limitCnt;
}

function countApiExecInMonth($userId, $kind, $time, &$dbh) {
	$year1 = intval(date('Y'));
	$month1 = intval(date('n'));
	$year2 = $year1;
	$month2 = $month1 + 1;
	if($month2 == 13) {
		$year2++;
		$month2 = 1;
	}
	$fromStr = sprintf('%04d-%02d-01 00:00:00', $year1, $month1);
	$toStr = sprintf('%04d-%02d-01 00:00:00', $year2, $month2);

	$sthSel = $dbh->prepare(''
		. "SELECT COUNT(*) AS cnt\n"
		. "FROM t_exec_api\n"
		. "WHERE user_id=:user_id\n"
		. " AND kind=:kind\n"
		. " AND res_status IN ('OK','ERROR')\n"
		. " AND created_at>=:from_str\n"
		. " AND created_at<:to_str\n");
	$sthSel->bindParam(':user_id', $userId);
	$sthSel->bindParam(':kind', $kind);
	$sthSel->bindParam(':from_str', $fromStr);
	$sthSel->bindParam(':to_str', $toStr);
	$cnt = 0;
	if($sthSel->execute()) {
		while($rsh = $sthSel->fetch()) {
			$cnt = $rsh['cnt'];
		}
	}

	return $cnt;
}

function watson_recognize_faces($cfg, $imagePath, $imageMime, &$errMsgs) {
	global $WAT_VRECOG_API_KEY, $WAT_VRECOG_API_URL;

	if(is_null($errMsgs)) {
		$errMsgs = array();
	}

	// Watsonサーバーと通信
	$curl = curl_init($WAT_VRECOG_API_URL);

	$postFile = new CURLFile($imagePath, $imageMime);
	$postData = array(
		'images_file' => $postFile
	);

	$user = 'apikey';
	$pass = $WAT_VRECOG_API_KEY;

	curl_setopt($curl, CURLOPT_POST, TRUE);
	curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

	$watRes = curl_exec($curl);
	curl_close($curl);

	// 結果を確認して返す
	if($watRes === FALSE) {
		$errMsgs[] = "Watson server communication error.";
	} else {
		$watResData = json_decode($watRes);
		if(is_null($watResData)) {
			$errMsgs[] = "Watson server invalid answer.";
		} else {
			// 正常な応答が得られた
			return $watResData;
		}
	}

	return FALSE;
}

function tableize($word) {
	if(is_null($word)) return NULL;
	$keyChained = str_replace('.', '__', $word);
	$tableized = preg_replace('~(?<=\\w)([A-Z])~u', '_$1', $keyChained);

	return strtolower($tableized);
}

function classify($word) {
	if(is_null($word)) return NULL;
	return str_replace([' ', '_', '-'], '', ucwords($word, ' _-'));
}

function pascalize($word) {
	if(is_null($word)) return NULL;
	$sb = '';
	$elems = preg_split("/__|\\./", $word);
	foreach($elems as $elem) {
		if(strlen($sb) > 0) {
			$sb .= '.';
		}
		$sb .= classify($elem);
	}
	return $sb;
}

function camelize($word) {
	if(is_null($word)) return NULL;
	$sb = '';
	$elems = preg_split("/__|\\./", $word);
	foreach($elems as $elem) {
		if(strlen($sb) > 0) {
			$sb .= '.';
		}
		$sb .= lcfirst(classify($elem));
	}
	return $sb;
}

function constantize($word) {
	if(is_null($word)) return NULL;
	return strtoupper(tableize($word));
}

function arraySnakeToCamel($src) {
	$dst = array();
	foreach($src as $key => $val) {
		$dst[camelize($key)] = $val;
	}
	return $dst;
}

function arrayCamelToSnake($src) {
	$dst = array();
	foreach($src as $key => $val) {
		$dst[tableize($key)] = $val;
	}
	return $dst;
}
