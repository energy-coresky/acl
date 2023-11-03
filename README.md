
# Access control list

Production ware. Use this product if your app need access control manager.

This ACL ware support simple and extended mode for manage access for
CRUD operations.

Simple mode: Create/Read/Update/Delete for users profile <span style="color:green">allow</span> objects types.

Extended mode:
Create/Read/Update/Delete = C/R/U/D, A(all)= C+R+U+D, M(modify)=
for
user_profile, user_group, user_id
<span style="color:green">allow</span>/<span style="color:red">deny</span> for
object_group, object_type, object_ID

Essence | Count
:----- | :-----
Installer class | present (**acl\\ware**)
_w3_ class | 2 (**Acl** (console), **ACM**)
Controllers | 1, has tune, optional
Models | 3
Jet templates | 10
Tables in the database | 5
dd drivers support | 2 (**sqlite3**, **mysqli**) for now
_Asset_ files | 0


Status: _under development_

## Rewrite for a_.. actions:
```php
if ($cnt && 'ctrl' == $surl[0]) {
    common_c::$tune = array_shift($surl);
    $cnt--;
}
```

## For j_.. actions add to HTML:
```html
<script src="w/acl/acl.js"></script>
<script>acl.jact = 'ctrl'</script>
```

Where 'ctrl' - tuning value (any of `/^[\w+\-]+$/`)

## Improvement for MySQL

```sql
-- use enum for object's types:
ALTER TABLE tblname CHANGE `obj` `obj` enum('com','per','prj','act','face') DEFAULT NULL,
-- add a index:
..
```
