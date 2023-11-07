<?php

class Acl extends Console
{
    function __construct($argv = [], $found = []) {
        Plan::$ware = 'acl';
        parent::__construct($argv, $found);
        Plan::$ware = 'main';
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
