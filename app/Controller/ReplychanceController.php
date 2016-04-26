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

	public function execute() {
		if (!in_array($this->request->clientIP(), Configure::read('exclude_front_ips'))) {
			throw new NotFoundException();
		}

		// 対象データ取得
		$conditions = ['TwitterAccount.status' => 1];
		$twitterAccounts = $this->TwitterAccount->find('all', ['conditions' => $conditions]);

		if (isset($this->request->query['screen_name'])) {
			$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth2.consumer_key'),
			                                 Configure::read('twitter_oauth2.consumer_secret'),
			                                 Configure::read('twitter_oauth2.oauth_token'),
			                                 Configure::read('twitter_oauth2.oauth_token_secret'));

			// Twitterデータ取得
			try {
				$parameters = ['count' => 200,
				               'screen_name' => $this->request->query['screen_name'],
				               'include_rts' => false];
				$result = $twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/user_timeline.json',
				                                      'GET',
				                                      $parameters);

				$result = json_decode($result);
				if (isset($result->errors)) {
					throw new Exception($result->errors[0]->message);
				} else if (!is_array($result)) {
					throw new Exception(sprintf('$result is %s.', gettype($result)));
				}

			} catch (Exception $e) {
				throw new InternalErrorException($e->getMessage());
			}

			$twitterAccountIds = Set::combine($twitterAccounts, '{n}.TwitterAccount.id', '{n}.TwitterAccount.id');
			$datas = [];
			$replyDatas = [];
			foreach ($result as $v) {
				if (!isset($v->in_reply_to_user_id)) {
					$datas[] = ['id' => $v->id_str,
					            'text' => $v->text,
					            'created_at' => $v->created_at];
				}
				if (
				    $v->in_reply_to_user_id === Configure::read('twitter_id2') ||
				    ($this->request->query['screen_name'] === Configure::read('twitter_screen_name2') && in_array($v->in_reply_to_user_id, $twitterAccountIds))
				) {
					$replyDatas[] = ['id' => $v->id_str,
					                 'text' => $v->text,
					                 'created_at' => $v->created_at];
				}
			}

			$this->set('screenName', $this->request->query['screen_name']);
			$this->set('datas', array_slice($datas, 0, 10));
			$this->set('replyDatas', array_slice($replyDatas, 0, 5));
		}

		$this->set('twitterAccounts', $twitterAccounts);
	}

	public function statuses_update() {
		if (!in_array($this->request->clientIP(), Configure::read('exclude_front_ips'))) {
			throw new NotFoundException();
		}

		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

		$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth2.consumer_key'),
		                                 Configure::read('twitter_oauth2.consumer_secret'),
		                                 Configure::read('twitter_oauth2.oauth_token'),
		                                 Configure::read('twitter_oauth2.oauth_token_secret'));

		// Twitter投稿
		try {
			$parameters = ['status' => sprintf('@%s %s', $this->request->data('t_screen_name'), $this->request->data('t_text'))];
			if ($this->request->data('t_id')) {
				$parameters['in_reply_to_status_id'] = $this->request->data('t_id');
			}
			$twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/update.json',
			                            'POST',
			                            $parameters);

			$this->log($parameters, 'info');

		} catch (Exception $e) {
			throw new InternalErrorException($e->getMessage());
		}

		$this->redirect(['controller' => 'replychance', 'action' => 'execute', '?' => ['screen_name' => $this->request->data('t_screen_name')]]);
	}

	public function cron() {
		if ($this->request->clientIP() !== Configure::read('global_ip')) {
			throw new ForbiddenException();
		}

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
				} else if (!is_array($result)) {
					throw new Exception(sprintf('$result is %s.', gettype($result)));
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

				// Twitterデータ取得
				try {
					$parameters = ['id' => $v->in_reply_to_status_id_str];
					$tweet = $twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/show.json',
					                                     'GET',
					                                     $parameters);

					$tweet = json_decode($tweet);
					if (isset($tweet->errors)) {
						throw new Exception($tweet->errors[0]->message);
					}

				} catch (Exception $e) {
					throw new InternalErrorException($e->getMessage());
				}

				$logs[] = sprintf('%s replies to %s(%s) "%s" | "%s" at %s.', $twitterAccount['TwitterAccount']['screen_name'],
				                                                             (isset($v->in_reply_to_screen_name)) ? $v->in_reply_to_screen_name : $v->quoted_status->user->screen_name,
				                                                             $inReplyToUserId,
				                                                             $this->_maskScreenName(str_replace(array("\r\n", "\r", "\n"), ' ', $v->text)),
				                                                             $this->_maskScreenName(str_replace(array("\r\n", "\r", "\n"), ' ', $tweet->text)),
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
