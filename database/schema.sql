CREATE DATABASE IF NOT EXISTS pubquest_risk
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pubquest_risk;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('system_name', 'SME Credit Decision System')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
