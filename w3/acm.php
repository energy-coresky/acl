<?php

class ACM extends Model_t # Access control manager
{
    use acl\common;

    static $user_states = [
        'ini' => 'Pre-registration passed',
        //2do: 'tel' => 'Phone code sent',
        'act' => 'Active User',
        //2do: 'acf' => 'Active 2 Factor User',
        'del' => 'User Deleted',
        'blk' => 'User Locked',
    ];

    static function instance() {
        static $acm;
        return $acm ?? ($acm = new self);
    }

    static function logging($desc) {
        $acm = self::instance();
        $acm->log($desc);
    }

    static function __callStatic($name, $args) {
        return Plan::set('acl', function () use ($name) {
            global $user;
            $cr = ['C' => 1, 'R' => 2, 'U' => 4, 'D' => 8, 'X' => 16];
            if (!isset($cr[$name[0]]))
                throw new Error('Wrong char');
            $acm = self::instance();
            return $user->pid < 2
                ? (bool)$user->pid
                : $acm->x_access->allow($cr[$name[0]], substr($name, 1), $user);
        });
    }

    static function usrStates($id) {
        $acm = self::instance();
    }

    static function usrProfiles($id = null) {
        static $profiles;
        if (null === $profiles) {
            $acm = self::instance();
            $profiles = $acm->x_user->sqlf('@select id, name from $_ where is_grp=0');
        }
        $out = $profiles;
        if (is_int($id))
            unset($out[$id]);
        return $out;
    }

    static function usrGroups($id, $new_grp_id = false) {
        static $user_id, $groups;
        if ($new_grp_id)
            return is_null($groups) ? null : array_merge($groups, [$new_grp_id]);
        if ($id === $user_id)
            return $groups;
        $acm = self::instance();
        return $groups = $acm->all(['user_id=' => $user_id = $id], 'grp_id');
    }
}
