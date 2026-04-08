const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

async function main() {
  const sqlFile = path.resolve(__dirname, '../../../database/init.mysql.sql');
  const sql = fs.readFileSync(sqlFile, 'utf8');

  const connection = await mysql.createConnection({
    host: 'localhost',
    port: 3306,
    user: 'root',
    password: '',
    multipleStatements: true,
  });

  await connection.query(sql);
  await connection.end();
  console.log('MYSQL_SCHEMA_IMPORTED_OK');
}

main().catch((error) => {
  console.error('MYSQL_SCHEMA_IMPORTED_KO', error?.code || error?.message || error);
  process.exit(1);
});
