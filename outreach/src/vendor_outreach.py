"""ZeroGrav vendor outreach — data layer + email template generation.

This module is intentionally framework-free: it operates on two CSV files
(vendors and outreach log) and exposes plain functions. The companion
``email_sender`` module wraps these for actual SMTP delivery.
"""
from __future__ import annotations

import csv
from datetime import date
from pathlib import Path
from typing import Optional
from urllib.parse import urlparse

import pandas as pd

# ── Constants ────────────────────────────────────────────────

VALID_CONTACT_STATUSES = {
    "new",
    "email_drafted",
    "email_sent",
    "follow_up_needed",
    "replied",
    "interested",
    "not_interested",
    "opted_out",
    "listed",
}

VALID_SOURCE_TYPES = {
    "website",
    "facebook_page",
    "facebook_group",
    "google_search",
    "manual",
}

VENDOR_COLUMNS = [
    "id",
    "company_name",
    "website",
    "email",
    "phone",
    "category",
    "source_url",
    "source_type",
    "contact_status",
    "last_contacted",
    "next_action",
    "notes",
]

LOG_COLUMNS = [
    "id",
    "date",
    "vendor_id",
    "email",
    "template_type",
    "subject",
    "status",
    "error_message",
]

VALID_LOG_STATUSES = {"drafted", "sent", "failed", "skipped"}

# Module-level paths so tests can monkeypatch easily.
_BASE_DIR = Path(__file__).resolve().parent.parent
VENDORS_CSV: Path = _BASE_DIR / "data" / "vendors.csv"
LOG_CSV: Path = _BASE_DIR / "data" / "email_outreach_log.csv"


# ── CSV helpers ──────────────────────────────────────────────

