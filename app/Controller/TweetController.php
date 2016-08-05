<?php
App::uses('AppController', 'Controller');
App::import('Vendor', 'twitteroauth', array('file' => 'twitteroauth' . DS . 'twitteroauth.php'));

class TweetController extends AppController {

	public $uses = ['Tweet'];

	public function cron_sync() {
		if ($this->request->clientIP() !== Configure::read('global_ip')) {
			throw new ForbiddenException();
		}

		$this->autoRender = false;

		// 対象データ取得
		$conditions = ['user_id' => Configure::read('twitter_id2')];
		if (!isset($this->request->query['type']) || $this->request->query['type'] !== 'all') {
			$conditions['created BETWEEN ? AND ?'] = [date('Y-m-d 00:00:00', strtotime('-7 day')), date('Y-m-d 23:59:59', strtotime('-7 day'))];
		}
		$tweets = $this->Tweet->find('all', ['conditions' => $conditions]);

		$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth2.consumer_key'),
		                                 Configure::read('twitter_oauth2.consumer_secret'),
		                                 Configure::read('twitter_oauth2.oauth_token'),
		                                 Configure::read('twitter_oauth2.oauth_token_secret'));

		foreach ($tweets as $v) {
			try {
				$parameters = ['id' => $v['Tweet']['status_id']];
				$tweet = $twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/show.json',
				                                     'GET',
				                                     $parameters);
				sleep(5);

				$tweet = json_decode($tweet);
				if (isset($tweet->errors)) {
					throw new Exception($tweet->errors[0]->message);
				}

			} catch (Exception $e) {
				$this->Tweet->delete($v['Tweet']['id']);
				$this->log(sprintf('Tweet is deleted(id=%s, text=%s).', $v['Tweet']['id'], $v['Tweet']['text']), 'info');
				continue;
			}

			$data = ['retweet_count' => $tweet->retweet_count,
			         'favorite_count' => $tweet->favorite_count];
			$this->Tweet->id = $v['Tweet']['id'];
			$this->Tweet->save($data);
		}
	}

	public function cron() {
		if ($this->request->clientIP() !== Configure::read('global_ip')) {
			throw new ForbiddenException();
		}

		$this->autoRender = false;

		$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth2.consumer_key'),
		                                 Configure::read('twitter_oauth2.consumer_secret'),
		                                 Configure::read('twitter_oauth2.oauth_token'),
		                                 Configure::read('twitter_oauth2.oauth_token_secret'));

		try {
			$parameters = ['count' => 200,
			               //'max_id' => 686768249619509248,
			               'screen_name' => Configure::read('twitter_screen_name2'),
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

		foreach ($result as $v) {
			$count = $this->Tweet->find('count',
			                            ['conditions' => ['status_id' => $v->id_str]]);
			if ($count === 0) {
				$data = ['status_id' => $v->id_str,
				         'text' => $v->text,
				         'user_id' => $v->user->id_str,
				         'screen_name' => $v->user->screen_name,
				         'in_reply_to_status_id' => $v->in_reply_to_status_id_str,
				         'in_reply_to_user_id' => $v->in_reply_to_user_id_str,
				         'in_reply_to_screen_name' => $v->in_reply_to_screen_name,
				         'created_at' => date('Y-m-d H:i:s', strtotime($v->created_at))];
				$this->Tweet->create();
				$this->Tweet->save($data);
			}
		}

		try {
			$parameters = ['count' => 200];
			$result = $twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/mentions_timeline.json',
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

		foreach ($result as $v) {
			$count = $this->Tweet->find('count',
			                            ['conditions' => ['status_id' => $v->id_str]]);
			if ($count === 0) {
				$data = ['status_id' => $v->id_str,
				         'text' => $v->text,
				         'user_id' => $v->user->id_str,
				         'screen_name' => $v->user->screen_name,
				         'in_reply_to_status_id' => $v->in_reply_to_status_id_str,
				         'in_reply_to_user_id' => $v->in_reply_to_user_id_str,
				         'in_reply_to_screen_name' => $v->in_reply_to_screen_name,
				         'created_at' => date('Y-m-d H:i:s', strtotime($v->created_at))];
				$this->Tweet->create();
				$this->Tweet->save($data);
			}
		}

	}

}
