<?php
namespace Hook\Storage\Providers;

class AmazonS3 extends Base
{
    public function upload($file, $options=array())
    {
        return false;
    }

}
