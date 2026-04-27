"""SMTP delivery for vendor outreach.

Sending is **disabled by default** — set EMAIL_SEND_ENABLED=true in .env to
actually transmit. All other functions raise loud, specific errors instead of
silently no-op'ing so a misconfigured environment is obvious.
"""
from __future__ import annotations

import os
import smtplib
from datetime import date
from email.message import EmailMessage
from pathlib import Path

from dotenv import load_dotenv

import vendor_outreach as vo

_BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(_BASE_DIR / ".env")


# ── Exceptions ───────────────────────────────────────────────

class EmailDisabledError(RuntimeError):
    """Raised when EMAIL_SEND_ENABLED is not 'true'."""


class EmailConfigError(RuntimeError):
    """Raised when required SMTP env vars are missing."""


class VendorSkipped(RuntimeError):
    """Raised when policy forbids sending to this vendor."""


class DailyLimitExceeded(RuntimeError):
    """Raised when EMAIL_DAILY_LIMIT has been reached today."""


# ── Config ───────────────────────────────────────────────────

def load_email_config() -> dict:
    return {
        "smtp_host": (os.getenv("EMAIL_SMTP_HOST", "") or "").strip(),
        "smtp_port": (os.getenv("EMAIL_SMTP_PORT", "587") or "587").strip(),
        "smtp_username": (os.getenv("EMAIL_SMTP_USERNAME", "") or "").strip(),
        "smtp_password": os.getenv("EMAIL_SMTP_PASSWORD", "") or "",
        "from_name": (os.getenv("EMAIL_FROM_NAME", "ZeroGrav") or "ZeroGrav").strip(),
        "from_address": (os.getenv("EMAIL_FROM_ADDRESS", "") or "").strip(),
        "daily_limit": int(os.getenv("EMAIL_DAILY_LIMIT", "20") or 20),
        "send_enabled": (os.getenv("EMAIL_SEND_ENABLED", "false") or "false").strip().lower() == "true",
    }


def set_send_enabled(enabled: bool) -> bool:
    """Flip EMAIL_SEND_ENABLED in .env. Returns the new boolean value.

    Writes atomically (temp file + replace) and updates ``os.environ`` so
    the next ``load_email_config()`` call in the same process sees the
    change without a restart.
    """
    env_path = _BASE_DIR / ".env"
    if not env_path.exists():
        raise FileNotFoundError(
            f"{env_path} 不存在。請先複製 .env.example：cp .env.example .env"
        )

    new_value = "true" if enabled else "false"
    lines = env_path.read_text(encoding="utf-8").splitlines()

    found = False
    new_lines = []
    for line in lines:
        stripped = line.lstrip()
        if stripped.startswith("EMAIL_SEND_ENABLED="):
            new_lines.append(f"EMAIL_SEND_ENABLED={new_value}")
            found = True
        else:
            new_lines.append(line)
    if not found:
        new_lines.append(f"EMAIL_SEND_ENABLED={new_value}")

    tmp_path = env_path.with_name(env_path.name + ".tmp")
    tmp_path.write_text("\n".join(new_lines) + "\n", encoding="utf-8")
    tmp_path.replace(env_path)

    os.environ["EMAIL_SEND_ENABLED"] = new_value
    return enabled


def _check_send_allowed(cfg: dict) -> None:
    if not cfg["send_enabled"]:
        raise EmailDisabledError(
            "EMAIL_SEND_ENABLED 不是 true，已禁止寄送。"
            "請到 .env 設定 EMAIL_SEND_ENABLED=true 才能真的寄出。"
        )
    missing = [
        k for k in ("smtp_host", "smtp_username", "smtp_password", "from_address")
        if not cfg.get(k)
    ]
    if missing:
        raise EmailConfigError(
            f"SMTP 設定不完整，缺少：{missing}（請檢查 .env）"
        )


# ── Low-level send ───────────────────────────────────────────

def send_email(to_email: str, subject: str, body: str) -> dict:
    """Send a single email. Bypasses vendor/log/policy logic — that's the
    higher-level ``send_vendor_email``'s job. Still honours the global
    EMAIL_SEND_ENABLED + SMTP-config checks."""
    cfg = load_email_config()
    _check_send_allowed(cfg)
    if not to_email:
        raise ValueError("to_email 不能為空")

    msg = EmailMessage()
    msg["From"] = f"{cfg['from_name']} <{cfg['from_address']}>"
    msg["To"] = to_email
    msg["Subject"] = subject
    msg.set_content(body)

    with smtplib.SMTP(cfg["smtp_host"], int(cfg["smtp_port"]), timeout=30) as smtp:
        smtp.starttls()
        smtp.login(cfg["smtp_username"], cfg["smtp_password"])
        smtp.send_message(msg)

    return {"ok": True, "to": to_email, "subject": subject}


# ── High-level send for a vendor row ─────────────────────────

def send_vendor_email(vendor_id: int, template_type: str = "initial") -> dict:
    """Generate + send + log + update vendor row."""
    cfg = load_email_config()
    _check_send_allowed(cfg)

    if vo.count_sent_today() >= cfg["daily_limit"]:
        raise DailyLimitExceeded(
            f"今日已寄送達上限 {cfg['daily_limit']} 封，請明天再試。"
        )

    vendor = vo._get_vendor(vendor_id)
    email = (vendor.get("email") or "").strip()
    source_url = (vendor.get("source_url") or "").strip()
    status = (vendor.get("contact_status") or "").strip()

    if status in {"opted_out", "not_interested"}:
        raise VendorSkipped(
            f"vendor_id={vendor_id} 狀態為 {status}，禁止寄送"
        )
    if not email:
        raise VendorSkipped(f"vendor_id={vendor_id} 沒有 email，禁止寄送")
    if not source_url:
        raise VendorSkipped(f"vendor_id={vendor_id} 沒有 source_url，禁止寄送")

    drafted = vo.generate_vendor_email(vendor_id, template_type=template_type)
    subject, body = drafted["subject"], drafted["body"]

    try:
        send_result = send_email(email, subject, body)
    except Exception as e:
        vo.append_log(
            vendor_id, email, template_type, subject,
            status="failed", error_message=str(e),
        )
        raise

    vo.append_log(vendor_id, email, template_type, subject, status="sent")
    vo.update_vendor_status(
        vendor_id, "email_sent", last_contacted=date.today().isoformat()
    )
    return {
        "ok": True,
        "vendor_id": vendor_id,
        "subject": subject,
        **send_result,
    }
