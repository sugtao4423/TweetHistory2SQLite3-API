# TweetHistory2SQLite3-API
[TweetHistory2SQLite3](https://github.com/sugtao4423/TweetHistory2SQLite3)で作ったSQLite3のDBからJSONを出力する

TwitterのAPIから得られるJSONとほとんど同じようなものを出力。

## ファイルツリー
```
├── api
│   ├── README.md
│   └── api.php
└── TweetHistory2SQLite3
    ├── README.md
    ├── script/
    ├── tweets.sqlite3
    └── twitterdata/
```

## api.php
### GET Params
`t`: 取得するもの  
* `lasttweet`: 最新50件のツイート (デフォルト)

`p`: ページ  
`c`: 取得件数 / ページ

## ツイート検索機能など随時追加予定
