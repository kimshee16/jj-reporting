CREATE TABLE IF NOT EXISTS facebook_ads_accounts_adsets (
    id VARCHAR(255) PRIMARY KEY,
    account_id VARCHAR(255) NOT NULL,
    campaign_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50),
    budget_type VARCHAR(50), -- e.g., DAILY, LIFETIME
    daily_budget DECIMAL(15, 2),
    lifetime_budget DECIMAL(15, 2),
    
    -- Audience targeting summary
    age_min INT,
    age_max INT,
    gender VARCHAR(50), -- e.g., MALE, FEMALE, ALL
    interests TEXT, -- JSON string of interests
    custom_audience_type VARCHAR(100), -- e.g., CUSTOM, LOOKALIKE, NONE
    lookalike_audience_id VARCHAR(255),
    
    -- Placement
    placement TEXT, -- JSON string of placements
    
    -- Device breakdown
    device_breakdown TEXT, -- JSON string of device data
    
    -- Schedule
    start_time DATETIME,
    end_time DATETIME,
    
    -- Performance metrics
    results DECIMAL(15, 2) DEFAULT 0.00,
    impressions DECIMAL(15, 2) DEFAULT 0.00,
    reach DECIMAL(15, 2) DEFAULT 0.00,
    cpm DECIMAL(15, 2) DEFAULT 0.00,
    ctr DECIMAL(15, 2) DEFAULT 0.00,
    cpc DECIMAL(15, 2) DEFAULT 0.00,
    
    -- Timestamps
    created_time DATETIME,
    last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
