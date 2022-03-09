<?php
global $session;
if ($session["admin"]) {
    $menu['setup']['l2']['admin'] = array(
        'name' => _("管理"),
        'href' => 'admin',
        'default' => 'admin/info',
        'icon' => 'tasks',
        'order' => 13,

        "l3"=>array(
            "info"=>array(
                "name"=>_("系统信息"),
                "href"=>"admin/info", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "update"=>array(
                "name"=>_("更新"),
                "href"=>"admin/update", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "components"=>array(
                "name"=>_("组件"),
                "href"=>"admin/components", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "firmware"=>array(
                "name"=>_("监视器固件"),
                "href"=>"admin/serial", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "log"=>array(
                "name"=>_("日志"),
                "href"=>"admin/log", 
                "order"=>1, 
                "icon"=>"input"
            ),
            "users"=>array(
                "name"=>_("用户"),
                "href"=>"admin/users", 
                "order"=>1, 
                "icon"=>"input"
            )
        )

    );
}
