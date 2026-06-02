
CREATE TABLE IF NOT EXISTS scoring_non_financial_factors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scoring_result_id INT UNSIGNED NOT NULL,
    business_reputation TINYINT NULL,
    management_quality TINYINT NULL,
    market_position TINYINT NULL,
    industry_risk TINYINT NULL,
    transparency_quality TINYINT NULL,
    relationship_history TINYINT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_scoring_non_financial_result_id (scoring_result_id),
    CONSTRAINT fk_scoring_non_financial_result
        FOREIGN KEY (scoring_result_id)
        REFERENCES scoring_results (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

