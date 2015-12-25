# リプライチャンスbot

## リプライチャンスとは



## 使用シーン

任意の女性声優さんのTwitterにて不定期でファンに向けてリプライをするときに、いち早く知ってリプライを貰いたい。

## 仕様



## 開発環境構築

**事前にインストールが必要なもの**

* Apache等のWebサーバ
* MySQL等のデータベースサーバ
* PHP >= 5.3.0
  
**本アプリケーションのバージョン**

* CakePHP 2.7.8
  
**インストール手順**

git clone https://github.com/itoppa/replychancebot.git  
cd replychancebot  
chmod -R 777 app/tmp  
mv app/Config/core.php.default app/Config/core.php  
mv app/Config/database.php.default app/Config/database.php  
vim app/Config/core.php  
vim app/Config/database.php  
mysql -u XXX -h XXX -p XXX < app/sql/create_table.sql  
  
「core.php」「database.php」は以下URLを参考に設定してください。

* <http://book.cakephp.org/2.0/ja/getting-started.html>
* <https://dev.twitter.com/oauth>
  
また「core.php」に以下の通りTwitter OAuthを設定してください。  
  
> Configure::write('twitter_oauth', ['consumer_key' => 'XXX',  
>                                    'consumer_secret' => 'XXX',  
>                                    'oauth_token' => 'XXX',  
>                                    'oauth_token_secret' => 'XXX']);  

## 保留事項

* Twitter APIの仕様に依存します。特にAPIコール回数には注意してください。

