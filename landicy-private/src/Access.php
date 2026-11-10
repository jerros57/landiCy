<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
final class Access {
    public static function allows(string $action,string $role,bool $assigned=true): bool {
        if(!in_array($role,['superadmin','admin','editor'],true))return false;
        if(in_array($action,['draft-trash','draft-restore','users','new-site','import-review','import-preview','scan','source','publish','download'],true))return $role==='superadmin';
        if(in_array($action,['import-live','import-visual','pages','site','edit','preview','code','new-page','delete-page','media-library','media','import-file','import-code'],true))return $role==='superadmin'||$assigned;
        return in_array($action,['dashboard','logout'],true);
    }
}
