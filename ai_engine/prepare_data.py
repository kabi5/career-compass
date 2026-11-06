"""
Converts the O*NET Excel files into clean JSON for the recommendation engine.
Run once whenever the source files change.

Usage:
    python prepare_data.py
"""
import json
import os
from collections import defaultdict
import openpyxl

RAW_DIR   = os.path.join(os.path.dirname(__file__), "data", "raw")
OUT_DIR   = os.path.join(os.path.dirname(__file__), "data", "processed")
os.makedirs(OUT_DIR, exist_ok=True)


def _rows(path, sheet_index=0):
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    ws = wb.worksheets[sheet_index]
    rows = list(ws.iter_rows(values_only=True))
    wb.close()
    header = [str(c).strip() if c else "" for c in rows[0]]
    return header, rows[1:]


def _col(header, name):
    return header.index(name)


# ---------------------------------------------------------------------------
# 1. Careers — from "Career Interest Types.xlsx"
# ---------------------------------------------------------------------------
def build_careers():
    """
    Each O*NET occupation has six rows (one per RIASEC dimension) with an
    'Occupational Interests' score 1-7, plus three rows giving the high-point
    code letters (1-6, where 1=R, 2=I, 3=A, 4=S, 5=E, 6=C).
    """
    path = os.path.join(RAW_DIR, "Career Interest Types.xlsx")
    header, rows = _rows(path)

    code_col   = _col(header, "O*NET-SOC Code")
    title_col  = _col(header, "Title")
    elem_col   = _col(header, "Element Name")
    scale_col  = _col(header, "Scale Name")
    value_col  = _col(header, "Data Value")

    LETTER_MAP = {1: "R", 2: "I", 3: "A", 4: "S", 5: "E", 6: "C"}
    DIMENSIONS = ["Realistic", "Investigative", "Artistic", "Social", "Enterprising", "Conventional"]

    careers = defaultdict(lambda: {
        "soc_code": "", "title": "",
        "riasec_scores": {}, "riasec_code": "",
        "top_dimensions": []
    })

    for r in rows:
        if not r or not r[code_col]:
            continue
        code  = str(r[code_col]).strip()
        title = str(r[title_col]).strip()
        elem  = str(r[elem_col]).strip() if r[elem_col] else ""
        scale = str(r[scale_col]).strip() if r[scale_col] else ""
        value = r[value_col]

        c = careers[code]
        c["soc_code"] = code
        c["title"]    = title

        if scale == "Occupational Interests" and elem in DIMENSIONS:
            try:
                c["riasec_scores"][elem] = float(value)
            except (TypeError, ValueError):
                pass

        elif scale == "Occupational Interest High-Point" and "High-Point" in elem:
            try:
                c["top_dimensions"].append(LETTER_MAP[int(value)])
            except (TypeError, ValueError, KeyError):
                pass

    # Derive Holland code from high-points, or fall back to top-3 of scores
    for c in careers.values():
        if c["top_dimensions"]:
            c["riasec_code"] = "".join(c["top_dimensions"][:3])
        elif c["riasec_scores"]:
            ordered = sorted(c["riasec_scores"].items(), key=lambda kv: kv[1], reverse=True)
            c["riasec_code"] = "".join(DIMENSIONS.index(k) and "" or "" for k, _ in ordered[:3])

        # Always provide a Holland code — derive from the six letters if needed
        if not c["riasec_code"] and c["riasec_scores"]:
            inv = {v: k for k, v in {"R": "Realistic", "I": "Investigative",
                                     "A": "Artistic", "S": "Social",
                                     "E": "Enterprising", "C": "Conventional"}.items()}
            ordered = sorted(c["riasec_scores"].items(), key=lambda kv: kv[1], reverse=True)
            c["riasec_code"] = "".join(inv[elem] for elem, _ in ordered[:3])

    out = list(careers.values())
    with open(os.path.join(OUT_DIR, "careers.json"), "w", encoding="utf-8") as f:
        json.dump(out, f, indent=2, ensure_ascii=False)
    print(f"[careers] wrote {len(out)} occupations")
    return out


# ---------------------------------------------------------------------------
# 2. Education requirements — from "Education.xlsx" + "Education Categories.xlsx"
# ---------------------------------------------------------------------------
def build_education():
    # Category names
    path = os.path.join(RAW_DIR, "Education Categories.xlsx")
    header, rows = _rows(path)
    cat_col  = _col(header, "Category")
    desc_col = _col(header, "Category Description")
    category_names = {int(r[cat_col]): str(r[desc_col]) for r in rows if r and r[cat_col] is not None}

    # Per-occupation category distribution
    path = os.path.join(RAW_DIR, "Education.xlsx")
    header, rows = _rows(path)
    code_col = _col(header, "O*NET-SOC Code")
    cat_col2 = _col(header, "Category")
    val_col  = _col(header, "Data Value")

    by_occ = defaultdict(lambda: defaultdict(float))
    for r in rows:
        if not r or not r[code_col]:
            continue
        code = str(r[code_col]).strip()
        try:
            cat = int(r[cat_col2])
            val = float(r[val_col] or 0)
        except (TypeError, ValueError):
            continue
        by_occ[code][cat] = val

    out = {}
    for code, dist in by_occ.items():
        if not dist:
            continue
        top_cat = max(dist.items(), key=lambda kv: kv[1])[0]
        out[code] = {
            "category_distribution": {int(k): v for k, v in dist.items()},
            "most_common_category": top_cat,
            "most_common_label":    category_names.get(top_cat, "Unknown"),
        }

    with open(os.path.join(OUT_DIR, "education.json"), "w", encoding="utf-8") as f:
        json.dump({"categories": category_names, "by_occupation": out}, f, indent=2, ensure_ascii=False)
    print(f"[education] wrote {len(out)} occupation entries + {len(category_names)} category labels")
    return out


