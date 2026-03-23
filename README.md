# アマチュア無線の国家試験対策
練習問題を表示し、合否判定などを行います。

2025年に作ったWEBアプリで、問題データセットにTSVファイルを使用していましたが、このリポジトリでSQLiteに移行します。

```
├── README.md
├── gaku-ura (ここに無いファイルはgaku-ura libから入手して結合)
│   ├── data
│   │   └── practice
│   │       ├── css
│   │       │   └── index.css
│   │       ├── html
│   │       │   ├── index.html
│   │       │   ├── morse.html
│   │       │   └── normal.html
│   │       └── problem
│   │           ├── problem.db (自分で作成してください)
│   │           └── description.txt
│   └── main
│       └── practice.php (☆)
└── practice
    └── index.php (☆を呼び出す)
```

## SQLite移行の手順
1. 學裏ライブラリの管理機能から「gaku-ura/data/practice/problem」ディレクトリに入る
2. 「SQLite DB」を選択して名前欄にproblem.dbと入力して作成
3. 「インポート」でTSVまたはCSVファイルを選択して保存を押す
4. テーブルが作成されていれば完成
5. 必要に応じて列名を変更

## table構造
table名が「A_」で始まるのが法規問題、「B_」で始まるのが工学問題。「_」の次に数値が入る。
```
id question(問題文) good(答え) bad1(不正解選択肢1) bad2(不正解選択肢2) bad3(不正解選択肢3) img(画像ファイル名) description(解説)
...問題数だけ行が続く...
```

## 使用ライブラリ
このリポジトリとは別に用意が必要です。

日本無線協会 CBT方式の国家試験の例題 https://www.nichimu.or.jp/kshiken/siken/vcmsFolder_856/vcms_856.html

gaku-ura lib: https://github.com/satuki-k/gaku-ura_web_tool
