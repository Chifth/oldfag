<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

use Johncms\UserProperties;

defined('_IN_JOHNADM') || die('Error: restricted access');

/**
 * @var Johncms\System\Legacy\Tools $tools
 */

$data = [];
$error = [];
$search_post = isset($_POST['search']) ? trim($_POST['search']) : '';
$search_get = isset($_GET['search']) ? rawurldecode(trim($_GET['search'])) : '';
$search = $search_post ? $search_post : $search_get;

if (isset($_GET['ip'])) {
    $search = trim($_GET['ip']);
}

$title = __('Search IP');
$nav_chain->add($title);

$data['filters'] = [
    [
        'url'    => '?search=' . rawurlencode($search),
        'name'   => __('Actual IP'),
        'active' => ! $mod,
    ],
    [
        'url'    => '?mod=history&amp;search=' . rawurlencode($search),
        'name'   => __('IP history'),
        'active' => $mod === 'history',
    ],
];

$data['search_query'] = $tools->checkout($search);

if ($search) {
    if (strpos($search, '-') !== false) {
        // Обрабатываем диапазон адресов
        $array = explode('-', $search);
        $ip = trim($array[0]);

        if (! preg_match('#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#', $ip)) {
            $error[] = __('First IP is entered incorrectly');
        } else {
            $ip1 = ip2long($ip);
        }

        $ip = trim($array[1]);

        if (! preg_match('#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#', $ip)) {
            $error[] = __('Second IP is entered incorrectly');
        } else {
            $ip2 = ip2long($ip);
        }
    } elseif (strpos($search, '*') !== false) {
        // Обрабатываем адреса с маской
        $array = explode('.', $search);
        $ipt1 = [];
        $ipt2 = [];
        for ($i = 0; $i < 4; $i++) {
            if (! isset($array[$i]) || $array[$i] === '*') {
                $ipt1[$i] = '0';
                $ipt2[$i] = '255';
            } elseif (is_numeric($array[$i]) && $array[$i] >= 0 && $array[$i] <= 255) {
                $ipt1[$i] = $array[$i];
                $ipt2[$i] = $array[$i];
            } else {
                $error = __('Invalid IP');
            }
        }

        $ip1 = ip2long($ipt1[0] . '.' . $ipt1[1] . '.' . $ipt1[2] . '.' . $ipt1[3]);
        $ip2 = ip2long($ipt2[0] . '.' . $ipt2[1] . '.' . $ipt2[2] . '.' . $ipt2[3]);
    } elseif (! preg_match('#^(?:(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(?:\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$#', $search)) {
        $error = __('Invalid IP');
    } else {
        $ip1 = ip2long($search);
        $ip2 = $ip1;
    }
}

if ($search && ! $error) {
    /** @var PDO $db */
    $db = di(PDO::class);

    // Выводим результаты поиска
    if ($mod === 'history') {
        $total = $db->query("SELECT COUNT(DISTINCT `cms_users_iphistory`.`user_id`) FROM `cms_users_iphistory` WHERE `ip` BETWEEN ${ip1} AND ${ip2} OR `ip_via_proxy` BETWEEN ${ip1} AND ${ip2}")->fetchColumn();
    } else {
        $total = $db->query("SELECT COUNT(*) FROM `users` WHERE `ip` BETWEEN ${ip1} AND ${ip2} OR `ip_via_proxy` BETWEEN ${ip1} AND ${ip2}")->fetchColumn();
    }


    if ($total) {
        if ($mod === 'history') {
            $req = $db->query(
                "SELECT `cms_users_iphistory`.*, `users`.`name`, `users`.`rights`, `users`.`lastdate`, `users`.`sex`, `users`.`status`, `users`.`datereg`, `users`.`id`, `users`.`browser`
                FROM `cms_users_iphistory` LEFT JOIN `users` ON `cms_users_iphistory`.`user_id` = `users`.`id`
                WHERE `cms_users_iphistory`.`ip` BETWEEN ${ip1} AND ${ip2} OR `cms_users_iphistory`.`ip_via_proxy` BETWEEN ${ip1} AND ${ip2}
                GROUP BY `users`.`id`
                ORDER BY `ip` ASC, `name` ASC LIMIT " . $start . ',' . $user->config->kmess
            );
        } else {
            $req = $db->query(
                "SELECT * FROM `users`
            WHERE `ip` BETWEEN ${ip1} AND ${ip2} OR `ip_via_proxy` BETWEEN ${ip1} AND ${ip2}
            ORDER BY `ip` ASC, `name` ASC LIMIT " . $start . ',' . $user->config->kmess
            );
        }

        $items = [];
        while ($res = $req->fetch()) {
            $res['user_id'] = $res['id'];
            $user_properties = new UserProperties();
            $user_data = $user_properties->getFromArray($res);
            $res = array_merge($res, $user_data);
            $items[] = $res;
        }
    }


    if ($total > $user->config->kmess) {
        $data['pagination'] = $tools->displayPagination('?' . ($mod === 'history' ? 'mod=history&amp;' : '') . 'search=' . urlencode($search) . '&amp;', $start, $total, $user->config->kmess);
    }
}

$data['back_url'] = '/admin/';

$data['errors'] = $error ?? [];
$data['total'] = $total ?? 0;

$data['items'] = $items ?? [];

echo $view->render(
    'admin::search_ip',
    [
        'title'      => $title,
        'page_title' => $title,
        'data'       => $data,
    ]
);
