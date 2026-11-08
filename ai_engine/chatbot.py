"""Career chatbot: TF-IDF retrieval over careers/programmes/FAQ, optionally
rewritten into a natural reply by the free-tier Gemini API. Falls back to the
plain retrieval answer if Gemini is not configured or unavailable."""
import json
import os
import re

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from model import CAREERS, EDUCATION, _infer_subjects
from programmes import PROGRAMMES, programmes_for_career
import gemini_client

BASE_DIR = os.path.dirname(__file__)
FAQ_P = os.path.join(BASE_DIR, "data", "faq.json")

RIASEC_INFO = {
    "R": ("Realistic", "You like practical, hands-on work with tools, machines, animals or the outdoors."),
    "I": ("Investigative", "You like analysing, researching and solving complex problems."),
    "A": ("Artistic", "You like creative, expressive work such as design, writing or performance."),
    "S": ("Social", "You like helping, teaching, advising and caring for people."),
    "E": ("Enterprising", "You like leading, persuading, selling and running things."),
    "C": ("Conventional", "You like organised, detail-focused work with data, records and procedures."),
}

UNIVERSITY_ALIASES = {
    "nul": "National University of Lesotho",
    "national university": "National University of Lesotho",
    "botho": "Botho University Lesotho",
    "limkokwing": "Limkokwing University of Creative Technology (Lesotho)",
    "lerotholi": "Lerotholi Polytechnic",
    "polytechnic": "Lerotholi Polytechnic",
    "lce": "Lesotho College of Education",
    "college of education": "Lesotho College of Education",
    "agricultural college": "Lesotho Agricultural College",
}

GREETINGS = ("hi", "hello", "hey", "good morning", "good afternoon", "good evening")
THANKS = ("thanks", "thank you", "cheers")

FALLBACK = ("I'm not sure I have a good answer to that. I can help with career options, "
            "school subjects, qualifications, and programmes at Lesotho institutions. "
            "Try asking, for example: \"What can I study to become a nurse?\" or "
            "\"What does Botho University offer?\"")

SYSTEM_INSTRUCTIONS = """You are the Career Compass assistant, a friendly, concise career guidance
helper for high school learners in Lesotho.

Rules:
- Only state facts about specific careers, university/college programmes, subjects, admission
  requirements, or institutions in Lesotho if they appear in the CONTEXT below. Never invent
  programme names, universities, admission requirements, fees, or statistics that are not in the
  context.
- If the context does not contain the answer, say so plainly and suggest the learner ask a
  teacher, career counsellor, or check the institution's current prospectus.
- General career advice, encouragement, and explanations of concepts (like RIASEC, what a job
  involves, how to think about subject choices) do not need to come from the context.
- Keep answers to about 120 words, in plain, encouraging language suited to a teenager.
- Do not use markdown headers or bullet-heavy formatting; write in short plain paragraphs.
- Remind the learner, when relevant, that this is guidance, not a final decision."""


