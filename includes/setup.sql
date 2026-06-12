CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('client','admin') DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    registration VARCHAR(20) NOT NULL,
    model VARCHAR(100),
    offer_type ENUM('flex','confort','serenite') NOT NULL,
    monthly_premium DECIMAL(10,2) NOT NULL,
    franchise DECIMAL(10,2) NOT NULL,
    engagement_months INT NOT NULL,
    status ENUM('sent','accepted','expired') DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quote_id INT,
    registration VARCHAR(20) NOT NULL,
    model VARCHAR(100),
    offer_type ENUM('flex','confort','serenite') NOT NULL,
    monthly_premium DECIMAL(10,2) NOT NULL,
    franchise DECIMAL(10,2) NOT NULL,
    engagement_months INT NOT NULL,
    status ENUM('active','cancelled','expired') DEFAULT 'active',
    start_date DATE NOT NULL,
    next_billing_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contract_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_type ENUM('engine_failure','airframe_damage','avionics_failure','landing_gear','prop_damage','other') NOT NULL,
    description TEXT,
    repair_cost DECIMAL(10,2) NOT NULL,
    indemnity DECIMAL(10,2),
    franchise_applied DECIMAL(10,2),
    status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id)
);
