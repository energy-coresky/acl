<?php

class c_acl extends Controller
{
    function a_profile() {
        return ['list' => $this->x_user->all(['is_grp=' => 0])];
    }

    function a_apid($post) {
        return $this->x_user->profile($post);
    }

    function a_epid($id, $post) {
        return $this->x_user->profile($post, $id);
    }

    function a_dpid($id) {
        $this->x_user->dpid($id);
    }
}
