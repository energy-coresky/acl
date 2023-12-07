
# Access control list

Production ware. Use this product if your application need access control manager.

This ACL ware support simple and extended mode for manage access for
CRUD operations. CUD operation logging configurable at the ware installation stage.

**Simple mode**: Create/Read/Update/Delete for users profile <span style="color:green">allow</span> objects.
User's groups and single _user\_id_ do not used for ACL.

**Extended mode**:
Create/Read/Update/Delete = C/R/U/D for
user_profile, user_groups, user_id
<span style="color:green">allow</span>/<span style="color:red">deny</span> for
objects OR object_ID

User to Profiles is one to many relation. Users to Groups is many to many relation.

Essence | Brief Info
:----- | :-----
Version | 0.888
Installer class | present (**acl\\ware**)
_w3_ classes | 2 (**Acl** (console), **ACM**)
Controllers | 1, has optional tune
Models | 3
Jet templates | 8, tunable
Tables in the database | 5, tunable names
dd drivers support | 2 (**sqlite3**, **mysqli**) for now
SKY::$vars | 1 (**$k_acl**)
_Asset_ files | 0

## Tuning the ware:
```php
# Rewrite for a_ actions:
if ($cnt && 'ctrl' == $surl[0]) { # Where 'ctrl' - tuning value (any of `/^[\w+\-]+$/`)
    common_c::$tune = array_shift($surl);
    $cnt--;
}
```

## Ware usage in the application code
You **must import** at least `\ACM` and controller's `c_acl` class into application namespace.
```php
// in the controllers:
if (!ACM::Ressence())
    return 404;
// in the Jet's templates the same way:
// @if(ACM::Ressence()) .. code .. ~if
```

Where **Ressence**:
* R - char one of C/R/U/D or X. R - access for reading
* essence - object (essence) name from acl_object database table

You also can use selected object ID:
```php
# Access for selected `topic` object ID
if (!$private || ACM::Rtopic($topic_id)) ..
# where $topic_id is ID numeric value, $topic_id cannot be 0
# Access records with obj_id=0 give access to any $topic_id
# But you can tune access for defined $topic_id with access records where obj_id=$topic_id (!=0)
```

Objects for selected ID you can create using call:

```php
ACM::object($obj, $obj_id, $desc) : `object record ID`
# where $obj - object name, example: "topic"
# where $desc - description
# object type_id will taken from $obj/0
# you can give access after object created:
ACM::access($id, $crud, $uid = 0, $pid = 0, $gid = 0)
# where $id is `object record ID`

# Must place in common_c::head_y($action) :
ACM::set([
    'topic' => fn() => (object)$this->t_topic->acl(),
    'forum' => fn() => (object)$this->t_forum->acl(),
    . . . other objects with own access for defined obj_ID
]);
# Where each `acl()` method return fields, see example:
'select' => $this->qp('select id, id as obj_id, topic_name || " at " || dt_created as comment'),
'from'   => $this->qp('from $_ where private=1'),
'order'  => $this->qp('order by id desc'),

```


## Replacing Jet templates
See the root templates call:
```jet
#._ magic marker
#if(Plan::view_t(['main', 'acl.jet']))
    @inc(acl.)
#else
    @inc(.menu)@inc(_access.)
#end
#._ magic marker
```
All templates can be changed with application code in file **acl.jet**.
You can also use parts of original ACL Jet files using back call: `@inc(_user.profiles)` for example


## Improvement for MySQL

```sql
-- use enum for object's types:
ALTER TABLE tblname CHANGE `obj` `obj` enum('com','per','prj','act','face') DEFAULT NULL,
-- add a index:
..
```

## Drop old ACL Log records

You can do it using CRON task for example:
```php
->at('2 2', function() use ($cron) {
    $cron->sql('delete from $_acl_log where dt ... ');
})
```

## Fictitious ACM class

If the application code contains references to the ACL class, but you need to temporarily uninstall
the ACL product, you can add a dummy ACM class to the application's w3 folder:
```php
<?php

class ACM # stub class used when ACL ware do not installed
{
    static function __callStatic($name, $args) {
        return false; # or true
    }
}

```

<hr>
