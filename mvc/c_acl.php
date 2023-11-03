<?php

class c_acl extends Controller
{
    function head_y($action) {
        MVC::body("ware." . substr($action, 2));
        return parent::head_y($action);
    }

    function a_profile() { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        return ['list' => $this->x_user->all(['is_grp=' => 0])];
    }

    function a_cpid($id, $post) {
        return ['form' => $this->x_user->profile($post, $id)];
    }

    function a_dpid($id) {
        $this->x_user->dpid($id);
    }

    function a_grp_user() { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        return ['list' => $this->x_user->all(['is_grp=' => 1])];
    }

    function a_cgu($id, $post) {
        return ['form' => $this->x_user->grp_user($post, $id)];
    }

    function a_dgu($id) {
        $this->x_user->dgu($id);
    }
}
