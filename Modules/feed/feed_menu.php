<?php
global $session;
if ($session["write"]) {
    $menu["setup"]["l2"]['feed'] = array(
        "name" => _("反馈"),
        "href" => "feed/view",
        "order" => 2,
        "icon" => "format_list_bulleted"
    );
}
