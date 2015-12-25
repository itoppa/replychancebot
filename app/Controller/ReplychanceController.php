<?php
App::uses('AppController', 'Controller');
App::import('Vendor', 'twitteroauth', array('file' => 'twitteroauth' . DS . 'twitteroauth.php'));

class ReplychanceController extends AppController {

	public $uses = ['TwitterAccount', 'TwitterFollow', 'ReplyChance', 'ReplyChanceLog'];

	public function cron() {
		$this->autoRender = false;

		// 対象データ取得
		$this->TwitterAccount->bindModel(['hasOne' => ['ReplyChance' => ['conditions' => ['ReplyChance.status' => 1]]]]);
		$twitterAccounts = $this->TwitterAccount->find('all', ['conditions' => ['TwitterAccount.status' => 1]]);

		$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth.consumer_key'),
		                                 Configure::read('twitter_oauth.consumer_secret'),
		                                 Configure::read('twitter_oauth.oauth_token'),
		                                 Configure::read('twitter_oauth.oauth_token_secret'));

		foreach ($twitterAccounts as $twitterAccount) {
			// 指定の期間ごとに実行
			if (((int)date('i') % $twitterAccount['ReplyChance']['term']) !== 0) {
				continue;
			}

			// Twitterデータ取得
			try {
				$parameters = ['count' => 200,
				               'screen_name' => $twitterAccount['TwitterAccount']['screen_name'],
				               'include_rts' => false];
				$result = $twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/user_timeline.json',
				                                      'GET',
				                                      $parameters);

				$result = json_decode($result);
				if (isset($result->errors)) {
					throw new Exception($result->errors[0]->message);
				}

			} catch (Exception $e) {
				throw new InternalErrorException($e->getMessage());
			}

			// 指定の期間内のリプライ数をカウント
			$count = 0;
			$start = strtotime(date('Y-m-d H:i:00', strtotime(sprintf('-%d minutes', $twitterAccount['ReplyChance']['term']))));
			$end = strtotime(date('Y-m-d H:i:59', strtotime('-1 minutes')));
			$toTwitterAccountIds = Set::combine($this->TwitterFollow->find('all', ['conditions' => ['from_twitter_account_id' => $twitterAccount['TwitterAccount']['id']]]), '{n}.TwitterFollow.id', '{n}.TwitterFollow.to_twitter_account_id');
			foreach ($result as $v) {
				// リラプイではない場合
				// フォロー内のリプライの場合
				if (!isset($v->in_reply_to_user_id) || in_array($v->in_reply_to_user_id, $toTwitterAccountIds)) {
					continue;
				}

				$now = strtotime($v->created_at);
				if (!($start <= $now && $now <= $end)) {
					break;
				}
				$count++;
			}

			// 指定の期間ごとのリプライ数を保存
			$data = ['twitter_account_id' => $twitterAccount['TwitterAccount']['id'],
			         'start_datetime' => date('Y-m-d H:i:s', $start),
			         'end_datetime' => date('Y-m-d H:i:s', $end),
			         'count' => $count];
			$this->ReplyChanceLog->create();
			$this->ReplyChanceLog->save($data);

			// 前回のリプライチャンスから1時間以上の場合
			// 指定の期間内のリプライ数が指定のカウント以上の場合
			if (
				3600 <= (strtotime('now') - strtotime($twitterAccount['ReplyChance']['latest_datetime'])) &&
				$twitterAccount['ReplyChance']['count'] <= $count
			) {
				// Twitter投稿
/*
				$twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/update.json',
				                            'POST',
				                            ['status' => sprintf('%sはリプライチャンス中です。', $twitterAccount['TwitterAccount']['screen_name'])]);
*/

				// リプライチャンスの日時を更新
				$data = ['latest_datetime' => date('Y-m-d H:i:00')];
				$this->ReplyChance->id = $twitterAccount['ReplyChance']['id'];
				$this->ReplyChance->save($data);

				$this->log(sprintf('%s is being reply chance.', $twitterAccount['TwitterAccount']['screen_name']), 'debug');
			}
		}

	}

}
