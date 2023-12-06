<?php

namespace acl;
use ACM, Form;
use function qp, pagination, jump;

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

    function filter() {
        $qp = $this->qp(' where o.is_typ=0');
        if ($_GET['t'] ?? false)
            $qp->append(' and o.typ_id=$.', $_GET['t']);
        if (($_GET['s'] ?? false) && is_string($_GET['s']))
            $qp->append(' and (o.name like $+ or o.comment like \1)', "%$_GET[s]%");
        return $qp;
    }

    function access($id, $oid) {
        if (!ACM::Racla())
            return 404;
        $limit = $ipp = 17;
        if ($oid) {
            if (!$obj = $this->one($oid) or !$func = ACM::$byId[$obj['name']] ?? false)
                return 404;
            $n = (object)call_user_func($func);
            $page = pagination($limit, $n->from, 'p', [4, 6]);
            $list = $this->sqlf("#$n->select" . ', t.name as type,
                "' . $obj['name'] . '" as name ' . $n->from . '
                left join $_ t on t.id=' . "$obj[typ_id] $n->order limit %d, %d", $limit, $ipp);
            $oid = $obj['name'];
        } else {
            $filter = $this->filter(); # o.id, o.name, o.comment
            $page = pagination($limit, $this->qp("from \$_ o $filter"), 'p', [4, 6]);
            $list = $this->sql("#select o.name as q, o.*, t.name as type from \$_ o
                left join \$_ t on t.id=o.typ_id$filter order by o.name limit $limit, $ipp");
        }
        switch ($this->_1) {
            case 'uid': # userID (integrated)
                $row = $this->x_user->get_user($id);
                $or = qp('(uid=$. or pid=$.', $id, $row->pid);
                if ($row->groups = $this->x_user->gnames($groups = ACM::usrGroups($id)))
                    $or->append(' or gid in ($@)', $groups);
                $this->x_access->crud($oid, $list, $or->append(')'));
            break;
            case 'pid': # profileID
                $row = $this->x_user->one(['.id=' => $id, 'is_grp=' => 0]);
                $this->x_access->crud($oid, $list, qp('pid=$.', $id));
            break;
            case 'gid': # groupID
                $row = $this->x_user->one(['.id=' => $id, 'is_grp=' => 1]);
                $this->x_access->crud($oid, $list, qp('gid=$.', $id));
            break;
        }
        return get_defined_vars();
    }

    function save_obj($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $ary = $id && ACM::Raclo() ? $this->one(['id=' => $id]) : [];
        $form = new Form([
            -1 => ['name' => ['Must match regexp: [a-z][a-z\\d]+', '/^[a-z][a-z\d]+$/']],
            '/name' => ['Name'],
            '+comment' => ['Comment'],
            '#typ_id' => ['Type', 'select', $this->types()],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $ary);

        if (!$post || $id && !ACM::Uaclo() || !$id && !ACM::Caclo() || $this->busy($_POST['name'], $id))
            return $form;

        $ary = $form->validate(['is_typ' => 0, '!dt' => '$now']);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?objects');
    }

    function add($obj, $obj_id, $desc) {
        //$typ_id
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

        $ary = $form->validate(['is_typ' => 1, '!dt' => '$now']);
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
            $page = pagination($limit, $this->qp($from .= $this->filter()), 'p', [4, 2]);
            $from .= " order by o.name limit $limit, $ipp";
        }
        return ['query' => $this->sqlf("select o.*, t.name as type $from")];
    }
}
