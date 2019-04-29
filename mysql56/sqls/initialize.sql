DROP DATABASE IF EXISTS vrecog;
CREATE DATABASE vrecog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vrecog;

CREATE USER vrecog@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON vrecog.* TO vrecog@'localhost';
CREATE USER vrecog@'%' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON vrecog.* TO vrecog@'%';

CREATE TABLE m_user (
 seq INT AUTO_INCREMENT PRIMARY KEY COMMENT '連番',
 user_id VARCHAR(20) NOT NULL COMMENT 'ユーザーID',
 uuid VARCHAR(100) NULL COMMENT 'デバイスのUUID',
 cordova VARCHAR(20) NULL COMMENT '使用cordovaバージョン',
 device_model VARCHAR(100) NULL COMMENT 'デバイスのモデル',
 device_version VARCHAR(20) NULL COMMENT 'デバイスのプラットフォーム',
 device_platform VARCHAR(20) NULL COMMENT 'デバイスのバージョン',
 is_virtual CHAR(1) NULL COMMENT 'エミュレーターかどうか',
 version_code VARCHAR(20) NULL COMMENT 'アプリのバージョンコード',
 version_num VARCHAR(20) NULL COMMENT 'アプリのバージョンナンバー',
 created_at TIMESTAMP NULL COMMENT '新規作成時刻',
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新時刻',
 expired_at TIMESTAMP NULL COMMENT '廃止時刻'
) comment='ユーザー管理';

ALTER TABLE m_user ADD CONSTRAINT unq_user_id UNIQUE(user_id);

CREATE TABLE t_exec_api (
 seq INT AUTO_INCREMENT PRIMARY KEY COMMENT '連番',
 user_id VARCHAR(20) NOT NULL COMMENT 'ユーザーID',
 cordova VARCHAR(20) NULL COMMENT '使用cordovaバージョン',
 device_model VARCHAR(100) NULL COMMENT 'デバイスのモデル',
 device_version VARCHAR(20) NULL COMMENT 'デバイスのバージョン',
 device_platform VARCHAR(20) NULL COMMENT 'デバイスのプラットフォーム',
 version_code VARCHAR(20) NULL COMMENT 'アプリのバージョンコード',
 version_num VARCHAR(20) NULL COMMENT 'アプリのバージョンナンバー',
 srv_app_ver VARCHAR(20) NULL COMMENT 'サーバープログラムのバージョン',
 kind VARCHAR(20) NOT NULL COMMENT 'API種類',
 params TEXT NULL COMMENT 'パラメータ',
 res_status VARCHAR(20) NULL COMMENT '結果ステータス',
 res_data TEXT NULL COMMENT '結果データ',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '新規作成時刻'
) comment='API実行記録';

CREATE INDEX idx_t_exec_api_ukc ON t_exec_api(user_id,kind,created_at);

CREATE TABLE t_exec_api_file (
 seq INT AUTO_INCREMENT PRIMARY KEY COMMENT '連番',
 api_seq INT NOT NULL COMMENT '対応するAPIの連番',
 user_id VARCHAR(20) NOT NULL COMMENT 'ユーザーID',
 file_size INT NULL COMMENT 'ファイルサイズ',
 file_mime VARCHAR(20) NULL COMMENT 'ファイルのMIMEタイプ',
 file_data MEDIUMBLOB NULL COMMENT 'ファイルデータ',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '新規作成時刻'
) comment='API実行ファイル';

CREATE INDEX idx_t_exec_api_file_as ON t_exec_api_file(api_seq);
