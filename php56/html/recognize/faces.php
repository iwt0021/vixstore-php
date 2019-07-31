<?php

require_once(dirname(__FILE__) . '/../common.php');

$SRV_API_KIND = '/recognize/faces';
$res = array(
	'status' => 'OK'
);

// パラメータ取得
$cfgPostDefs = array(
	'userId' => array(NULL, 1, 20)
);
$cfgPost = checkPost($cfgPostDefs, $errMsgs);

// アップロードファイルをチェック
$allowedMimes = array(
		'jpg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif'
	);
$imageFile = checkUpload('images_file', 5000000, $allowedMimes, $imageMime, $errMsgs);

try {
	if(count($errMsgs) == 0) {
		$dbh = new PDO("mysql:host=$DBHOST;dbname=$DBNAME", $DBUSER, $DBPASS);

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
		if(is_null($cfgCur)) {
			// APIを利用不可
			$errMsgs[] = 'Not valid user.';
			$res['status'] = 'ERROR';
			$res['messages'] = $errMsgs;
		} else {
			// API呼び出し回数制限
			// 次なら1日20回まで
			// $limitOver = checkApiLimit($cfgCur['userId'], $SRV_API_KIND, 86400, 20, $dbh);
			$limitOver = checkApiLimit($cfgCur['userId'], $SRV_API_KIND, 60, 2, $dbh);
			if($limitOver) {
				$errMsgs[] = 'API call limit over.';
				$res['status'] = 'LIMIT OVER';
				$res['messages'] = $errMsgs;

				$apiSeq = insertExecApi($cfgCur, $SRV_API_KIND, json_encode($cfgPost), $res['status'], json_encode($res['messages']), $dbh);
			} else {
				// APIを利用可能
				$resData = watson_recognize_faces($cfgCur, $imageFile['tmp_name'], $imageMime, $errMsgs);
				if($resData === FALSE) {
					// Watsonの応答が不正
					$res['status'] = 'ERROR';
					$res['messages'] = $errMsgs;

					$apiSeq = insertExecApi($cfgCur, $SRV_API_KIND, json_encode($cfgPost), $res['status'], json_encode($res['messages']), $dbh);
				} else {
					// Watsonの応答が正常
					$res['data'] = $resData;

					$apiSeq = insertExecApi($cfgCur, $SRV_API_KIND, json_encode($cfgPost), $res['status'], json_encode($res['data']), $dbh);
				}
			}

			insertExecApiFile($apiSeq, $cfgCur['userId'], $imageFile['size'], $imageMime,
					$imageFile['tmp_name'], $dbh);

			$res['apiCount'] = countApiExecInMonth($cfgCur['userId'], $SRV_API_KIND, time(), $dbh);
		}
	} else {
		error_log(json_encode($res['messages']));
		$res['status'] = 'ERROR';
		$res['messages'] = $errMsgs;
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

?>
