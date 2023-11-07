<?php

namespace acl;
use ACM, SQL;
use Form;
use function qp;

class t_object extends \Model_t
{
    use common;

    function listing($page = 1) {
        $sql = 'select o.*, t.name as type from $_ o left join $_ t on t.id=o.typ_id where o.is_typ=0';
        return [
            'query' => $this->sqlf($sql),
            'row_c' => function ($row) {
                //$row->type = $row->user ?? 'Anonymous';
            },
        ];
    }

    function save_o($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $types = $this->all(['is_typ=' => 1], 'id,name');
        $form = [
            '+name' => ['Name'],
            'comment' => ['Comment'],
            'typ_id' => ['Type', 'select', $types],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ];
        if (!$post)
            return new Form($form, $id ? $this->one(['id=' => $id]) : []);
        (new Form($form))->validate();
        $ary = [
            'is_typ' => 0,
            'name' => $post->name,
            'comment' => $post->comment,
            'typ_id' => $post->typ_id,
            '!dt' => '$now',
        ];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object Type `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?object');
    }

    function save_t($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $form = [
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ];
        if (!$post)
            return new Form($form, $id ? $this->one(['id=' => $id]) : []);
        (new Form($form))->validate();
        $ary = [
            'is_typ' => 1,
            'name' => $post->name,
            'comment' => $post->comment,
            '!dt' => '$now',
        ];
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Object Type `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?types');
    }
}
