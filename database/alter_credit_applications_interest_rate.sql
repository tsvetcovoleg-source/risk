-- Run this migration once after deploying the code change.
-- If your MySQL version supports ADD COLUMN IF NOT EXISTS, you may add that clause.
-- Otherwise, execute this ALTER only if the columns do not already exist.
ALTER TABLE credit_applications
ADD COLUMN interest_rate DECIMAL(8,4) NULL AFTER requested_term_months,
ADD COLUMN annual_debt_service_amount DECIMAL(18,2) NULL AFTER interest_rate;
