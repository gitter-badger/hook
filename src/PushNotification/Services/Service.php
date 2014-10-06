<?php
namespace Hook\PushNotification\Services;

interface Service
{
    /**
     * push
     * @param mixed $registrations
     * @param mixed $data
     */
    public function push($registrations, $data);

}
