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
            [$ok] = $this->access($user, $name, $obj_id);
            $cache[$name][$obj_id] = $ok;
            if (SKY::$debug)
                trace(array_flip(ACM::$cr)[$x] . $name, $ok & $x ? 'ACL ALLOW' : 'ACL DENY');
        }
        return $ok & $x;
    }

    function access($user, $name, $obj_id) {
        $qp = $this->qp('obj=$+ and ', $name);
        $obj_id ? $qp->append('obj_id in (0, $.)', $obj_id) : $qp->append('obj_id=0');
        if ($groups = ACM::usrGroups($user->id)) {
            $qp->append(' and (pid=$. or uid=$. or gid in ($@))', $user->pid, $user->id, $groups);
        } else {
            $qp->append(' and (pid=$. or uid=$.)', $user->pid, $user->id);
        }

        $ok = $deny = $allow = 0;
        foreach ($this->all($qp, 'id as q, id, is_deny, crud, uid') as $one) {
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

    function set($x, $name, $mode) { # sample: 3 acla gid7
        $x = 1 << $x;
        $id = substr($mode, 3);
        $mode = $mode[0]; # u or p or g
        $obj_id = 0;
        if ('u' == $mode) { # user integrated
            if (!ACM::Xaclu())
                return json(['y' => 'X']);
            $user = $this->x_user->get_user($id);
            [$ok, $_ok, $deny, $allow] = $this->access($user, $name, $obj_id);
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

    function crud($oid, &$in, \SQL $or) {
        $ary = $id0 = [];
        $crud = function ($ary = []) use (&$in, &$id0) {
            $allow = $deny = 0;
            foreach ($id0 as $v)
                $v->is_deny ? ($deny |= $v->crud) : ($allow |= $v->crud);
            $allow &= ~$deny;
            foreach ($ary as $v)
                $v->is_deny ? ($deny |= $v->crud) : ($allow |= $v->crud);
            $fn = fn($x) => $allow & ~$deny & $x ? 'Y' : '';
            return $ary ? ($in[$v->k]->crud = $fn) : $fn;
        };
        $e = $this->sql('&select *, obj!! as k from $_ where $$ order by obj, obj_id', $oid ? '_id' : '', $oid
            ? qp('obj=$+ and obj_id in (0, $@) and $$', $oid, array_keys($in), $or)
            : qp('obj in ($@) and obj_id=0 and $$', array_keys($in), $or)
        );
        $mem = ['', 0];
        foreach ($e as $row) {
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
        if ($ary)
            $crud($ary);
        $crud = $id0 ? $crud() : fn($x) => '';
        foreach ($in as &$v) {
            property_exists($v, 'crud') or $v->crud = $crud;
            $v->a = $oid
                ? "$oid.$v->obj_id"
                : (!isset(ACM::$byId[$v->name]) ? $v->name : a($v->name, "?$this->_1=$this->_2&obj=$v->id"));
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
