<?php

class c_acl extends Controller
{
    function head_y($action) {
        $acl = explode('/', array_flip(SKY::$plans['main']['ctrl'])['acl']);
        $this->k_acl = new stdClass;
        $this->k_acl->jact = 2 == count($acl) ? $acl[0] : 'main';
        MVC::body("ware." . substr($action, 2));
        return parent::head_y($action);
    }

    function a_error() {
    }

    function a_log() {
        return ACM::Racll() ? ['e_log' => $this->x_access->logging()] : 404;
    }

    function j_set($x, $name, $mode) {
        $this->x_access->set($x, $name, $mode);
    }

    function a_uid($id) {
        return ACM::Racla() ? ['e_obj' => $this->x_object->access($id, 0, 0)] : 404;
    }

    function a_pid($id) {
        return ACM::Racla() ? ['e_obj' => $this->x_object->access(0, $id, 0)] : 404;
    }

    function a_gid($id) {
        return ACM::Racla() ? ['e_obj' => $this->x_object->access(0, 0, $id)] : 404;
    }

    function a_users() {
        return ACM::Raclu() ? ['e_users' => $this->x_user->users()] : 404;
    }

    function a_emulate($id) {
        return $this->x_user->emulate($id);
    }

    function a_state($id, $name) {
        return ACM::Daclv() ? $this->x_user->state($id, $name) : 404;
    }

    function a_register($post) {
        return ACM::Raclv() ? ['form' => $this->x_user->register($post)] : 404;
    }

    function a_user2grp($id, $post) {
        return ['e_grp' => $this->x_user->user2grp($id, $post)];
    }

    function a_profiles() { # -=-=-=-=-=-=-= PROFILE =-=-=-=-=-=-=-=-=-=-=
        return ACM::Raclp() ? ['list' => $this->x_user->all(['is_grp=' => 0])] : 404;
    }

    function a_spid($id, $post) {
        return ['form' => $this->x_user->profile($post, $id)];
    }

    function a_dpid($id) {
        $this->x_user->dpid($id);
    }

    function a_groups() { # -=-=-=-=- USER GRUOPS -=-=-=-=-=-=-=-=-=-=-=
        return ACM::Raclg() ? ['e_grp' => $this->x_user->groups()] : 404;
    }

    function a_sgrp($id, $post) {
        return ['form' => $this->x_user->group($post, $id)];
    }

    function a_dgrp($id) {
        $this->x_user->dgrp($id);
    }

    function a_objects() { # -=-=-=-=-=-= OBJECTS =-=-=-=-=-=-=-=-=-=-=-=
        return ACM::Raclo() ? ['e_obj' => $this->x_object->listing(0)] : 404;
    }

    function a_sobj($id, $post) {
        return ['form' => $this->x_object->save_obj($post, $id)];
    }

    function a_dobj($id) {
        $this->x_object->dobj($id);
    }

    function a_types() { # -=-=-=-=-=- OBJECT TYPES =-=-=-=-=-=-=-=-=-=
        return ACM::Raclt() ? ['e_obj' => $this->x_object->listing(1)] : 404;
    }

    function a_styp($id, $post) {
        return ['form' => $this->x_object->save_typ($post, $id)];
    }

    function a_dtyp($id) {
        $this->x_object->dtyp($id);
    }

    function x_filter() {
        MVC::body('object.filter');
        return [
            'list' => $this->x_object->types(true),
            't' => $_GET['t'] ?? 0,
            's' => $_GET['s'] ?? '',
        ];
    }
}
