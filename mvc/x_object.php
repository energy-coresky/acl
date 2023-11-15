<?php

namespace acl;
use ACM, SQL;
use Form;
use function qp;

class t_object extends \Model_t
{
    use common;

    function listing() {
        $sql = 'select o.*, t.name as type from $_ o left join $_ t on t.id=o.typ_id';
        return [
            'query' => $this->sqlf($sql .' where o.is_typ=0'),
        ];
    }

    function access($uid, $pid, $gid) {
        $sql = 'select o.*, t.name as type,
                  a.is_deny as deny, a.crud, a.obj_id
                    from $_ o
                    left join $_ t on t.id=o.typ_id
                    left join $_` a on (a.obj=o.name and ';
        $access = (string)$this->x_access;
        if ($uid) { # userID
            $user = $this->x_user->get_user($uid);
            $groups = ACM::usrGroups($uid);
            $user->groups = $this->x_user->groups($groups);
            $sql .= $groups
                ? '(a.uid=$. or a.pid=$. or a.gid in ($@))) where o.is_typ=0'
                : '(a.uid=$. or a.pid=$.)) where o.is_typ=0';
            return [
                'query' => $groups ? $this->sql($sql, $access, $uid, $pid, $groups) : $this->sql($sql, $access, $uid, $pid),
                'row_c' => function ($row) {
                },
                'usr' => $user,
            ];
        } elseif ($gid) { # groupID
            $row = $this->x_user->one(['.id=' => $gid, 'is_grp=' => 1]);
            $sql .= 'a.gid=$.) where o.is_typ=0';
            $q = $this->sql($sql, $access, $gid);
        } else { # profileID
            $row = $this->x_user->one(['.id=' => $pid, 'is_grp=' => 0]);
            $sql .= 'a.pid=$.) where o.is_typ=0';
            $q = $this->sql($sql, $access, $pid);
        }
        return [
            'query' => $q,
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
        if (!$post)
            return $form;
        $ary = $form->validate() + ['is_typ' => 0, '!dt' => '$now'];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?objects');
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
}
