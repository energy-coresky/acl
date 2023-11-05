<?php

namespace acl;
use Form;
use function jump;

class t_user extends \Model_t
{
    use common;

    private $form = [
        '+name' => ['Name'],
        '+comment' => ['Comment'],
        ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
    ];

    function data($post, $is_grp) {
        return [
            'is_grp' => $is_grp,
            'name' => $post->name,
            'comment' => $post->comment,
            '!dt' => '$now',
        ];
    }

    function users() {
        $list = $this->sqlf('@select id, name from $_ where is_grp=0');
        return [
            'query' => $this->sqlf('select * from $_users'),
            'row_c' => function ($row) use (&$list) {
                $row->profile = $list[$row->pid];
            },
        ];
    }

    function register($post) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

    }

    function profile($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post)
            return new Form($this->form, $id ? $this->one(['id=' => $id]) : []);
        (new Form($this->form))->validate();
        $ary = $this->data($post, 0);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        jump('acl?profile');
    }

    function dpid($id) {
        $this->delete(['id=' => $id, 'id>' => 4, 'is_grp=' => 0])
            && $this->sqlf('update $_users set pid=2 where pid=%d', $id);
        jump('acl?profile');
    }

    function group($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post)
            return new Form($this->form, $id ? $this->one(['id=' => $id]) : []);
        (new Form($this->form))->validate();
        $ary = $this->data($post, 1);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        jump('acl?group');
    }

    function dgu($id) {
        $this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 1])
            && $this->t_user2grp->delete(['grp_id=' => $id]);
        jump('acl?group');
    }
}
