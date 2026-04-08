const mysql = require('mysql2/promise');

async function main() {
  const connection = await mysql.createConnection({
    host: 'localhost',
    port: 3306,
    user: 'root',
    password: '',
  });

  await connection.query("CREATE DATABASE IF NOT EXISTS e_parapheur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  await connection.query("CREATE USER IF NOT EXISTS 'epadmin_app'@'localhost' IDENTIFIED BY 'epAdminDev2024!'");
  await connection.query("CREATE USER IF NOT EXISTS 'epadmin_app'@'127.0.0.1' IDENTIFIED BY 'epAdminDev2024!'");
  await connection.query("GRANT ALL PRIVILEGES ON e_parapheur.* TO 'epadmin_app'@'localhost'");
  await connection.query("GRANT ALL PRIVILEGES ON e_parapheur.* TO 'epadmin_app'@'127.0.0.1'");
  await connection.query('FLUSH PRIVILEGES');

  await connection.end();
  console.log('DB_AND_GRANTS_OK');
}

main().catch((error) => {
  console.error('DB_AND_GRANTS_KO', error?.code || error?.message || error);
  process.exit(1);
});
