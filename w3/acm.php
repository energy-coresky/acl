<?php

class ACM extends Model_t # Access control manager
{
    use acl\common;

    static function instance() {
        static $acm;
        return $acm ?? ($acm = new self);
    }

    static function logging($desc) {
        $acm = self::instance();
        $acm->log($desc);
    }

    static function __callStatic($name, $args) {
        global $user;
        $prev = Plan::set('acl');
        $acm = self::instance();
        $bool = $user->pid < 2
            ? (bool)$user->pid
            : $acm->x_access->allow($user, $name[0], substr($name, 1), $acm->groups());
        Plan::$ware = $prev;
        return $bool;
    }

    function groups() {
        global $user;
        return $this->all(['user_id=' => $user->id], 'grp_id');
    }
}
