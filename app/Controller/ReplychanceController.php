<?php
App::uses('AppController', 'Controller');
App::import('Vendor', 'twitteroauth', array('file' => 'twitteroauth' . DS . 'twitteroauth.php'));

class ReplychanceController extends AppController {

	public $uses = ['TwitterAccount', 'TwitterFollow', 'ReplyChance', 'ReplyChanceLog', 'PushNotification'];

	public function index() {
		// 対象データ取得
		$conditions = [];
		if (isset($this->request->query['count']) && preg_match('/^\d+$/', $this->request->query['count'])) {
			$conditions['ReplyChanceLog.count >'] = $this->request->query['count'];
		}
		$this->TwitterAccount->bindModel(['hasMany' => ['ReplyChanceLog' => ['conditions' => $conditions,
		                                                                     'order' => ['ReplyChanceLog.id DESC'],
		                                                                     'limit' => 20]]]);
		$conditions = ['TwitterAccount.status' => 1];
		if (isset($this->request->query['screen_name'])) {
			$conditions['TwitterAccount.screen_name'] = $this->request->query['screen_name'];
		}
		$twitterAccounts = $this->TwitterAccount->find('all', ['conditions' => $conditions]);

		$this->set('twitterAccounts', $twitterAccounts);
	}

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
				sleep(1);

				$result = json_decode($result);
				if (isset($result->errors)) {
					throw new Exception($result->errors[0]->message);
				}

			} catch (Exception $e) {
				throw new InternalErrorException($e->getMessage());
			}

			$logs = [];

			// 指定の期間内のリプライ数をカウント
			$count = 0;
			$start = strtotime(date('Y-m-d H:i:00', strtotime(sprintf('-%d minutes', $twitterAccount['ReplyChance']['term']))));
			$end = strtotime(date('Y-m-d H:i:59', strtotime('-1 minutes')));
			$toTwitterAccountIds = Set::combine($this->TwitterFollow->find('all',
			                                                               ['conditions' => ['from_twitter_account_id' => $twitterAccount['TwitterAccount']['id']]]),
			                                    '{n}.TwitterFollow.id',
			                                    '{n}.TwitterFollow.to_twitter_account_id');
			foreach ($result as $v) {
				// 通常/引用リプライから対象ユーザIDを取得
				$inReplyToUserId = 0;
				if (isset($v->in_reply_to_user_id)) {
					$inReplyToUserId = $v->in_reply_to_user_id;
				} else if (isset($v->quoted_status->user->id)) {
					$inReplyToUserId = $v->quoted_status->user->id;
				}

				// リラプイではない場合
				// 自分自身のリプライの場合
				// フォロー内のリプライの場合
				if ($inReplyToUserId === 0 ||
				    $inReplyToUserId == $twitterAccount['TwitterAccount']['id'] ||
				    in_array($inReplyToUserId, $toTwitterAccountIds)
				) {
					continue;
				}

				$now = strtotime($v->created_at);
				if (!($start <= $now && $now <= $end)) {
					break;
				}
				$count++;

				$logs[] = sprintf('%s replies to %s(%s) "%s" at %s.', $twitterAccount['TwitterAccount']['screen_name'],
				                                                 (isset($v->in_reply_to_screen_name)) ? $v->in_reply_to_screen_name : $v->quoted_status->user->screen_name,
				                                                 $inReplyToUserId,
				                                                 $this->_maskScreenName($v->text),
				                                                 date('Y-m-d H:i:s', strtotime($v->created_at)));
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
				// リプライチャンスのプッシュ通知データを保存
				$data = ['title' => 'リプライチャンス',
				         'body' => sprintf('%sはリプライチャンス中です。', $twitterAccount['TwitterAccount']['screen_name'])];
				$this->PushNotification->create();
				$this->PushNotification->save($data);
				sleep(1);

				// Google Cloud Messaging
				$shell = APP . 'webroot' . DS . 'files' . DS . 'replychance_cron.sh';
				if (file_exists($shell)) {
					exec('bash ' . $shell);
				}

				// Twitter投稿
				try {
					$twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/update.json',
					                            'POST',
					                            ['status' => sprintf('%sはリプライチャンス中です。 %s', $twitterAccount['TwitterAccount']['screen_name'], CakeText::uuid())]);

				} catch (Exception $e) {
					throw new InternalErrorException($e->getMessage());
				}

				// リプライチャンスの日時を更新
				$data = ['latest_datetime' => date('Y-m-d H:i:00')];
				$this->ReplyChance->id = $twitterAccount['ReplyChance']['id'];
				$this->ReplyChance->save($data);

				$logs[] = sprintf('%s is being reply chance.', $twitterAccount['TwitterAccount']['screen_name']);
			}

			if (count($logs) > 0) {
				$logs = array_reverse($logs);
				foreach ($logs as $log) {
					$this->log($log, 'debug');
				}
			}
		}

	}

	private function _maskScreenName($text) {
		if (preg_match_all('/@\w+/', $text, $matches) > 0) {
			foreach ($matches[0] as $match) {
				$text = preg_replace('/'.$match.'/', str_pad('', (strlen($match)-1), '*'), $text);
			}
		}
		return $text;
	}

}
