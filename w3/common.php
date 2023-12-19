<?php

namespace acl;
use SKY, SQL, common_c;

trait common
{
    protected $ext;
    protected $log;
    protected $ipp;
    protected $x0;

    function head_y() {
        $cfg = $this->cfg();
        $table = 'ACM' == __CLASS__ ? 'user2grp' : substr(explode('\\', __CLASS__)[1], 2);
        $this->table = $cfg->tt . '_' . $table;
        $this->ext = $cfg->ext;
        $this->log = $cfg->log;
        $this->ipp = $this->x0 = $cfg->ipp;
        return SQL::open($cfg->connection);
    }

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

    function page($cnt, $v) {
        $page = \pagination($this->x0, $cnt, 'p', $v);
        return false !== common_c::$page ? false : $page;
    }
}
