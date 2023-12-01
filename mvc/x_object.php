<?php

namespace acl;
use ACM, Form;
use function pagination, jump;

class t_object extends \Model_t
{
    use common;

    function busy(&$name, $id = 0) {
        $acm = new \ReflectionClass('ACM');
        $ary = array_map(fn($v) => strtolower($v->name), $acm->getMethods());
        $ary = array_filter($ary, fn($v) => in_array($v[0], ['c', 'r', 'u', 'd', 'x']));
        if (in_array($name = strtolower($name), array_map(fn($v) => substr($v, 1), $ary)))
            return $this->k_acl->busy = true;
        return $this->k_acl->busy = $this->one(['is_typ=' => 0, 'id!=' => $id, 'name=' => $name]);
    }

    function filter($order = false) {
        $end = $this->qp(' where o.is_typ=0');
        if ($_GET['t'] ?? false)
            $end->append(' and o.typ_id=$.', $_GET['t']);
        if (($_GET['s'] ?? false) && is_string($_GET['s']))
            $end->append(' and (o.name like $+ or o.comment like \1)', "%$_GET[s]%");
        if (!$order)
            $end->append(' and o.name!="zzz"');
        if (1 !== $order)
            $end->append(' or o.name="zzz"');
        return $end->append(' order by o.name');
    }

    function access($uid, $pid, $gid, &$page) {
        $from = $to = 17;
        $page = pagination($from, $this->qp('from $_ o' . $this->filter()), 'p', [4, 6]);
        $to += $from - 1;
        $row_c = function (&$row) use ($from, $to) {
            static $p = [], $cur = -1;
            if (!$row->__i || $p[0]->name == $row->name) {
                $p[] = $row;
                return true;
            }

            $_ = $row;
            $crud = $deny = 0;
            foreach ($p as $one)
                $one->deny ? ($deny |= $one->crud) : ($crud |= $one->crud);
            $row = $p[0];
            $crud &= ~$deny;
            $row->crud = fn($x) => $crud & $x ? 'Y' : '';
            $p = [$_];
            if (++$cur < $from || $cur > $to)
                return true;
        };

        $end = $this->filter(true);
        $sql = 'select o.*, t.name as type,
                  a.is_deny as deny, a.crud, a.obj_id
                    from $_ o
                    left join $_ t on t.id=o.typ_id
                    left join $_` a on (a.obj=o.name and ';
        $access = (string)$this->x_access;
        if ($uid) { # userID
            $user = $this->x_user->get_user($uid);
            $user->groups = $this->x_user->gnames($groups = ACM::usrGroups($uid));
            $q = $groups
                ? $this->sql($sql . '(a.uid=$. or a.pid=$. or a.gid in ($@)))' . $end, $access, $uid, $user->pid, $groups)
                : $this->sql($sql . '(a.uid=$. or a.pid=$.))' . $end, $access, $uid, $user->pid);
            return [
                'query' => $q,
                'row_c' => $row_c,
                'usr' => $user,
            ];
        } elseif ($gid) { # groupID
            $row = $this->x_user->one(['.id=' => $gid, 'is_grp=' => 1]);
            $q = $this->sql($sql . 'a.gid=$.)' . $end, $access, $gid);
        } else { # profileID
            $row = $this->x_user->one(['.id=' => $pid, 'is_grp=' => 0]);
            $q = $this->sql($sql . 'a.pid=$.)' . $end, $access, $pid);
        }
        return [
            'query' => $q,
            'row_c' => $row_c,
            'rw' => $row,
        ];
    }

    function save_obj($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $ary = $id && ACM::Raclo() ? $this->one(['id=' => $id]) : [];
        $form = new Form([
            '+name' => ['Name'],
            'comment' => ['Comment'],
            'typ_id' => ['Type', 'select', $this->types()],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $ary);

        if (!$post || $id && !ACM::Uaclo() || !$id && !ACM::Caclo() || $this->busy($_POST['name'], $id))
            return $form;

        $ary = $form->validate() + ['is_typ' => 0, '!dt' => '$now'];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?objects');
    }

    function drop_obj($id) {
        if (ACM::Daclo()) {
            $obj = $this->one(['id=' => $id, 'id>' => 10, 'is_typ=' => 0]);
            if (!$obj || $this->x_access->one(['obj=' => $obj['name']]))
                jump('acl?error=1');
            $this->delete($id) && $this->log("Object `$obj[name]` ID=$id deleted");
        }
        jump('acl?objects');
    }

    function save_typ($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $ary = $id && ACM::Raclt() ? $this->one(['id=' => $id]) : [];
        $form = new Form([
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $ary);

        if (!$post || $id && !ACM::Uaclt() || !$id && !ACM::Caclt())
            return $form;

        $ary = $form->validate() + ['is_typ' => 1, '!dt' => '$now'];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object Type `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?types');
    }

    function drop_typ($id) {
        if (ACM::Daclt()) {
            if ($this->one(['typ_id=' => $id]))
                jump('acl?error=2');
            $this->delete(['id=' => $id, 'id>' => 10, 'is_typ=' => 1])
                && $this->log("Object Type ID=$id deleted");
        }
        jump('acl?types');
    }

    function types($all = false) {
        $list = $this->all(['is_typ=' => 1], 'id, name');
        return ($all ? ['--ALL--'] : []) + $list;
    }

    function listing($is_typ, &$page = null) {
        $from = 'from $_ o left join $_ t on t.id=o.typ_id';
        if ($is_typ) {
            $from .= ' where o.is_typ=1 order by o.id desc';
        } else {
            $limit = $ipp = 17;
            $page = pagination($limit, $this->qp($from .= $this->filter(1)), 'p', [4, 2]);
            $from .= " limit $limit, $ipp";
        }
        return ['query' => $this->sqlf("select o.*, t.name as type $from")];
    }
}
