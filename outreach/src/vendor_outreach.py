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

_SIGN_OFF = (
    "\n\n—\n"
    "ZeroGrav 二手儀器交易平台\n"
    "https://zerograv.com.tw\n\n"
    "若不方便收到後續聯繫，回覆「不需聯繫」即可，我們會停止後續通知。"
)


def generate_vendor_email(vendor_id: int, template_type: str = "initial") -> dict:
    if template_type not in {"initial", "follow_up"}:
        raise ValueError(f"未支援的 template_type：{template_type}")

    vendor = _get_vendor(vendor_id)
    company = (vendor.get("company_name") or "").strip() or "貴公司"
    source_url = (vendor.get("source_url") or "").strip()
    last_contacted = (vendor.get("last_contacted") or "").strip()

    if template_type == "initial":
        subject = f"二手儀器設備曝光合作邀請 — {company}"
        source_line = (
            f"我們在 {source_url} 看到貴公司有公開販售二手儀器/量測設備的資訊，"
            if source_url
            else "我們注意到貴公司有經營二手儀器/量測設備的業務，"
        )
        body = (
            f"{company} 您好，\n\n"
            "我們是 ZeroGrav，一個專注於台灣二手科學儀器與量測設備的集中式曝光平台 "
            "(https://zerograv.com.tw)。\n\n"
            f"{source_line}希望能邀請貴公司在 ZeroGrav 上架現有設備，提高曝光與洽詢機會。\n\n"
            "幾項說明：\n"
            "- 平台目前提供免費刊登，無上架費或抽成。\n"
            "- 我們不取代貴公司原有官網與通路；買家依貴公司指定方式聯絡（電話、Line、Email 等）。\n"
            "- 若您願意嘗試，我們可以協助先行刊登 3-5 筆設備，後續再由貴公司決定是否自行維護。\n"
            "- 我們不對成交做任何承諾，也不誇大流量；目標是為買賣雙方提供透明的集中目錄。\n\n"
            "若有興趣了解更多，回信告訴我們即可，我們會提供刊登所需的格式與範例。"
            f"{_SIGN_OFF}"
        )
    else:  # follow_up
        subject = f"Re: 二手儀器設備曝光合作邀請 — {company}"
        prior_line = (
            f"我們在 {last_contacted} 曾與貴公司聯繫過 ZeroGrav 二手儀器交易平台合作邀請的事，"
            if last_contacted
            else "前陣子曾與貴公司聯繫過 ZeroGrav 二手儀器交易平台合作邀請的事，"
        )
        body = (
            f"{company} 您好，\n\n"
            f"{prior_line}想再跟您確認一次是否方便進一步討論。\n\n"
            "如先前所提：\n"
            "- 平台免費刊登，無上架費。\n"
            "- 不取代貴公司現有通路；買家直接依貴公司指定方式聯絡。\n"
            "- 我們可以協助先行刊登 3-5 筆設備作為試水溫，您再決定是否續用。\n\n"
            "如果這段時間貴公司有其他考量，也歡迎告訴我們，我們會更新聯繫頻率。"
            f"{_SIGN_OFF}"
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
