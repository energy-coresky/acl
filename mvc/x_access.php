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

    function log($page = 1) {
        return [
            'query' => $this->t_log->all(),
            'row_c' => function ($row) {
                $row->profile = 1;
            },
        ];
    }
}
