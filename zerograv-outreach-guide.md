# ZeroGrav Vendor Outreach Agent — 設計與實作說明

## 狀態

✅ **已完成並驗證可用**。完整使用文件請看 [`outreach/README.md`](outreach/README.md)。

實作位於 `outreach/` 子資料夾（與 ZeroGrav 主 PHP 專案隔離），檔案結構、操作流程、規則均詳列於 README。本文件記錄**設計決策與實作過程中學到的教訓**，供未來擴充參考。

## 為什麼選 Python + Streamlit（而不是融入 PHP 專案）

ZeroGrav 主站是純 PHP（虛擬主機，無 Composer、無 CLI），不適合做這類**本機 dev tool**：

- 工具只在你本機跑（不部署到 production）
- pandas / Streamlit 處理 CSV + UI 比 PHP 簡潔
- 跟 PHP 主站完全解耦，動主站時不用怕影響 outreach，反之亦然
- 獨立 `.env` 隔離敏感資訊（outreach 工具不需看到 LINE token、FTP 密碼）

## 資料層 — CSV 而不是 MySQL

選 CSV 的理由：
- 量小（< 1000 vendor，遠遠用不到 DB 索引）
- 你可以直接用 Excel / Google Sheets 開來看、改、備份
- 不需多人同寫
- 設定簡單，不用裝 MySQL client

CSV 是 **append-friendly 但不是 concurrent-friendly**。如果之後要做：多人同時用 / 排程自動寄信 / 大量 vendor → 才需考慮搬到 SQLite 或 MySQL。

## 寄送預設關閘（EMAIL_SEND_ENABLED=false）

最重要的設計決策。理由：
- 寄錯難收回（特別是冷開發信）
- 強迫流程：先預覽 → 再開閘 → 寄完關閘
- 測試時不會誤觸 SMTP

實作上有三個層級的把關：

1. **環境變數** `EMAIL_SEND_ENABLED=false` — base 防線
2. **Dashboard toggle 按鈕** — 一鍵切換，不必手動編輯 .env
3. **規則檢查** — 即使開閘了，opted_out / 沒 email / 沒 source_url / 過量都會擋

## Spam Filter — 學到的教訓

第一版範本被 Gmail 判 spam，原因「similar to messages identified as spam in the past」。診斷過程：

| 檢查項 | 結果 |
|---|---|
| SPF | ✓ pass |
| DKIM | ✓ pass（selector `x`） |
| DMARC | ✓ pass（p=none） |
| 寄信 IP 信譽 | ✓ 沒問題 |
| **內容 pattern** | ✗ 命中多項業務開發信特徵 |

**結論：DNS 認證全過 ≠ 不會被擋到 spam**。Gmail 對冷開發信的內容檢查很嚴格。

### 第一版踩到的雷

| 雷 | 原始寫法 |
|---|---|
| 標題「合作邀請」「曝光」 | `二手儀器設備曝光合作邀請 — 光明儀器公司` |
| 開頭就介紹自己 + URL | `我們是 ZeroGrav，一個...(https://zerograv.com.tw)` |
| 「免費」+「上架」+「無上架費」 | 多處重複 |
| 條列式賣點（4 個 bullet `-`） | 中段 |
| 多個 URL（zerograv.com.tw + source_url 完整版） | 兩處 |
| 結尾固定退訂句太制式 | `若不方便收到後續聯繫...` |

### 修正後的版本（user 提供，已驗證 inbox）

特性：
- 主旨「想邀請{company}評估將設備同步刊登到 ZeroGrav」（用「邀請」單字而非「合作邀請」）
- 開頭「{company} 團隊您好：」
- 自然敘述段落，**不用 bullet**
- body 完全沒有 `http(s)://` URL（連 `zerograv.com.tw` 也拿掉，網址從 From header 自然帶）
- `source_url` 只放 domain（如 `ren-ji-tech.com.tw`），降低 URL 計數
- 保留「不需聯繫」關鍵字 — 是必要的合規 opt-out + 操作端追蹤回覆用
- follow_up 不用「Re:」開頭（cold outreach 用 Re: 反而像偽裝）

實際範本內容看 `outreach/src/vendor_outreach.py` 的 `generate_vendor_email()`。

## 合規

- 只聯繫在公開網站主動揭露 email 的公司 — 程式強制 `source_url` 不能空
- 每封都有 opt-out 機制 — 範本固定加「不需聯繫」句
- 收到「不需聯繫」立刻改 `opted_out`，程式擋下一切後續寄送
- 不蒐集非公開資訊；遵守台灣《個人資料保護法》

## 執行環境注意事項

開發時遇到並解決的小坑：

- **WSL2 + Windows 桌面**：用 `.bat` 包 `wsl bash -c "..."` 的形式啟動。`.bat` 必須是 **CRLF + ASCII-only**（用 `chcp 65001` 太晚會吃中文亂碼），所以 echo 用英文，中文輸出由 Python (UTF-8) 處理。
- **Pip 安裝**：Ubuntu 24.04 預設無 pip，且有 PEP 668 限制。第一次 `run_dashboard.bat` 用 `--user --break-system-packages` 自動裝 streamlit 等套件。
- **Streamlit 不會重讀 .env**：在 dashboard 跑著時手動編輯 .env 不會生效。要不重啟 streamlit、要不用 dashboard 上的 toggle 按鈕（toggle 同時更新 .env 和 os.environ）。

## 進階擴充（未實作，僅備忘）

| 功能 | 說明 |
|---|---|
| **自動爬蟲** | 用 BeautifulSoup 從公司官網自動抓取公開 email |
| **排程寄送** | 用 cron / Windows Task Scheduler 每天早上自動 dry-run 並 email summary 給自己 |
| **回信偵測** | 用 IMAP 定期檢查收件匣，自動標記有回覆的 vendor |
| **Email 開信追蹤** | 嵌入 tracking pixel（注意隱私合規） |
| **多語系範本** | 支援英文範本，用於聯繫國際廠商 |
| **CRM 整合** | vendor 資料同步到 Notion 或 Airtable |
| **mark_replies CLI** | `python3 mark_replies.py 5 opted_out --note "..."` 快速更新狀態 |

要加任何上述功能，可以直接跟 Claude Code 說明，會在 `outreach/` 子目錄內擴充、不影響主 PHP 專案。
