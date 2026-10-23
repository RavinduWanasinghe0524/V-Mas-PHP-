-- MySQL 8+ schema matching the original Spring Boot entity table and column names.
-- Create an empty database first, then import this file into it.
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(255) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL,
  account_status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
  profile_picture LONGTEXT NULL,
  phone_number VARCHAR(255) NULL,
  gender VARCHAR(255) NULL,
  nic VARCHAR(255) NULL,
  date_of_birth DATE NULL,
  license_number VARCHAR(255) NULL,
  license_expiry_date DATE NULL,
  license_document_path VARCHAR(500) NULL,
  date_joined DATE NULL,
  experience VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_by VARCHAR(100) NULL,
  deleted_at DATETIME NULL,
  INDEX idx_users_status (account_status, is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_name VARCHAR(150) NOT NULL DEFAULT 'Unknown',
  chassis_no VARCHAR(150) NULL,
  engine_no VARCHAR(150) NULL,
  registration_no VARCHAR(50) NOT NULL UNIQUE,
  manufacturer VARCHAR(100) NULL,
  model VARCHAR(100) NULL,
  year INT NULL,
  current_mileage_km INT NULL DEFAULT 0,
  initial_mileage_km INT NULL DEFAULT 0,
  driver_id BIGINT UNSIGNED NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  updated_by VARCHAR(100) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'AVAILABLE',
  fuel_type VARCHAR(50) NULL,
  fuel_capacity DOUBLE NULL,
  vehicle_type VARCHAR(50) NOT NULL DEFAULT 'CAR',
  insurance_expiry_date DATE NULL,
  license_expiry_date DATE NULL,
  insurance_document_path VARCHAR(500) NULL,
  license_document_path VARCHAR(500) NULL,
  registration_book_path VARCHAR(500) NULL,
  vehicle_image LONGTEXT NULL,
  is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_by VARCHAR(100) NULL,
  deleted_at DATETIME NULL,
  deactivation_reason VARCHAR(500) NULL,
  INDEX idx_vehicle_driver (driver_id),
  CONSTRAINT fk_vehicle_driver FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_username VARCHAR(255) NOT NULL,
  vehicle_reg_number VARCHAR(255) NOT NULL,
  origin VARCHAR(255) NULL,
  destination VARCHAR(255) NOT NULL,
  purpose TEXT NULL,
  scheduled_date DATE NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'ASSIGNED',
  assigned_by VARCHAR(255) NULL,
  decline_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  responded_at DATETIME NULL,
  completed_at DATETIME NULL,
  updated_at DATETIME NULL,
  is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_by VARCHAR(100) NULL,
  deleted_at DATETIME NULL,
  INDEX idx_trip_driver (driver_username, status, is_deleted),
  INDEX idx_trip_vehicle (vehicle_reg_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fuel_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_reg_number VARCHAR(255) NOT NULL,
  fuel_type VARCHAR(255) NOT NULL,
  liters DOUBLE NOT NULL,
  cost_per_liter DOUBLE NOT NULL,
  total_cost DOUBLE NOT NULL,
  mileage DOUBLE NOT NULL,
  date DATE NOT NULL,
  driver_username VARCHAR(255) NULL,
  trip_id BIGINT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'APPROVED',
  uploaded_by VARCHAR(255) NULL,
  is_updated BOOLEAN NULL DEFAULT FALSE,
  updated_at DATETIME NULL,
  updated_by VARCHAR(255) NULL,
  is_deleted BOOLEAN NULL DEFAULT FALSE,
  deleted_at DATETIME NULL,
  INDEX idx_fuel_vehicle_date (vehicle_reg_number, date, id),
  INDEX idx_fuel_driver (driver_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_reg_number VARCHAR(255) NOT NULL,
  service_type VARCHAR(100) NOT NULL,
  service_type_detail VARCHAR(255) NULL,
  service_date DATE NOT NULL,
  current_mileage_km INT NOT NULL,
  service_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  technician_workshop VARCHAR(255) NULL,
  next_service_due DATE NULL,
  next_service_mileage_km INT NULL,
  description TEXT NULL,
  created_by VARCHAR(255) NULL,
  attachment_path VARCHAR(500) NULL,
  parts_replaced TEXT NULL,
  service_classification VARCHAR(50) NULL DEFAULT 'ROUTINE',
  trip_id BIGINT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'APPROVED',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  deleted_by VARCHAR(100) NULL,
  deleted_at DATETIME NULL,
  INDEX idx_service_vehicle (vehicle_reg_number, service_type, service_date),
  INDEX idx_service_due (next_service_due)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_record_audits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_record_id BIGINT UNSIGNED NOT NULL,
  changed_by VARCHAR(100) NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_fields TEXT NULL,
  INDEX idx_audit_record (service_record_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_intervals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_type VARCHAR(50) NOT NULL,
  service_type VARCHAR(100) NOT NULL,
  interval_km INT NOT NULL,
  UNIQUE KEY uq_interval (vehicle_type, service_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_reg_number VARCHAR(255) NULL,
  message TEXT NOT NULL,
  type VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read BOOLEAN NOT NULL DEFAULT FALSE,
  INDEX idx_notification_unread (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token VARCHAR(255) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  used BOOLEAN NOT NULL DEFAULT FALSE,
  INDEX idx_reset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(255) NOT NULL,
  last_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
