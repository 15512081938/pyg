<?php

namespace tools\es;

use think\Config;

class EsPage
{
    public static function paginate($results, $listRows = null, $simple = false, $config = [])
    {
        if (is_int($simple)) {
            $total = $simple;
            $simple = false;
        } else {
            $total = null;
            $simple = true;
        }

        if (is_array($listRows)) {
            $config = array_merge(Config::get('paginate'), $listRows);
            $listRows = $config['list_rows'];
        } else {
            $config = array_merge(Config::get('paginate'), $config);
            $listRows = $listRows ?: $config['list_rows'];
        }

        /** @var Paginator $class */
        $class = false !== strpos($config['type'], '\\') ? $config['type'] : '\\think\\paginator\\driver\\' . ucwords($config['type']);
        $page = isset($config['page']) ? (int)$config['page'] : call_user_func([
            $class,
            'getCurrentPage',
        ], $config['var_page']);

        $page = $page < 1 ? 1 : $page;

        $config['path'] = isset($config['path']) ? $config['path'] : call_user_func([$class, 'getCurrentPath']);

        return $class::make($results, $listRows, $page, $total, $simple, $config);
    }
}