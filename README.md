# RequestThrottlerComponent for CakePHP

* Version: 0.0.1 (i.e. "experimental stage"!)
* Author: David Zentgraf (deceze@gmail.com)
* URL: https://github.com/deceze/CakePHP-RequestThrottler-Component

The RequestThrottlerComponent throttles user actions to a defined number of actions per defined period of time.
Upon reaching this limit, the user will need to verify his humanness by solving a Captcha.

## Requirements

* CakeDC recaptcha plugin (https://github.com/CakeDC/recaptcha)
* An action and corresponding view that display a Captcha, by default `UsersController::captcha`
* Assumes standard AuthComponent setup and requires a working session
* PHP 5+

## TODO

* Plugin-ize, bundling UsersController::captcha action and view
* Different actions upon reaching limit
* Different time calculation algorithms
* Make all options configurable from the controller and/or beforeFilter

## Usage

Include in controller with settings array of actions to protect:

    public $components = array('RequestThrottler' => array('edit', 'add'));

Example `UsersController::captcha` action and view:

    public function captcha($token) {
        if (!$token) {
            $this->cakeError('error404');
        }
        $this->set(compact('token'));
    }

    <?php echo $this->Form->create('Verification', array('url' => array('controller' => 'users', 'action' => 'captcha', $token))); ?>
    <?php echo $this->Recaptcha->display(); ?>
    <?php echo $this->Form->hidden('token', array('value' => $token)); ?>
    <?php echo $this->Form->end(__('I am human', true)); ?>
