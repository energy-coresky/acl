<?php

class c_acl extends Controller
{
    function a_profile() {
        return ['list' => $this->x_user->all(['is_grp=' => 0])];
    }

    function a_cpid($id, $post) {
        return ['form' => $this->x_user->profile($post, $id)];
    }

    function a_dpid($id) {
        $this->x_user->dpid($id);
    }
}
