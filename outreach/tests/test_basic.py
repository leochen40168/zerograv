"""Tests for vendor_outreach + email_sender.

CSV paths are isolated per-test via monkeypatch on the module-level path
attributes. SMTP is mocked — no real network calls.
"""
from __future__ import annotations

from datetime import date
from unittest.mock import MagicMock, patch

import pytest

import vendor_outreach as vo
import email_sender as es
import pre_send_check as psc


@pytest.fixture(autouse=True)
def _isolate_csv(tmp_path, monkeypatch):
    """Point CSV paths at a fresh tmp dir for every test."""
    monkeypatch.setattr(vo, "VENDORS_CSV", tmp_path / "vendors.csv")
    monkeypatch.setattr(vo, "LOG_CSV", tmp_path / "log.csv")
    yield


def _enable_smtp(monkeypatch):
    monkeypatch.setenv("EMAIL_SEND_ENABLED", "true")
    monkeypatch.setenv("EMAIL_SMTP_HOST", "smtp.test")
    monkeypatch.setenv("EMAIL_SMTP_PORT", "587")
    monkeypatch.setenv("EMAIL_SMTP_USERNAME", "user")
    monkeypatch.setenv("EMAIL_SMTP_PASSWORD", "pass")
    monkeypatch.setenv("EMAIL_FROM_ADDRESS", "from@test")


# ── add_vendor ───────────────────────────────────────────────

def test_add_vendor_returns_first_id():
    vid = vo.add_vendor("Acme Inc", email="a@x.com", source_url="https://x.com/contact")
    assert vid == 1
    df = vo.load_vendors()
    assert df.loc[df["id"] == 1, "company_name"].iloc[0] == "Acme Inc"


def test_vendor_id_auto_increments():
    vo.add_vendor("First", email="a@x.com", source_url="https://a.com")
    vid2 = vo.add_vendor("Second", email="b@x.com", source_url="https://b.com")
    vid3 = vo.add_vendor("Third", email="c@x.com", source_url="https://c.com")
    assert (vid2, vid3) == (2, 3)


def test_invalid_contact_status_raises_on_add():
    with pytest.raises(ValueError):
        vo.add_vendor("X", contact_status="banana")


def test_invalid_contact_status_raises_on_update():
    vid = vo.add_vendor("X", email="x@x.com", source_url="https://x.com")
    with pytest.raises(ValueError):
        vo.update_vendor_status(vid, "banana")


def test_invalid_source_type_raises():
    with pytest.raises(ValueError):
        vo.add_vendor("X", source_type="instagram")


# ── generate_vendor_email ────────────────────────────────────

def test_generate_initial_email_includes_company_and_source():
    vid = vo.add_vendor(
        "Acme Inc", email="a@x.com",
        source_url="https://acme.example/contact",
    )
    out = vo.generate_vendor_email(vid, template_type="initial")
    assert set(out.keys()) == {"subject", "body"}
    assert "Acme Inc" in out["subject"]
    assert "Acme Inc" in out["body"]
    assert "https://acme.example/contact" in out["body"]
    assert "不需聯繫" in out["body"]  # opt-out language


def test_generate_follow_up_mentions_last_contacted():
    vid = vo.add_vendor(
        "Acme", email="a@x.com", source_url="https://a.com",
        last_contacted="2026-04-20",
    )
    out = vo.generate_vendor_email(vid, template_type="follow_up")
    assert "2026-04-20" in out["body"]
    assert out["subject"].startswith("Re:")


def test_generate_follow_up_without_last_contacted_still_works():
    vid = vo.add_vendor("Acme", email="a@x.com", source_url="https://a.com")
    out = vo.generate_vendor_email(vid, template_type="follow_up")
    assert "Acme" in out["body"]


def test_generate_unknown_template_raises():
    vid = vo.add_vendor("X", email="x@x.com", source_url="https://x.com")
    with pytest.raises(ValueError):
        vo.generate_vendor_email(vid, template_type="haiku")


# ── send_vendor_email policy gates ───────────────────────────

def test_send_disabled_by_default(monkeypatch):
    monkeypatch.setenv("EMAIL_SEND_ENABLED", "false")
    vid = vo.add_vendor("X", email="x@x.com", source_url="https://x.com")
    with pytest.raises(es.EmailDisabledError):
        es.send_vendor_email(vid)


def test_send_blocked_when_smtp_config_missing(monkeypatch):
    monkeypatch.setenv("EMAIL_SEND_ENABLED", "true")
    for k in ("EMAIL_SMTP_HOST", "EMAIL_SMTP_USERNAME",
              "EMAIL_SMTP_PASSWORD", "EMAIL_FROM_ADDRESS"):
        monkeypatch.delenv(k, raising=False)
    vid = vo.add_vendor("X", email="x@x.com", source_url="https://x.com")
    with pytest.raises(es.EmailConfigError):
        es.send_vendor_email(vid)


def test_send_blocked_when_email_empty(monkeypatch):
    _enable_smtp(monkeypatch)
    vid = vo.add_vendor("X", email="", source_url="https://x.com")
    with pytest.raises(es.VendorSkipped):
        es.send_vendor_email(vid)


def test_send_blocked_when_source_url_empty(monkeypatch):
    _enable_smtp(monkeypatch)
    vid = vo.add_vendor("X", email="x@x.com", source_url="")
    with pytest.raises(es.VendorSkipped):
        es.send_vendor_email(vid)


@pytest.mark.parametrize("status", ["opted_out", "not_interested"])
def test_send_blocked_for_optout_statuses(monkeypatch, status):
    _enable_smtp(monkeypatch)
    vid = vo.add_vendor(
        "X", email="x@x.com", source_url="https://x.com",
        contact_status=status,
    )
    with pytest.raises(es.VendorSkipped):
        es.send_vendor_email(vid)


