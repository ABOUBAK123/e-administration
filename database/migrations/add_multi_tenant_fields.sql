-- Add administrationId to signature_provider_configs (make configs per-administration)
ALTER TABLE signature_provider_configs 
ADD COLUMN IF NOT EXISTS administration_id UUID DEFAULT NULL;

-- Add index for administrationId
CREATE INDEX IF NOT EXISTS idx_signature_provider_configs_admin_id 
  ON signature_provider_configs(administration_id);

-- Add foreign key to issuing_administrations
ALTER TABLE signature_provider_configs
ADD CONSTRAINT IF NOT EXISTS fk_signature_provider_config_admin 
  FOREIGN KEY (administration_id) 
  REFERENCES issuing_administrations(id) 
  ON DELETE CASCADE;

-- Add adminRole column to administration_users
ALTER TABLE administration_users 
ADD COLUMN IF NOT EXISTS admin_role VARCHAR(50) DEFAULT 'user';

-- Add index for adminRole for faster queries
CREATE INDEX IF NOT EXISTS idx_administration_users_admin_role 
  ON administration_users(admin_role);

-- Create index on administrationId + adminRole for efficient hierarchy queries
CREATE INDEX IF NOT EXISTS idx_administration_users_admin_hierarchy 
  ON administration_users(administration_id, admin_role);

-- Ensure existing admin users are marked correctly
UPDATE administration_users 
SET admin_role = 'admin' 
WHERE admin_role = 'user' AND id IN (
  SELECT DISTINCT au.id FROM administration_users au
  WHERE EXISTS (
    SELECT 1 FROM administration_users au2 
    WHERE au2.administration_id = au.administration_id 
    LIMIT 1
  )
)
LIMIT 1; -- This is a safety measure; update from the first admin record per administration
