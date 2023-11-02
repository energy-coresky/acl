<?php

namespace acl;
use SKY, Plan, SQL;

trait common
{
    function cfg() {
        return (object)SKY::$plans['acl']['app']['options'];
    }

    function head_y() {
        $cfg = $this->cfg();
        $this->table = $cfg->tt . '_' . substr(explode('\\', __CLASS__)[1], 2);
        return SQL::open($cfg->connection);
    }
}
