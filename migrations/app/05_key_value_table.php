<?php

return array('key_values' => function ($t) {
    $t->increments('_id');
    $t->string('name');
    $t->string('value');

    // timestamps
    $t->softDeletes();
    $t->timestamps();
});
