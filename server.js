require('dotenv').config();
const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const bodyParser = require('body-parser');
const OpenCC = require('opencc-js');

// 初始化簡轉繁轉換器 (CN to TW)
const converter = OpenCC.Converter({ from: 'cn', to: 'tw' });

// 遞歸遍歷物件進行字串轉換的輔助函數
function convertObjectRecursively(obj) {
    if (typeof obj === 'string') {
        return converter(obj);
    }
    if (Array.isArray(obj)) {
        return obj.map(convertObjectRecursively);
    }
    if (obj !== null && typeof obj === 'object') {
        const newObj = {};
        for (const key in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, key)) {
                newObj[key] = convertObjectRecursively(obj[key]);
            }
        }
        return newObj;
    }
    return obj;
}

const app = express();
app.use(cors());
app.use(bodyParser.json());

// 資料庫連線設定
const db = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'lunar_calendar',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// 時辰對照表 (將下拉選單的值轉換為小時)
const timeMapping = {
    '23-01': '00', // 子時通常算作當日早子時(00點)或夜子時
    '01-03': '02',
    '03-05': '04',
    '05-07': '06',
    '07-09': '08',
    '09-11': '10',
    '11-13': '12',
    '13-15': '14',
    '15-17': '16',
    '17-19': '18',
    '19-21': '20',
    '21-23': '22'
};

const TABLE_NAME = process.env.DB_TABLE || 'lunar_records';

app.post('/api/calculate', (req, res) => {
    const { birthDate, birthTimeRange } = req.body;

    if (!birthDate || !birthTimeRange) {
        return res.status(400).json({ error: '請輸入完整的日期與時辰' });
    }

    // 1. 取得對應的小時
    const hour = timeMapping[birthTimeRange];
    
    // 2. 組合完整的 DATETIME 字串 (例如: 1955-01-10 02:00:00)
    const queryDateTime = `${birthDate} ${hour}:00:00`;

    console.log(`查詢時間: ${queryDateTime}`);

    // 3. 執行 SQL 查詢
    // 使用新的欄位名稱 solarDate
    const sql = `SELECT * FROM \`${TABLE_NAME}\` WHERE solarDate = ? LIMIT 1`;

    db.query(sql, [queryDateTime], (err, results) => {
        if (err) {
            console.error('Database error:', err);
            return res.status(500).json({ error: '資料庫讀取錯誤' });
        }
        
        if (results.length === 0) {
            return res.json({ message: '找不到對應的資料', data: null });
        }

        // 對查詢結果進行簡繁轉換
        const rawData = results[0];
        const convertedData = convertObjectRecursively(rawData);

        res.json({ message: '查詢成功', data: convertedData });
    });
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});