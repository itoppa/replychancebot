<?php
App::uses('AppController', 'Controller');

class PushnotificationController extends AppController {

	public $uses = ['PushNotification'];

	public function index() {
		// 対象データ取得
		$pushNotifications = $this->PushNotification->find('all', ['order' => ['created DESC'],
		                                                           'limit' => 20]);

		$this->set('pushNotifications', $pushNotifications);
	}

	public function get() {
		// 対象データ取得
		$conditions = ['PushNotification.is_pushed' => 0];
		$pushNotification = $this->PushNotification->find('first', ['conditions' => $conditions,
		                                                            'order' => ['PushNotification.id DESC'],]);

		if ($pushNotification) {
			// プッシュ通知フラグを更新
			$data = ['is_pushed' => 1];
			$this->PushNotification->id = $pushNotification['PushNotification']['id'];
			$this->PushNotification->save($data);

		} else {
			$pushNotification['PushNotification'] = ['title' => 'dummy',
			                                         'body' => 'dummy'];
		}

		$this->viewClass = 'Json';
		$this->set(compact('pushNotification'));
		$this->set('_serialize', 'pushNotification');
	}

}
