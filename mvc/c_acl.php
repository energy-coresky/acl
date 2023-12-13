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

    function x_filter() {
        MVC::body('ware.filter');
        return ['s' => $_GET['s'] ?? ''];
    }

    function x_filter_obj() {
        MVC::body('ware.filter_obj');
        return [
            'list' => ACM::typNames(true),
            't' => $_GET['t'] ?? 0,
            's' => $_GET['s'] ?? '',
        ];
    }

    function a_log() {
        return ACM::Racll() ? $this->x_access->logging() : 404;
    }

    function j_set($x, $name, $mode) {
        $this->x_access->set($x, $name, $mode);
    }

    function a_uid($id, $oid) {
        return $this->x_object->access($id, $oid);
    }

    function a_pid($id, $oid) {
        return $this->x_object->access($id, $oid);
    }

    function a_gid($id, $oid) {
        return $this->x_object->access($id, $oid);
    }

    function a_users() {
        return ACM::Raclu() ? $this->x_user->users() : 404;
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
        return ACM::Caclv() ? $this->x_user->user2grp($id, $post) : 404;
    }

    function a_profiles() { # -=-=-=-=-=-=-= PROFILE =-=-=-=-=-=-=-=-=-=-=
        return ACM::Raclp() ? ['list' => $this->x_user->all(['is_grp=' => 0])] : 404;
    }

    function a_spid($id, $post) {
        return ['form' => $this->x_user->save_pid($post, $id)];
    }

    function a_dpid($id) {
        $this->x_user->drop_pid($id);
    }

    function a_groups() { # -=-=-=-=- USER GROUPS -=-=-=-=-=-=-=-=-=-=-=
        return ACM::Raclg() ? $this->x_user->groups() : 404;
    }

    function a_sgrp($id, $post) {
        return ['form' => $this->x_user->save_grp($post, $id)];
    }

    function a_dgrp($id) {
        $this->x_user->drop_grp($id);
    }

    function a_objects() { # -=-=-=-=-=-= OBJECTS =-=-=-=-=-=-=-=-=-=-=-=
        return ACM::Raclo() ? $this->x_object->objects() : 404;
    }

    function a_sobj($id, $post) {
        return ['form' => $this->x_object->save_obj($post, $id)];
    }

    function a_dobj($id) {
        $this->x_object->drop_obj($id);
    }

    function a_types() { # -=-=-=-=-=- OBJECT TYPES =-=-=-=-=-=-=-=-=-=
        return ACM::Raclt() ? $this->x_object->types() : 404;
    }

    function a_styp($id, $post) {
        return ['form' => $this->x_object->save_typ($post, $id)];
    }

    function a_dtyp($id) {
        $this->x_object->drop_typ($id);
    }
}
