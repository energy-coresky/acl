
# Access control list

Production ware. Use this product if your application need access control manager.

This ACL ware support simple and extended mode for manage access for
CRUD operations. CUD operation logging configurable at the ware installation stage.

**Simple mode**: Create/Read/Update/Delete for users profile <span style="color:green">allow</span> objects types.
User's groups and single _user\_id_ do not used for ACL.

**Extended mode**:
Create/Read/Update/Delete = C/R/U/D for
user_profile, user_group, user_id
<span style="color:green">allow</span>/<span style="color:red">deny</span> for
object_group, object_type, object_ID

User to Profiles is one to many relation. Users to Groups is many to many relation.

Essence | Brief Info
:----- | :-----
Current status | _UNDER DEVELOPMENT_
Installer class | present (**acl\\ware**)
_w3_ classes | 2 (**Acl** (console), **ACM**)
Controllers | 1, has optional tune
Models | 3
Jet templates | 8, tunable
Tables in the database | 5, tunable names
dd drivers support | 2 (**sqlite3**, **mysqli**) for now
_Asset_ files | 0

## Rewrite for a_.. actions if "tune" used:
```php
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
* R - char one of C/R/U/D or X/Y/Z. R - access for reading
* essence - object (essence) name from acl_object database table

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
You can also use parts of original ACL Jet files using back `@inc(_user.profile)` for example


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