def _ensure_csv(path: Path, columns: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if not path.exists():
        with path.open("w", newline="", encoding="utf-8") as f:
            csv.writer(f).writerow(columns)


def load_vendors() -> pd.DataFrame:
    _ensure_csv(VENDORS_CSV, VENDOR_COLUMNS)
    df = pd.read_csv(VENDORS_CSV, dtype=str, keep_default_na=False)
    if df.empty:
        return pd.DataFrame(columns=VENDOR_COLUMNS)
    df["id"] = pd.to_numeric(df["id"], errors="coerce").astype("Int64")
    return df


def save_vendors(df: pd.DataFrame) -> None:
    _ensure_csv(VENDORS_CSV, VENDOR_COLUMNS)
    out = df.copy()
    for col in VENDOR_COLUMNS:
        if col not in out.columns:
            out[col] = ""
    out = out[VENDOR_COLUMNS]
    out.to_csv(VENDORS_CSV, index=False, encoding="utf-8")


# ── Vendor CRUD ──────────────────────────────────────────────

def add_vendor(
    company_name: str,
    website: str = "",
    email: str = "",
    phone: str = "",
    category: str = "",
    source_url: str = "",
    source_type: str = "manual",
    contact_status: str = "new",
    last_contacted: str = "",
    next_action: str = "",
    notes: str = "",
) -> int:
    """Append a vendor row, return the assigned id."""
    if not company_name or not str(company_name).strip():
        raise ValueError("company_name 不能為空")
    if contact_status not in VALID_CONTACT_STATUSES:
        raise ValueError(
            f"無效 contact_status：{contact_status}（合法值：{sorted(VALID_CONTACT_STATUSES)}）"
        )
    if source_type not in VALID_SOURCE_TYPES:
        raise ValueError(
            f"無效 source_type：{source_type}（合法值：{sorted(VALID_SOURCE_TYPES)}）"
        )

    df = load_vendors()
    next_id = 1 if df.empty or df["id"].dropna().empty else int(df["id"].max()) + 1
    row = {
        "id": next_id,
        "company_name": company_name,
        "website": website,
        "email": email,
        "phone": phone,
        "category": category,
        "source_url": source_url,
        "source_type": source_type,
        "contact_status": contact_status,
        "last_contacted": last_contacted,
        "next_action": next_action,
        "notes": notes,
    }
    df = pd.concat([df, pd.DataFrame([row])], ignore_index=True)
    save_vendors(df)
    return next_id


def update_vendor_status(
    vendor_id: int,
    contact_status: str,
    last_contacted: Optional[str] = None,
    next_action: Optional[str] = None,
    notes: Optional[str] = None,
) -> None:
    if contact_status not in VALID_CONTACT_STATUSES:
        raise ValueError(
            f"無效 contact_status：{contact_status}（合法值：{sorted(VALID_CONTACT_STATUSES)}）"
        )
    df = load_vendors()
    mask = df["id"] == int(vendor_id)
    if not mask.any():
        raise ValueError(f"找不到 vendor_id={vendor_id}")
    df.loc[mask, "contact_status"] = contact_status
    if last_contacted is not None:
        df.loc[mask, "last_contacted"] = last_contacted
    if next_action is not None:
        df.loc[mask, "next_action"] = next_action
    if notes is not None:
        df.loc[mask, "notes"] = notes
    save_vendors(df)


def get_vendors_by_status(contact_status: str) -> pd.DataFrame:
    df = load_vendors()
    if df.empty:
        return df
    return df[df["contact_status"] == contact_status].reset_index(drop=True)


def _get_vendor(vendor_id: int) -> dict:
    df = load_vendors()
    mask = df["id"] == int(vendor_id)
    if not mask.any():
        raise ValueError(f"找不到 vendor_id={vendor_id}")
    return df.loc[mask].iloc[0].to_dict()


# ── Email templates ──────────────────────────────────────────

def _source_domain(source_url: str) -> str:
    """從 URL 抽出乾淨網域：'https://www.ren-ji-tech.com.tw/about' -> 'ren-ji-tech.com.tw'"""
    if not source_url:
        return ""
    try:
        url = source_url if "://" in source_url else "http://" + source_url
        host = urlparse(url).netloc or urlparse(url).path.split("/")[0]
        host = host.split(":")[0]  # strip port
        if host.startswith("www."):
            host = host[4:]
        return host or source_url
    except Exception:
        return source_url


def generate_vendor_email(vendor_id: int, template_type: str = "initial") -> dict:
    """正式商務 outreach 範本（採用 user 提供的版本）。

    避開 spam pattern：標題不用「合作邀請」、body 不用條列式賣點、
    body 完全不放任何 http(s):// URL（品牌只用文字提及，收件方可從 From
    header 取得網址）。保留 opt-out 關鍵字「不需聯繫」操作端追蹤用。
    """
    if template_type not in {"initial", "follow_up"}:
        raise ValueError(f"未支援的 template_type：{template_type}")

    vendor = _get_vendor(vendor_id)
    company = (vendor.get("company_name") or "").strip() or "貴公司"
    source_url = (vendor.get("source_url") or "").strip()
    source_domain = _source_domain(source_url)
    last_contacted = (vendor.get("last_contacted") or "").strip()

    optout = "若不方便收到後續聯繫，回覆「不需聯繫」即可，我們會停止後續通知。"

    if template_type == "initial":
        subject = f"想邀請{company}評估將設備同步刊登到 ZeroGrav"
        source_line = (
            f"我們在 {source_domain} 留意到貴公司公開資訊中"
            if source_domain
            else "我們留意到貴公司公開資訊中"
        )
        body = (
            f"{company} 團隊您好：\n\n"
            "我們是 ZeroGrav，正在建立一個二手儀器設備的集中式曝光平台，"
            "協助買家更容易找到量測儀器、檢測設備與相關二手設備供應資訊。\n\n"
            f"{source_line}有販售或提供「二手儀器 / 量測儀器 / 檢測設備」相關設備，"
            "因此想邀請貴公司評估是否將部分設備同步刊登到 ZeroGrav。"
            "刊登可免費開始，不會取代貴公司原有官網、Facebook 或既有銷售管道，"
            "而是多一個讓潛在買家看見設備的曝光入口。\n\n"
            "若您願意，我們可以先協助整理並刊登 3-5 筆設備資訊。"
            "買家後續可依照貴公司指定方式聯絡，例如電話、Email、LINE 或官網表單；"
            "ZeroGrav 不會承諾成交，也不會誇大流量，"
            "只希望用低成本方式協助設備多一個被搜尋與詢問的機會。\n\n"
            "若方便的話，歡迎回覆可刊登的設備資料或適合聯繫的窗口，我們會再協助整理下一步。\n\n"
            f"{optout}"
        )
    else:  # follow_up
        subject = f"再次請教：{company}是否方便將設備刊登到 ZeroGrav"
        prior = (
            f"前陣子（{last_contacted}）"
            if last_contacted
            else "前陣子"
        )
        body = (
            f"{company} 團隊您好：\n\n"
            f"{prior}有與貴公司聯繫過 ZeroGrav 二手儀器平台刊登的事，"
            "因為不確定當時是否有看到，想再請教一次。\n\n"
            "如先前所提，刊登可免費開始，不取代貴公司原有銷售管道，"
            "買家會依貴公司指定方式聯絡。"
            "若願意嘗試，我們可以先協助整理並刊登 3-5 筆設備資訊。\n\n"
            "若方便的話，歡迎回覆可刊登的設備資料或適合聯繫的窗口。\n\n"
            f"{optout}"
        )

    return {"subject": subject, "body": body}


# ── Outreach log ─────────────────────────────────────────────

def load_log() -> pd.DataFrame:
    _ensure_csv(LOG_CSV, LOG_COLUMNS)
    df = pd.read_csv(LOG_CSV, dtype=str, keep_default_na=False)
    if df.empty:
        return pd.DataFrame(columns=LOG_COLUMNS)
    df["id"] = pd.to_numeric(df["id"], errors="coerce").astype("Int64")
    df["vendor_id"] = pd.to_numeric(df["vendor_id"], errors="coerce").astype("Int64")
    return df


def append_log(
    vendor_id: int,
    email: str,
    template_type: str,
    subject: str,
    status: str,
    error_message: str = "",
) -> int:
    if status not in VALID_LOG_STATUSES:
        raise ValueError(f"無效 log status：{status}（合法值：{sorted(VALID_LOG_STATUSES)}）")
    df = load_log()
    next_id = 1 if df.empty or df["id"].dropna().empty else int(df["id"].max()) + 1
    row = {
        "id": next_id,
        "date": date.today().isoformat(),
        "vendor_id": int(vendor_id),
        "email": email,
        "template_type": template_type,
        "subject": subject,
        "status": status,
        "error_message": error_message,
    }
    df = pd.concat([df, pd.DataFrame([row])], ignore_index=True)
    df.to_csv(LOG_CSV, index=False, encoding="utf-8")
    return next_id


def count_sent_today() -> int:
    df = load_log()
    if df.empty:
        return 0
    today = date.today().isoformat()
    return int(((df["date"] == today) & (df["status"] == "sent")).sum())
