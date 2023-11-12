<?php

namespace acl;
use ACM, Form;
use function jump;

class t_user extends \Model_t
{
    use common;

    function form($id = 0) {
        return new Form([
            '+name' => ['Name'],
            '+comment' => ['Comment'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $id ? $this->one(['id=' => $id]) : []);
    }

    function validate($is_grp) {
        return $this->form()->validate() + [
            '.is_grp' => $is_grp,
            '!dt' => '$now',
        ];
    }

    function users() {
        $profiles = ACM::usrProfiles();
        return [
            'query' => $this->sqlf('select * from $_users'),
            'row_c' => function ($row) use (&$profiles) {
                $row->profile = $profiles[$row->pid];
            },
        ];
    }

    function register($post) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        $form = new Form([
            '+login' => ['Login'],
            '+passw' => ['Password'],
            '+email' => ['E-mail'],
            '+pid' => ['Profile', 'select', ACM::usrProfiles(0), '', 2],
            '+uname' => ['User Name'],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ]);
        if (!$post)
            return $form;
        $user = new \Model_t('users');
        $id = $user->insert($form->validate() + ['!dt_r' => '$now']);
        $this->log("Register new user `$post->login`");
        jump('acl?users');
    }

    function profile($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post)
            return $this->form($id);
        $ary = $this->validate(0);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Profile `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?profiles');
    }

    function dpid($id) {
        if ($this->delete(['id=' => $id, 'id>' => 4, 'is_grp=' => 0])) {
            $this->sqlf('update $_users set pid=2 where pid=%d', $id);
            $this->log("Profile ID=$id deleted");
        }
        jump('acl?profiles');
    }

    function group($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post)
            return $this->form($id);
        $ary = $this->validate(1);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("User Group `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?groups');
    }

    function groups(array $ids) {
        return $ids ? $this->sqlf('@select id,name from $_ where is_grp=1 and id in (%s)', $ids) : [];
    }

    function dgu($id) {
        if ($this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 1])) {
            $this->t_user2grp->delete(['grp_id=' => $id]);
            $this->log("User Group ID=$id deleted");
        }
        jump('acl?groups');
    }
}