# ---------------------------------------------------------------------------
# 3. RIASEC keywords — from "Career Interest Type Keywords.xlsx"
# ---------------------------------------------------------------------------
def build_keywords():
    path = os.path.join(RAW_DIR, "Career Interest Type Keywords.xlsx")
    header, rows = _rows(path)
    elem_col = _col(header, "Element Name")
    kw_col   = _col(header, "Keyword")
    type_col = _col(header, "Keyword Type")

    out = defaultdict(lambda: {"action": [], "object": []})
    for r in rows:
        if not r or not r[elem_col]:
            continue
        dimension = str(r[elem_col]).strip()
        keyword   = str(r[kw_col]).strip()
        kw_type   = str(r[type_col]).strip().lower()
        if kw_type in ("action", "object"):
            out[dimension][kw_type].append(keyword.lower())

    with open(os.path.join(OUT_DIR, "riasec_keywords.json"), "w", encoding="utf-8") as f:
        json.dump(dict(out), f, indent=2, ensure_ascii=False)
    print(f"[keywords] wrote {len(out)} RIASEC dimensions")
    return out


# ---------------------------------------------------------------------------
# 4. Content model reference — human-readable labels for abilities / activities
# ---------------------------------------------------------------------------
def build_content_reference():
    path = os.path.join(RAW_DIR, "Content Model Reference.xlsx")
    header, rows = _rows(path)
    id_col   = _col(header, "Element ID")
    name_col = _col(header, "Element Name")
    desc_col = _col(header, "Description")

    out = {}
    for r in rows:
        if not r or not r[id_col]:
            continue
        out[str(r[id_col]).strip()] = {
            "name": str(r[name_col] or "").strip(),
            "description": str(r[desc_col] or "").strip(),
        }

    with open(os.path.join(OUT_DIR, "content_reference.json"), "w", encoding="utf-8") as f:
        json.dump(out, f, indent=2, ensure_ascii=False)
    print(f"[content] wrote {len(out)} element definitions")
    return out


# ---------------------------------------------------------------------------
# 5. Ability → Work Activity / Context mappings
# ---------------------------------------------------------------------------
def build_ability_maps():
    # Activity
    path = os.path.join(RAW_DIR, "Abilities to Work Activities.xlsx")
    header, rows = _rows(path)
    a_id   = _col(header, "Abilities Element ID")
    a_name = _col(header, "Abilities Element Name")
    w_id   = _col(header, "Work Activities Element ID")
    w_name = _col(header, "Work Activities Element Name")

    activities = defaultdict(lambda: {"name": "", "activities": []})
    for r in rows:
        if not r or not r[a_id]:
            continue
        k = str(r[a_id]).strip()
        activities[k]["name"] = str(r[a_name] or "").strip()
        activities[k]["activities"].append({
            "id":   str(r[w_id] or "").strip(),
            "name": str(r[w_name] or "").strip(),
        })

    with open(os.path.join(OUT_DIR, "abilities_to_activities.json"), "w", encoding="utf-8") as f:
        json.dump(dict(activities), f, indent=2, ensure_ascii=False)
    print(f"[abilities->activities] wrote {len(activities)} abilities")

    # Context
    path = os.path.join(RAW_DIR, "Abilities to Work Context.xlsx")
    header, rows = _rows(path)
    a_id   = _col(header, "Abilities Element ID")
    a_name = _col(header, "Abilities Element Name")
    c_id   = _col(header, "Work Context Element ID")
    c_name = _col(header, "Work Context Element Name")

    contexts = defaultdict(lambda: {"name": "", "contexts": []})
    for r in rows:
        if not r or not r[a_id]:
            continue
        k = str(r[a_id]).strip()
        contexts[k]["name"] = str(r[a_name] or "").strip()
        contexts[k]["contexts"].append({
            "id":   str(r[c_id] or "").strip(),
            "name": str(r[c_name] or "").strip(),
        })

    with open(os.path.join(OUT_DIR, "abilities_to_context.json"), "w", encoding="utf-8") as f:
        json.dump(dict(contexts), f, indent=2, ensure_ascii=False)
    print(f"[abilities->context] wrote {len(contexts)} abilities")


# ---------------------------------------------------------------------------
if __name__ == "__main__":
    print("Building processed datasets from O*NET sources…\n")
    build_careers()
    build_education()
    build_keywords()
    build_content_reference()
    build_ability_maps()
    print("\nDone. Processed files are in:", OUT_DIR)