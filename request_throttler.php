<?php

Cache::config('throttle', array('engine' => 'File', 'duration' => '+2 hours', 'path' => CACHE . 'throttle' . DS, 'prefix' => false));

class RequestThrottlerComponent extends Object {
	
	public $components = array('Auth', 'RequestHandler', 'Session', 'Recaptcha.Recaptcha');
	
	protected $controller = null;
	
	public $actions = array();
	
	public $limit = array('requests' => 5, 'time' => 3600);
	
	public $captchaAction = array('controller' => 'users', 'action' => 'captcha');
	
	protected $cacheKey = null;
	
	public function initialize(&$controller, $actions = array()) {
		$this->controller = $controller;
		$this->actions = $actions;
		$this->cacheKey = 'user_' . $this->Auth->user('id');

		if (in_array(strtolower($this->controller->action), $this->actions)) {
			if ($this->Session->read('Throttle.verified')) {
				return $this->restoreRequest();
			}
			$this->throttle();
		}
	}
	
	public function startup() {
		if ($this->isCaptchaAction() && $this->RequestHandler->isPost()) {
			$this->verifyCaptcha();
		}
	}
	
	protected function isCaptchaAction() {
		return strtolower($this->controller->name) == $this->captchaAction['controller'] && strtolower($this->controller->action) == $this->captchaAction['action'];
	}
	
	public function throttle() {
		if ($this->RequestHandler->isGet()) {
			return true;
		}
		
		$activity = Cache::read($this->cacheKey, 'throttle');
		if (!$activity) {
			$activity = array();
		}
		$activity[] = time();
		
		if (count($activity) <= $this->limit['requests']) {
			Cache::write($this->cacheKey, $activity, 'throttle');
			return true;
		}

		$oldestActivity = array_shift($activity);
		Cache::write($this->cacheKey, $activity, 'throttle');
		
		if ($oldestActivity > time() - $this->limit['time']) {
			$this->verifyHuman();
		}
	}

	protected function verifyHuman() {
		$redirects = $this->Session->read('Throttle.redirects');
		if (!$redirects) {
			$redirects = array();
		}
		
		$token = String::uuid();
		$redirects[$token] = array(
			'url'        => $this->controller->params['url']['url'],
			'data'       => $this->controller->data,
			'controller' => $this->controller->name,
			'action'     => $this->controller->action
		);
		
		$this->Session->write('Throttle.redirects', $redirects);
		$this->controller->redirect(array_merge($this->captchaAction, array($token)));
	}
	
	protected function verifyCaptcha() {
		if (!$this->Recaptcha->verify() || !$this->controller->data['Verification']['token']) {
			$this->Session->setFlash($this->Recaptcha->error);
			return false;
		}
		
		$token = $this->controller->data['Verification']['token'];
		$data = $this->Session->read("Throttle.redirects.$token");
		if (!$data) {
			$this->controller->cakeError('error500');
		}
		
		$this->Session->write('Throttle.verified', $token);
		$this->controller->redirect('/' . $data['url']);
	}
	
	protected function restoreRequest() {
		$token = $this->Session->read('Throttle.verified');
		$data = $this->Session->read("Throttle.redirects.$token");
		if (!$token || !$data || $data['controller'] != $this->controller->name || $data['action'] != $this->controller->action) {
			$this->controller->cakeError('error500');
		}
		
		$this->controller->data = $data['data'];
		$this->Session->delete('Throttle.verified');
		$this->Session->delete("Throttle.redirects.$token");
		Cache::delete($this->cacheKey, 'throttle');
	}
	
}
