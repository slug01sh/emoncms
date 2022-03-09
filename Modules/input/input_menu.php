<?php
global $session;
if ($session["write"]) $menu["setup"]["l2"]['input'] = array("name" => _("输入"), "href" => "input/view", "order" => 1, "icon" => "input");
