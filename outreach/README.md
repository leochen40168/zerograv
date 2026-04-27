# ZeroGrav Vendor Email Outreach Agent

冷啟動供給端用的 B2B 開發信工具：建立廠商名單、產生開發信、小量寄送、追蹤狀態。設計成**預設不寄信**，所有規則都明寫在 `src/email_sender.py`，方便先 dry-run 再開閘。

## 目錄結構

```
outreach/
├── data/
│   ├── vendors.csv              # 廠商名單
│   └── email_outreach_log.csv   # 寄送紀錄（含失敗）
├── src/
│   ├── vendor_outreach.py       # 資料 + 範本
│   ├── email_sender.py          # SMTP 寄送 + 規則檢查
│   └── app.py                   # Streamlit dashboard
├── tests/test_basic.py
├── .env.example
└── requirements.txt
```

## 安裝

```bash
cd outreach
pip install -r requirements.txt
cp .env.example .env       # 編輯 .env 填入 SMTP 設定
```

## 啟動 Dashboard

```bash
streamlit run src/app.py
```

## 工作流程

### 1. 建立廠商名單

對象：**已經在網路上公開販售二手儀器、量測設備的公司**。

來源管道：
- Google 搜尋「二手儀器 / 中古儀器 / 量測設備 / 二手電子顯微鏡 ...」
- 公司官網
- Facebook 粉絲團 / 社團公開貼文

### 2. 記錄 email 來源網址（重要）

`source_url` **必填**，是「合法商業利益」的佐證 — 你只聯繫在公開頁面主動揭露 email 的公司。沒有 `source_url` 的 vendor 會被 `send_vendor_email` 直接拒絕。

| source_type | 用途 |
|---|---|
| `website` | 公司官網的聯絡頁 |
| `facebook_page` | 粉絲團公開貼文 / 「關於」頁 |
| `facebook_group` | 公開社團貼文 |
| `google_search` | 從搜尋結果中找到的其他來源 |
| `manual` | 名片、展會、線下管道 |

### 3. 產生開發信

兩種範本：

- **initial** — 第一次接觸；會把 `source_url` 帶進信中（「我們在 ... 看到貴公司 ...」）增加信任感
- **follow_up** — 7 天後對沒回覆的人追一次；會帶入 `last_contacted` 日期

兩種範本都包含：
- 強調免費刊登、不取代原通路、可協助先刊 3-5 筆
- 不承諾成交、不誇大流量
- 結尾固定退訂句：「若不方便收到後續聯繫，回覆「不需聯繫」即可，我們會停止後續通知。」

### 4. 啟用真寄送

預設 `EMAIL_SEND_ENABLED=false`，所有寄送呼叫會 raise `EmailDisabledError`。要真的寄出：

```
# .env
EMAIL_SEND_ENABLED=true
EMAIL_SMTP_HOST=smtp.example.com
EMAIL_SMTP_PORT=587
EMAIL_SMTP_USERNAME=...
EMAIL_SMTP_PASSWORD=...
EMAIL_FROM_NAME=ZeroGrav
EMAIL_FROM_ADDRESS=contact@zerograv.com.tw
EMAIL_DAILY_LIMIT=20
```

### 5. 為什麼預設不寄送

- 避免測試或剛 setup 完，不小心對真實名單發信
- 強制你先看過 dashboard 預覽信件內容
- 寄錯難收，留個閘門

### 6. 寄送節奏建議

- 每天 **10-20 封**，超過 `EMAIL_DAILY_LIMIT` 會被擋
- 連發太快、量太大會傷網域信譽，後面信全進垃圾匣
- 兩週後檢查回覆率：< 5% 表示信件內容或名單品質要調整

### 7. 規則：誰不能寄

`send_vendor_email` 會拒絕：

- `contact_status` 為 `opted_out` 或 `not_interested`
- `email` 為空
- `source_url` 為空
- 今日寄送數已達 `EMAIL_DAILY_LIMIT`
- `EMAIL_SEND_ENABLED` 不是 `true`
- SMTP 設定不完整

收到「不需聯繫」回覆 → 立刻把該 vendor 改成 `opted_out`。

### 8. 寄件網域必須設好 SPF / DKIM / DMARC

用自有網域寄信（例 `contact@zerograv.com.tw`）時，DNS 必須設：

| 設定 | 用途 |
|---|---|
| **SPF** | 告訴收件方哪些 IP 可以代表此網域寄信 |
| **DKIM** | 對信件做數位簽章，防止被竄改 |
| **DMARC** | 定義收件方該如何處理驗證失敗的信 |

沒設好的後果：信直接進垃圾郵件，整個工具白做。可用 [MXToolbox](https://mxtoolbox.com/) 檢查。

## 跑測試

```bash
cd outreach
pytest -v
```

測試會 monkeypatch CSV 路徑到 tmp 目錄、mock SMTP，不會動到真實資料、也不會發出實際請求。
