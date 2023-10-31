<?php

class c_acl extends Controller
{
    function j_delete($id) {
        $n = $this->x_able->remove(qp(' id=$.', $id));
        echo 1 == $n ? 'ok' : '-';
    }

    function a_test() {
        if (!DEV)
            return 404;
        echo implode('<br>', glob($this->x_able->get_dir() . '/*'));
    }
}
