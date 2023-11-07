<?php

class ACM extends MVC_BASE # Access control manager
{
    static function cfg() {
        return (object)SKY::$plans['acl']['app']['options'];
    }

    static function __callStatic($name, $args) {
        global $user;
        static $acm;
        isset($acm) or $acm = new self;

        $prev = Plan::set('acl');
        if ($user->pid < 2) # root
            return (bool)$user->pid;
        $result = $acm->x_access->allow($user, $name[0], substr($name, 1));
        Plan::$ware = $prev;
        return $result;
    }

    static function model($tbl) {
        $prev = Plan::set('acl');
        $name = 'x_' . self::cfg()->tt . '_' . $tbl;
        $model = MVC::$mc->$name;
        Plan::$ware = $prev;
        return $model;
    }
}