# ── Successful send writes log + updates vendor ──────────────

def test_smtp_success_logs_and_updates_status(monkeypatch):
    _enable_smtp(monkeypatch)
    vid = vo.add_vendor("Acme", email="a@x.com", source_url="https://a.com")
    smtp_inst = MagicMock()
    with patch("smtplib.SMTP") as mock_smtp_cls:
        mock_smtp_cls.return_value.__enter__.return_value = smtp_inst
        result = es.send_vendor_email(vid, template_type="initial")

    assert result["ok"] is True
    smtp_inst.send_message.assert_called_once()

    log = vo.load_log()
    assert len(log) == 1
    assert log.iloc[0]["status"] == "sent"
    assert int(log.iloc[0]["vendor_id"]) == vid
    assert log.iloc[0]["email"] == "a@x.com"

    vendors = vo.load_vendors()
    row = vendors.loc[vendors["id"] == vid].iloc[0]
    assert row["contact_status"] == "email_sent"
    assert row["last_contacted"] == date.today().isoformat()


def test_smtp_failure_writes_failed_log_and_raises(monkeypatch):
    _enable_smtp(monkeypatch)
    vid = vo.add_vendor("Acme", email="a@x.com", source_url="https://a.com")
    with patch("smtplib.SMTP") as mock_smtp_cls:
        mock_smtp_cls.side_effect = OSError("connection refused")
        with pytest.raises(OSError):
            es.send_vendor_email(vid)

    log = vo.load_log()
    assert len(log) == 1
    assert log.iloc[0]["status"] == "failed"
    assert "connection refused" in log.iloc[0]["error_message"]
    # vendor status NOT updated on failure
    vendors = vo.load_vendors()
    assert vendors.loc[vendors["id"] == vid, "contact_status"].iloc[0] == "new"


# ── Daily limit ──────────────────────────────────────────────

def test_daily_limit_blocks_further_sends(monkeypatch):
    _enable_smtp(monkeypatch)
    monkeypatch.setenv("EMAIL_DAILY_LIMIT", "1")
    vid1 = vo.add_vendor("A", email="a@x.com", source_url="https://a.com")
    vid2 = vo.add_vendor("B", email="b@x.com", source_url="https://b.com")

    with patch("smtplib.SMTP") as mock_smtp_cls:
        mock_smtp_cls.return_value.__enter__.return_value = MagicMock()
        es.send_vendor_email(vid1)  # first one OK
        with pytest.raises(es.DailyLimitExceeded):
            es.send_vendor_email(vid2)


# ── send_email standalone honours EMAIL_SEND_ENABLED ─────────

def test_send_email_standalone_blocked_when_disabled(monkeypatch):
    monkeypatch.setenv("EMAIL_SEND_ENABLED", "false")
    with pytest.raises(es.EmailDisabledError):
        es.send_email("a@x.com", "s", "b")


# ── pre_send_check helpers ───────────────────────────────────

def test_suggest_template_for_known_statuses():
    assert psc.suggest_template("new") == "initial"
    assert psc.suggest_template("email_drafted") == "initial"
    assert psc.suggest_template("follow_up_needed") == "follow_up"
    assert psc.suggest_template("opted_out") is None
    assert psc.suggest_template("listed") is None


def test_eligible_candidates_excludes_blocked_and_missing_data():
    vo.add_vendor("ok-new", email="a@x.com", source_url="https://a.com",
                  contact_status="new")
    vo.add_vendor("ok-followup", email="b@x.com", source_url="https://b.com",
                  contact_status="follow_up_needed")
    vo.add_vendor("blocked", email="c@x.com", source_url="https://c.com",
                  contact_status="opted_out")
    vo.add_vendor("no-email", email="", source_url="https://d.com",
                  contact_status="new")
    vo.add_vendor("no-source", email="e@x.com", source_url="",
                  contact_status="new")
    vo.add_vendor("already-listed", email="f@x.com", source_url="https://f.com",
                  contact_status="listed")

    cands = psc.eligible_candidates(vo.load_vendors())
    names = list(cands["company_name"])
    # follow_up_needed should sort before new (priority)
    assert names == ["ok-followup", "ok-new"]
    assert list(cands["suggested_template"]) == ["follow_up", "initial"]


def test_find_issues_lists_actionable_vendors_with_missing_data():
    vo.add_vendor("ok", email="a@x.com", source_url="https://a.com",
                  contact_status="new")
    vo.add_vendor("no-email", email="", source_url="https://d.com",
                  contact_status="new")
    vo.add_vendor("no-source", email="e@x.com", source_url="",
                  contact_status="follow_up_needed")
    # opted_out with missing data — should NOT show up (already excluded)
    vo.add_vendor("blocked-incomplete", email="", source_url="",
                  contact_status="opted_out")

    issues = psc.find_issues(vo.load_vendors())
    names = sorted(issues["company_name"].tolist())
    assert names == ["no-email", "no-source"]
    assert all(s for s in issues["issue"])


def test_pre_send_check_main_runs_without_error(capsys):
    vo.add_vendor("Acme", email="a@x.com", source_url="https://a.com",
                  contact_status="new")
    rc = psc.main([])
    captured = capsys.readouterr().out
    assert rc == 0
    assert "今日候選名單" in captured
    assert "Acme" in captured


def test_pre_send_check_preview_prints_email(capsys):
    vid = vo.add_vendor("Acme", email="a@x.com",
                        source_url="https://acme.example/contact",
                        contact_status="new")
    rc = psc.main(["--preview", str(vid)])
    captured = capsys.readouterr().out
    assert rc == 0
    assert "[Subject]" in captured
    assert "Acme" in captured
    assert "https://acme.example/contact" in captured
