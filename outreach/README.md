# ZeroGrav Vendor Email Outreach Agent

冷啟動供給端用的 B2B 開發信工具：建立廠商名單、產生開發信、小量寄送、追蹤狀態。設計成**預設不寄信**，所有規則都明寫在 `src/email_sender.py`，方便先 dry-run 再開閘。

## 目錄結構

```
outreach/
├── data/
│   ├── vendors.csv              # 廠商名單（含個資；建議 .gitignore）
│   └── email_outreach_log.csv   # 寄送紀錄（含失敗）
├── src/
│   ├── vendor_outreach.py       # 資料 CRUD + email 範本
│   ├── email_sender.py          # SMTP 寄送 + 規則檢查 + 開關切換
│   ├── pre_send_check.py        # CLI dry-run review 工具
│   └── app.py                   # Streamlit dashboard
├── tests/test_basic.py          # 29 個測試（pandas + mock SMTP）
├── conftest.py
├── .env.example
├── .gitattributes               # *.bat 強制 CRLF
├── .gitignore
├── requirements.txt
├── run_dashboard.bat            # Windows 桌面點擊：啟動 dashboard
├── stop_dashboard.bat           # Windows 桌面點擊：強制停止
└── pre_send_check.bat           # Windows 桌面點擊：跑 dry-run
```

## 安裝

### Windows + WSL（一般使用者）

把這三個 `.bat` 拉去桌面建捷徑（不用安裝步驟，第一次點擊會自動裝套件）：

| 檔案 | 用途 |
|---|---|
| `run_dashboard.bat` | 啟動 dashboard（自動裝 streamlit + 開瀏覽器） |
| `pre_send_check.bat` | 跑 dry-run，看今天會寄給誰 |
| `stop_dashboard.bat` | 強制停止卡住的 dashboard |

桌面建立捷徑路徑：`\\wsl.localhost\Ubuntu\home\a0915\zerograv\outreach\*.bat`

### Linux/Mac/CLI

```bash
cd outreach
pip install -r requirements.txt
cp .env.example .env       # 編輯 .env 填入 SMTP 設定
streamlit run src/app.py   # 啟動 dashboard
python3 src/pre_send_check.py  # 跑 dry-run
```

## SMTP 設定

`outreach/.env` 與主專案 `.env` 分離（避免 Python 工具看到不必要的 LINE/FTP 等敏感資訊）。

需要的欄位：
```
EMAIL_SMTP_HOST=mail.zerograv.com.tw
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USERNAME=marry@zerograv.com.tw
EMAIL_SMTP_PASSWORD=...
EMAIL_FROM_NAME=ZeroGrav 二手儀器交易平台
EMAIL_FROM_ADDRESS=marry@zerograv.com.tw
EMAIL_DAILY_LIMIT=20
EMAIL_SEND_ENABLED=false
```

ZeroGrav 主專案已有 SMTP 設定可沿用 — 從主 `.env` 把 `SMTP_*` 欄位複製並改名為 `EMAIL_SMTP_*` 即可。

## 工作流程

### 1. 新增廠商

對象：**已經在網路上公開販售二手儀器、量測設備的公司**。

來源管道：
- Google 搜尋「二手儀器 / 中古儀器 / 量測設備 / 二手電子顯微鏡 ...」
- 公司官網
- Facebook 粉絲團 / 公開社團

**`source_url` 必填**，是「合法商業利益」的佐證 — 你只聯繫在公開頁面主動揭露 email 的公司。沒有 `source_url` 的 vendor 會被 `send_vendor_email` 直接拒絕。

| source_type | 用途 |
|---|---|
| `website` | 公司官網的聯絡頁 |
| `facebook_page` | 粉絲團公開貼文 / 「關於」頁 |
| `facebook_group` | 公開社團貼文 |
| `google_search` | 從搜尋結果中找到的其他來源 |
| `manual` | 名片、展會、線下管道 |

### 2. 早上跑 dry-run（pre_send_check）

點 `pre_send_check.bat` 或 `python3 src/pre_send_check.py`。輸出三區塊：

1. **今日候選名單** — 會被寄信的 vendor，依優先序排（`follow_up_needed` → `new` → `email_drafted`）；含建議套用的模板
2. **資料缺失** — actionable 但缺 email 或 `source_url` 的 vendor，需要補資料
3. **已封鎖名單** — `opted_out` / `not_interested`，永遠不會寄

純 read-only，不會打開 SMTP。常用旗標：

```bash
python3 src/pre_send_check.py --status new          # 只看 status=new
python3 src/pre_send_check.py --template follow_up  # 只看建議寄追蹤信的
python3 src/pre_send_check.py --limit 5             # 顯示前 5 筆
python3 src/pre_send_check.py --preview 12          # 印出 vendor #12 完整信件內容
```

### 3. Dashboard 預覽 + 寄送

開 dashboard（http://localhost:8501）。**頂端有寄送開關按鈕**，不用手動編輯 .env：

```
🟡 寄送已關閉（draft-only 模式）       [🟢 開啟寄送]
```

