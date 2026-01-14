const axios = require('axios');
const dayjs = require('dayjs');
const fs = require('fs-extra');
const path = require('path');

// ================= 設定區 =================

// 起始與結束時間
const START_DATE = '1950-01-01 00:00:00';
const END_DATE = '2050-12-31 23:00:00';

// 抓取間隔 (單位: 小時)
// 如果只想抓每天，改成 24
const STEP_HOURS = 2; 

// 基礎延遲 (毫秒)，建議至少 2500
const BASE_DELAY = 2500; 

// 隨機抖動延遲 (毫秒)，讓間隔不那麼規律
const JITTER_DELAY = 3000; 

// API 地址
const API_URL = 'https://www.fatemaster.ai/api/bazi-calculate';

// 固定的請求參數 (除了時間以外的參數)
const BASE_PAYLOAD = {
    "name": "",
    "gender": "male",
    "calendarType": "solar",
    // birthDate 和 birthTime 會在迴圈中動態生成
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

// ================= 主程式 =================

// 睡眠函式
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// 產生隨機延遲時間
const getRandomDelay = () => BASE_DELAY + Math.floor(Math.random() * JITTER_DELAY);

async function main() {
    let current = dayjs(START_DATE);
    const end = dayjs(END_DATE);

    console.log(`🚀 開始抓取任務：${START_DATE} 至 ${END_DATE}`);
    console.log(`📁 資料將儲存於 ./data 資料夾中`);

    while (current.isBefore(end) || current.isSame(end)) {
        const year = current.format('YYYY');
        const month = current.format('MM');
        const day = current.format('DD');
        const hour = current.format('HH');
        
        // 建立檔案路徑： data/1993/08/1993-08-20_09.json
        const dirPath = path.join(__dirname, 'data', year, month);
        const fileName = `${year}_${month}_${day}_${hour}.json`;
        const filePath = path.join(dirPath, fileName);

        // 1. 檢查檔案是否存在 (斷點續傳功能)
        if (fs.existsSync(filePath)) {
            // console.log(`⏭️  跳過 (已存在): ${fileName}`);
            current = current.add(STEP_HOURS, 'hour');
            continue;
        }

        // 2. 準備 Payload
        // API 需要的 ISO 格式: YYYY-MM-DDTHH:mm:ss
        // 但 birthTime 物件需要分開 hour/minute
        const isoDate = current.format('YYYY-MM-DDTHH:mm:ss');
        
        const payload = {
            ...BASE_PAYLOAD,
            "birthDate": isoDate,
            "birthTime": {
                "hour": parseInt(hour),
                "minute": 0 // 這裡設為 0 分，若需要更細可調整
            }
        };

        // 3. 發送請求
        try {
            await fs.ensureDir(dirPath); // 確保資料夾存在

            console.log(`📥 正在抓取: ${isoDate} ...`);
            
            const response = await axios.post(API_URL, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                },
                timeout: 10000 // 10秒超時設定
            });

            // 4. 儲存檔案
            await fs.writeJson(filePath, response.data, { spaces: 2 });
            console.log(`✅ 儲存成功: ${fileName}`);

            // 5. 隨機延遲 (避免被 Ban)
            const delay = getRandomDelay();
            await sleep(delay);

            // 只有成功時才推進時間
            current = current.add(STEP_HOURS, 'hour');

        } catch (error) {
            console.error(`❌ 抓取失敗: ${isoDate}`);
            
            if (error.response) {
                console.error(`   Status: ${error.response.status}, Data: ${JSON.stringify(error.response.data).slice(0, 100)}`);
                // 如果是 429 Too Many Requests，休息久一點
                if (error.response.status === 429) {
                    console.warn(`⚠️  偵測到限流 (429)，暫停 60 秒...`);
                    await sleep(60000);
                }
            } else {
                console.error(`   Error: ${error.message}`);
            }

            // 發生錯誤時，休息 5 秒後重試 (不推進 current 時間，下次迴圈會重抓同一個時間)
            await sleep(5000);
        }
    }

    console.log('🎉 所有資料抓取完成！');
}

main().catch(err => console.error(err));