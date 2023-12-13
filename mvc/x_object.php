<?php

namespace acl;
use ACM, Form;
use function qp, jump;

class t_object extends \Model_t
{
    use common;

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

        if ($oid) {
            if (!$row = $this->one($oid, '>') or !$func = ACM::$byId[$oid = $row->name] ?? false)
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
            if (!$page = $this->page($model->from, [4, 6]))
                return 404;
            $what = "$cn as q, $cn as obj_id, $cc as comment, $row->typ_id as typ_id, '$row->name' as name";
            $list = $this->sql("#select $what $model->from $model->order limit $this->x0, $this->ipp");
        } else {
            if (!$page = $this->page($from = $this->filter(), [4, 6]))
                return 404;
            $list = $this->sql("#select name as q, * $from order by name limit $this->x0, $this->ipp");
        }

        if ('uid' == $this->_1) { # userID (integrated)
            $row = $this->x_user->get_user($id);
            $or = qp('(uid=$. or pid=$.', $id, $row->pid);
            if ($row->groups = ACM::grpNames($groups = ACM::usrGroups($id)))
                $or->append(' or gid in ($@)', $groups);
            $list && $this->x_access->crud($oid, $list, $or->append(')'));
        } elseif ('pid' == $this->_1) {
            $row = $this->x_user->one(['.id=' => $id, 'is_grp=' => 0]);
            $list && $this->x_access->crud($oid, $list, qp('pid=$.', $id));
        } elseif ('gid' == $this->_1) {
            $row = $this->x_user->one(['.id=' => $id, 'is_grp=' => 1]);
            $list && $this->x_access->crud($oid, $list, qp('gid=$.', $id));
        }
        return get_defined_vars();
    }

    function save_obj($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $ary = $id && ACM::Raclo() ? $this->one(['id=' => $id]) : [];
        $form = new Form([
            -1 => ['name' => ['Must match regexp: [a-z][a-z\\d]+', '/^[a-z][a-z\d]+$/']],
            '/name' => ['Name'],
            '+comment' => ['Comment'],
            '#typ_id' => ['Type', 'select', ACM::typNames()],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $ary);

        $busy = function ($id = 0) {
            $acm = new \ReflectionClass('ACM');
            $ary = array_map(fn($v) => strtolower($v->name), $acm->getMethods());
            $ary = array_filter($ary, fn($v) => in_array($v[0], ['c', 'r', 'u', 'd', 'x']));
            if (in_array($name = $_POST['name'], array_map(fn($v) => substr($v, 1), $ary)))
                return $this->k_acl->busy = true;
            return $this->k_acl->busy = $this->one(['is_typ=' => 0, 'id!=' => $id, 'name=' => $name]);
        };

        if (!$post || $id && !ACM::Uaclo() || !$id && !ACM::Caclo() || $busy($id))
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

    function objects() {
        $page = $this->page($from = $this->filter(), [4, 2]);
        return !$page ? 404 : [
            'page' => $page,
            'e_list' => $this->sql("select * $from order by name limit $this->x0, $this->ipp"),
            'types' => ACM::typNames(),
        ];
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

    function types() {
        return ['e_list' => $this->sqlf('select * from $_ where is_typ=1 order by id desc')];
    }
}