點「🟢 開啟寄送」→ 變綠色 → 才能真的寄出。寄完按「⛔ 關閉寄送」回到 draft-only。

操作流程：
1. 區塊 1：新增廠商
2. 區塊 2：依 `contact_status` 篩選看清單
3. 區塊 3：選 vendor → 選模板（initial / follow_up）→ 按「產生信件」→ 預覽 Subject/Body → 確認 OK 按「Send Email」
4. 區塊 4：看寄送紀錄

### 4. 處理回覆

| 對方回覆 | 你的動作 |
|---|---|
| 「不需聯繫」/「請勿再寄」 | `contact_status` → `opted_out` |
| 婉拒 | `contact_status` → `not_interested` |
| 詢問細節 / 表達興趣 | `contact_status` → `interested` 或 `replied`，notes 記重點 |
| 已成功上架 ZeroGrav | `contact_status` → `listed` |
| 沒回覆過 7 天 | `contact_status` → `follow_up_needed`（下次 dry-run 會建議用 follow_up 模板） |

收到「不需聯繫」**立刻**改 `opted_out`，再也不能寄（程式會自動擋）。

## 規則：誰不能寄

`send_vendor_email` 會 raise 拒絕：

| 狀況 | 例外類別 |
|---|---|
| `EMAIL_SEND_ENABLED` 不是 true | `EmailDisabledError` |
| SMTP 設定不完整 | `EmailConfigError` |
| 對方狀態為 `opted_out` / `not_interested` | `VendorSkipped` |
| email 為空 | `VendorSkipped` |
| `source_url` 為空 | `VendorSkipped` |
| 今日寄送數已達 `EMAIL_DAILY_LIMIT` | `DailyLimitExceeded` |

寄送成功 → 寫入 `email_outreach_log.csv`、vendor 狀態改 `email_sent`、`last_contacted` 改今天。  
寄送失敗 → 一樣寫 log，狀態與 last_contacted 不變。

## 信件範本

兩種模板都採正式商務 outreach 風格（user 指定，已驗證不被 Gmail 判 spam）：

- **initial** — 第一次接觸；body 帶入 `{company}` 和 `{source_domain}`（只放網域不放完整 URL，降低 spam score）
- **follow_up** — 對未回覆者追蹤；提及 `last_contacted` 日期

兩種範本共同特性：
- 主旨用「邀請評估刊登」而非「合作邀請」（後者高 spam score）
- body 完全不放 `http(s)://` URL（品牌只用文字 ZeroGrav 提及，網址從 From header 自然帶）
- 不用條列式賣點（- - - 多行）— 改成自然敘述段落
- 結尾固定 opt-out 句保留「不需聯繫」關鍵字（操作端追蹤回覆用）

要改範本：直接編輯 `src/vendor_outreach.py` 的 `generate_vendor_email()` 函式。

## 寄送節奏建議

- 每天 **10-20 封**，超過 `EMAIL_DAILY_LIMIT` 會被擋
- 不要連續同一段時間寄完，散開到 2-3 小時內
- 連發太快、量太大會傷網域信譽，後面信全進垃圾匣
- 兩週後檢查回覆率：< 5% 表示信件內容或名單品質要調整
- 進 spam 的 vendor 改為 `not_interested` 別追，避免持續傷信譽

## SPF / DKIM / DMARC + 內容反 spam

用自有網域寄信時，DNS 必須設：

| 設定 | 用途 |
|---|---|
| **SPF** | 告訴收件方哪些 IP 可以代表此網域寄信 |
| **DKIM** | mail server 對信件做數位簽章；收件方可驗證簽章 |
| **DMARC** | 定義收件方該如何處理驗證失敗的信 |

**ZeroGrav 已設好**：可用 https://mxtoolbox.com/ 用「SPF Record Lookup」「DKIM Lookup」（selector = `x`）「DMARC Lookup」三項驗證。

⚠️ **但 SPF/DKIM/DMARC 全 pass 不代表不會被擋到 spam**。Gmail 也看內容 pattern：「合作邀請」「曝光」「免費 + 上架」「過多 URL」「條列式賣點」「Re: 開頭但實為冷信」都會加 spam score。範本已避開這些雷。改範本時請保持同樣的低風險寫法。

## 跑測試

```bash
cd outreach
pytest -v
```

29 個測試全部通過：
- vendor CRUD（add / update / by_status / 自動 id）
- 不合法 contact_status / source_type 會 raise
- email 範本生成（initial / follow_up）含必要欄位
- 範本避開 spam pattern（沒有 http://、沒有「合作邀請」、沒有 bullet）
- 寄送規則（disabled / config missing / opted_out / 缺 email/source_url / daily limit）
- 寄送成功會寫 log 並更新 vendor 狀態
- 寄送失敗也會寫 failed log 但不更新 vendor
- `set_send_enabled` toggle 翻轉 .env
- pre_send_check 候選排序、issue 偵測、preview 輸出

測試會 monkeypatch CSV 路徑到 tmp 目錄、mock SMTP，不會動到真實資料、也不會發出實際請求。
