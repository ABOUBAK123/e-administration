const { Client } = require('pg');
const bcrypt = require('bcryptjs');

async function run() {
  const adminEmail = process.env.ADMIN_EMAIL || 'admin@eparapheur.local';
  const adminUsername = process.env.ADMIN_USERNAME || 'admin';
  const adminFullName = process.env.ADMIN_FULLNAME || 'Super Admin';
  const adminPassword = process.env.ADMIN_PASSWORD || 'Admin@123456';

  const client = new Client({
    host: process.env.DB_HOST || 'localhost',
    port: Number(process.env.DB_PORT || 5432),
    user: process.env.DB_USER || 'epAdmin',
    password: process.env.DB_PASSWORD || 'epPassword',
    database: process.env.DB_NAME || 'e_parapheur',
  });

  try {
    await client.connect();

    const hash = await bcrypt.hash(adminPassword, 10);

    const query = `
      INSERT INTO users (username, email, "passwordHash", "fullName", role, status, "createdAt", "updatedAt")
      VALUES ($1, $2, $3, $4, 'admin', 'active', NOW(), NOW())
      ON CONFLICT (email)
      DO UPDATE SET
        username = EXCLUDED.username,
        "passwordHash" = EXCLUDED."passwordHash",
        "fullName" = EXCLUDED."fullName",
        role = 'admin',
        status = 'active',
        "updatedAt" = NOW()
      RETURNING id, username, email, role, status;
    `;

    const result = await client.query(query, [
      adminUsername,
      adminEmail,
      hash,
      adminFullName,
    ]);

    const account = result.rows[0];
    console.log('ADMIN_CREATED', JSON.stringify(account));
    console.log('ADMIN_LOGIN', JSON.stringify({ email: adminEmail, password: adminPassword }));
  } catch (error) {
    console.error('ADMIN_CREATE_ERROR', JSON.stringify({
      message: error?.message || '',
      code: error?.code || '',
      detail: error?.detail || '',
      errno: error?.errno || '',
    }));
    process.exitCode = 1;
  } finally {
    await client.end().catch(() => undefined);
  }
}

run();
