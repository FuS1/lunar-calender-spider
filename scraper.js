require('dotenv').config();
const axios = require('axios');
const dayjs = require('dayjs');
const fs = require('fs-extra');
const path = require('path');
const mysql = require('mysql2/promise');

// ================= 設定區 =================

// 從環境變數讀取配置
const START_DATE = process.env.START_DATE || '1950-01-01 00:00:00';
const END_DATE = process.env.END_DATE || '2050-12-31 23:00:00';
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

// 基礎延遲 3000 毫秒
const BASE_DELAY = 4000; 

// 隨機抖動延遲 (毫秒)
const JITTER_DELAY = 3000; 

// API 地址
const API_URL = 'https://www.fatemaster.ai/api/bazi-calculate';

// 固定的請求參數
const BASE_PAYLOAD = {
    "name": "",
    "gender": "male",
    "calendarType": "solar",
    "birthPlace": {
        "address": "台湾",
        "latitude": 23.777978,
        "longitude": 120.930229,
        "country_code": "TW"
    },
    "useTrueSolarTime": true,
    "useEarlyLateZiHour": false,
    "dstAdjusted": null,
    "language": "zh-Hant",
    "fromBazi": false
};

// ================= User Agents =================
const USER_AGENTS = [
    // Windows - Chrome
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    
    // Windows - Edge
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0",
    "Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0",
    
    // Windows - Firefox
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0",
    "Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0",

    // macOS - Safari
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Safari/605.1.15",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15",

    // macOS - Chrome
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",

    // macOS - Firefox
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14.4; rv:124.0) Gecko/20100101 Firefox/124.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:124.0) Gecko/20100101 Firefox/124.0",

    // Linux - Chrome & Firefox
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0",
    "Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0",

    // iOS - iPhone
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 16_7_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.7.7 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/123.0.6312.52 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/124.1 Mobile/15E148 Safari/605.1.15",

    // iOS - iPad
    "Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",

    // Android - Samsung
    "Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 13; SM-A536B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",

    // Android - Pixel / Generic
    "Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 13; Pixel 6a) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",

    // Android - Xiaomi / Redmi
    "Mozilla/5.0 (Linux; Android 13; 22101320G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 13; 23049PCD8G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.80 Mobile Safari/537.36",

    // Android - Other Browsers
    "Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/24.0 Chrome/118.0.0.0 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Firefox/124.0 Mobile Safari/537.36",

    // Legacy / Others
    "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_16_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
    
    // More Windows Varieties
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.86 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.6312.58 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.128 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/123.0.2420.65",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/122.0.2365.92",
    
    // More Mac Varieties
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.6167.184 Safari/537.36",
    
    // More Mobile Varieties
    "Mozilla/5.0 (iPhone; CPU iPhone OS 16_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (iPad; CPU OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Mobile/15E148 Safari/604.1",
    "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36",
    "Mozilla/5.0 (Linux; Android 12; moto g pure) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36"
];

const getRandomUserAgent = () => USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)];

// ================= 主程式 =================

const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const getRandomDelay = () => BASE_DELAY + Math.floor(Math.random() * JITTER_DELAY);

async function initDB() {
    const pool = mysql.createPool(dbConfig);
    return pool;
}

async function getLastSolarDate(pool) {
    // 檢查是否有未抓取的資料 (檢查 api_id 為 NULL 的，且在範圍內)
    const sql = `SELECT solarDate FROM \`${TABLE_NAME}\` WHERE api_id IS NULL AND solarDate >= ? AND solarDate <= ? ORDER BY solarDate ASC LIMIT 1`;
    const [rows] = await pool.query(sql, [START_DATE, END_DATE]);
    if (rows.length > 0) {
        return dayjs(rows[0].solarDate);
    }
    // 如果範圍內都抓完了，回傳 null
    return null;
}

