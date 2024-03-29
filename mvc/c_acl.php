<?php

class c_acl extends Controller
{
    private $pap;

    function head_y($action) {
        $this->k_acl = new stdClass;
        $this->pap = ACM::instance()->cfg()->pap;
        MVC::body("ware." . substr($action, 2));
        return parent::head_y($action);
    }

    function empty_a() {
        jump(ACM::Raclu() ? 'acl?users' : HOME);
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
            'list' => $this->x_object->typNames(true),
            't' => $_GET['t'] ?? 0,
            's' => $_GET['s'] ?? '',
        ];
    }

    function a_log() {
        return ACM::Racll() ? $this->x_access->logging() : 404;
    }

    function a_users() {
        return ACM::Raclu() ? $this->x_user->users() : 404;
    }

    function a_emulate($id) {
        return $this->x_user->emulate($id);
    }

    function a_state($id, $name) {
        return ACM::Daclv() && isset(SKY::$states[$name]) ? $this->x_user->state($id, $name) : 404;
    }

    function a_user($id, $post) {
        return ACM::Uaclu() ? $this->x_user->register($id, $post) : 404;
    }

    function a_register($post) {
        return ACM::Caclu() ? $this->x_user->register(0, $post) : 404;
    }

    function a_user2grp($id, $post) {
        return ACM::Caclv() ? $this->x_user->user2grp($id, $post) : 404;
    }

    /* ====================== ACCESS ======================
    */
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

    /* ====================== PROFILES ======================
    */
    function a_profiles() {
        return ACM::Raclp() ? $this->x_user->profiles() : 404;
    }

    function a_spid($id, $post) {
        return $this->pap ? 404 : ['form' => $this->x_user->save_pid($post, $id)];
    }

    function a_dpid($id) {
        return $this->pap ? 404 : $this->x_user->drop_pid($id);
    }

    /* ====================== USER GROUPS ======================
    */
    function a_groups() {
        return ACM::Raclg() ? $this->x_user->groups() : 404;
    }

    function a_sgrp($id, $post) {
        return ['form' => $this->x_user->save_grp($post, $id)];
    }

    function a_dgrp($id) {
        $this->x_user->drop_grp($id);
    }

    /* ====================== OBJECTS ======================
    */
    function a_objects() {
        return ACM::Raclo() ? $this->x_object->objects() : 404;
    }

    function a_sobj($id, $post) {
        return $this->x_object->save_obj($post, $id);
    }

    function a_dobj($id) {
        $this->x_object->drop_obj($id);
    }

    /* ====================== OBJECT TYPES ======================
    */
    function a_types() {
        return ACM::Raclt() ? $this->x_object->types() : 404;
    }

    function a_styp($id, $post) {
        return $this->x_object->save_typ($post, $id);
    }

    function a_dtyp($id) {
        $this->x_object->drop_typ($id);
    }
}
