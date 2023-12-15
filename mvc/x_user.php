<?php

namespace acl;
use SKY, ACM, Form, Error;
use function qp, jump;

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
        return $this->form()->validate([
            '.is_grp' => $is_grp,
            '!dt' => '$now',
        ]);
    }

    function get_user($id) {
        if (!$user = $this->sqlf('>select * from $_users where id=%d', $id))
            throw new Error("Wrong user ID=$id");
        return $user;
    }

    function users() {
        $filter = function ($s = 'from $_users u ') {
            return ($_GET['s'] ?? false) && is_string($_GET['s'])
                ? $this->qp($s . 'where u.login like $+ or u.email like \1 or u.uname like \1', "%$_GET[s]%")
                : $this->qp($s);
        };

        $sql = 'select u.*, count(g.user_id) as cnt from $_users u
            left join $_' . $this->t_user2grp . ' g on (g.user_id=u.id) $$
            group by u.id
            order by u.id desc limit $., $.';
        $page = $this->page($filter(), [4, 2]);
        return !$page ? 404 : [
            'page' => $page,
            'e_users' => [
                'query' => $this->sql($sql, $filter(''), $this->x0, $this->ipp),
                'row_c' => function ($row) {
                    $row->profile = SKY::$profiles[$row->pid] ?? '<r>Broken!!!</r>';
                },
            ],
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
        $options = array_filter(SKY::$profiles, fn($k) => $k, ARRAY_FILTER_USE_KEY);
        $form = new Form([
            '.login' => ['Login'],
            '*passw' => ['Password'],
            '-email' => ['E-mail'],
            '#pid' => ['Profile', 'select', $options, '', 2],
            '+uname' => ['User Name'],
            '#x' => ['Try to send e-mail to user', 'chk', '', 1],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ]);
        $user = new \Model_t('users');
        $busy = fn($qp) => $this->k_acl->busy = $user->one($qp);
        if (!$post || $busy(qp('login=$+ or email=$+', $post->login, $post->email)))
            return $form;
        $ary = $form->validate();
        if ($ary['x']) {
            [$subject, $message] = explode('~', \view('ware.mail', $ary), 2);
            \Rare::mail($message, $subject, $ary['email']);
        }
        unset($ary['x']);
        $user->insert($ary + ['!dt_r' => '$now']);
        $this->log("Register new user `$post->login`");
        jump('acl?users');
    }

    function save_pid($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post || $id && !ACM::Uaclp() || !$id && !ACM::Caclp())
            return $this->form($id && ACM::Raclp() ? $id : 0);
        $ary = $this->validate(0);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("Profile `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?profiles');
    }

    function drop_pid($id) {
        if (ACM::Daclp() && $this->delete(['id=' => $id, 'id>' => 3, 'is_grp=' => 0])) {
            $this->sqlf('update $_users set pid=2 where pid=%d', $id);
            $this->log("Profile ID=$id deleted");
        }
        jump('acl?profiles');
    }

    function profiles($as_e = true) {
        $from = 'from $_ where is_grp=0 order by id';
        if (!$as_e)
            return $this->sqlf("@select id, name $from");
        $ary = ACM::$profiles_app ? SKY::$profiles : $this->sqlf("%select * $from");
        return new \eVar(function () use (&$ary) {
            if (null === ($id = key($ary)))
                return false;
            $out = ACM::$profiles_app ? ['name' => pos($ary)] : pos($ary);
            next($ary);
            return $out + ['id' => $id];
        });
    }

    function save_grp($post, $id = 0) { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        if (!$post || $id && !ACM::Uaclg() || !$id && !ACM::Caclg())
            return $this->form($id && ACM::Raclg() ? $id : 0);
        $ary = $this->validate(1);
        $id ? $this->update($ary, ['id=' => $id]) : $this->insert($ary);
        $this->log("User Group `$post->name` " . ($id ? ", ID=$id modified" : 'added'));
        jump('acl?groups');
    }

    function drop_grp($id) {
        if (ACM::Daclg() && $this->delete(['id=' => $id, 'id>' => 2, 'is_grp=' => 1])) {
            $this->t_user2grp->delete(['grp_id=' => $id]);
            $this->log("User Group ID=$id deleted");
        }
        jump('acl?groups');
    }

    function filter($s = 'from $_ g') {
        $qp = $this->qp($s . ' where g.is_grp=1');
        if (($_GET['s'] ?? false) && is_string($_GET['s']))
            $qp->append(' and (g.name like $+ or g.comment like \1)', "%$_GET[s]%");
        return $s ? $qp : $qp->append(' order by g.name');
    }

    function groups() {
        $page = $this->page($this->filter(), [3, 2]);
        return !$page ? 404 : [
            'page' => $page,
            'e_grp' => $this->sql('select * from $_ g $$ limit $., $.', $this->filter(''), $this->x0, $this->ipp),
        ];
    }

    function user2grp($id, $post) {
        $user = $this->get_user($id);
        if ($post && $post->is_add) {
            in_array($post->grp_id, ACM::usrGroups($id))
                or $this->t_user2grp->insert(['.user_id' => $id, '.grp_id' => $post->grp_id]);
        } elseif ($post) {
            $this->t_user2grp->delete(['.user_id=' => $id, '.grp_id=' => $post->grp_id]);
        }
        $sql = 'select g.*, u2g.grp_id as ok from $_ g
            left join $_` u2g on (u2g.user_id=$. and u2g.grp_id=g.id) $$ limit $., $.';
        $page = $this->page($this->filter(), [2, 2]);
        return !$page ? 404 : [
            'page' => $page,
            'usr' => $user,
            'e_grp' => $this->sql($sql, (string)$this->t_user2grp, $id, $this->filter(''), $this->x0, $this->ipp),
        ];
    }
}