async function saveToDB(pool, data) {
    // 欄位對應: data.id -> db.api_id
    // db.id 是 auto increment, 不作更動
    const columns = [
        'version', 'yearPillar', 'monthPillar', 'dayPillar', 'timePillar',
        'lunarDate', 'zodiacSign', 'timeAdjustment', 'calendarType',
        'wuXing', 'mingGe', 'mingType', 'wuXingAnalysis', 'shiShenAnalysis', 'shenShaAnalysis',
        'daYunWithStarting', 'liuNian', 'liuYue', 'relations', 'display', 'rawWuXing'
    ];
    // 特別處理 api_id
    const apiId = data.id;

    // 準備 UPDATE 語句 (既然已經預先寫入，我們主要做 UPDATE)
    const updateAssignments = columns.map(col => `\`${col}\` = ?`);
    updateAssignments.push(`\`api_id\` = ?`); // 加入 api_id 更新

    const values = columns.map(col => {
        let val = data[col];
        if (typeof val === 'object' && val !== null) {
            return JSON.stringify(val);
        }
        return val;
    });
    values.push(apiId); // 對應 api_id
    
    // WHERE 條件
    values.push(dayjs(data.solarDate).format('YYYY-MM-DD HH:mm:ss'));

    const sql = `
        UPDATE \`${TABLE_NAME}\` 
        SET ${updateAssignments.join(', ')}
        WHERE solarDate = ?
    `;

    await pool.query(sql, values);
}

async function main() {
    let pool;
    try {
        console.log('🔌 連接資料庫...');
        pool = await initDB();
        console.log('✅ 資料庫連接成功');

        let current = dayjs(START_DATE);
        const end = dayjs(END_DATE);

        // 檢查上次抓取進度
        const nextDate = await getLastSolarDate(pool);
        if (nextDate) {
            console.log(`📋 檢測到未完成的任務，將從: ${nextDate.format('YYYY-MM-DD HH:mm')} 開始`);
            // 直接從這個時間點開始抓，不需要再 +2 小時，因為 getLastSolarDate 回傳的是未完成的那一筆
            current = nextDate;
        } else {
            console.log(`🎉 範圍內的資料看起來都已抓取完畢！(或找不到符合條件的空白資料)`);
            // 讓它跑一次 while 迴圈檢查(如果真的結束，while 條件會擋住)
            // 但如果全部完成了，current 設為 end 之後，直接退出
            current = end.add(1, 'hour'); 
        }

        console.log(`🚀 開始抓取任務：${current.format('YYYY-MM-DD HH:mm')} 至 ${end.format('YYYY-MM-DD HH:mm')}`);

        while (current.isBefore(end) || current.isSame(end)) {
            const hour = current.format('HH');
            const isoDate = current.format('YYYY-MM-DDTHH:mm:ss');
            
            const payload = {
                ...BASE_PAYLOAD,
                "birthDate": isoDate,
                "birthTime": {
                    "hour": parseInt(hour),
                    "minute": 0
                }
            };

            try {
                console.log(`📥 正在抓取: ${isoDate} ...`);
                
                const response = await axios.post(API_URL, payload, {
                    headers: {
                        'Content-Type': 'application/json',
                        'User-Agent': getRandomUserAgent()
                    },
                    timeout: 10000 
                });

                // 寫入資料庫
                await saveToDB(pool, response.data);
                console.log(`✅ 存入資料庫成功: ${response.data.solarDate}`);

                const delay = getRandomDelay();
                await sleep(delay);

                current = current.add(STEP_HOURS, 'hour');

            } catch (error) {
                console.error(`❌ 抓取失敗: ${isoDate}`);
                
                if (error.response) {
                    console.error(`   Status: ${error.response.status}, Data: ${JSON.stringify(error.response.data).slice(0, 100)}`);
                    if (error.response.status === 429) {
                        console.warn(`⚠️  偵測到限流 (429)，暫停 60 秒...`);
                        await sleep(60000);
                    }
                } else {
                    console.error(`   Error: ${error.message}`);
                }
                await sleep(5000);
            }
        }

        console.log('抓取完畢');

        // 保持 Process 存活，不退出
        setInterval(() => {
            console.log('抓取完畢');
        }, 60000);

    } catch (err) {
        console.error('Fatal Error:', err);
    }
}

main();