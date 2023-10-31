<?php

namespace acl;
use function qp;

class t_able extends \Model_t
{
    private $handle = [
        'jpg' => 'imagecreatefromjpeg',
        'png' => 'imagecreatefrompng',
        'gif' => 'imagecreatefromgif',
    ];

    private $dir;
    private $ext_in;
    //private $ext_out;

    function head_y() {
        $cfg = ant::cfg();
        $this->dir = $cfg->dir;
        $this->table = $cfg->table;
        return \SQL::open($cfg->connection);
    }

    function get_dir() {
        return $this->dir;
    }

    function remove($rule) {
        $cnt = 0;
        if ($tmp = $this->all($rule)) {
            $ids = [];
            foreach ($tmp as $id => $one) {
                $ary = explode(' ', $one->type);
                if (@unlink("$this->dir/$id.$ary[1]")) {
                    $ids[] = $id;
                    $cnt++;
                }
            }
            if ($ids) {
                $d = $this->delete(qp(' id in ($@)', $ids));
                $d == $cnt or $cnt--;
            }
        }
        return $cnt == count($tmp) ? $cnt : false;
    }
}
