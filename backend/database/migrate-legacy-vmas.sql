-- Run only on a backup/copy of the older Spring Boot vmas_db schema after
-- importing schema.sql to create missing tables. Do not run twice.
ALTER TABLE users
  ADD COLUMN phone_number VARCHAR(255) NULL,
  ADD COLUMN gender VARCHAR(255) NULL,
  ADD COLUMN nic VARCHAR(255) NULL,
  ADD COLUMN date_of_birth DATE NULL,
  ADD COLUMN license_number VARCHAR(255) NULL,
  ADD COLUMN license_expiry_date DATE NULL,
  ADD COLUMN license_document_path VARCHAR(500) NULL,
  ADD COLUMN date_joined DATE NULL,
  ADD COLUMN experience VARCHAR(255) NULL,
  ADD COLUMN address VARCHAR(255) NULL,
  ADD COLUMN is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN deleted_by VARCHAR(100) NULL,
  ADD COLUMN deleted_at DATETIME NULL;

ALTER TABLE vehicles
  ADD COLUMN engine_no VARCHAR(150) NULL,
  ADD COLUMN initial_mileage_km INT NULL DEFAULT 0,
  ADD COLUMN fuel_capacity DOUBLE NULL,
  ADD COLUMN vehicle_type VARCHAR(50) NOT NULL DEFAULT 'CAR',
  ADD COLUMN vehicle_image LONGTEXT NULL,
  ADD COLUMN is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN deleted_by VARCHAR(100) NULL,
  ADD COLUMN deleted_at DATETIME NULL,
  ADD COLUMN deactivation_reason VARCHAR(500) NULL;

ALTER TABLE fuel_logs
  ADD COLUMN trip_id BIGINT NULL,
  ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'APPROVED';

ALTER TABLE service_records
  ADD COLUMN trip_id BIGINT NULL,
  ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'APPROVED';
