-- Run once for an existing vehicle_passes table, after backing up the database.
ALTER TABLE vehicle_passes
  ADD COLUMN faculty VARCHAR(100) NULL,
  ADD COLUMN department VARCHAR(150) NULL,
  ADD COLUMN approval_token_hash CHAR(64) NULL,
  ADD COLUMN approval_token_expires_at DATETIME NULL,
  ADD COLUMN hod_approved_at DATETIME NULL,
  ADD COLUMN dean_approved_at DATETIME NULL;
