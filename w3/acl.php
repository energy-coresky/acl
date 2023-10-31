<?php

class ACL
{
    static $image = [
    ];

    static function cfg() {
        return (object)\SKY::$plans['acl']['app']['options'];
    }

    static function model() {
        $prev = Plan::set('acl');
        $model = \MVC::$mc->x_able;
        Plan::$ware = $prev;
        return $model;
    }
}
