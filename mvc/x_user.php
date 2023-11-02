<?php

namespace acl;
use Form;
use function sqlf;

class t_user extends \Model_t
{
    use common;    //private $ext_out;

    function profile($post, $id = 0) {
        $form = [
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ];
        if (!$post)
            return ['form' => new Form($form, $id ? $this->one(['id=' => $id]) : [])];
        (new Form($form))->validate();
        $ary = [
            'is_grp' => 0,
            'name' => $post->name,
            'comment' => $post->comment,
            '!dt' => '$now',
        ];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        jump('acl?profile');
    }

    function dpid($id) {
        $id > 2 or die;
        $this->delete($id);
        sqlf('update $_users set pid=2 where pid=%d', $id);
        jump('acl?profile');
    }
}
