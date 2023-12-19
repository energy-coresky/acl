<?php

class ACM extends Model_t # Access control manager
{
    use acl\common;

    static $usrStates = [
        'ini' => 'Pre-registration passed',
        //2do: 'tel' => 'Phone code sent',
        'act' => 'Active User',
        //2do: 'acf' => 'Active 2 Factor User',
        'del' => 'User Deleted',
        'blk' => 'User Locked',
    ];
    static $byId;
    static $cr = ['C' => 1, 'R' => 2, 'U' => 4, 'D' => 8, 'X' => 16];

    static function instance() {
        static $acm;
        return $acm ?? ($acm = new self);
    }

    static function init($byId = []) {
        self::$byId = $byId;
        $acm = self::instance();
        $acm->cfg()->pap or SKY::$profiles = Plan::set('acl', fn() => $acm->x_user->profiles(false));
    }

    static function logging($desc) {
        Plan::set('acl', fn() => self::instance()->log($desc));
    }

    static function __callStatic($name, $args) {
        if (!isset(self::$cr[$name[0]]))
            throw new Error("Wrong char `$name[0]`");

        return Plan::set('acl', function () use ($name, $args) {
            global $user;
            return $user->pid < 2
                ? (bool)$user->pid
                : self::instance()->x_access->allow($user, self::$cr[$name[0]], substr($name, 1), $args[0] ?? 0);
        });
    }

    static function usrGroups($user_id, $with_names = false) {
        static $cache = [];

        isset($cache[$user_id]) or
            $cache[$user_id] = Plan::set('acl', fn() => self::instance()->all(['user_id=' => $user_id], 'grp_id, ""'));

        $p =& $cache[$user_id];
        if (!$with_names || !$p || pos($p) !== '')
            return $with_names ? $p : array_keys($p);

        $select = '@select id, name from $_ where is_grp=1 and id in (%s)';
        return $p = Plan::set('acl', fn() => self::instance()->x_user->sqlf($select, array_keys($p)));
    }

    #static function access($obj, $obj_id) {
    #    $acm = self::instance();
    #    $acm->x_object->add($obj, $obj_id, $desc);
    #}
}
