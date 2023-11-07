<?php

namespace acl;
use SKY, Plan, SQL;

trait common
{
    function cfg() {
        return (object)SKY::$plans['acl']['app']['options'];
    }

    function __get($name) {
        $log = 't_log' == $name;
        if (!$log && 't_user2grp' != $name)
            return parent::__get($name);
        return new \Model_t($this->cfg()->tt . ($log ? '_log' : '_user2grp'));
    }

    function log($desc) {
        global $user;
        $this->t_log->insert([
            'user_id' => (int)$user->id,
            'comment' => $desc,
            '!dt' => '$now',
        ]);
    }

    function head_y() {
        $cfg = $this->cfg();
        $table = 'ACM' == __CLASS__ ? 'user2grp' : substr(explode('\\', __CLASS__)[1], 2);
        $this->table = $cfg->tt . '_' . $table;
        return SQL::open($cfg->connection);
    }
}
