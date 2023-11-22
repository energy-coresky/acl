<?php

namespace acl;
use SKY, ACM;
use function qp;

class t_access extends \Model_t
{
    use common;

    function allow($user, $x, $name, $obj_id) {
        static $cache = [];
        if (isset($cache[$name][$obj_id])) {
            $ok = $cache[$name][$obj_id];
            if (SKY::$debug > 1)
                \trace(array_flip(ACM::$cr)[$x] . $name, $ok & $x ? 'ACL ALLOW' : 'ACL DENY');
        } else {
            [$ok] = $this->user($name, $user, $obj_id);
            $cache[$name][$obj_id] = $ok;
            if (SKY::$debug)
                \trace(array_flip(ACM::$cr)[$x] . $name, $ok & $x ? 'ACL ALLOW' : 'ACL DENY');
        }
        return $ok & $x;
    }

    function user($name, $user, $obj_id) { # 2do: $obj_id
        $where = qp('obj=$+ and (pid=$. or uid=$.', $name, $user->pid, $user->id);
        ($groups = ACM::usrGroups($user->id)) ? $where->append(' or gid in ($@))', $groups) : $where->append(')');
        $obj_id ? $where->append(' and obj_id in (0, $.)', $obj_id) : $where->append(' and obj_id=0');
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
        $obj_id = 0;
        if ('u' == $mode) { # user integrated
            if (!ACM::Xaclu())
                return json(['y' => 'X']);
            $user = $this->x_user->get_user($id);
            [$ok, $_ok, $deny, $allow] = $this->user($name, $user, $obj_id);
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
            if ('p' == $mode && !ACM::Xaclp() || 'g' == $mode && !ACM::Xaclg())
                return json(['y' => 'X']);
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
        $sql = "select l.*, u.login as user from \$_$this->t_log l left join \$_users u on u.id=l.user_id order by id desc";
        return [
            'query' => $this->sqlf($sql),
            'row_c' => function ($row) {
                $row->user = $row->user ?? 'Anonymous';
            },
        ];
    }
}
