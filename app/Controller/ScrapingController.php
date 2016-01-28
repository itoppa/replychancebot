<?php
App::uses('AppController', 'Controller');
App::import('Vendor', 'twitteroauth', array('file' => 'twitteroauth' . DS . 'twitteroauth.php'));
require_once(ROOT . DS . 'vendor' . DS . 'autoload.php');

use Goutte\Client;

class ScrapingController extends AppController {

	public $uses = ['Scraping', 'ScrapingLog'];

	public function index() {
		// 対象データ取得
		$this->Scraping->bindModel(['hasMany' => ['ScrapingLog' => ['order' => ['ScrapingLog.id DESC'],
		                                                            'limit' => 20]]]);
		$conditions = [];
		if (isset($this->request->query['id'])) {
			$conditions['Scraping.id'] = $this->request->query['id'];
		}
		$scrapings = $this->Scraping->find('all', ['conditions' => $conditions]);

		$this->set('scrapings', $scrapings);
	}

	public function cron() {
		$this->autoRender = false;

		// 対象データ取得
		$scrapings = $this->Scraping->find('all');

		$twitterOAuth = new TwitterOAuth(Configure::read('twitter_oauth.consumer_key'),
		                                 Configure::read('twitter_oauth.consumer_secret'),
		                                 Configure::read('twitter_oauth.oauth_token'),
		                                 Configure::read('twitter_oauth.oauth_token_secret'));

		foreach ($scrapings as $scraping) {
			// スクレイピング実行
			$client = new Client();
			$targetUrl = sprintf('%s://%s%s', $scraping['Scraping']['protocol'], $scraping['Scraping']['domain'], $scraping['Scraping']['target_path']);
			$crawler = $client->request('GET', $targetUrl);
			$hrefs = $crawler->filter($scraping['Scraping']['target_dom'])->each(function($node){
				return $node->attr('href');
			});

			$insertHrefs = [];
			foreach ($hrefs as $href) {
				$count = $this->ScrapingLog->find('count',
				                                  ['conditions' => ['scraping_id' => $scraping['Scraping']['id'],
				                                                    'url' => $href]]);
				if ($count === 0) {
					$insertHrefs[] = $href;
				}
			}

			if (count($insertHrefs) > 0) {
				// Twitter投稿
				try {
					$twitterOAuth->OAuthRequest('https://api.twitter.com/1.1/statuses/update.json',
					                            'POST',
					                            ['status' => sprintf('%s( %s ) is being updated(count=%s).', $scraping['Scraping']['name'], $targetUrl, count($insertHrefs))]);

				} catch (Exception $e) {
					throw new InternalErrorException($e->getMessage());
				}

				// スクレイピングデータを保存
				foreach ($insertHrefs as $href) {
					$data = ['scraping_id' => $scraping['Scraping']['id'],
					         'url' => $href];
					$this->ScrapingLog->create();
					$this->ScrapingLog->save($data);
				}
			}
		}
	}

}
