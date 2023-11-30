<?php

namespace acl;
use SKY, ACM, Form;
use function qp, pagination, jump;

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

    function get_user($id) {
        if (!$user = $this->sqlf('>select * from $_users where id=%d', $id))
            throw new Error("Wrong user ID=$id");
        return $user;
    }

    function users(&$page) {
        $filter = function ($s = 'from $_users u ') {
            return ($_GET['s'] ?? false) && is_string($_GET['s'])
                ? qp($s . 'where u.login like $+ or u.email like \1 or u.uname like \1', "%$_GET[s]%")
                : qp($s);
        };
        $limit = $ipp = 17;
        $page = pagination($limit, $filter(), 'p');
        $page->cs = [4, 2];
        $profiles = ACM::usrProfiles();
        return [
            'query' => $this->sql('select u.*, count(g.user_id) as cnt from $_users u
                left join $_' . $this->t_user2grp . ' g on (g.user_id=u.id) $$
                group by u.id
                order by u.id desc limit $., $.', $filter(''), $limit, $ipp),
            'row_c' => function ($row) use (&$profiles) {
                $row->profile = $profiles[$row->pid];
            },
        ];
    }

    function emulate($id) {
        global $user;
        if (!ACM::Xaclv() && !$user->v_emulate || !$id)
            return 404;
        if (($self = $user->v_emulate == $id) || !$user->v_emulate)
            SKY::v('emulate', $self ? null : $user->id);
        SKY::v(null, ['uid' => $id]);
        jump(LINK);
    }

    function state($id, $name) {
        $m = new \Model_t('users');
        $m->update(['state' => $name], (int)$id);
        jump(LINK);
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
        if (!$post || $id && !ACM::Uaclp() || !$id && !ACM::Caclp())
            return $this->form($id && ACM::Raclp() ? $id : 0);
        $ary = $this->validate(0);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Profile `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?profiles');
    }

    function dpid($id) {
        if (ACM::Daclp() && $this->delete(['id=' => $id, 'id>' => 3, 'is_grp=' => 0])) {
            $this->sqlf('update $_users set pid=2 where pid=%d', $id);
            $this->log("Profile ID=$id deleted");
        }
        jump('acl?profiles');
    }

    function group($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post || $id && !ACM::Uaclg() || !$id && !ACM::Caclg())
            return $this->form($id && ACM::Raclg() ? $id : 0);
        $ary = $this->validate(1);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("User Group `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?groups');
    }

    function dgrp($id) {
        if (ACM::Daclg() && $this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 1])) {
            $this->t_user2grp->delete(['grp_id=' => $id]);
            $this->log("User Group ID=$id deleted");
        }
        jump('acl?groups');
    }

    function filter($s = '') {
        $end = qp($s . ' where g.is_grp=1');
        if (($_GET['s'] ?? false) && is_string($_GET['s']))
            $end->append(' and (g.name like $+ or g.comment like \1)', "%$_GET[s]%");
        return $s ? $end : $end->append(' order by g.name');
    }

    function groups(&$page) {
        $limit = $ipp = 17;
        $page = pagination($limit, $sql = $this->filter('from $_ g'), 'p');
        $page->cs = [3, 2];
        return ['query' => sql('select * $$ limit $., $.', $sql, $limit, $ipp)];
    }

    function user2grp($id, $post, &$page) {
        $limit = $ipp = 17;
        $page = pagination($limit, $this->filter('from $_ g'), 'p');
        $page->cs = [2, 2];
        $user = $this->get_user($id);
        if ($post && $post->is_add) {
            in_array($post->grp_id, ACM::usrGroups($id))
                or $this->t_user2grp->insert(['.user_id' => $id, '.grp_id' => $post->grp_id]);
        } elseif ($post) {
            $this->t_user2grp->delete(['.user_id=' => $id, '.grp_id=' => $post->grp_id]);
        }
        $sql = 'select g.*, u2g.grp_id as ok from $_ g
            left join $_` u2g on (u2g.user_id=$. and u2g.grp_id=g.id) $$';
        return [
            'query' => sql($sql, (string)$this->t_user2grp, $id, $this->filter()),
            'usr' => $user,
        ];
    }

    function gnames(array $ids) {
        return $ids ? $this->sqlf('@select id,name from $_ where is_grp=1 and id in (%s)', $ids) : [];
    }
}
