"""Thin wrapper around the free-tier Gemini API, with graceful failure."""
import os
import requests

GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY", "").strip()
GEMINI_MODEL = os.environ.get("GEMINI_MODEL", "gemini-2.5-flash").strip()
GEMINI_URL = f"https://generativelanguage.googleapis.com/v1beta/models/{GEMINI_MODEL}:generateContent"

TIMEOUT = 20


def is_configured():
    # Off by default: chat may contain information about minors.
    return os.getenv("ENABLE_GEMINI", "false").lower() == "true" and bool(GEMINI_API_KEY)


def generate(prompt, temperature=0.4, max_tokens=800):
    """
    Returns the generated text, or None if Gemini is not configured,
    rate-limited, or unreachable (caller falls back to the retrieval bot).
    """
    if not is_configured():
        return None

    body = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {
            "temperature": temperature,
            "maxOutputTokens": max_tokens,
            "thinkingConfig": {"thinkingBudget": 0},  # no hidden reasoning tokens
        },
    }
    headers = {
        "Content-Type": "application/json",
        "x-goog-api-key": GEMINI_API_KEY,
    }

    try:
        resp = requests.post(GEMINI_URL, json=body, headers=headers, timeout=TIMEOUT)
    except requests.RequestException as e:
        print(f"[gemini] request failed: {e}")
        return None

    if resp.status_code == 429:
        print("[gemini] rate limited (free tier quota hit)")
        return None
    if resp.status_code == 404:
        print(f"[gemini] 404 for model '{GEMINI_MODEL}'. Check GEMINI_MODEL in .env")
        return None
    if resp.status_code != 200:
        print(f"[gemini] HTTP {resp.status_code}: {resp.text[:300]}")
        return None

    try:
        data = resp.json()
        candidates = data.get("candidates", [])
        if not candidates:
            print(f"[gemini] no candidates returned: {data}")
            return None

        cand = candidates[0]
        if cand.get("finishReason") == "MAX_TOKENS":
            print("[gemini] hit MAX_TOKENS, reply may be truncated")

        parts = cand.get("content", {}).get("parts", [])
        text = "".join(p.get("text", "") for p in parts if not p.get("thought")).strip()
        return text or None
    except (KeyError, IndexError, ValueError) as e:
        print(f"[gemini] unexpected response shape: {e} -> {resp.text[:300]}")
        return None