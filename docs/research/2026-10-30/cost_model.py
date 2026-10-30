"""Reproducible assumptions, not a measured quote or device benchmark.

Run: python3 docs/research/2026-10-30/cost_model.py
Prices were observed on 2026-10-30; verify again before purchasing.
SIM, hosting, storage, support, taxes and failed-attempt overhead are excluded.
"""
from decimal import Decimal
import json

D = Decimal


def stt_cost(segment_seconds, usd_per_hour=D("0.04"), minimum_seconds=D("10")):
    segments = [D(str(value)) for value in segment_seconds]
    if any(value <= 0 for value in segments):
        raise ValueError("Each submitted audio request must have a positive duration")
    billed = sum((max(value, minimum_seconds) for value in segments), D(0))
    return billed, billed / D(3600) * usd_per_hour


def tts_cost(total_utf8_bytes, cache_fraction, usd_per_1000_bytes=D("0.015")):
    fraction = D(str(cache_fraction))
    if total_utf8_bytes < 0 or not D(0) <= fraction <= D(1):
        raise ValueError("Nonnegative bytes and a cache fraction from zero to one are required")
    new_bytes = D(total_utf8_bytes) * (D(1) - fraction)
    return new_bytes, new_bytes / D(1000) * usd_per_1000_bytes


def example_scenarios():
    billed, stt = stt_cost([5] * 60)
    llm_budget = D("0.01")  # Hypothetical budget, not a provider tariff.
    rows = []
    for fraction in (D(0), D("0.75"), D("0.90")):
        new_bytes, tts = tts_cost(5000, fraction)
        rows.append({
            "call_minutes_assumed": 10,
            "actual_submitted_stt_seconds": 300,
            "billed_stt_seconds": str(billed),
            "tts_total_utf8_bytes_assumed": 5000,
            "cache_fraction_assumed": str(fraction),
            "new_tts_bytes": str(new_bytes),
            "stt_usd": str(stt),
            "tts_usd": str(tts),
            "llm_budget_assumed_usd": str(llm_budget),
            "variable_subtotal_usd": str(stt + tts + llm_budget),
        })
    return {
        "price_review_date": "2026-10-30",
        "status": "CALCULATED_ASSUMPTIONS_NOT_MEASURED",
        "sources": {
            "stt": "https://console.groq.com/docs/speech-to-text",
            "tts": "https://beta.fish.audio/text-to-speech-api/",
        },
        "excluded": ["SIM plan and overage", "servers", "bandwidth", "storage", "support", "taxes", "failed attempts", "one-time cache creation"],
        "scenarios": rows,
        "fixed_cost_example_usd_per_call": {str(n): str(D(30) / D(n)) for n in (100, 1000, 10000)},
        "stt_only_hypothetical_20usd_monthly_break_even_hours": str(D(20) / D("0.04")),
    }


if __name__ == "__main__":
    print(json.dumps(example_scenarios(), ensure_ascii=False, indent=2))
