"""Dry-run review tool for the outreach queue.

Daily morning use: see exactly which vendors would be emailed today,
which template each would get, and what's blocking the rest — without
sending anything. Never touches SMTP.

Examples:
    python3 src/pre_send_check.py
    python3 src/pre_send_check.py --status new
    python3 src/pre_send_check.py --preview 5
    python3 src/pre_send_check.py --limit 10
"""
from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

import pandas as pd

sys.path.insert(0, str(Path(__file__).resolve().parent))

import vendor_outreach as vo
import email_sender as es

# Statuses that translate to "should we try to email today?"
SENDABLE_STATUSES = {"new", "email_drafted", "follow_up_needed"}

# How to map contact_status → template type for the suggestion column.
TEMPLATE_FOR_STATUS = {
    "new": "initial",
    "email_drafted": "initial",
    "follow_up_needed": "follow_up",
}

BLOCKED_STATUSES = {"opted_out", "not_interested"}


def suggest_template(status: str) -> str | None:
    """Return 'initial' / 'follow_up' / None for a given contact_status."""
    return TEMPLATE_FOR_STATUS.get(status)


def eligible_candidates(df: pd.DataFrame) -> pd.DataFrame:
    """Vendors that ``send_vendor_email`` would actually accept today.

    Rules mirror those in ``email_sender.send_vendor_email``:
    - status not in opted_out / not_interested
    - email non-empty
    - source_url non-empty
    Plus a soft restriction to "actionable" statuses so we don't suggest
    re-emailing already-replied/listed vendors.
    """
    if df.empty:
        return df
    mask = (
        df["contact_status"].isin(SENDABLE_STATUSES)
        & df["email"].astype(str).str.strip().ne("")
        & df["source_url"].astype(str).str.strip().ne("")
    )
    out = df.loc[mask].copy()
    if out.empty:
        return out
    out["suggested_template"] = out["contact_status"].map(TEMPLATE_FOR_STATUS)
    # Priority: follow_up_needed first (these are actively waiting), then new, then drafted
    priority = {"follow_up_needed": 0, "new": 1, "email_drafted": 2}
    out["_p"] = out["contact_status"].map(priority).fillna(99)
    out = out.sort_values(["_p", "id"]).drop(columns=["_p"]).reset_index(drop=True)
    return out


def find_issues(df: pd.DataFrame) -> pd.DataFrame:
    """Vendors in actionable status but missing email or source_url —
    these need cleanup before they can be contacted."""
    if df.empty:
        return df
    mask = df["contact_status"].isin(SENDABLE_STATUSES) & (
        df["email"].astype(str).str.strip().eq("")
        | df["source_url"].astype(str).str.strip().eq("")
    )
    out = df.loc[mask].copy()
    if out.empty:
        return out
    out["issue"] = out.apply(
        lambda r: ", ".join(filter(None, [
            "缺 email" if not str(r["email"]).strip() else "",
            "缺 source_url" if not str(r["source_url"]).strip() else "",
        ])),
        axis=1,
    )
    return out[["id", "company_name", "contact_status", "issue"]].reset_index(drop=True)


# ── CLI ──────────────────────────────────────────────────────

def _print_section(title: str, body: str = "") -> None:
    print(f"\n── {title} {'─' * max(0, 60 - len(title))}")
    if body:
        print(body)


def _format_table(df: pd.DataFrame, columns: list[str]) -> str:
    if df.empty:
        return "（無）"
    keep = [c for c in columns if c in df.columns]
    return df[keep].to_string(index=False, max_colwidth=40)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Dry-run outreach queue review.")
    parser.add_argument("--status", help="只看特定 contact_status")
    parser.add_argument("--template", choices=["initial", "follow_up"],
                        help="只看建議套用此模板的 vendor")
    parser.add_argument("--limit", type=int,
                        help="最多顯示幾筆（預設 = 今日剩餘可寄額度）")
    parser.add_argument("--preview", type=int, metavar="VENDOR_ID",
                        help="印出此 vendor 完整信件內容（自動判斷模板）")
    args = parser.parse_args(argv)

    cfg = es.load_email_config()
    sent_today = vo.count_sent_today()
    remaining = max(0, cfg["daily_limit"] - sent_today)

    _print_section("設定狀態")
    print(f"  EMAIL_SEND_ENABLED = {cfg['send_enabled']}"
          + ("  (寄送功能停用，下面僅為 dry-run)" if not cfg["send_enabled"] else ""))
    print(f"  EMAIL_DAILY_LIMIT  = {cfg['daily_limit']}")
    print(f"  今日已寄          = {sent_today}")
    print(f"  今日剩餘可寄      = {remaining}")

    df = vo.load_vendors()
    if df.empty:
        _print_section("結果", "  data/vendors.csv 是空的，先去 dashboard 新增 vendor。")
        return 0

    # Preview mode short-circuits everything else
    if args.preview is not None:
        try:
            vendor = vo._get_vendor(args.preview)
        except ValueError as e:
            print(f"\nERROR: {e}")
            return 1
        tpl = suggest_template(vendor.get("contact_status", "")) or "initial"
        drafted = vo.generate_vendor_email(args.preview, template_type=tpl)
        _print_section(f"Preview vendor #{args.preview} ({vendor.get('company_name')})")
        print(f"  狀態：{vendor.get('contact_status')}")
        print(f"  收件：{vendor.get('email')}")
        print(f"  source_url：{vendor.get('source_url')}")
        print(f"  套用模板：{tpl}")
        print(f"\n[Subject] {drafted['subject']}")
        print(f"\n[Body]\n{drafted['body']}")
        return 0

    candidates = eligible_candidates(df)
    if args.status:
        candidates = candidates[candidates["contact_status"] == args.status]
    if args.template:
        candidates = candidates[candidates["suggested_template"] == args.template]

    limit = args.limit if args.limit is not None else remaining
    truncated = len(candidates) > limit
    shown = candidates.head(limit)

    _print_section(f"今日候選名單（共 {len(candidates)}，顯示 {len(shown)}）")
    if not cfg["send_enabled"]:
        print("  （EMAIL_SEND_ENABLED=false，下方僅供 review，沒有任何信會寄出）")
    print()
    print(_format_table(
        shown,
        ["id", "company_name", "email", "contact_status",
         "last_contacted", "suggested_template"],
    ))
    if truncated:
        print(f"\n  ⚠ 還有 {len(candidates) - len(shown)} 筆未顯示，"
              f"用 --limit N 看更多，或先處理上面這批。")

    issues = find_issues(df)
    _print_section(f"資料缺失（共 {len(issues)}，需要補資料才能寄）")
    if issues.empty:
        print("  ✓ 沒有缺資料的 vendor。")
    else:
        print(_format_table(issues, ["id", "company_name", "contact_status", "issue"]))

    blocked = df[df["contact_status"].isin(BLOCKED_STATUSES)]
    if not blocked.empty:
        _print_section(f"已封鎖名單（{len(blocked)} 筆，永遠不會寄）")
        print(_format_table(
            blocked,
            ["id", "company_name", "email", "contact_status", "notes"],
        ))

    return 0


if __name__ == "__main__":
    sys.exit(main())
