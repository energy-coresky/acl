<?php

namespace acl;
use Form;
use function sqlf, jump;

class t_user extends \Model_t
{
    use common;

    function profile($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $form = [
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ];
        if (!$post)
            return new Form($form, $id ? $this->one(['id=' => $id]) : []);
        (new Form($form))->validate();
        $ary = [
            'is_grp=' => 0,
            'name' => $post->name,
            'comment' => $post->comment,
            '!dt' => '$now',
        ];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        jump('acl?profile');
    }

    function dpid($id) {
        if ($this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 0]))
            sqlf('update $_users set pid=2 where pid=%d', $id);
        jump('acl?profile');
    }

    function grp_user($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $form = [
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ];
        if (!$post)
            return new Form($form, $id ? $this->one(['id=' => $id]) : []);
        (new Form($form))->validate();
        $ary = [
            'is_grp' => 1,
            'name' => $post->name,
            'comment' => $post->comment,
            '!dt' => '$now',
        ];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        jump('acl?grp_user');
    }

    function dgu($id) {
        if ($this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 1]))
            sqlf('update $_users set pid=2 where pid=%d', $id);
        jump('acl?grp_user');
    }
}
