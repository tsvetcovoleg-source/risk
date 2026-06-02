-- SME Credit Decision System - MVP database schema
-- Target platform: MySQL 8.x / MariaDB compatible, InnoDB, utf8mb4.
--
-- Design notes:
-- 1. The schema keeps reference values as ENUMs for MVP simplicity. In a later stage,
--    statuses, currencies, collateral types, and scoring risk levels can be moved to
--    dedicated reference tables if business administration of dictionaries is needed.
-- 2. Business records use soft deletion through deleted_at where reactivation,
--    auditability, or historical review may be required.
-- 3. Foreign keys intentionally use conservative ON DELETE rules. Core business
--    relationships are RESTRICTed so clients, applications, and decisions cannot be
--    removed accidentally with their dependent records. Purely dependent detail tables
--    such as balance sheet lines, income statement lines, committee votes, and comments
--    use CASCADE only where deleting the parent detail would leave no meaningful record.
-- 4. clients.idno is indexed but not UNIQUE in the MVP. This supports realistic lookup
--    by Moldovan fiscal code while avoiding blockers during early testing, data cleanup,
--    and duplicate-case experiments. A UNIQUE constraint can be added later after test
--    data and operational rules are finalized.

CREATE DATABASE IF NOT EXISTS pubquest_risk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pubquest_risk;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_system_settings_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    idno VARCHAR(50) NULL,
    legal_form VARCHAR(100) NULL,
    registration_date DATE NULL,
    activity_sector VARCHAR(255) NULL,
    caem_code VARCHAR(20) NULL,
    address TEXT NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'watchlist', 'rejected') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_clients_idno (idno),
    KEY idx_clients_client_name (client_name),
    KEY idx_clients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    IDNO VARCHAR(50) NULL,
    REPORT_KEY VARCHAR(50) NULL,
    META_CSV LONGTEXT NULL,
    BIL_CSV LONGTEXT NULL,
    PNL_CSV LONGTEXT NULL,
    EQT_CSV LONGTEXT NULL,
    CF_CSV LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fin_data_client_id (client_id),
    KEY idx_fin_data_idno (IDNO),
    KEY idx_fin_data_report_key (REPORT_KEY),
    UNIQUE KEY uq_fin_data_client_report (client_id, REPORT_KEY),
    CONSTRAINT fk_fin_data_client
        FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_related_parties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    party_name VARCHAR(255) NOT NULL,
    party_type ENUM('individual', 'legal_entity') NOT NULL,
    idno_or_idnp VARCHAR(50) NULL,
    relationship_type ENUM('shareholder', 'beneficial_owner', 'administrator', 'group_company', 'guarantor', 'other') NOT NULL DEFAULT 'other',
    ownership_percent DECIMAL(5,2) NULL,
    is_beneficiary BOOLEAN NOT NULL DEFAULT FALSE,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_client_related_parties_client_id (client_id),
    KEY idx_client_related_parties_idno_or_idnp (idno_or_idnp),
    CONSTRAINT fk_client_related_parties_client
        FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credit_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    application_number VARCHAR(50) NOT NULL,
    application_date DATE NOT NULL,
    requested_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    currency ENUM('MDL', 'EUR', 'USD') NOT NULL DEFAULT 'MDL',
    requested_term_months INT UNSIGNED NULL,
    credit_product VARCHAR(150) NULL,
    credit_purpose TEXT NULL,
    repayment_source TEXT NULL,
    existing_exposure_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    proposed_total_exposure_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft', 'submitted', 'in_analysis', 'risk_review', 'committee_review', 'approved', 'approved_with_conditions', 'rejected', 'cancelled', 'disbursed') NOT NULL DEFAULT 'draft',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_credit_applications_application_number (application_number),
    KEY idx_credit_applications_client_id (client_id),
    KEY idx_credit_applications_status (status),
    KEY idx_credit_applications_application_date (application_date),
    CONSTRAINT fk_credit_applications_client
        FOREIGN KEY (client_id) REFERENCES clients (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    period_type ENUM('annual', 'quarterly', 'interim', 'management') NOT NULL,
    period_end_date DATE NOT NULL,
    period_label VARCHAR(100) NOT NULL,
    is_audited BOOLEAN NOT NULL DEFAULT FALSE,
    data_source ENUM('official_financial_statements', 'management_accounts', 'tax_reports', 'manual_input', 'other') NOT NULL DEFAULT 'manual_input',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_financial_periods_application_id (application_id),
    KEY idx_financial_periods_period_end_date (period_end_date),
    CONSTRAINT fk_financial_periods_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_balance_sheet (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    financial_period_id INT UNSIGNED NOT NULL,
    cash_and_equivalents DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    accounts_receivable DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    inventory DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    other_current_assets DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_current_assets DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    fixed_assets DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    other_non_current_assets DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_non_current_assets DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_assets DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    short_term_debt DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    accounts_payable DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    other_current_liabilities DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_current_liabilities DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    long_term_debt DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    other_non_current_liabilities DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_non_current_liabilities DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    equity DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_liabilities_and_equity DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_financial_balance_sheet_period (financial_period_id),
    CONSTRAINT fk_financial_balance_sheet_period
        FOREIGN KEY (financial_period_id) REFERENCES financial_periods (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_income_statement (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    financial_period_id INT UNSIGNED NOT NULL,
    revenue DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    cost_of_goods_sold DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    gross_profit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    operating_expenses DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    ebitda DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    depreciation_amortization DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    ebit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    interest_expense DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    profit_before_tax DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    tax_expense DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    net_profit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_financial_income_statement_period (financial_period_id),
    CONSTRAINT fk_financial_income_statement_period
        FOREIGN KEY (financial_period_id) REFERENCES financial_periods (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_ratios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    financial_period_id INT UNSIGNED NOT NULL,
    current_ratio DECIMAL(10,4) NULL,
    quick_ratio DECIMAL(10,4) NULL,
    debt_to_equity DECIMAL(10,4) NULL,
    debt_to_assets DECIMAL(10,4) NULL,
    equity_ratio DECIMAL(10,4) NULL,
    ebitda_margin DECIMAL(10,4) NULL,
    net_profit_margin DECIMAL(10,4) NULL,
    interest_coverage_ratio DECIMAL(10,4) NULL,
    debt_service_coverage_ratio DECIMAL(10,4) NULL,
    revenue_growth_percent DECIMAL(10,4) NULL,
    net_profit_growth_percent DECIMAL(10,4) NULL,
    calculated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_financial_ratios_application_period (application_id, financial_period_id),
    KEY idx_financial_ratios_financial_period_id (financial_period_id),
    CONSTRAINT fk_financial_ratios_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_financial_ratios_period
        FOREIGN KEY (financial_period_id) REFERENCES financial_periods (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS collateral (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    collateral_type ENUM('real_estate', 'vehicle', 'equipment', 'inventory', 'deposit', 'guarantee', 'suretyship', 'other') NOT NULL DEFAULT 'other',
    description TEXT NOT NULL,
    owner_name VARCHAR(255) NULL,
    estimated_market_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    accepted_collateral_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    currency ENUM('MDL', 'EUR', 'USD') NOT NULL DEFAULT 'MDL',
    valuation_date DATE NULL,
    valuation_source VARCHAR(255) NULL,
    pledge_status ENUM('proposed', 'under_review', 'accepted', 'rejected', 'registered', 'released') NOT NULL DEFAULT 'proposed',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_collateral_application_id (application_id),
    KEY idx_collateral_pledge_status (pledge_status),
    CONSTRAINT fk_collateral_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scoring_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    financial_score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    non_financial_score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    collateral_score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    risk_level ENUM('low', 'moderate', 'medium', 'high', 'very_high') NOT NULL DEFAULT 'medium',
    model_version VARCHAR(50) NOT NULL DEFAULT '1.0',
    expert_override BOOLEAN NOT NULL DEFAULT FALSE,
    override_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_scoring_results_application_id (application_id),
    KEY idx_scoring_results_risk_level (risk_level),
    CONSTRAINT fk_scoring_results_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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

CREATE TABLE IF NOT EXISTS credit_memos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    executive_summary TEXT NULL,
    client_description TEXT NULL,
    transaction_description TEXT NULL,
    financial_analysis TEXT NULL,
    risk_analysis TEXT NULL,
    collateral_analysis TEXT NULL,
    strengths TEXT NULL,
    weaknesses TEXT NULL,
    recommendation TEXT NULL,
    recommended_decision ENUM('approve', 'approve_with_conditions', 'reject', 'postpone', 'request_additional_information') NULL,
    prepared_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_credit_memos_application_id (application_id),
    CONSTRAINT fk_credit_memos_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS committee_decisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    committee_date DATE NOT NULL,
    decision ENUM('approved', 'approved_with_conditions', 'rejected', 'postponed', 'returned_for_revision') NOT NULL,
    approved_amount DECIMAL(18,2) NULL,
    approved_currency ENUM('MDL', 'EUR', 'USD') NULL,
    approved_term_months INT UNSIGNED NULL,
    conditions TEXT NULL,
    rejection_reason TEXT NULL,
    decision_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_committee_decisions_application_id (application_id),
    KEY idx_committee_decisions_committee_date (committee_date),
    UNIQUE KEY uq_committee_decisions_application_id (application_id),
    CONSTRAINT fk_committee_decisions_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS committee_votes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    committee_decision_id INT UNSIGNED NOT NULL,
    vote ENUM('for', 'against', 'abstain', 'conditional') NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_committee_votes_decision_id (committee_decision_id),
    CONSTRAINT fk_committee_votes_decision
        FOREIGN KEY (committee_decision_id) REFERENCES committee_decisions (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_application_comments_application_id (application_id),
    CONSTRAINT fk_application_comments_application
        FOREIGN KEY (application_id) REFERENCES credit_applications (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    entity_type ENUM('client', 'application', 'comment', 'related_party', 'financials', 'financial_period', 'balance_sheet', 'income_statement', 'financial_ratios', 'collateral', 'scoring', 'memo', 'credit_memo', 'committee_decision', 'committee_vote', 'system') NOT NULL,
    entity_id INT UNSIGNED NULL,
    old_value JSON NULL,
    new_value JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_entity_type (entity_type),
    KEY idx_audit_logs_entity_id (entity_id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_entity_lookup (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO system_settings (setting_key, setting_value)
VALUES
    ('system_name', 'SME Credit Decision System'),
    ('default_currency', 'MDL'),
    ('country', 'Republic of Moldova'),
    ('mvp_version', '1.0')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;
