<?php

namespace acl;
use ACM, SQL;
use function qp;

class t_access extends \Model_t
{
    use common;

    function allow($x, $name, $user) {
        [$ok] = $this->user($name, $user);
        return 1; # $ok & $x
    }

    function user($name, $user) {
        $where = qp('obj=$+ and (pid=$. or uid=$.', $name, $user->pid, $user->id);
        ($groups = ACM::usrGroups($user->id)) ? $where->append(' or gid in ($@))', $groups) : $where->append(')');
        $ok = $deny = $allow = 0;
        $list = $this->all($where, 'id as q, id, is_deny, crud, uid');
        foreach ($list as $one) {
            if ($one->uid) {
                $one->is_deny ? ($deny = $one) : ($allow = $one);
            } else {
                $ok |= $one->crud;
            }
        }
        $_ok = $ok;
        if ($allow)
            $ok |= $allow->crud;
        if ($deny)
            $ok &= ~$deny->crud;
        return [$ok, $_ok, $deny, $allow];
    }

    function add($crud, $name, $id, $mode = 'u', $deny = 0) {
        global $user;
        $this->insert([
            '+obj' => $name,
            '.crud' => $crud,
            '.is_deny' => $deny,
            ".{$mode}id" => $id,
            '.user_id' => $user->id,
            '!dt_c' => '$now',
        ]);
    }

    function crud($x, $name, $mode) { # sample: 3 acla gid7
        $x = 1 << $x;
        $id = substr($mode, 3);
        $mode = $mode[0]; # u or p or g
        if ('u' == $mode) { # user integrated
            if (!$user = $this->x_user->get_user($id))
                throw new Error('Wrong user id');
            [$ok, $_ok, $deny, $allow] = $this->user($name, $user);
            if ($on = $ok & $x) { # allow change to deny
                if ($allow)
                    $x == $allow->crud ? $this->delete($allow->id) : $this->update(['.crud' => $allow->crud & ~$x], $allow->id);
                if ($on === ($_ok & $x))
                    $deny ? $this->update(['.crud' => $deny->crud | $x], $deny->id) : $this->add($x, $name, $id, 'u', 1);
                $y = '';
            } else { # deny change to allow
                if ($deny)
                    $x == $deny->crud ? $this->delete($deny->id) : $this->update(['.crud' => $deny->crud & ~$x], $deny->id);
                if (!($_ok & $x))
                    $allow ? $this->update(['.crud' => $allow->crud | $x], $allow->id) : $this->add($x, $name, $id);
            }
        } else {
            $row = $this->one(['obj=' => $name, $mode . 'id=' => $id]);
            if (!$row) {
                $this->add($x, $name, $id, $mode);
            } elseif ($x & $row['crud']) {
                $x == $row['crud']
                    ? $this->delete($row['id'])
                    : $this->update(['.crud' => $row['crud'] & ~$x], $row['id']);
                $y = '';
            } else {
                $this->update(['.crud' => $row['crud'] | $x], $row['id']);
            }
        }
        json(['y' => $y ?? 'Y']);
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