def _load_faq():
    try:
        with open(FAQ_P, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return []


def _programme_text(p):
    flag = "" if p.get("verified") else " (please confirm against the current prospectus)"
    subj = ", ".join(p.get("required_subjects", [])) or "see prospectus"
    dur = f", {p['duration_years']} years" if p.get("duration_years") else ""
    return (f"{p['programme']} - {p['university']} ({p.get('level', '')}{dur}). "
            f"Subjects: {subj}. {p.get('entry_requirements', '')}{flag}")


def _career_answer(career):
    soc = career.get("soc_code", "")
    edu = EDUCATION.get("by_occupation", {}).get(soc, {})
    level = edu.get("most_common_label", "not specified").split(" - ")[0]
    progs = programmes_for_career(career["title"], limit=3)

    lines = [f"{career['title']}: Holland code {career.get('riasec_code', '?')}.",
             f"Typical education (US data): {level}.",
             f"Useful subjects: {_infer_subjects(career)}."]
    if progs:
        lines.append("Where to study in Lesotho: "
                     + "; ".join(f"{p['programme']} at {p['university']}" for p in progs) + ".")
    else:
        lines.append("No matching local programme is listed yet; ask a career counsellor "
                     "about related fields.")
    return "\n".join(lines)


class CareerBot:
    def __init__(self):
        self.docs = []

        for item in _load_faq():
            self.docs.append({"kind": "faq", "title": item["question"],
                              "text": item["question"], "answer": item["answer"]})

        for p in PROGRAMMES:
            text = " ".join([p["programme"], p["university"], p.get("faculty", ""),
                             p.get("description", ""), " ".join(p.get("related_careers", []))])
            self.docs.append({"kind": "programme", "title": p["programme"], "text": text,
                              "answer": _programme_text(p), "university": p["university"]})

        for c in CAREERS:
            self.docs.append({"kind": "career", "title": c["title"],
                              "text": c["title"] + " " + c["title"],
                              "answer": None, "career": c})

        self.vectorizer = TfidfVectorizer(stop_words="english", ngram_range=(1, 2),
                                          sublinear_tf=True)
        self.matrix = self.vectorizer.fit_transform([d["text"] for d in self.docs])

    # ---- helpers ---------------------------------------------------------
    def _detect_university(self, msg):
        for alias, full in UNIVERSITY_ALIASES.items():
            if re.search(r"\b" + re.escape(alias) + r"\b", msg):
                return full
        return None

    def _answer_for(self, doc):
        if doc["kind"] == "career":
            return _career_answer(doc["career"])
        return doc["answer"]

    def _ranked_docs(self, msg, uni=None, top_k=4):
        q = self.vectorizer.transform([msg])
        sims = cosine_similarity(q, self.matrix)[0]
        if uni:
            for i, d in enumerate(self.docs):
                if d.get("university") == uni:
                    sims[i] *= 1.5
        ranked = sims.argsort()[::-1][:top_k]
        return [(self.docs[i], float(sims[i])) for i in ranked]

    def _build_context(self, ranked_docs, riasec_code=None, subjects=None):
        blocks = []
        if riasec_code:
            names = [RIASEC_INFO[c][0] for c in riasec_code if c in RIASEC_INFO]
            blocks.append(f"Learner's Holland code: {riasec_code} ({', '.join(names)}).")
        if subjects:
            blocks.append(f"Learner's current subjects: {', '.join(subjects)}.")
        for d, score in ranked_docs:
            if score < 0.08:
                continue
            blocks.append(self._answer_for(d))
        return "\n\n".join(blocks) if blocks else "No matching information found in the knowledge base."

    # ---- main entry point ------------------------------------------------
    def reply(self, message, context=None):
        context = context or {}
        raw_msg = (message or "").strip()
        msg = raw_msg.lower()
        if not msg:
            return {"reply": "Please type a question.", "sources": [], "engine": "none"}

        if msg in GREETINGS or any(msg.startswith(g + " ") for g in GREETINGS):
            return {"reply": "Hello! I'm the Career Compass assistant. Ask me about careers, "
                             "subjects, qualifications or programmes at Lesotho institutions.",
                    "sources": [], "engine": "canned"}
        if any(t in msg for t in THANKS):
            return {"reply": "You're welcome! Ask me anything else about your future.",
                    "sources": [], "engine": "canned"}

        code = context.get("riasec_code") or ""
        subjects = context.get("subjects") or []
        uni = self._detect_university(msg)

        ranked = self._ranked_docs(msg, uni=uni, top_k=4)
        best_score = ranked[0][1] if ranked else 0.0

        # Build retrieval context regardless of whether Gemini is used
        riasec_wanted = bool(code) and re.search(r"\b(my|me)\b.*\b(result|code|profile|interest|type)s?\b", msg)
        context_text = self._build_context(
            ranked, riasec_code=code if riasec_wanted else None, subjects=subjects
        )
        sources = [d["title"] for d, s in ranked if s >= 0.08][:3]

        # Try Gemini first (it only answers from context_text + its own general knowledge
        # of career guidance concepts, per SYSTEM_INSTRUCTIONS)
        if gemini_client.is_configured():
            prompt = (f"{SYSTEM_INSTRUCTIONS}\n\nCONTEXT:\n{context_text}\n\n"
                     f"Learner's question: {raw_msg}")
            gemini_reply = gemini_client.generate(prompt)
            if gemini_reply:
                return {"reply": gemini_reply, "sources": sources, "engine": "gemini"}
            # fall through to offline retrieval on any Gemini failure

        # ---- Offline fallback (same behaviour as before) ----
        if code and riasec_wanted:
            parts = [f"Your Holland code is {code}."]
            for ch in code:
                if ch in RIASEC_INFO:
                    name, desc = RIASEC_INFO[ch]
                    parts.append(f"{ch} - {name}: {desc}")
            parts.append("See your dashboard for the careers matched to this profile.")
            return {"reply": "\n".join(parts), "sources": ["Your assessment"], "engine": "offline"}

        if uni and re.search(r"offer|programme|program|course|study|faculty|qualification", msg):
            progs = [p for p in PROGRAMMES if p["university"] == uni]
            if progs:
                lines = [f"Programmes listed for {uni}:"]
                lines += ["- " + _programme_text(p) for p in progs]
                return {"reply": "\n".join(lines), "sources": [uni], "engine": "offline"}

        if best_score < 0.12:
            return {"reply": FALLBACK, "sources": [], "engine": "offline"}

        top_doc, _ = ranked[0]
        reply_text = self._answer_for(top_doc)
        srcs = [top_doc["title"]]
        for d, s in ranked[1:]:
            if s >= 0.2 and d["kind"] == "programme" and top_doc["kind"] != "programme":
                reply_text += "\n\nRelated programme: " + d["answer"]
                srcs.append(d["title"])
                break

        reply_text += "\n\n(This is guidance only. Please confirm details with a teacher or the institution.)"
        return {"reply": reply_text, "sources": srcs, "engine": "offline"}


BOT = CareerBot()