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
        $qp = $this->qp('from $_ where is_typ=0');
        if ($_GET['t'] ?? false)
            $qp->append(' and typ_id=$.', $_GET['t']);
        if (($_GET['s'] ?? false) && is_string($_GET['s']))
            $qp->append(' and (name like $+ or comment like \1)', "%$_GET[s]%");
        return $qp;
    }

    function access($id, $oid) {
        if (!ACM::Racla())
            return 404;

        $limit = $this->ipp;
        if ($oid) {
            if (!$row = $this->one($oid, '>') or !$func = ACM::$byId[$row->name] ?? false)
                return 404;
            [$cn, $cc, $cs] = ($model = $func())->columns;
            if ($_GET['t'] ?? false)
                $model->from->append(" and $cn < $.", $_GET['t']);
            if (($_GET['s'] ?? false) && is_string($_GET['s'])) {
                $s = array_shift($cs) . ' like $+';
                if ($cs)
                    $s = "($s or " . implode(' or ', array_map(fn($v) => "$v like \\1", $cs)) . ')';
                $model->from->append(" and $s", "%$_GET[s]%");
            }
            $page = pagination($limit, $model->from, 'p', [4, 6]);
            $what = "$cn as q, $cn as obj_id, $cc as comment, $row->typ_id as typ_id, '$row->name' as name";
            $list = $this->sql("#select $what $model->from $model->order limit $limit, $this->ipp");
            $oid = $row->name;
        } else {
            $page = pagination($limit, $from = $this->filter(), 'p', [4, 6]);
            $list = $this->sql("#select name as q, * $from order by name limit $limit, $this->ipp");
        }

        if ('uid' == $this->_1) { # userID (integrated)
            $row = $this->x_user->get_user($id);
            $or = qp('(uid=$. or pid=$.', $id, $row->pid);
            if ($row->groups = $this->x_user->gnames($groups = ACM::usrGroups($id)))
                $or->append(' or gid in ($@)', $groups);
            $this->x_access->crud($oid, $list, $or->append(')'));
        } elseif ('pid' == $this->_1) {
            $row = $this->x_user->one(['.id=' => $id, 'is_grp=' => 0]);
            $this->x_access->crud($oid, $list, qp('pid=$.', $id));
        } elseif ('gid' == $this->_1) {
            $row = $this->x_user->one(['.id=' => $id, 'is_grp=' => 1]);
            $this->x_access->crud($oid, $list, qp('gid=$.', $id));
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

    function listing($is_typ) {
        if ($is_typ) {
            $from = 'from $_ where is_typ=1 order by id desc';
        } else {
            $limit = $this->ipp;
            $page = pagination($limit, $from = $this->filter(), 'p', [4, 2]);
            $from .= " order by name limit $limit, $this->ipp";
        }
        return [
            'page' => $page ?? 0,
            'list' => $this->sql("#select id as q, * $from"),
            'types' => $this->types(),
        ];
    }
}
