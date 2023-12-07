<?php

namespace acl;
use SKY, ACM;
use function qp, pagination, trace;

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
            [$ok] = $this->user_access($user, $name, $obj_id);
            $cache[$name][$obj_id] = $ok;
            if (SKY::$debug)
                trace(array_flip(ACM::$cr)[$x] . $name, $ok & $x ? 'ACL ALLOW' : 'ACL DENY');
        }
        return $ok & $x;
    }

    function user_access($user, $name, $obj_id) {
        $qp = $this->qp('obj=$+ and ', $name);
        $obj_id ? $qp->append('obj_id in (0, $.)', $obj_id) : $qp->append('obj_id=0');
        if ($groups = ACM::usrGroups($user->id)) {
            $qp->append(' and (pid=$. or uid=$. or gid in ($@))', $user->pid, $user->id, $groups);
        } else {
            $qp->append(' and (pid=$. or uid=$.)', $user->pid, $user->id);
        }

        $ok = $deny = $allow = 0;
        $what = 'id as q, id, is_deny, crud, uid, obj_id';
        foreach ($this->all($qp->append(' order by obj_id, is_deny'), $what) as $one) {
            if ($one->uid && (!$obj_id || $one->obj_id)) {
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
        $x = 1 << $x;
        [$name, $obj_id] = explode('.', $name);
        [$mode, $id] = explode('.', $mode);
        if (!call_user_func("ACM::Xacl$mode"))
            return json(['y' => 'X']);
        $on = false;

        $insert = function ($mode = 'u', $deny = 0) use ($x, $name, $obj_id, $id) {
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

        if ('u' == $mode) { # user integrated
            $user = $this->x_user->get_user($id);
            [$ok, $_ok, $deny, $allow] = $this->user_access($user, $name, $obj_id);
            if ($on = $ok & $x) { # allow change to deny
                if ($allow)
                    $x == $allow->crud ? $this->delete($allow->id) : $this->update(['.crud' => $allow->crud & ~$x], $allow->id);
                if ($on === ($_ok & $x))
                    $deny ? $this->update(['.crud' => $deny->crud | $x], $deny->id) : $insert('u', 1);
            } else { # deny change to allow
                if ($deny)
                    $x == $deny->crud ? $this->delete($deny->id) : $this->update(['.crud' => $deny->crud & ~$x], $deny->id);
                if (!($_ok & $x))
                    $allow ? $this->update(['.crud' => $allow->crud | $x], $allow->id) : $insert();
            }
        } else {
            if (!$row = $this->one(['obj=' => $name, 'obj_id=' => $obj_id, $mode . 'id=' => $id], '>')) {
                $insert($mode);
            } elseif ($on = $x & $row->crud) {
                $x == $row->crud ? $this->delete($row->id) : $this->update(['.crud' => $row->crud & ~$x], $row->id);
            } else {
                $this->update(['.crud' => $row->crud | $x], $row->id);
            }
        }
        json(['y' => $on ? '' : 'Y']);
    }

    function crud($oid, &$list, \SQL $or) {
        if (!$list)
            return;
        $ary = $id0 = [];

        $crud = function ($ary = []) use (&$list, &$id0) {
            $allow = $deny = 0;
            foreach ($id0 as $v)
                $v->is_deny ? ($deny |= $v->crud) : ($allow |= $v->crud);
            $allow &= ~$deny;
            $deny = 0;
            foreach ($ary as $v)
                $v->is_deny ? ($deny |= $v->crud) : ($allow |= $v->crud);
            $fn = fn($x) => $allow & ~$deny & $x ? 'Y' : '';
            return $ary ? ($list[$v->k]->crud = $fn) : $fn;
        };

        $sql = '&select *, obj' . ($oid ? '_id' : '') . ' as k from $_ where $$ and $$ order by obj, obj_id';
        $keys = array_keys($list);
        $qp = $oid ? qp('obj=$+ and obj_id in (0, $@)', $oid, $keys) : qp('obj in ($@) and obj_id=0', $keys);
        $mem = ['', 0];
        foreach ($this->sql($sql, $qp, $or) as $row) {
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
        $crud = $id0 ? $crud() : fn($x) => '';
        $types = $this->x_object->types();
        foreach ($list as &$v) {
            property_exists($v, 'crud') or $v->crud = $crud;
            $v->a = $oid ? "$oid.$v->obj_id" : (!isset(ACM::$byId[$v->name])
                ? $v->name
                : a("<b>$v->name</b>", "?$this->_1=$this->_2&obj=$v->id"));
            $v->type = $types[$v->typ_id];
        }
    }

    function logging(&$page) {
        $filter = function ($join = false) {
            $from = $this->qp("from \$_$this->t_log l");
            $join AND $from->append(' left join $_users u on u.id=l.user_id');
            return ($_GET['s'] ?? false) && is_string($_GET['s'])
                ? $from->append(' where l.comment like $+', "%$_GET[s]%")
                : $from;
        };

        $limit = $ipp = 17;
        $page = pagination($limit, $filter(), 'p', [2, 1]);
        $sql = 'select l.*, u.login as user $$ order by id desc limit $., $.';
        return [
            'query' => $this->sql($sql, $filter(true), $limit, $ipp),
            'row_c' => function ($row) {
                $row->user = $row->user ?? 'Anonymous';
            },
        ];
    }
}
