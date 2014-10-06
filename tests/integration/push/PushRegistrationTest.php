<?php

use Hook\Model\AppKey as AppKey;

class PushRegistrationTest extends HTTP_TestCase
{

    public function testRegistrationFail()
    {
        $this->setKeyType(AppKey::TYPE_SERVER);
        $registration = $this->post('push/registration', array(
            'app_name' => "Testing App",
            'app_version' => "1.0.0",
            'device_id' => "ios-12345",
            'platform' => "ios"
        ));
        $this->assertTrue(is_array($registration) && is_string($registration['error']));
    }

    public function testRegistrationSuccess()
    {
        $this->setKeyType(AppKey::TYPE_DEVICE);
        $registration = $this->post('push/registration', array(
            'app_name' => "Testing App",
            'app_version' => "1.0.0",
            'device_id' => "ios-12345",
            'platform' => "ios"
        ));
        $this->assertTrue(is_array($registration) && is_string($registration['app_name']));

        $registration = $this->post('push/registration', array(
            'app_name' => "Testing App",
            'app_version' => "1.0.0",
            'device_id' => "android-12345",
            'platform' => "android"
        ));
        $this->assertTrue(is_array($registration) && is_string($registration['app_name']));
    }

    public function testCreateMessageFail()
    {
        $this->setKeyType(AppKey::TYPE_BROWSER);
        $message = $this->post('collection/push_messages', array('message' => "Hello!"));
        $this->assertTrue(is_array($message) && isset($message['error']) && is_string($message['error']), "Shouldn't be able to create push message as a BROWSER.");
        $this->setKeyType(AppKey::TYPE_DEVICE);
        $message = $this->post('collection/push_messages', array('message' => "Hello!"));
        $this->assertTrue(is_array($message) && isset($message['error']) && is_string($message['error']), "Shouldn't be able to create push message as a DEVICE.");
    }

    public function testCreateMessageSuccess()
    {
        $this->setKeyType(AppKey::TYPE_SERVER);
        $message = $this->post('collection/push_messages', array('message' => "Hello!"));
        $this->assertTrue(is_array($message) && isset($message['message']) && $message['message'] == "Hello!", "Should be able to create a push message as a SERVER");
    }

    public function testNotify()
    {
        $this->setKeyType(AppKey::TYPE_SERVER);
        $notify = $this->get('push/notify', array('X-Scheduled-Task' => 'yes'));

        $this->assertTrue($notify['push_messages'] === 1);
        $this->assertTrue($notify['devices'] >= 2);
        $this->assertTrue($notify['success'] === 0);
        $this->assertTrue($notify['failure'] === 0);
    }

}
