<?php
App::uses('AppController', 'Controller');
App::import('Vendor', 'twitteroauth', array('file' => 'twitteroauth' . DS . 'twitteroauth.php'));

class TwitterfollowController extends AppController {

	public $uses = ['TwitterAccount', 'TwitterFollow'];

	public function cron() {
		$this->autoRender = false;

		// 対象データ取得
		$conditions = ['TwitterAccount.status' => 1];
		if (isset($this->request->query['screen_name'])) {
			$conditions['TwitterAccount.screen_name'] = $this->request->query['screen_name'];
		}
		$twitterAccounts = $this->TwitterAccount->find('all', ['conditions' => $conditions]);

		$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth.consumer_key'),
		                                 Configure::read('twitter_oauth.consumer_secret'),
		                                 Configure::read('twitter_oauth.oauth_token'),
		                                 Configure::read('twitter_oauth.oauth_token_secret'));

		foreach ($twitterAccounts as $twitterAccount) {
			$twitterAccountId = $twitterAccount['TwitterAccount']['id'];

			// Twitterデータ取得
			try {
				$parameters = ['user_id' => $twitterAccountId];
				$result = $twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/friends/ids.json',
				                                      'GET',
				                                      $parameters);

				$result = json_decode($result);
				if (isset($result->errors)) {
					throw new Exception($result->errors[0]->message);
				}

			} catch (Exception $e) {
				throw new InternalErrorException($e->getMessage());
			}

			$twitterFollows = $result->ids;
			$databaseFollows = Set::combine($this->TwitterFollow->find('all',
			                                                           ['conditions' => ['from_twitter_account_id' => $twitterAccountId]]),
			                                '{n}.TwitterFollow.id',
			                                '{n}.TwitterFollow.to_twitter_account_id');
			$createFollows = array_diff($twitterFollows, $databaseFollows);
			$deleteFollows = array_diff($databaseFollows, $twitterFollows);

			// フォローデータを保存
			foreach ($createFollows as $v) {
				$data = ['from_twitter_account_id' => $twitterAccountId,
				         'to_twitter_account_id' => $v];
				$this->TwitterFollow->create();
				$this->TwitterFollow->save($data);
			}

			// フォローデータを削除
			foreach ($deleteFollows as $v) {
				$conditions = ['from_twitter_account_id' => $twitterAccountId,
				               'to_twitter_account_id' => $v];
				$this->TwitterFollow->deleteAll($conditions);
			}
		}

	}

}
