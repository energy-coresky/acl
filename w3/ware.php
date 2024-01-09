<?php

namespace acl;
use Plan, SQL, Display, Form, Rare;

class ware extends \Wares
{
    public $bases   = ['SQLite3', 'MySQLi'];
    public $engines = ['InnoDB', 'MyISAM'];
    public $tables  = ['access', 'log', 'user2grp', 'object', 'user'];

    function form() {
        return [
            'connection' => ['Database connection', 'select', \DEV::databases(['main'])],
            'tt' => ['Table\'s tune (middle part)', '', '', 'acl'],
            'ext' => ['Ware\'s mode', 'radio', ['simple', 'extended'], 1],
            'log' => ['Logate C/U/D in ACL ware', 'radio', ['off', 'on'], 1],
            'pap' => ['Profiles from APP', 'radio', ['off', 'on'], 0],
            'ram' => ['Use `ram` plan for Redis cache', 'radio', ['off', 'on'], 0],
            'ipp' => ['Items per page for paginations', 'number', '', 17],
        ];
    }

    function tables($dd, $tt) {
        $ary = [];
        foreach ($this->tables as $one)
            $ary[] = [$name = $dd->pref . $tt . "_$one", $dd->_tables($name)];
        return $ary;
    }

    function vars() {
        $cfg = \ACM::instance()->cfg();
        $dd = SQL::open($cfg->connection, 'main');
        $tables = $this->tables($dd, $tt = $cfg->tt);
        $object = $this;
        $tune = Plan::_r(['main', 'wares.php'])['acl']['tune'];
        return get_defined_vars();
    }

    function install($mode) {
        $data = \view('ware.data', $vars = $this->vars());
        [$sql, $rewrite, $ajax] = explode("\n~\n", \unl($data));
        $sql = explode("\n~~\n", $sql);
        extract($vars, EXTR_REFS);
        if ($mode) {
            foreach ($tables as $i => $table) {
                if ($table[1])
                    continue;
                $str = str_replace('%engine%', $this->engines[$_POST['engine'] ?? 0], $sql[$i]);
                foreach (Rare::split($str) as $create)
                    $dd->sqlf(SQL::NO_PARSE, $create); //2do: use migrations
            }
            echo 'OK';
            return;
        }
        $form = ['s' => 'acl.install', 'mode' => 1];
        foreach ($tables as $i => $table) {
            $form += [
                $i * 10 + 4 => ["Table <b>$table[0]</b>", 'ni', $table[1] ? 'exist' : 'NOT exist'],
                $i * 10 + 5 => ["Create <b>$table[0]</b> table", 'checkbox', ' disabled', !$table[1]],
                $i * 10 + 6 => ['', 'ni', \pre($sql[$i])],
            ];
        }
        if ('MySQLi' == $dd->name)
            $form += ['engine' => ['Select %engine%', 'select', $this->engines]];
        unset($_POST['mode']);
        return [
            'md' => Display::md(Plan::_g('README.md')),
            'license' => Display::bash(Plan::_g('LICENSE')),
            'form' => Form::A([], $form + [
                99 => ['<b>Manual step:</b><br>Add/check rewrite for this ware', 'ni', $rewrite],
                ['Finalize', 'button', "onclick=\"$ajax\""]
            ]),
        ];
    }

    function uninstall($mode) {
        return $this->vars();
    }

    function update($mode) {
    }
}
