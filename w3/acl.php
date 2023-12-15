<?php

class Acl extends Console
{
    function __construct($argv = [], $found = []) {
        Plan::set('acl', fn() => parent::__construct($argv, $found));
    }

    /** Lock user */
    function a_lock() {
        echo '2do';
    }

    /** ACL test */
    function a_t() {
        MVC::$cc = new common_c;
        echo (string)MVC::$cc->x_object;
    }
}
