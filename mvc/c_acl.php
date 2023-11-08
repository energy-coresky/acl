<?php

class c_acl extends Controller
{
    function head_y($action) {
        MVC::body("ware." . substr($action, 2));
        return parent::head_y($action);
    }

    function a_log() {
        return ['e_log' => $this->x_access->listing()];
    }

    function a_users() {
        return ['e_users' => $this->x_user->users()];
    }

    function a_register($post) {
        return ['form' => $this->x_user->register($post)];
    }

    function a_profile() { # -=-=-=-=-=-=-= PROFILE =-=-=-=-=-=-=-=-=-=-=
        return ['list' => $this->x_user->all(['is_grp=' => 0])];
    }

    function a_cpid($id, $post) {
        return ['form' => $this->x_user->profile($post, $id)];
    }

    function a_dpid($id) {
        $this->x_user->dpid($id);
    }

    function a_group() { # -=-=-=-=- USER GRUOPS -=-=-=-=-=-=-=-=-=-=-=
        return ['list' => $this->x_user->all(['is_grp=' => 1])];
    }

    function a_cgu($id, $post) {
        return ['form' => $this->x_user->group($post, $id)];
    }

    function a_dgu($id) {
        $this->x_user->dgu($id);
    }

    function a_object() { # -=-=-=-=-=-= OBJECTS =-=-=-=-=-=-=-=-=-=-=-=
        return ['e_obj' => $this->x_object->listing()];
    }

    function a_cobj($id, $post) {
        return ['form' => $this->x_object->save_o($post, $id)];
    }

    function a_dobj($id) {
        $this->x_object->dobj($id);
    }

    function a_types() { # -=-=-=-=-=- OBJECT TYPES =-=-=-=-=-=-=-=-=-=
        return ['list' => $this->x_object->all(['is_typ=' => 1])];
    }

    function a_cot($id, $post) {
        return ['form' => $this->x_object->save_t($post, $id)];
    }

    function a_dot($id) {
        $this->x_object->dobj($id);
    }

    function a_access() { # -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
        return ['e_access' => $this->x_access->page()];
    }
}