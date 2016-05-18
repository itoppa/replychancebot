<?php
class KosatsuController extends AppController {

	public $uses = ['KosatsuReservation'];

	public function cron() {
		if ($this->request->clientIP() !== Configure::read('global_ip')) {
			throw new ForbiddenException();
		}

		$this->autoRender = false;

		$kosatsuReservations = $this->KosatsuReservation->find('all', ['conditions' => ['is_reserved' => 0,
		                                                                                'start_datetime <=' => date('Y-m-d H:i:s')]]);

		foreach ($kosatsuReservations as $kosatsuReservation) {
			$this->log(sprintf('kosatsu/cron start(controller=%s, action=%s)', $kosatsuReservation['KosatsuReservation']['controller'], $kosatsuReservation['KosatsuReservation']['action']), 'info');

			$this->requestAction(['controller' => $kosatsuReservation['KosatsuReservation']['controller'],
			                      'action' => $kosatsuReservation['KosatsuReservation']['action']]);

			// 個撮予約のステータスを更新
			$data = ['is_reserved' => 1];
			$this->KosatsuReservation->id = $kosatsuReservation['KosatsuReservation']['id'];
			$this->KosatsuReservation->save($data);

			$this->log(sprintf('kosatsu/cron end(controller=%s, action=%s)', $kosatsuReservation['KosatsuReservation']['controller'], $kosatsuReservation['KosatsuReservation']['action']), 'info');
		}
	}

}
