const mysql = require('mysql2/promise');

async function main() {
  const c = await mysql.createConnection({
    host: 'localhost',
    port: 3306,
    user: 'epadmin_app',
    password: 'epAdminDev2024!',
    database: 'e_parapheur',
  });

  const [rows] = await c.query(
    "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'administration_users' AND COLUMN_NAME = 'adminRole'",
  );

  if (Number(rows[0]?.cnt || 0) === 0) {
    await c.query("ALTER TABLE administration_users ADD COLUMN adminRole VARCHAR(50) NOT NULL DEFAULT 'user' AFTER username");
    console.log('ADDED=administration_users.adminRole');
  } else {
    console.log('ALREADY_EXISTS=administration_users.adminRole');
  }

  await c.end();
}

main().catch((e) => {
  console.error('PATCH_KO', e.code || e.message);
  process.exit(1);
});
