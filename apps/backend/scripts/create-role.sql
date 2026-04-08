DO $$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'epAdmin') THEN
    CREATE ROLE "epAdmin" LOGIN PASSWORD 'epPassword';
  ELSE
    ALTER ROLE "epAdmin" WITH LOGIN PASSWORD 'epPassword';
  END IF;
END
$$;
