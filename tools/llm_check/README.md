# LLM の発言の確認

学内 LLM を使って、いろいろな学生（10種類）の会話を流し、AI の発言を自動で調べる。学内ネットワークで、MAMP の Apache が `http://localhost` で SUISEN を出しているときに使う。

```
python3 tools/llm_check/run_conversations.py result.json 2 1      # 10種類×2回、1人ずつ
python3 tools/llm_check/run_conversations.py result.json 2 5      # 5人同時
python3 tools/llm_check/analyze.py result.json --judge judge.json # 集計と、学内 LLM による事実確認
```

調べること：エラー、返事の時間、推薦が想定どおりか、実在しない授業名、検索で渡していない授業名、同じ質問の繰り返し、指示文の漏れ、チャットでのコース推薦、分からないはずのことへの回答、先輩の感想の作り話。

- 自動チェックと LLM の判定は、正しい返事も「疑い」として出すことがある。疑いは必ずシラバス（`data/search_index.json`）で確かめる。
- テストで作った利用者は `bigtest.` で始まるメールアドレスになる。終わったら DB から消す。
