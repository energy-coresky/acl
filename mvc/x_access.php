<?php

namespace acl;
use ACM, SQL;
use function qp;

class t_access extends \Model_t
{
    use common;

    function page($page = 1) {
        return [
            'query' => $this->all(),
            'row_c' => function ($row) {
                $row->profile = 1;
            },
        ];
    }

    function listing($page = 1) {
        $sql = "select l.*, u.login as user from $this->t_log l left join \$_users u on u.id=l.user_id";
        return [
            'query' => $this->sqlf($sql),
            'row_c' => function ($row) {
                $row->user = $row->user ?? 'Anonymous';
            },
        ];
    }
}
