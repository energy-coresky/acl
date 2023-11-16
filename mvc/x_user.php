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
            'query' => $this->sqlf('select u.*, count(g.user_id) as cnt from $_users u
                left join $_' . $this->t_user2grp . ' g on (g.user_id=u.id)
                group by u.id
                order by u.id desc'),
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

    function dgu($id) {
        if ($this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 1])) {
            $this->t_user2grp->delete(['grp_id=' => $id]);
            $this->log("User Group ID=$id deleted");
        }
        jump('acl?groups');
    }

    function get_user($id) {
        return $this->sqlf('>select * from $_users where id=%d', $id);
    }

    function user2grp($id, $post) {
        $user = $this->get_user($id);
        if ($post && $post->is_add) {
            in_array($post->grp_id, ACM::usrGroups($id))
                or $this->t_user2grp->insert(['.user_id' => $id, '.grp_id' => $post->grp_id]);
        } elseif ($post) {
            $this->t_user2grp->delete(['.user_id=' => $id, '.grp_id=' => $post->grp_id]);
        }
        $tbl = (string)$this->t_user2grp;
        $sql = 'select g.*, u2g.grp_id as ok from $_ g
            left join $_` u2g on (u2g.user_id=$. and u2g.grp_id=g.id)
            where g.is_grp=1';
        return [
            'query' => sql($sql, $tbl, $id),
            'usr' => $user,
        ];
    }

    function groups(array $ids) {
        return $ids ? $this->sqlf('@select id,name from $_ where is_grp=1 and id in (%s)', $ids) : [];
    }
}
