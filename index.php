<?php
// PHP Helper Functions
function getElementClass($text) {
    if (strpos($text, '木') !== false) return 'element-wood';
    if (strpos($text, '火') !== false) return 'element-fire';
    if (strpos($text, '土') !== false) return 'element-earth';
    if (strpos($text, '金') !== false) return 'element-metal';
    if (strpos($text, '水') !== false) return 'element-water';
    return 'text-slate-800';
}

function getBGClass($text) {
    if (strpos($text, '木') !== false) return 'bg-wood';
    if (strpos($text, '火') !== false) return 'bg-fire';
    if (strpos($text, '土') !== false) return 'bg-earth';
    if (strpos($text, '金') !== false) return 'bg-metal';
    if (strpos($text, '水') !== false) return 'bg-water';
    return 'bg-slate-100';
}

$resultData = null;
$errorMsg = null;
$birthDateInput = '';
$birthTimeInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $birthDateInput = $_POST['birthDate'] ?? '';
    $birthTimeInput = $_POST['birthTime'] ?? '';

    // Load .env file
    $env = [];
    if (file_exists(__DIR__ . '/.env')) {
        $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $env[trim($name)] = trim($value);
        }
    }

    $host = $env['DB_HOST'] ?? 'localhost';
    $port = $env['DB_PORT'] ?? 3306;
    $user = $env['DB_USER'] ?? 'root';
    $password = $env['DB_PASSWORD'] ?? '';
    $dbname = $env['DB_NAME'] ?? 'lunar_calendar';
    $table = $env['DB_TABLE'] ?? 'bazi_records';
    
    // Time mapping
    $timeMapping = [
        '23-01' => '00',
        '01-03' => '02',
        '03-05' => '04',
        '05-07' => '06',
        '07-09' => '08',
        '09-11' => '10',
        '11-13' => '12',
        '13-15' => '14',
        '15-17' => '16',
        '17-19' => '18',
        '19-21' => '20',
        '21-23' => '22'
    ];

    if ($birthDateInput && $birthTimeInput && isset($timeMapping[$birthTimeInput])) {
        $hour = $timeMapping[$birthTimeInput];
        $queryDateTime = "$birthDateInput $hour:00:00";

        $conn = new mysqli($host, $user, $password, $dbname, $port);
        if ($conn->connect_error) {
            $errorMsg = "資料庫連線失敗: " . $conn->connect_error;
        } else {
            $conn->set_charset("utf8mb4");
            
            $sql = "SELECT * FROM `$table` WHERE solarDate = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("s", $queryDateTime);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $resultData = $row;
                    
                    // Decode JSON fields manually
                    $jsonFields = ['wuXing', 'mingGe', 'wuXingAnalysis', 'shiShenAnalysis', 'shenShaAnalysis', 'daYunWithStarting', 'liuNian', 'liuYue', 'relations'];
                    foreach ($jsonFields as $field) {
                        if (!empty($resultData[$field]) && is_string($resultData[$field])) {
                            $decoded = json_decode($resultData[$field], true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $resultData[$field] = $decoded;
                            } else {
                                $resultData[$field] = null;
                            }
                        }
                    }
                } else {
                    $errorMsg = "找不到對應的資料";
                }
                $stmt->close();
            } else {
                $errorMsg = "資料表讀取錯誤";
            }
            $conn->close();
        }
    } else {
        $errorMsg = "請輸入完整的日期與時辰";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>八字命理查詢</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .element-wood { color: #16a34a; } /* Green */
        .element-fire { color: #dc2626; } /* Red */
        .element-earth { color: #d97706; } /* Brown context orange */
        .element-metal { color: #4b5563; } /* Gray */
        .element-water { color: #2563eb; } /* Blue */
        
        .bg-wood { background-color: #dcfce7; color: #14532d; }
        .bg-fire { background-color: #fee2e2; color: #7f1d1d; }
        .bg-earth { background-color: #fef3c7; color: #78350f; }
        .bg-metal { background-color: #f3f4f6; color: #1f2937; }
        .bg-water { background-color: #dbeafe; color: #1e3a8a; }
    </style>
</head>
<body class="bg-indigo-50 min-h-screen py-10 px-4">

    <div class="max-w-4xl mx-auto space-y-8">
        <!-- 查詢表單區塊 -->
        <div class="bg-white p-8 rounded-2xl shadow-xl">
            <h1 class="text-3xl font-bold text-center text-slate-800 mb-8">八字命盤查詢</h1>
            
            <form id="baziForm" method="POST" action="index.php" class="flex flex-col md:flex-row gap-4 justify-center items-end">
                <div class="w-full md:w-auto">
                    <label for="birthDate" class="block text-sm font-semibold text-slate-600 mb-2">出生日期</label>
                    <input type="date" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($birthDateInput ?? ''); ?>" required
                        class="w-full md:w-48 px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
                </div>

                <div class="w-full md:w-auto">
                    <label for="birthTime" class="block text-sm font-semibold text-slate-600 mb-2">出生時辰</label>
                    <div class="relative">
                        <select id="birthTime" name="birthTime" required
                            class="w-full md:w-56 px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg appearance-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition pr-10">
                            <option value="" disabled selected>請選擇時辰</option>
                            <option value="23-01">子時 (23:00 - 01:00)</option>
                            <option value="01-03">丑時 (01:00 - 03:00)</option>
                            <option value="03-05">寅時 (03:00 - 05:00)</option>
                            <option value="05-07">卯時 (05:00 - 07:00)</option>
                            <option value="07-09">辰時 (07:00 - 09:00)</option>
                            <option value="09-11">巳時 (09:00 - 11:00)</option>
                            <option value="11-13">午時 (11:00 - 13:00)</option>
                            <option value="13-15">未時 (13:00 - 15:00)</option>
                            <option value="15-17">申時 (15:00 - 17:00)</option>
                            <option value="17-19">酉時 (17:00 - 19:00)</option>
                            <option value="19-21">戌時 (19:00 - 21:00)</option>
                            <option value="21-23">亥時 (21:00 - 23:00)</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-auto">
                    <button type="submit" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition duration-200 shadow-lg transform active:scale-95">
                        立即排盤
                    </button>
                </div>
            </form>
        </div>

        <!-- 結果顯示區 -->
        <div id="resultArea" class="<?php echo $resultData ? '' : 'hidden'; ?> space-y-6">
            
            <!-- 1. 基本資料卡片 -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-indigo-900 px-6 py-4">
                    <h2 class="text-xl text-white font-bold flex items-center">
                        <span class="text-indigo-200 mr-2">📌</span> 命盤基本資料
                    </h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-6 text-sm">
                    <div>
                        <span class="text-slate-400 block text-xs uppercase tracking-wider mb-1">農曆日期</span>
                        <span id="lunarDateDisplay" class="font-medium text-slate-800 text-lg"><?php echo htmlspecialchars($resultData['lunarDate'] ?? ''); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-xs uppercase tracking-wider mb-1">生肖</span>
                        <span id="zodiacDisplay" class="font-medium text-slate-800 text-lg"><?php echo htmlspecialchars($resultData['zodiacSign'] ?? ''); ?></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-xs uppercase tracking-wider mb-1">命主</span>
                        <span id="mingTypeDisplay" class="font-medium text-indigo-600 text-lg"><?php echo htmlspecialchars($resultData['mingType'] ?? ''); ?></span>
                    </div>
                </div>
            </div>

            <!-- 2. 八字四柱 (核心) -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-6 border-l-4 border-indigo-500 pl-3">八字命造</h3>
                <div class="grid grid-cols-4 gap-4 text-center">
                    <!-- 標頭 -->
                    <div class="text-slate-500 font-medium pb-2 border-b">時柱</div>
                    <div class="text-slate-500 font-medium pb-2 border-b">日柱</div>
                    <div class="text-slate-500 font-medium pb-2 border-b">月柱</div>
                    <div class="text-slate-500 font-medium pb-2 border-b">年柱</div>
                    
                    <?php
                    $pillars = ['time', 'day', 'month', 'year'];
                    $pillarNames = ['timePillar', 'dayPillar', 'monthPillar', 'yearPillar'];
                    
                    // 天干 row
                    foreach ($pillarNames as $idx => $pName) {
                        $pText = $resultData[$pName] ?? '';
                        $gan = mb_substr($pText, 0, 1, 'UTF-8');
                        $ganEle = $resultData['wuXing'][$pillars[$idx]]['tianGan'] ?? '';
                        echo '<div class="py-2">';
                        if ($gan) {
                            echo '<div class="text-3xl font-serif ' . getElementClass($ganEle) . '">' . $gan . '</div>';
                            echo '<div class="text-xs text-slate-500 mt-1 font-medium bg-slate-100 rounded px-1 inline-block" title="天干五行：' . $ganEle . '">' . $ganEle . '</div>';
                        }
                        echo '</div>';
                    }

                    // 地支 row
                    foreach ($pillarNames as $idx => $pName) {
                        $pText = $resultData[$pName] ?? '';
                        $zhi = mb_substr($pText, 1, 1, 'UTF-8');
                        $zhiEle = $resultData['wuXing'][$pillars[$idx]]['diZhi'] ?? '';
                        echo '<div class="py-2">';
                        if ($zhi) {
                            echo '<div class="text-3xl font-serif ' . getElementClass($zhiEle) . '">' . $zhi . '</div>';
                            echo '<div class="text-xs text-slate-500 mt-1 font-medium bg-slate-100 rounded px-1 inline-block" title="地支五行：' . $zhiEle . '">' . $zhiEle . '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- 3. 五行與格局分析 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- 五行分析 -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-green-500 pl-3">五行分析</h3>
                    <div id="wuxingBar" class="space-y-3 mb-4">
                        <?php
                        if (isset($resultData['wuXingAnalysis'])) {
                            $counts = $resultData['wuXingAnalysis']['counts'] ?? [];
                            $total = array_sum($counts);
                            $elements = ['金', '木', '水', '火', '土'];
                            foreach ($elements as $ele) {
                                $count = $counts[$ele] ?? 0;
                                $pct = $total > 0 ? ($count / $total) * 100 : 0;
                                $bgClass = str_replace('text', 'bg', getElementClass($ele));
                                // getBGClass logic from JS mapped to PHP:
                                $barColorClass = str_replace('bg-', '', getBGClass($ele)); // Extract color name for tailwind
                                // Re-using getBGClass logic directly
                                $barClass = explode(' ', getBGClass($ele))[0];
                                
                                echo '<div class="flex items-center">';
                                echo '<div class="w-10 text-sm font-bold ' . getElementClass($ele) . '">' . $ele . '</div>';
                                echo '<div class="w-8 text-xs text-slate-500 text-right pr-2">' . $count . '</div>';
                                echo '<div class="flex-1 bg-slate-100 rounded-full h-2.5 overflow-hidden">';
                                echo '<div class="h-2.5 rounded-full ' . $barClass . '" style="width: ' . $pct . '%"></div>';
                                echo '</div>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <p id="wuxingSummary" class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg leading-relaxed">
                        <?php echo isset($resultData['wuXingAnalysis']['analysis']) ? nl2br(htmlspecialchars($resultData['wuXingAnalysis']['analysis'])) : ''; ?>
                    </p>
                </div>

                <!-- 格局與命格 -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-orange-500 pl-3">命格分析</h3>
                    <div class="mb-4">
                        <span id="mingGeType" class="text-xl font-bold text-slate-800 ml-2"><?php echo htmlspecialchars($resultData['mingGe']['type'] ?? ''); ?></span>
                    </div>
                    <p id="mingGeDesc" class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg leading-relaxed mb-4"><?php echo htmlspecialchars($resultData['mingGe']['description'] ?? ''); ?></p>
                    <div id="mingGeStrength" class="text-sm border-t pt-2 mt-2">
                         <?php echo isset($resultData['mingGe']['strength']['explanation']) ? nl2br(htmlspecialchars($resultData['mingGe']['strength']['explanation'])) : ''; ?>
                    </div>
                </div>
            </div>

            <!-- 十神分析 -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-amber-500 pl-3">十神分析</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left text-slate-600">
                        <thead class="text-xs text-slate-700 uppercase bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3">柱位</th>
                                <th scope="col" class="px-4 py-3">十神</th>
                                <th scope="col" class="px-4 py-3 min-w-[200px]">含意</th>
                                <th scope="col" class="px-4 py-3 text-center">類型</th>
                            </tr>
                        </thead>
                        <tbody id="shiShenTableBody" class="divide-y divide-slate-100">
                            <?php
                            if (isset($resultData['shiShenAnalysis']['relations']) && is_array($resultData['shiShenAnalysis']['relations'])) {
                                foreach ($resultData['shiShenAnalysis']['relations'] as $rel) {
                                    $isHidden = !empty($rel['isHidden']) 
                                        ? '<span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded">藏干</span>' 
                                        : '<span class="text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded font-bold">本氣</span>';
                                    
                                    $shiShenColor = 'text-emerald-700';
                                    $xiong = ['七杀', '傷官', '偏印', '劫財'];
                                    foreach ($xiong as $x) {
                                        if (strpos($rel['shiShen'], $x) !== false) {
                                            $shiShenColor = 'text-red-600';
                                            break;
                                        }
                                    }

                                    echo '<tr class="hover:bg-slate-50 transition border-b border-slate-50 last:border-0">';
                                    echo '<td class="px-4 py-3 font-medium text-slate-700">' . htmlspecialchars($rel['pillar']) . '</td>';
                                    echo '<td class="px-4 py-3 font-bold ' . $shiShenColor . '">' . htmlspecialchars($rel['shiShen']) . '</td>';
                                    echo '<td class="px-4 py-3 text-xs text-slate-600 leading-relaxed">' . htmlspecialchars($rel['description'] ?? '') . '</td>';
                                    echo '<td class="px-4 py-3 text-center">' . $isHidden . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <p id="shiShenSummary" class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg leading-relaxed mt-4">
                    <?php echo isset($resultData['shiShenAnalysis']['analysis']) ? nl2br(htmlspecialchars($resultData['shiShenAnalysis']['analysis'])) : ''; ?>
                </p>
            </div>

            <!-- 神煞分析 -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-purple-500 pl-3">神煞分析</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left text-slate-600">
                        <thead class="text-xs text-slate-700 uppercase bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3">神煞名稱</th>
                                <th scope="col" class="px-4 py-3 text-center">類型</th>
                                <th scope="col" class="px-4 py-3">所在柱位</th>
                                <th scope="col" class="px-4 py-3 min-w-[200px]">含意</th>
                            </tr>
                        </thead>
                        <tbody id="shenShaTableBody" class="divide-y divide-slate-100">
                            <?php
                            if (isset($resultData['shenShaAnalysis'])) {
                                $jiShen = $resultData['shenShaAnalysis']['jiShen'] ?? [];
                                $xiongSha = $resultData['shenShaAnalysis']['xiongSha'] ?? [];
                                $allSha = [];
                                foreach ($jiShen as $s) { $s['isJi'] = true; $allSha[] = $s; }
                                foreach ($xiongSha as $s) { $s['isJi'] = false; $allSha[] = $s; }
                                
                                if (empty($allSha)) {
                                     echo '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">無特殊神煞</td></tr>';
                                } else {
                                    foreach ($allSha as $item) {
                                        $typeBadge = $item['isJi'] 
                                            ? '<span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">吉神</span>'
                                            : '<span class="px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">凶煞</span>';
                                        
                                        echo '<tr class="hover:bg-slate-50 transition border-b border-slate-50 last:border-0">';
                                        echo '<td class="px-4 py-3 font-bold text-slate-700">' . htmlspecialchars($item['name']) . '</td>';
                                        echo '<td class="px-4 py-3 text-center">' . $typeBadge . '</td>';
                                        echo '<td class="px-4 py-3 text-slate-600">' . htmlspecialchars($item['location']) . '</td>';
                                        echo '<td class="px-4 py-3 text-xs text-slate-500 leading-relaxed">' . htmlspecialchars($item['description']) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <p id="shenShaSummary" class="text-sm text-slate-600 bg-slate-50 p-3 rounded-lg leading-relaxed mt-4">
                     <?php echo isset($resultData['shenShaAnalysis']['analysis']) ? nl2br(htmlspecialchars($resultData['shenShaAnalysis']['analysis'])) : ''; ?>
                </p>
            </div>

            <!-- 5. 大運 -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-blue-500 pl-3">大運排盤</h3>
                <div id="dayunInfo" class="mb-4 text-sm text-slate-500">
                    <?php 
                        if (isset($resultData['daYunWithStarting'])) {
                            echo htmlspecialchars($resultData['daYunWithStarting']['startingAge']['age'] ?? '') . ' (' . htmlspecialchars($resultData['daYunWithStarting']['startingAge']['startDate'] ?? '') . ')';
                        }
                    ?>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-center text-sm" id="dayunTable">
                        <?php
                        if (isset($resultData['daYunWithStarting']['daYun']) && is_array($resultData['daYunWithStarting']['daYun'])) {
                            $rowShiShen = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600">主星</td>';
                            $rowGan = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600">天干</td>';
                            $rowZhi = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600">地支</td>';
                            $rowAge = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600">虛歲</td>';
                            $rowYear = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600">起運</td>';

                            foreach ($resultData['daYunWithStarting']['daYun'] as $dy) {
                                $gan = mb_substr($dy['ganZhi'], 0, 1, 'UTF-8');
                                $zhi = mb_substr($dy['ganZhi'], 1, 1, 'UTF-8');
                                $wuxingGan = $dy['wuXing']['tianGan'] ?? '';
                                $wuxingZhi = $dy['wuXing']['diZhi'] ?? '';

                                $rowShiShen .= '<td class="p-2 border border-slate-100 min-w-[60px] text-xs font-medium text-slate-600">' . htmlspecialchars($dy['shiShen']) . '</td>';
                                $rowGan .= '<td class="p-2 border border-slate-100 text-xl font-serif ' . getElementClass($wuxingGan) . '">' . $gan . '</td>';
                                $rowZhi .= '<td class="p-2 border border-slate-100 text-xl font-serif ' . getElementClass($wuxingZhi) . '">' . $zhi . '</td>';
                                $rowAge .= '<td class="p-2 border border-slate-100 text-xs text-slate-500">' . htmlspecialchars($dy['startYear']) . '</td>';
                                $rowYear .= '<td class="p-2 border border-slate-100 text-xs text-slate-500">' . substr($dy['startDate'], 0, 4) . '</td>';
                            }
                            
                            $rowShiShen .= '</tr>';
                            $rowGan .= '</tr>';
                            $rowZhi .= '</tr>';
                            $rowAge .= '</tr>';
                            $rowYear .= '</tr>';
                            
                            echo $rowShiShen . $rowGan . $rowZhi . $rowAge . $rowYear;
                        }
                        ?>
                    </table>
                </div>
            </div>

            <!-- 6. 命盤關係 (刑衝會合) -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-pink-500 pl-3">命盤關係 (刑衝會合)</h3>
                
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-bold text-slate-600 mb-2">天干地支關係</h4>
                        <div id="pairwiseRelations" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php
                            if (isset($resultData['relations']['pairwise']) && is_array($resultData['relations']['pairwise'])) {
                                if (empty($resultData['relations']['pairwise'])) {
                                    echo '<div class="text-sm text-slate-400">無明顯衝合</div>';
                                } else {
                                    foreach ($resultData['relations']['pairwise'] as $r) {
                                        echo '<div class="bg-slate-50 p-2 rounded border border-slate-200 text-sm">';
                                        echo '<span class="font-bold text-slate-700">' . htmlspecialchars($r['label']) . '</span>';
                                        echo '<span class="bg-slate-200 text-xs px-1 rounded ml-1 text-slate-600">' . htmlspecialchars($r['category']) . '</span>';
                                        echo '</div>';
                                    }
                                }
                            } else {
                                echo '<div class="text-sm text-slate-400">無明顯衝合</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-600 mb-2">合局與方局</h4>
                        <div id="groupRelations" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                             <?php
                            if (isset($resultData['relations']['groups']) && is_array($resultData['relations']['groups'])) {
                                if (empty($resultData['relations']['groups'])) {
                                    echo '<div class="text-sm text-slate-400">無合局</div>';
                                } else {
                                    foreach ($resultData['relations']['groups'] as $g) {
                                        echo '<div class="bg-indigo-50 p-2 rounded border border-indigo-100 text-sm">';
                                        echo '<span class="font-bold text-indigo-700">' . htmlspecialchars($g['label']) . '</span>';
                                        echo '<span class="text-xs text-indigo-500 ml-1">(' . implode(',', $g['elements']) . ')</span>';
                                        echo '</div>';
                                    }
                                }
                            } else {
                                echo '<div class="text-sm text-slate-400">無合局</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. 流年運勢 -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-yellow-500 pl-3">流年運勢</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-center text-sm" id="liuNianTable">
                         <?php
                        if (isset($resultData['liuNian']) && is_array($resultData['liuNian'])) {
                            $stickyClass = "sticky left-0 z-10 shadow-sm border-r border-slate-200";

                            $rowShiShen = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600 ' . $stickyClass . '">主星</td>';
                            $rowGan = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600 ' . $stickyClass . '">天干</td>';
                            $rowZhi = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600 ' . $stickyClass . '">地支</td>';
                            $rowAge = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600 ' . $stickyClass . '">虛歲</td>';
                            $rowYear = '<tr><td class="p-3 border border-slate-100 bg-slate-50 font-bold whitespace-nowrap text-slate-600 ' . $stickyClass . '">年份</td>';

                            foreach ($resultData['liuNian'] as $ln) {
                                $gan = mb_substr($ln['ganZhi'], 0, 1, 'UTF-8');
                                $zhi = mb_substr($ln['ganZhi'], 1, 1, 'UTF-8');
                                $wuxingGan = $ln['wuXing']['tianGan'] ?? '';
                                $wuxingZhi = $ln['wuXing']['diZhi'] ?? '';
                                
                                $isMajorXiong = false;
                                $majorXiongArr = ['七杀', '七殺', '傷官', '伤官'];
                                foreach ($majorXiongArr as $x) {
                                    if (strpos($ln['shiShen'], $x) !== false) { $isMajorXiong = true; break; }
                                }
                                
                                $isXiong = false;
                                $xiongArr = ['七杀', '七殺', '傷官', '伤官', '偏印', '劫財', '劫财'];
                                foreach ($xiongArr as $x) {
                                    if (strpos($ln['shiShen'], $x) !== false) { $isXiong = true; break; }
                                }

                                $cellClass = $isMajorXiong ? 'bg-red-50 border-red-100' : 'border-slate-100';
                                $shiShenColor = $isXiong ? 'text-red-600 font-bold' : 'text-slate-600';
                                $xiongMarker = $isMajorXiong ? '<div class="text-[10px] text-red-500 mt-1 transform scale-90">⚠️注意</div>' : '';

                                $rowShiShen .= '<td class="p-2 border ' . $cellClass . ' min-w-[60px] text-xs font-medium ' . $shiShenColor . '">' . htmlspecialchars($ln['shiShen']) . $xiongMarker . '</td>';
                                $rowGan .= '<td class="p-2 border ' . $cellClass . ' text-xl font-serif ' . getElementClass($wuxingGan) . '">' . $gan . '</td>';
                                $rowZhi .= '<td class="p-2 border ' . $cellClass . ' text-xl font-serif ' . getElementClass($wuxingZhi) . '">' . $zhi . '</td>';
                                $rowAge .= '<td class="p-2 border ' . $cellClass . ' text-xs text-slate-500">' . htmlspecialchars($ln['age']) . '歲</td>';
                                $rowYear .= '<td class="p-2 border ' . $cellClass . ' text-xs text-slate-500">' . htmlspecialchars($ln['year']) . '</td>';
                            }
                            
                            $rowShiShen .= '</tr>';
                            $rowGan .= '</tr>';
                            $rowZhi .= '</tr>';
                            $rowAge .= '</tr>';
                            $rowYear .= '</tr>';

                            echo $rowShiShen . $rowGan . $rowZhi . $rowAge . $rowYear;
                        }
                        ?>
                    </table>
                </div>
                <div class="mt-4 p-3 bg-red-50 rounded border border-red-100 text-sm text-slate-600">
                    <span class="font-bold text-red-600 mr-2">⚠️ 特別注意：</span>
                    表格中標示為紅色背景的年份（七杀、傷官），代表該年變動較大或壓力較強，行事宜謹慎保守，注意身體健康與人際關係。
                </div>
            </div>

            <!-- 8. 流月運勢 -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 border-l-4 border-cyan-500 pl-3">近期流月運勢</h3>
                <div id="liuYueList" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <?php
                    if (isset($resultData['liuYue']) && is_array($resultData['liuYue'])) {
                        foreach ($resultData['liuYue'] as $ly) {
                            $analysis = $ly['analysis'] ?? '平順之月';
                            echo '<div class="bg-white border border-slate-200 p-4 rounded-lg shadow-sm">';
                            echo '<div class="flex justify-between items-start mb-2">';
                            echo '<div>';
                            echo '<span class="text-sm font-bold text-slate-500 block">' . htmlspecialchars($ly['year']) . '年' . htmlspecialchars($ly['month']) . '月</span>';
                            echo '<span class="text-xl font-serif font-bold text-slate-800">' . htmlspecialchars($ly['ganZhi']) . '</span>';
                            echo '</div>';
                            echo '<span class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-600">' . htmlspecialchars($ly['shiShen']) . '</span>';
                            echo '</div>';
                            echo '<div class="text-xs text-slate-500 mb-2">';
                            echo '<span class="bg-indigo-50 text-indigo-700 px-1 rounded mr-1">' . htmlspecialchars($ly['jieQi']['name'] ?? '') . '</span>';
                            echo htmlspecialchars($ly['jieQi']['start']['solar'] ?? '') . ' ~ ' . htmlspecialchars($ly['jieQi']['end']['solar'] ?? '');
                            echo '</div>';
                            echo '<p class="text-sm text-slate-600 leading-relaxed border-t border-slate-100 pt-2 mt-2">';
                            echo nl2br(htmlspecialchars($analysis));
                            echo '</p>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>

        </div>

    </div>

    <!-- Script -->
    <script>
        const phpError = <?php echo json_encode($errorMsg); ?>;
        const postedTime = "<?php echo $birthTimeInput ?? ''; ?>";

        $(document).ready(function() {
            if(postedTime) $('#birthTime').val(postedTime);
            if(phpError) alert(phpError);
            
            <?php if ($resultData): ?>
            // 滾動到結果區
            $('html, body').animate({
                scrollTop: $("#resultArea").offset().top
            }, 500);
            <?php endif; ?>
        });
    </script>
</body>
</html>