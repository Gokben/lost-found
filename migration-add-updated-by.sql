-- Run once in the live MySQL database before deploying the updated edit form.
ALTER TABLE items ADD COLUMN updated_by VARCHAR(255) NULL AFTER recorded_by;

-- Existing records did not retain a separate updater. Preserve the best
-- available historical value for records that were updated previously.
UPDATE items
SET updated_by = recorded_by
WHERE updated_at IS NOT NULL
  AND updated_at <> created_at
  AND (updated_by IS NULL OR updated_by = '');
