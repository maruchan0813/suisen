"""run_conversations.py の結果を自動で調べる。学内ネットワークで使う。--judge を付けると、授業の説明がシラバスに書かれているかを学内 LLM に判定させる。
使い方：python3 analyze.py <結果.json> [--judge <判定の保存先.json>]
"""
import csv, json, re, statistics, sys, urllib.request

import os
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
TITLES = {r["科目名"] for r in csv.DictReader(open(f"{ROOT}/data/syllabus/subjects_2026.csv", encoding="utf-8-sig"))}
INDEX = json.load(open(f"{ROOT}/data/search_index.json"))
CHUNKS_BY_TITLE = {}
for c in INDEX["chunks"]:
    CHUNKS_BY_TITLE.setdefault(c["title"], []).append(c)
CODE_BY_NAME = {r["name"]: r["code"] for r in csv.DictReader(open(f"{ROOT}/data/courses.csv"))}
SIMPLIFIED_ONLY = set("这们说码吗么样还没问题现实验习发时个对过动")
UNANSWERABLE = ("就職率", "学食", "門限", "量子ロボット工学")
LEAK_MARKERS = ("質問を1つ", "質問を一つ", "身近な例で質問", "「力学・材料」", "「熱・流体」", "「設計・ものづくり」", "「制御・ロボット」", "「回路・電子」", "「電力・電気機器」", "「プログラミング・情報」", "「生物・食品」", "聞き出したいこと", "# 守ること", "興味（次の9つ）", "今回の返し方", "asked_questions")
RECOMMEND_IN_CHAT = re.compile(r"(コース|あなた)(が|には|に)(一番|いちばん|最も)?(合|向いて|おすすめ|ぴったり)")
CAREER_FABRICATION = re.compile(r"役(に)?立った|受けた|好きだった|活躍して")


def bigrams(text):
    text = re.sub(r"[\s、。！？!?「」（）]", "", text)
    return {text[i:i + 2] for i in range(len(text) - 1)}


def questions(reply):
    return [q.strip() for q in re.split(r"(?<=[。！？!?\n])", reply) if q.strip().endswith(("？", "?"))]


def source_titles(sources):
    return {m.group(1) for s in sources if (m := re.match(r"「(.+?)」（", s))}


def mentioned_titles(text):
    return [n for n in re.findall(r"「([^」]+)」", text) if n in TITLES]


SUBJECT_LIKE = re.compile(r"「([^」]{2,30}?(学|工学|演習|実験|実習|概論|特論|技術|論|[ⅠⅡⅢⅣＡＢＣＤABCD]))」")


def fake_subjects(text, student):
    import unicodedata
    norm = lambda x: unicodedata.normalize("NFKC", x)
    titles = [norm(t) for t in TITLES]
    out = []
    for name in {m.group(1) for m in SUBJECT_LIKE.finditer(text)}:
        base = norm(re.sub(r"[ⅠⅡⅢⅣＡＢＣＤABCD・･、\s]+$", "", name))
        if name in student or (len(base) >= 2 and any(t.startswith(base) for t in titles)):
            continue
        out.append(name)
    return out


def analyze(results):
    convs = {k: v for k, v in results.items() if not k.startswith("_")}
    issues, latencies, diag_latencies = [], [], []
    errors = 0
    recommend_ok = recommend_total = 0
    judge_targets = []
    for key, conv in convs.items():
        asked = []
        for i, turn in enumerate(conv["turns"]):
            res = turn["result"]
            latencies.append(turn["seconds"])
            where = f"{key} Q{i + 1}"
            if not res.get("ok"):
                errors += 1
                issues.append((where, "エラー", res.get("error")))
                continue
            reply, sources = res["data"]["reply"], res["data"].get("sources", [])
            given = source_titles(sources)
            outside = [n for n in mentioned_titles(reply) if n not in given]
            if outside:
                issues.append((where, "渡していない授業名", outside))
            fake = fake_subjects(reply, turn["user"])
            if fake:
                issues.append((where, "実在しない授業名", fake))
            if SIMPLIFIED_ONLY & set(reply):
                issues.append((where, "中国語の字", "".join(SIMPLIFIED_ONLY & set(reply))))
            if any(m in reply for m in LEAK_MARKERS):
                issues.append((where, "指示文の漏れ", reply[:80]))
            if RECOMMEND_IN_CHAT.search(reply):
                issues.append((where, "チャットでコースを推薦", reply[:100]))
            if any(w in turn["user"] for w in UNANSWERABLE) and "分かりません" not in reply and "わかりません" not in reply:
                issues.append((where, "分からないはずのことに答えた", reply[:100]))
            qs = questions(reply)
            if any(len(bigrams(q) & bigrams(p)) / max(1, len(bigrams(q) | bigrams(p))) > 0.4 for q in qs for p in asked):
                issues.append((where, "同じ質問の繰り返し", qs))
            asked += qs
            if re.search(r"(先ほど|さきほど).{0,10}(質問|お話)", reply):
                issues.append((where, "「先ほどの質問」", reply[:80]))
            if len(reply) > 300:
                issues.append((where, f"長すぎる返事（{len(reply)}字）", ""))
            if mentioned_titles(reply):
                judge_targets.append((where, turn["user"], reply))

        diag = conv["diagnosis"]
        diag_latencies.append(conv["diagnose_seconds"])
        where = f"{key} 診断"
        if not diag.get("ok"):
            errors += 1
            issues.append((where, "エラー", diag.get("error")))
            continue
        d = diag["data"]
        code = CODE_BY_NAME.get(d["course_name"])
        if conv["expected"] is not None:
            recommend_total += 1
            if code in conv["expected"]:
                recommend_ok += 1
            else:
                issues.append((where, "推薦が想定と違う", f"{d['course_name']}（想定：{conv['expected']}）"))
        for title in mentioned_titles(d["recommendation_text"]):
            chunks = CHUNKS_BY_TITLE.get(title, [])
            if not any(code in c["course_codes"] and not c.get("shared") for c in chunks):
                issues.append((where, "推薦コースの授業ではない", title))
        career = d["career_text"]
        if CAREER_FABRICATION.search(career):
            issues.append((where, "先輩の感想の作り話", CAREER_FABRICATION.search(career).group(0)))
        if fake_subjects(career, ""):
            issues.append((where, "進路紹介で実在しない授業名", fake_subjects(career, "")))
        outside = [n for n in mentioned_titles(career) if n not in source_titles(d.get("sources", []))]
        if outside:
            issues.append((where, "進路紹介で渡していない授業名", outside))
        if len(career) > 350:
            issues.append((where, f"進路紹介が長い（{len(career)}字）", ""))
        if mentioned_titles(career):
            judge_targets.append((where, "（進路紹介）", career))

    return {
        "conversations": len(convs),
        "replies": len(latencies),
        "errors": errors,
        "latency": (statistics.median(latencies), sorted(latencies)[int(len(latencies) * 0.95) - 1], max(latencies)),
        "diagnose_latency": (statistics.median(diag_latencies), max(diag_latencies)),
        "recommend": (recommend_ok, recommend_total),
        "issues": issues,
        "judge_targets": judge_targets,
    }


