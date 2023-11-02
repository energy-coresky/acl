<?php

class ACM # Access control manager
{
    static function cfg() {
        return (object)SKY::$plans['acl']['app']['options'];
    }

    static function model($tbl) {
        $prev = Plan::set('acl');
        $name = 'x_' . self::cfg()->tt . '_' . $tbl;
        $model = MVC::$mc->$name;
        Plan::$ware = $prev;
        return $model;
    }
}
