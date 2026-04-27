"""Streamlit dashboard for the Vendor Email Outreach Agent.

Run with:
    streamlit run outreach/src/app.py
"""
from __future__ import annotations

import sys
from pathlib import Path

import streamlit as st

# allow running with `streamlit run outreach/src/app.py` from repo root
sys.path.insert(0, str(Path(__file__).resolve().parent))

import vendor_outreach as vo
import email_sender as es


st.set_page_config(page_title="ZeroGrav Vendor Outreach", layout="wide")
st.title("ZeroGrav Vendor Email Outreach Agent")

cfg = es.load_email_config()
if cfg["send_enabled"]:
    st.success(
        f"EMAIL_SEND_ENABLED=true — 按 Send Email 會真的寄出。每日上限 {cfg['daily_limit']} 封，"
        f"今日已寄 {vo.count_sent_today()} 封。"
    )
else:
    st.warning(
        "EMAIL_SEND_ENABLED=false — 目前為 **draft-only** 模式。"
        "Send Email 會被擋下，方便你先檢查信件內容。要真的寄出請到 .env 設 EMAIL_SEND_ENABLED=true。"
    )

# ── Add vendor ───────────────────────────────────────────────

st.header("1. 新增廠商")
with st.form("add_vendor"):
    cols = st.columns(2)
    with cols[0]:
        company_name = st.text_input("公司名稱 *")
        website = st.text_input("官網")
        email = st.text_input("Email *")
        phone = st.text_input("電話")
        category = st.text_input("分類（可空）")
    with cols[1]:
        source_url = st.text_input("Source URL（找到 email 的那個頁面） *")
        source_type = st.selectbox(
            "Source Type",
            sorted(vo.VALID_SOURCE_TYPES),
            index=sorted(vo.VALID_SOURCE_TYPES).index("website"),
        )
        contact_status = st.selectbox(
            "初始狀態",
            sorted(vo.VALID_CONTACT_STATUSES),
            index=sorted(vo.VALID_CONTACT_STATUSES).index("new"),
        )
        next_action = st.text_input("Next action（可空）")
        notes = st.text_area("備註", height=80)

    submitted = st.form_submit_button("新增廠商")
    if submitted:
        try:
            new_id = vo.add_vendor(
                company_name=company_name,
                website=website,
                email=email,
                phone=phone,
                category=category,
                source_url=source_url,
                source_type=source_type,
                contact_status=contact_status,
                next_action=next_action,
                notes=notes,
            )
            st.success(f"已新增 vendor #{new_id}")
        except ValueError as e:
            st.error(str(e))

# ── Browse / filter ──────────────────────────────────────────

st.header("2. 廠商清單")
all_vendors = vo.load_vendors()
status_options = ["(全部)"] + sorted(vo.VALID_CONTACT_STATUSES)
chosen_status = st.selectbox("依 contact_status 篩選", status_options, index=0)

if chosen_status == "(全部)":
    view_df = all_vendors
else:
    view_df = vo.get_vendors_by_status(chosen_status)
st.caption(f"共 {len(view_df)} 筆")
st.dataframe(view_df, use_container_width=True, hide_index=True)

# ── Generate / send for one vendor ───────────────────────────

st.header("3. 產生開發信 / 寄送")
if all_vendors.empty:
    st.info("還沒有任何廠商，請先在上方新增。")
else:
    pick_cols = st.columns([2, 1, 1])
    with pick_cols[0]:
        vendor_choice = st.selectbox(
            "選擇 vendor",
            options=all_vendors["id"].tolist(),
            format_func=lambda vid: f"#{vid} — "
            + str(all_vendors.loc[all_vendors["id"] == vid, "company_name"].iloc[0]),
        )
    with pick_cols[1]:
        template_type = st.radio("模板", ["initial", "follow_up"], horizontal=True)
    with pick_cols[2]:
        st.write("")
        st.write("")
        gen_clicked = st.button("產生信件")

    if gen_clicked:
        try:
            drafted = vo.generate_vendor_email(int(vendor_choice), template_type=template_type)
            st.session_state["drafted"] = drafted
            st.session_state["drafted_vendor"] = int(vendor_choice)
            st.session_state["drafted_template"] = template_type
        except ValueError as e:
            st.error(str(e))

    if "drafted" in st.session_state:
        st.subheader("信件預覽（請先人工檢查）")
        st.text_input("Subject", st.session_state["drafted"]["subject"], disabled=True)
        st.text_area("Body", st.session_state["drafted"]["body"], height=320, disabled=True)

        send_disabled = not cfg["send_enabled"]
        send_label = "Send Email" if cfg["send_enabled"] else "Send Email（已停用）"
        if st.button(send_label, disabled=send_disabled, type="primary"):
            try:
                result = es.send_vendor_email(
                    int(st.session_state["drafted_vendor"]),
                    template_type=st.session_state["drafted_template"],
                )
                st.success(f"已寄出 → {result['to']}")
                del st.session_state["drafted"]
            except (es.EmailDisabledError, es.EmailConfigError, es.VendorSkipped,
                    es.DailyLimitExceeded) as e:
                st.error(str(e))
            except Exception as e:
                st.error(f"寄送失敗：{e}")

# ── Outreach log ─────────────────────────────────────────────

st.header("4. 寄送紀錄")
log_df = vo.load_log()
st.caption(f"共 {len(log_df)} 筆，今日已寄 {vo.count_sent_today()} 封")
if not log_df.empty:
    st.dataframe(log_df.iloc[::-1], use_container_width=True, hide_index=True)
else:
    st.info("還沒有寄送紀錄。")
