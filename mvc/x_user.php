<?php

namespace acl;
use SKY, ACM, Form, Rare, Error;
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

    function row($id, $pref = '>') {
        if (!$user = $this->sqlf($pref . 'select * from $_users where id=%d', $id))
            throw new Error("Wrong user ID=$id");
        return $user;
    }

    function emulate($id) {
        global $user;
        if ($user->v_emulate && $user->v_emulate == $id) {
            SKY::v('emulate', null); # delete property
        } elseif (!$id || !ACM::Xaclv() || 1 != $user->pid && 1 == $this->row($id)->pid) {
            return 404;
        } elseif (!$user->v_emulate) {
            SKY::v('emulate', $user->id);
        }
        SKY::v(null, ['uid' => $id]);
        jump(HOME);
    }

    function state($id, $stt) {
        if (isset(SKY::$states[$stt])) {
            $this->t_users->update(['state' => $stt], (int)$id);
            if ('act' != $stt)
                $this->t_visitors->update(['uid' => null], qp('uid=$. || uid=-\1', $id));
        }
        jump(HOME);
    }

    /* ====================== USERS ======================
    */
    function register($id, $post) {
        $ary = $id ? $this->row($id, '~') : [];
        $ary['passw'] = '';
        $profiles = array_filter(SKY::$profiles, fn($k) => $k, ARRAY_FILTER_USE_KEY);
        $uname = $this->uname ? ['+uname' => ['User Name']] : [
            'fname' => ['First Name'],
            'lname' => ['Last Name'],
        ];
        $form = new Form($uname + [
            -1 => ['state' => ['State not valid', '/^(' . implode('|', array_keys(SKY::$states)) . ')$/']],
            '.login' => ['Login'],
            '*passw' => ['Password'],
            '-email' => ['E-mail'],
            '/state' => ['State', 'select', SKY::$states, 'class="w170"', 'act'],
            '#pid' => ['Profile', 'select', $profiles, 'class="w170"', 2],
            '#x' => ['Try to send e-mail to user', 'chk', '', 1],
            ['Submit', 'submit', 'onclick="return sky.f.submit()"'],
        ], $ary);

        $mod = $this->t_users;
        $busy = fn($qp) => $this->k_acl->busy = $mod->one($qp->append(' and id!=$.', $id));
        if (!$post || $busy(qp('(login=$+ or email=$+)', $post->login, $post->email)))
            return get_defined_vars();

        $ary = $form->validate(['id' => $id]);
        if ($ary['x']) {
            [$subject, $message] = explode('~', \view('ware.mail', $ary), 2);
            Rare::mail($message, $subject, $ary['email']);
        }

        unset($ary['x'], $ary['id']);
        if (PASS_CRYPT)
            $ary['passw'] = Rare::passwd($ary['passw']);
        $ary += ['!dt_r' => '$now'];
        $id ? $mod->update($ary, $id) : ($id = $mod->insert($ary));
        $this->log(($id ? 'Update' : 'Register new') . " user `$post->login`, ID=$id");
        jump('acl?users');
    }

    function users() {
        $filter = function ($s = 'from $_users u ') {
            $uname = $this->uname ? 'u.uname' : 'u.fname like \1 or u.lname';
            return ($_GET['s'] ?? false) && is_string($_GET['s'])
                ? $this->qp($s . "where u.login like $+ or u.email like \\1 or $uname like \\1", "%$_GET[s]%")
                : $this->qp($s);
        };

        $sql = 'select u.*, count(g.user_id) as cnt from $_users u
            left join $_' . $this->t_user2grp . ' g on (g.user_id=u.id) $$
            group by u.id
            order by u.id desc limit $., $.';
        $page = $this->page($filter(), [4, 2]);
        global $user;
        return !$page ? 404 : [
            'page' => $page,
            'user' => $user,
            'e_users' => [
                'query' => $this->sql($sql, $filter(''), $this->x0, $this->ipp),
                'row_c' => function ($row) {
                    $row->profile = SKY::$profiles[$row->pid] ?? '<r>Broken!!!</r>';
                },
            ],
        ];
    }

    /* ====================== PROFILES ======================
    */
    function save_pid($post, $id = 0) {
        if (!$post || $id && !ACM::Uaclp() || !$id && !ACM::Caclp())
            return $this->form($id && ACM::Raclp() ? $id : 0);
        $ary = $this->validate(0);
        $id ? $this->update($ary, $id) : $this->insert($ary);
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
        $ary = $this->cfg()->pap ? SKY::$profiles : $this->sqlf("%select * $from");
        return [
            'pap' => $pap = $this->cfg()->pap,
            'list' => new \eVar(function () use (&$ary, $pap) {
                if (null === ($id = key($ary)))
                    return false;
                $out = $pap ? ['name' => pos($ary)] : pos($ary);
                next($ary);
                return $out + ['id' => $id];
            }),
        ];
    }

    /* ====================== USER GROUPS ======================
    */
    function save_grp($post, $id = 0) {
        if (!$post || $id && !ACM::Uaclg() || !$id && !ACM::Caclg())
            return $this->form($id && ACM::Raclg() ? $id : 0);
        $ary = $this->validate(1);
        $id ? $this->update($ary, $id) : $this->insert($ary);
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
        $usr = $this->row($id);
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
            'usr' => $usr,
            'e_grp' => $this->sql($sql, (string)$this->t_user2grp, $id, $this->filter(''), $this->x0, $this->ipp),
        ];
    }
}
