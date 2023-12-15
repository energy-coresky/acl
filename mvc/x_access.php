<?php

namespace acl;
use SKY, SQL, ACM;
use function qp, trace;

class t_access extends \Model_t
{
    use common;

    function allow($user, $x, $name, $obj_id) {
        static $cache = [];

        if (isset($cache[$name][$obj_id])) {
            $ok = $cache[$name][$obj_id];
            if (SKY::$debug > 1)
                trace(array_flip(ACM::$cr)[$x] . $name, $ok & $x ? 'ACL ALLOW' : 'ACL DENY');
        } else {
            [$ok] = $this->aggregate($user, $name, $obj_id);
            $cache[$name][$obj_id] = $ok;
            if (SKY::$debug)
                trace(array_flip(ACM::$cr)[$x] . $name, $ok & $x ? 'ACL ALLOW' : 'ACL DENY');
        }
        return $ok & $x;
    }

    function aggregate($at, $name, $obj_id) {
        $qp = $this->qp('from $_ where obj=$+ and ', $name);
        $obj_id ? $qp->append('obj_id in (0, $.)', $obj_id) : $qp->append('obj_id=0');
        if ($s = $at instanceof SQL) {
            $qp->append(' and $$', $at);
        } elseif ($groups = ACM::usrGroups($at->id)) { # user integrated
            $qp->append(' and (pid=$. or uid=$. or gid in ($@))', $at->pid, $at->id, $groups);
        } else {
            $qp->append(' and (pid=$. or uid=$.)', $at->pid, $at->id);
        }

        $ok = $deny = $allow = 0;
        foreach ($this->sql('&select * $$ order by obj_id, is_deny', $qp) as $one) {
            if ($one->uid && !$obj_id || $one->obj_id && ($one->uid || $s)) {
                $one->is_deny ? ($deny = $one) : ($allow = $one);
            } else {
                $one->is_deny ? ($ok &= ~$one->crud) : ($ok |= $one->crud);
            }
        }
        $_ok = $ok;

        if ($allow)
            $ok |= $allow->crud;
        if ($deny)
            $ok &= ~$deny->crud;
        return [$ok, $_ok, $deny, $allow];
    }

    function set($x, $name, $mode) { # sample: 3 acla.0 g.7
        [$name, $obj_id] = explode('.', $name);
        [$mode, $id] = explode('.', $mode);
        if (!call_user_func("ACM::Xacl$mode"))
            return json(['y' => 'X']);

        $x = 1 << $x; # 1-C 2-R 4-U 8-D 16-X
        $insert = function ($deny) use ($mode, $x, $name, $obj_id, $id) {
            global $user;
            $this->insert([
                '+obj' => $name,
                '.obj_id' => $obj_id,
                '.crud' => $x,
                '.is_deny' => $deny,
                ".{$mode}id" => $id,
                '.user_id' => $user->id,
                '!dt_c' => '$now',
            ]);
        };

        $at = 'u' == $mode ? $this->x_user->get_user($id) : qp($mode . 'id=$.', $id);
        [$ok, $_ok, $deny, $allow] = $this->aggregate($at, $name, $obj_id);
        if ($on = $ok & $x) { # allow change to deny
            if ($allow)
                $x == $allow->crud ? $this->delete($allow->id) : $this->update(['.crud' => $allow->crud & ~$x], $allow->id);
            if ($on === ($_ok & $x))
                $deny ? $this->update(['.crud' => $deny->crud | $x], $deny->id) : $insert(1);
        } else { # deny change to allow
            if ($deny)
                $x == $deny->crud ? $this->delete($deny->id) : $this->update(['.crud' => $deny->crud & ~$x], $deny->id);
            if (!($_ok & $x))
                $allow ? $this->update(['.crud' => $allow->crud | $x], $allow->id) : $insert(0);
        }
        json(['y' => $on ? '' : 'Y']);
    }

    function crud($oid, &$list, SQL $or) {
        $ary = $id0 = [];

        $crud = function ($ary = []) use (&$list, &$id0) {
            static $ok0;
            if (null === $ok0) {
                $ok0 = 0;
                foreach ($id0 as $row)
                    $row->is_deny ? ($ok0 &= ~$row->crud) : ($ok0 |= $row->crud);
            }
            $allow = $ok0;
            foreach ($ary as $row)
                $row->is_deny ? ($allow &= ~$row->crud) : ($allow |= $row->crud);
            $fn = fn($x) => $allow & $x ? 'Y' : '';
            return $ary ? ($list[$row->key]->crud = $fn) : $fn;
        };

        $select = '&select *, !! as key from $_ where $$ and $$ order by obj, obj_id, uid, is_deny';
        $keys = array_keys($list);
        $qp = $oid ? qp('obj=$+ and obj_id in (0, $@)', $oid, $keys) : qp('obj in ($@) and obj_id=0', $keys);
        $mem = ['', 0];
        foreach ($this->sql($select, $oid ? 'obj_id' : 'obj', $qp, $or) as $row) {
            if ($oid && !$row->obj_id) {
                $id0[] = $row;
                $row->obj = '';
            } elseif ('' === $mem[0] || $mem[0] == $row->obj && $mem[1] == $row->obj_id) {
                $ary[] = $row;
            } else {
                $crud($ary);
                $ary = [$row];
            }
            $mem = [$row->obj, $row->obj_id];
        }
        $ary && $crud($ary);
        $crud = $id0 ? $crud() : fn() => '';
        $types = $this->x_object->typNames();
        foreach ($list as &$row) {
            property_exists($row, 'crud') or $row->crud = $crud;
            $row->a = $oid ? "$oid.$row->obj_id" : (!isset(ACM::$byId[$row->name])
                ? $row->name
                : a("<b>$row->name</b>", "?$this->_1=$this->_2&obj=$row->id"));
            $row->type = $types[$row->typ_id];
        }
    }

    function logging() {
        $from = $this->qp("from \$_$this->t_log l left join \$_users u on u.id=l.user_id");
        if (($_GET['s'] ?? false) && is_string($_GET['s']))
            $from->append(' where l.comment like $+', "%$_GET[s]%");
        $sql = 'select l.*, u.login as user $$ order by id desc limit $., $.';
        $page = $this->page($from, [2, 1]);
        return !$page ? 404 : [
            'page' => $page,
            'e_log' => [
                'query' => $this->sql($sql, $from, $this->x0, $this->ipp),
                'row_c' => function ($row) {
                    $row->user = $row->user ?? 'Anonymous';
                },
            ],
        ];
    }
}
