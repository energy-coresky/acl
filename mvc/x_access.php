<?php

namespace acl;
use ACM, SQL;
use function qp;

class t_access extends \Model_t
{
    use common;

    function allow($user, $char, $name) {
        $where = qp('obj=$+ and (pid=$. or uid=$.', $name, $user->pid, $user->id);
        if ($groups = ACM::usrGroups($user->id)) {
            $where->append(' or gid in ($@))', $groups);
        } else {
            $where->append(')');
        }

        foreach ($this->all($where, 'is_deny') as $deny) {

        }
        return 1;
    }

    function page($page = 1) {
        return [
            'query' => $this->all(),
            'row_c' => function ($row) {
                $row->profile = 1;
            },
        ];
    }

    function logging($page = 1) {
        $sql = "select l.*, u.login as user from \$_$this->t_log l left join \$_users u on u.id=l.user_id";
        return [
            'query' => $this->sqlf($sql),
            'row_c' => function ($row) {
                $row->user = $row->user ?? 'Anonymous';
            },
        ];
    }
}
/*
    [id] => "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL
    [obj] => "obj" VARCHAR(55) NOT NULL
    [crud] => "crud" INTEGER NOT NULL
    [obj_id] => "obj_id" INTEGER DEFAULT NULL
    [is_deny] => "is_deny" INTEGER DEFAULT NULL
    [pid] => "pid" INTEGER DEFAULT NULL
    [gid] => "gid" INTEGER DEFAULT NULL
    [uid] => "uid" INTEGER DEFAULT NULL
    [user_id] => "user_id" INTEGER NOT NULL
    [dt_c] => "dt_c" DATETIME NOT NULL
*/
