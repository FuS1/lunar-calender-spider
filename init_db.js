require('dotenv').config();
const mysql = require('mysql2/promise');
const dayjs = require('dayjs');

// ================= 設定區 =================
const START_YEAR = 1950;
const END_YEAR = 2050;
const STEP_HOURS = parseInt(process.env.STEP_HOURS || '2');

// 資料庫配置
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'lunar_calendar',
    port: process.env.DB_PORT || 3306,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

const TABLE_NAME = process.env.DB_TABLE || 'bazi_records';

async function main() {
    let pool;
    try {
        console.log('🔌 連接資料庫...');
        const connection = await mysql.createConnection({
            host: dbConfig.host,
            user: dbConfig.user,
            password: dbConfig.password,
            port: dbConfig.port
        });

        await connection.query(`CREATE DATABASE IF NOT EXISTS \`${dbConfig.database}\`;`);
        await connection.end();

        pool = mysql.createPool(dbConfig);
        
        console.log('🛠️  建立資料表...');
        // 修改 Schema: id 為 INT AUTO_INCREMENT, 新增 api_id 存原始 UUID
        const createTableSQL = `
            CREATE TABLE IF NOT EXISTS \`${TABLE_NAME}\` (
                \`id\` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                \`solarDate\` DATETIME NULL,
                \`yearPillar\` VARCHAR(50) NULL,
                \`monthPillar\` VARCHAR(50) NULL,
                \`dayPillar\` VARCHAR(50) NULL,
                \`timePillar\` VARCHAR(50) NULL,
                \`lunarDate\` VARCHAR(255) NULL,
                \`zodiacSign\` VARCHAR(50) NULL,
                \`timeAdjustment\` INT NULL,
                \`calendarType\` VARCHAR(50) NULL,
                \`wuXing\` LONGTEXT NULL,
                \`mingGe\` LONGTEXT NULL,
                \`mingType\` VARCHAR(255) NULL,
                \`wuXingAnalysis\` LONGTEXT NULL,
                \`shiShenAnalysis\` LONGTEXT NULL,
                \`shenShaAnalysis\` LONGTEXT NULL,
                \`daYunWithStarting\` LONGTEXT NULL,
                \`liuNian\` LONGTEXT NULL,
                \`liuYue\` LONGTEXT NULL,
                \`relations\` LONGTEXT NULL,
                \`display\` LONGTEXT NULL,
                \`rawWuXing\` LONGTEXT NULL,
                \`version\` INT NULL,
                \`api_id\` VARCHAR(255) NULL COMMENT '原始API回傳的ID',
                PRIMARY KEY (\`id\`),
                UNIQUE KEY \`unique_solar_date\` (\`solarDate\`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        `;
        
        await pool.query(createTableSQL);
        console.log('✅ 資料表準備完成');

        console.log(`🌱 開始預先寫入資料 (${START_YEAR} ~ ${END_YEAR})...`);

        let current = dayjs(`${START_YEAR}-01-01 00:00:00`);
        const end = dayjs(`${END_YEAR}-12-31 23:00:00`);
        
        let batchParams = [];
        const BATCH_SIZE = 5000;
        let count = 0;

        while (current.isBefore(end) || current.isSame(end)) {
            // 準備插入 solarDate
            batchParams.push([current.format('YYYY-MM-DD HH:mm:ss')]);
            
            if (batchParams.length >= BATCH_SIZE) {
                await insertBatch(pool, batchParams);
                count += batchParams.length;
                console.log(`... 已寫入 ${count} 筆 (目前: ${current.format('YYYY-MM-DD')})`);
                batchParams = [];
            }

            current = current.add(STEP_HOURS, 'hour');
        }

        // 寫入剩餘的
        if (batchParams.length > 0) {
            await insertBatch(pool, batchParams);
            count += batchParams.length;
        }

        console.log(`🎉 總共預先寫入 ${count} 筆資料！`);
        
    } catch (err) {
        console.error('❌ 錯誤:', err);
    } finally {
        if (pool) await pool.end();
    }
}

async function insertBatch(pool, values) {
    const sql = `INSERT IGNORE INTO \`${TABLE_NAME}\` (solarDate) VALUES ?`;
    await pool.query(sql, [values]);
}

main();