JUDGE_PROMPT = """あなたは事実確認の担当です。AI の返事のうち、授業について述べている部分が、下のシラバスの記述で裏づけられるかを判定してください。
- シラバスに書かれていない授業の内容・学年・使う道具や言語などを言っていたら、それは「裏づけなし」です。
- 授業以外の話（あいさつ、質問、学生の感想への相づち）は判定しません。
- 言い換えや要約は、意味が同じなら裏づけありとします。

# シラバス
{syllabus}

# AI の返事
{reply}
"""


def judge(targets):
    schema = {"type": "json_schema", "json_schema": {"name": "judge", "strict": True, "schema": {
        "type": "object",
        "properties": {"supported": {"type": "boolean"}, "unsupported_claims": {"type": "array", "items": {"type": "string"}}},
        "required": ["supported", "unsupported_claims"], "additionalProperties": False}}}
    verdicts = []
    for where, user, reply in targets:
        syllabus = "\n\n".join(c["label"] + "／" + c["text"] for t in dict.fromkeys(mentioned_titles(reply)) for c in CHUNKS_BY_TITLE.get(t, []))
        body = {"model": "qwen3.8-27b", "temperature": 0.0, "max_tokens": 400,
                "chat_template_kwargs": {"enable_thinking": False}, "response_format": schema,
                "messages": [{"role": "user", "content": JUDGE_PROMPT.format(syllabus=syllabus, reply=reply)}]}
        req = urllib.request.Request("http://10.201.24.24:8200/v1/chat/completions", data=json.dumps(body).encode(),
                                     headers={"Content-Type": "application/json", "Authorization": "Bearer local-llm-key"})
        content = json.loads(urllib.request.urlopen(req, timeout=120).read())["choices"][0]["message"]["content"]
        verdicts.append({"where": where, "user": user, "reply": reply, **json.loads(content)})
    return verdicts


if __name__ == "__main__":
    results = json.load(open(sys.argv[1]))
    report = analyze(results)
    meta = results.get("_meta", {})
    print(f"会話 {report['conversations']} 件・返事 {report['replies']} 回・エラー {report['errors']} 件（同時 {meta.get('workers')} 人、全体 {meta.get('total_seconds')} 秒）")
    print("返事の時間 中央値 {:.1f}秒 / 95% {:.1f}秒 / 最大 {:.1f}秒".format(*report["latency"]))
    print("診断の時間 中央値 {:.1f}秒 / 最大 {:.1f}秒".format(*report["diagnose_latency"]))
    print("推薦が想定どおり {}/{}".format(*report["recommend"]))
    kinds = {}
    for where, kind, detail in report["issues"]:
        kinds.setdefault(kind, []).append((where, detail))
    for kind, items in kinds.items():
        print(f"\n■ {kind}：{len(items)} 件")
        for where, detail in items[:8]:
            print(f"   {where}: {detail}")
    if "--judge" in sys.argv:
        verdicts = judge(report["judge_targets"])
        json.dump(verdicts, open(sys.argv[sys.argv.index("--judge") + 1], "w"), ensure_ascii=False, indent=1)
        bad = [v for v in verdicts if not v["supported"]]
        print(f"\n■ LLM 判定：授業の説明 {len(verdicts)} 件のうち、裏づけなしの疑い {len(bad)} 件")
        for v in bad:
            print(f"   {v['where']}: {v['unsupported_claims']}")
