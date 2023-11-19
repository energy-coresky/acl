<?php

namespace acl;
use ACM, SQL;
use Form;
use function qp;

class t_object extends \Model_t
{
    use common;

    function busy(&$name, $id = 0) {
        $acm = new \ReflectionClass('ACM');
        $ary = array_map(fn($v) => strtolower($v->name), $acm->getMethods());
        $ary = array_filter($ary, fn($v) => in_array($v[0], ['c', 'r', 'u', 'd', 'x']));
        if (in_array($name = strtolower($name), array_map(fn($v) => substr($v, 1), $ary)))
            return $this->k_busy = true;//////////////////////
        return $this->k_busy = $this->one(['is_typ=' => 0, 'id!=' => $id, 'name=' => $name]);
    }

    function row_c(&$row) {
        static $p = [];
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
        $row->crud = function ($x) use ($crud) {
            return $crud & $x ? 'Y' : '';
        };
        $p = [$_];
    }

    function access($uid, $pid, $gid) {
        $end = ' where o.is_typ=0 order by name';
        $sql = 'select o.*, t.name as type,
                  a.is_deny as deny, a.crud, a.obj_id
                    from $_ o
                    left join $_ t on t.id=o.typ_id
                    left join $_` a on (a.obj=o.name and ';
        $access = (string)$this->x_access;
        if ($uid) { # userID
            $user = $this->x_user->get_user($uid);
            $user->groups = $this->x_user->groups($groups = ACM::usrGroups($uid));
            $q = $groups
                ? $this->sql($sql . '(a.uid=$. or a.pid=$. or a.gid in ($@)))' . $end, $access, $uid, $user->pid, $groups)
                : $this->sql($sql . '(a.uid=$. or a.pid=$.))' . $end, $access, $uid, $user->pid);
            return [
                'query' => $q,
                'row_c' => [$this, 'row_c'],
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
            'row_c' => [$this, 'row_c'],
            'rw' => $row,
        ];
    }

    function save_o($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $types = $this->all(['is_typ=' => 1], 'id,name');
        $form = new Form([
            '+name' => ['Name'],
            'comment' => ['Comment'],
            'typ_id' => ['Type', 'select', $types],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $id ? $this->one(['id=' => $id]) : []);
        if (!$post || $this->busy($_POST['name'], $id))
            return $form;
        $ary = $form->validate() + ['is_typ' => 0, '!dt' => '$now'];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?objects');
    }

    function dobj($id) {
        if (ACM::Daclo()) {
            $obj = $this->one(['id=' => $id, 'id>' => 10, 'is_typ=' => 0]);
            if (!$obj || $this->x_access->one(['obj=' => $obj['name']]))
                jump('acl?error');
            $this->delete($id) && $this->log("Object ID=$id deleted");
        }
        jump('acl?objects');
    }

    function listing() {
        $sql = 'select o.*, t.name as type from $_ o left join $_ t on t.id=o.typ_id';
        return [
            'query' => $this->sqlf($sql .' where o.is_typ=0 order by name'),
        ];
    }

    function save_t($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $form = new Form([
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $id ? $this->one(['id=' => $id]) : []);
        if (!$post)
            return $form;
        $ary = $form->validate() + ['is_typ' => 1, '!dt' => '$now'];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object Type `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?types');
    }

    function dot($id) {
        if (ACM::Daclt()) {
            if ($this->one(['typ_id=' => $id]))
                jump('acl?error=2');
            $this->delete(['id=' => $id, 'id>' => 10, 'is_typ=' => 1])
                && $this->log("Object Type ID=$id deleted");
        }
        jump('acl?types');
    }
}
