<?php

class Acl extends Console
{
    function __construct($argv = [], $found = []) {
        Plan::$ware = 'acl';
        parent::__construct($argv, $found);
        Plan::$ware = 'main';
    }

    /** ACL test */
    function a_t() {
        print_r(1);
    }
}
